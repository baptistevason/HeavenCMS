<?php
// SPDX-License-Identifier: AGPL-3.0-only
/**
 * Outil de développement (jamais livré) : prépare les paquets d'un canal de mises à jour (thèmes et fonctions d'un site).
 *
 *   php tools/make-packages.php --out=<dossier> --repo=<compte/depot> [--ref=main] [--notes="texte"] <dossier-paquet> [<dossier-paquet>…]
 *
 * Chaque dossier-paquet contient un theme.json ou un extension.json. Produit dans <dossier> : une archive <id>-<version>.zip par
 * paquet (manifeste à la racine) et latest.json (annonce : version, adresse, empreinte SHA-256, taille). Publier ensuite le
 * contenu de <dossier> à la racine de la branche <ref> du dépôt public <compte/depot> : l'adresse du canal à déclarer dans
 * les manifestes (« update »: {"channel": …}) est affichée à la fin.
 */
declare(strict_types=1);

$opts = [];
$dirs = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('~^--([a-z]+)=(.*)$~s', $a, $m)) {
        $opts[$m[1]] = $m[2];
    } else {
        $dirs[] = rtrim($a, '/');
    }
}
$out = rtrim((string) ($opts['out'] ?? ''), '/');
$repo = (string) ($opts['repo'] ?? '');
$ref = (string) ($opts['ref'] ?? 'main');
if ($out === '' || !preg_match('~^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$~', $repo) || !preg_match('~^[A-Za-z0-9._-]+$~', $ref) || !$dirs) {
    exit("Usage : php tools/make-packages.php --out=<dossier> --repo=<compte/depot> [--ref=main] [--notes=\"texte\"] <dossier-paquet>…\n");
}
if (!class_exists('ZipArchive')) {
    exit("Extension zip absente.\n");
}
@mkdir($out, 0775, true);
$packages = [];
foreach ($dirs as $dir) {
    $type = is_file($dir . '/theme.json') ? 'theme' : (is_file($dir . '/extension.json') ? 'extension' : '');
    if ($type === '') {
        exit("$dir : ni theme.json ni extension.json.\n");
    }
    $m = json_decode((string) file_get_contents($dir . '/' . $type . '.json'), true);
    $id = (string) ($m['id'] ?? '');
    $version = (string) ($m['version'] ?? '');
    if (!preg_match('~^[a-z0-9][a-z0-9-]{0,40}$~', $id) || !preg_match('~^\d+\.\d+\.\d+$~', $version)) {
        exit("$dir : identifiant ou version invalide dans le manifeste.\n");
    }
    $zipFile = $out . '/' . $id . '-' . $version . '.zip';
    @unlink($zipFile);
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $path => $info) {
        $rel = substr((string) $path, strlen($dir) + 1);
        if ($info->isFile() && !is_link((string) $path) && !str_starts_with(basename($rel), '.')) {
            $zip->addFile((string) $path, $rel);
        }
    }
    $zip->close();
    $packages[] = ['type' => $type, 'id' => $id, 'version' => $version,
        'url' => 'https://raw.githubusercontent.com/' . $repo . '/' . $ref . '/' . basename($zipFile),
        'sha256' => hash_file('sha256', $zipFile), 'size' => filesize($zipFile), 'date' => date('Y-m-d'), 'notes' => (string) ($opts['notes'] ?? '')];
    echo "✓ $type $id $version → " . basename($zipFile) . "\n";
}
file_put_contents($out . '/latest.json', json_encode(['packages' => $packages], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "✓ latest.json\nCanal : https://raw.githubusercontent.com/$repo/$ref/latest.json\n";
