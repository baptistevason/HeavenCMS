<?php
// SPDX-License-Identifier: AGPL-3.0-only
/**
 * Outil de développement (jamais livré) : crée le thème d'un nouveau site à partir du thème modèle.
 *
 *   php tools/new-theme.php <id> "<Nom du thème>" [--out=dossier] [--zip] [--accent=#1f6feb] [--bg=#ffffff] …
 *
 * Réglages de palette acceptés : accent, link, link-dark, on-accent, bg, bg-soft, card, text, muted, line (couleurs #rrggbb),
 * radius (ex. 10px), font, font-head (listes de polices CSS), logo-h (ex. 96px).
 * --out : dossier de création (défaut : ./<id>) ; --zip : produit aussi <id>.zip, importable dans Administration › Thèmes.
 */
declare(strict_types=1);

$args = array_slice($argv, 1);
$opts = [];
$pos = [];
foreach ($args as $a) {
    if (preg_match('~^--([a-z-]+)(?:=(.*))?$~', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? '1';
    } else {
        $pos[] = $a;
    }
}
[$id, $name] = [$pos[0] ?? '', $pos[1] ?? ''];
if (!preg_match('~^[a-z0-9][a-z0-9-]{0,40}$~', $id) || $name === '') {
    exit("Usage : php tools/new-theme.php <id> \"<Nom>\" [--out=dossier] [--zip] [--accent=#rrggbb …]\n  id : minuscules, chiffres et tirets.\n");
}
$src = dirname(__DIR__) . '/examples/themes/modele';
$out = rtrim((string) ($opts['out'] ?? getcwd() . '/' . $id), '/');
if (file_exists($out)) {
    exit("Le dossier $out existe déjà : rien n'a été écrit.\n");
}

$colors = ['accent', 'link', 'link-dark', 'on-accent', 'bg', 'bg-soft', 'card', 'text', 'muted', 'line'];
$free = ['radius' => '~^\d{1,2}(px|rem)$~', 'logo-h' => '~^\d{2,3}px$~', 'font' => '~^[A-Za-z0-9 ,"\'_-]{1,160}$~', 'font-head' => '~^[A-Za-z0-9 ,"\'_-]{1,160}$~'];
$set = [];
foreach ($opts as $k => $v) {
    if (in_array($k, $colors, true) && preg_match('~^#[0-9a-fA-F]{6}$~', $v)) {
        $set[$k] = $v;
    } elseif (isset($free[$k]) && preg_match($free[$k], $v)) {
        $set[$k] = $v;
    } elseif (!in_array($k, ['out', 'zip'], true)) {
        exit("Réglage refusé : --$k=$v\n");
    }
}

mkdir($out, 0775, true);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $path => $info) {
    $rel = $it->getSubPathname();
    if ($info->isDir()) {
        mkdir($out . '/' . $rel, 0775, true);
    } else {
        copy((string) $path, $out . '/' . $rel);
    }
}

// theme.json : identité du thème.
$manifest = json_decode((string) file_get_contents($out . '/theme.json'), true, 16, JSON_THROW_ON_ERROR);
$manifest['id'] = $id;
$manifest['name'] = $name;
$manifest['version'] = '1.0.0';
$manifest['description'] = "Thème du site $name, créé à partir du thème modèle Heaven.";
file_put_contents($out . '/theme.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

// site.css : valeurs de la palette (seul le bloc :root change).
$css = (string) file_get_contents($out . '/styles/site.css');
foreach ($set as $k => $v) {
    $css = preg_replace('~(--' . preg_quote($k, '~') . ':)[^;]*;~', '$1' . addcslashes($v, '\\$') . ';', $css, 1, $n) ?? $css;
    if ($n === 0) {
        exit("Variable --$k introuvable dans la palette du thème modèle.\n");
    }
}
file_put_contents($out . '/styles/site.css', $css);
echo "✓ Thème « $name » créé : $out\n";

if (isset($opts['zip'])) {
    if (!class_exists('ZipArchive')) {
        exit("Extension zip absente : archive non créée.\n");
    }
    $zipFile = $out . '.zip';
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)) as $path => $info) {
        if ($info->isFile()) {
            $zip->addFile((string) $path, substr((string) $path, strlen($out) + 1));
        }
    }
    $zip->close();
    echo "✓ Archive : $zipFile (à importer dans Administration › Thèmes)\n";
}
