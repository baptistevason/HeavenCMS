<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);

namespace Heaven;

defined('HEAVEN') || exit;

/**
 * Mises à jour des thèmes et des fonctions du site, proposées dans l'administration (bandeau + écran Mises à jour) et
 * installées au clic d'un administrateur, jamais en silence pour un thème ou une fonction en service.
 *
 * Deux sources :
 *  - un thème livré avec le noyau (app/bundled-themes/) plus récent que celui installé ET actif : installé au clic ;
 *  - un canal déclaré par le thème ou la fonction dans son manifeste (« update »: {"channel": "<adresse de latest.json>"}).
 *    Le canal est un fichier public annonçant des paquets (archive ZIP, version, empreinte SHA-256). Le site le lit au plus
 *    toutes les 12 h, depuis l'administration seulement.
 *
 * Confiance : le canal et l'archive doivent venir du même dépôt sur raw.githubusercontent.com (HTTPS, sans redirection) ;
 * l'archive est refusée si son empreinte, sa taille, son identifiant ou sa version ne correspondent pas à l'annonce, puis
 * elle passe la validation complète du contrat (Packages::installZip). Il n'y a pas de signature : la confiance est celle
 * du compte propriétaire du dépôt du canal.
 */
final class PackageUpdates
{
    private const CHANNEL = '~^https://raw\.githubusercontent\.com/([A-Za-z0-9._-]+/[A-Za-z0-9._-]+)/[A-Za-z0-9._-]+/(?:[A-Za-z0-9._-]+/)*latest\.json$~D';
    private const EVERY = 43200;     // relecture des canaux : 12 h
    private const RETRY = 3600;      // après un échec : 1 h
    private const MAX_ZIP = 15728640;

    /** Adresse de canal valide (sinon ''), pour les manifestes. */
    public static function channel(mixed $value): string
    {
        return is_string($value) && strlen($value) <= 300 && !str_contains($value, '..') && preg_match(self::CHANNEL, $value) === 1 ? $value : '';
    }

    /** Thèmes et fonctions installés avec un canal : clé « theme:<id> » ou « extension:<id> » => [type, id, name, version, channel]. */
    private static function installed(): array
    {
        $out = [];
        foreach (['theme' => [Themes::dir(), 'theme.json'], 'extension' => [Extensions::dir(), 'extension.json']] as $type => [$dir, $file]) {
            foreach (glob($dir . '/*/' . $file) ?: [] as $path) {
                $id = basename(dirname($path));
                $m = json_decode((string) Disk::read($path), true);
                if (!is_array($m) || ($m['id'] ?? null) !== $id || is_link(dirname($path))) {
                    continue;
                }
                $out[$type . ':' . $id] = ['type' => $type, 'id' => $id, 'name' => mb_substr((string) ($m['name'] ?? $id), 0, 80),
                    'version' => (string) ($m['version'] ?? ''), 'channel' => self::channel($m['update']['channel'] ?? '')];
            }
        }
        return $out;
    }

    public static function state(): array
    {
        $s = Store::read('package-updates', []);
        return is_array($s) ? $s : [];
    }

    /** Relit les canaux si la dernière lecture date de plus de 12 h (ou si $force). Appelée depuis l'administration. */
    public static function check(bool $force = false): void
    {
        $state = self::state();
        if (!$force && (int) ($state['next'] ?? 0) > time()) {
            return;
        }
        if (!function_exists('curl_init')) {
            return;
        }
        $items = [];
        $failed = false;
        $channels = array_values(array_unique(array_filter(array_column(self::installed(), 'channel'))));
        foreach ($channels as $url) {
            $body = self::get($url, 262144);
            $json = $body !== null ? json_decode($body, true) : null;
            if (!is_array($json) || !is_array($json['packages'] ?? null)) {
                $failed = true;
                continue;
            }
            foreach (array_slice($json['packages'], 0, 40) as $p) {
                $a = is_array($p) ? self::announcement($p, $url) : null;
                if ($a !== null) {
                    $items[$a['type'] . ':' . $a['id']] = $a;
                }
            }
        }
        Store::write('package-updates', ['checked' => time(), 'next' => time() + ($failed ? self::RETRY : self::EVERY), 'items' => $items]);
    }

    /** Annonce nettoyée et vérifiée (même dépôt que le canal), ou null. */
    private static function announcement(array $p, string $channel): ?array
    {
        preg_match(self::CHANNEL, $channel, $c);
        $url = (string) ($p['url'] ?? '');
        $type = (string) ($p['type'] ?? '');
        $id = (string) ($p['id'] ?? '');
        if (!in_array($type, ['theme', 'extension'], true) || !preg_match('~^[a-z0-9][a-z0-9-]{0,40}$~', $id)
            || !preg_match('~^\d+\.\d+\.\d+$~', (string) ($p['version'] ?? ''))
            || !preg_match('~^[a-f0-9]{64}$~', (string) ($p['sha256'] ?? ''))
            || !str_starts_with($url, 'https://raw.githubusercontent.com/' . ($c[1] ?? '-') . '/') || !str_ends_with($url, '.zip')
            || str_contains($url, '..') || str_contains($url, '?') || str_contains($url, '#') || strlen($url) > 300) {
            return null;
        }
        return ['type' => $type, 'id' => $id, 'version' => (string) $p['version'], 'sha256' => (string) $p['sha256'], 'url' => $url,
            'size' => max(0, min(self::MAX_ZIP, (int) ($p['size'] ?? 0))), 'date' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($p['date'] ?? '')) ? (string) $p['date'] : '',
            'notes' => mb_substr(trim(strip_tags((string) ($p['notes'] ?? ''))), 0, 300), 'channel' => $channel];
    }

    /**
     * Mises à jour proposées : liste de [type, id, name, from, to, notes, source]. Aucun accès réseau (état enregistré et fichiers).
     * @return list<array<string,string>>
     */
    public static function pending(): array
    {
        $out = [];
        $installed = self::installed();
        foreach ((array) (self::state()['items'] ?? []) as $key => $a) {
            $i = $installed[$key] ?? null;
            if ($i !== null && is_array($a) && version_compare((string) $a['version'], $i['version'], '>')) {
                $out[$key] = ['type' => $a['type'], 'id' => $a['id'], 'name' => $i['name'], 'from' => $i['version'], 'to' => (string) $a['version'],
                    'notes' => (string) $a['notes'], 'source' => 'channel'];
            }
        }
        $active = (string) (Themes::state()['active']['id'] ?? '');
        foreach (Themes::bundled() as $id => $b) {
            $i = $installed['theme:' . $id] ?? null;
            if ($id === $active && $i !== null && version_compare((string) $b['version'], $i['version'], '>')) {
                $out['theme:' . $id] = ['type' => 'theme', 'id' => $id, 'name' => $i['name'], 'from' => $i['version'], 'to' => (string) $b['version'], 'notes' => '', 'source' => 'bundled'];
            }
        }
        return array_values($out);
    }

    /** Installe la mise à jour d'un thème ou d'une fonction. @return array{ok: bool, error?: string, message?: string} */
    public static function install(string $type, string $id): array
    {
        $key = $type . ':' . $id;
        $item = null;
        foreach (self::pending() as $p) {
            if ($p['type'] . ':' . $p['id'] === $key) {
                $item = $p;
            }
        }
        if ($item === null) {
            return ['ok' => false, 'error' => t('Mise à jour introuvable : relance la vérification.')];
        }
        extend_time(300);
        if ($item['source'] === 'bundled') {
            $r = Themes::install(Themes::bundled()[$id]['dir']);
            if ($r['ok']) {
                $r = Themes::activate($id);
            }
        } else {
            $a = (array) (self::state()['items'][$key] ?? []);
            $r = self::installRemote($type, $id, $a);
        }
        if (!$r['ok']) {
            return ['ok' => false, 'error' => (string) ($r['error'] ?? '')];
        }
        self::forgetInstalled();
        Cache::purge();
        Log::security('package_updated', ['type' => $type, 'id' => $id, 'version' => $item['to']]);
        return ['ok' => true, 'message' => t('{name} est passé à la version {v}. Tes contenus et tes réglages n’ont pas bougé.', ['name' => $item['name'], 'v' => $item['to']])];
    }

    private static function installRemote(string $type, string $id, array $a): array
    {
        $tmp = HEAVEN_ROOT . '/cache/pkg-' . bin2hex(random_bytes(6)) . '.zip';
        try {
            if (self::get((string) $a['url'], self::MAX_ZIP, $tmp) === null || !is_file($tmp)
                || !hash_equals((string) $a['sha256'], (string) hash_file('sha256', $tmp))
                || ((int) $a['size'] > 0 && filesize($tmp) !== (int) $a['size'])) {
                return ['ok' => false, 'error' => t('Archive téléchargée altérée ou inattendue : rien n’a été modifié.')];
            }
            // L'archive doit contenir bien le paquet annoncé (identifiant et version) avant toute installation.
            $zip = new \ZipArchive();
            $manifest = $type === 'extension' ? 'extension.json' : 'theme.json';
            $m = null;
            if ($zip->open($tmp) === true) {
                $raw = $zip->getFromName($manifest);
                if ($raw === false) {
                    $folders = [];
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $n = (string) $zip->getNameIndex($i);
                        if (substr_count($n, '/') === 1 && str_ends_with($n, '/' . $manifest)) {
                            $folders[] = $n;
                        }
                    }
                    $raw = count($folders) === 1 ? $zip->getFromName($folders[0]) : false;
                }
                $m = is_string($raw) ? json_decode($raw, true) : null;
                $zip->close();
            }
            if (!is_array($m) || ($m['id'] ?? null) !== $id || ($m['version'] ?? null) !== $a['version']) {
                return ['ok' => false, 'error' => t('Archive téléchargée altérée ou inattendue : rien n’a été modifié.')];
            }
            $r = Packages::installZip($tmp, $type);
            if ($r['ok'] && $type === 'theme' && (string) (Themes::state()['active']['id'] ?? '') === $id) {
                $r = Themes::activate($id);
            }
            return $r;
        } finally {
            @unlink($tmp);
        }
    }

    private static function forgetInstalled(): void
    {
        Themes::forget();
        Store::forget();
    }

    /** Téléchargement borné : HTTPS seulement, sans redirection ; dans un fichier si $file est donné ('' en cas de succès). Null en cas d'échec. */
    private static function get(string $url, int $max, ?string $file = null): ?string
    {
        if (!preg_match('~^https://raw\.githubusercontent\.com/~', $url) || !function_exists('curl_init')) {
            return null;
        }
        // Tests uniquement : un serveur local joue le rôle de raw.githubusercontent.com (jamais hors de cette machine).
        $http = defined('HEAVEN_TESTING') && defined('HEAVEN_PACKAGE_HOST') && preg_match('~^http://127\.0\.0\.1:\d+$~', (string) HEAVEN_PACKAGE_HOST) === 1;
        if ($http) {
            $url = HEAVEN_PACKAGE_HOST . substr($url, strlen('https://raw.githubusercontent.com'));
        }
        $out = $file !== null ? @fopen($file, 'wb') : null;
        if ($out === false) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => $file !== null ? 120 : 8, CURLOPT_FAILONERROR => true,
            CURLOPT_PROTOCOLS => $http ? CURLPROTO_HTTP : CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Heaven/' . HEAVEN_VERSION, CURLOPT_NOPROGRESS => false,
            curl_progress_option() => static fn ($h, $total, $done): int => $done > $max ? 1 : 0,
        ] + ($out !== null ? [CURLOPT_FILE => $out] : [CURLOPT_RETURNTRANSFER => true]));
        $body = curl_exec($ch);
        $ok = $body !== false && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        if ($out !== null) {
            fclose($out);
        }
        return $ok ? ($out !== null ? '' : (string) $body) : null;
    }
}
