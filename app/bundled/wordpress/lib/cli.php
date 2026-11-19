<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Import d'un paquet Heaven Export en ligne de commande (SSH), pour migrer des sites à la chaîne.
 *
 *   php app/bundled/wordpress/lib/cli.php import paquet.zip [paquet-part2.zip …] [options]
 *
 * Ou d'un export WordPress (Outils › Exporter), quelle que soit sa taille (lu en flux, sans limite d'envoi ni de durée) :
 *
 *   php app/bundled/wordpress/lib/cli.php import export.xml [--drafts] [--no-images] [--user=EMAIL] [--json]
 *
 *   --dry-run      simulation : affiche ce qui serait créé, n'écrit rien
 *   --drafts       tout importer en brouillon
 *   --no-site      ne pas reprendre la page d'accueil ni le nom du site
 *   --no-menus     garder les menus de Heaven
 *   --no-remote    ne pas télécharger depuis l'ancien site les médias absents du paquet
 *   --no-images    (export .xml) ne pas copier les images de l'ancien site dans la médiathèque
 *   --user=EMAIL   compte auteur de l'import (par défaut : le premier administrateur)
 *   --json         bilan en JSON (pour un script)
 *
 * Code de sortie : 0 import terminé, 1 erreur. Jamais exécuté depuis le web (dossier app/ fermé, et contrôle ci-dessous).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('HEAVEN', true);
define('HEAVEN_ROOT', dirname(__DIR__, 4));
define('HEAVEN_VERSION', preg_match("~define\\('HEAVEN_VERSION', '([^']+)'\\)~", (string) @file_get_contents(HEAVEN_ROOT . '/index.php'), $m) ? $m[1] : 'cli');
if (is_file(HEAVEN_ROOT . '/heaven-config.php')) {
    require HEAVEN_ROOT . '/heaven-config.php';
}
defined('HEAVEN_DATA') || define('HEAVEN_DATA', HEAVEN_ROOT . '/data');
defined('HEAVEN_DEBUG') || define('HEAVEN_DEBUG', false);
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Heaven\\', 7) === 0 && is_file($file = HEAVEN_ROOT . '/app/lib/' . str_replace('\\', '/', substr($class, 7)) . '.php')) {
        require $file;
    }
});
require HEAVEN_ROOT . '/app/lib/helpers.php';
require __DIR__ . '/Wxr.php';
require __DIR__ . '/Package.php';
require __DIR__ . '/PackageImporter.php';
require __DIR__ . '/Importer.php';

use Heaven\Settings;
use Heaven\Users;
use HeavenExt\Wordpress\Importer;
use HeavenExt\Wordpress\Package;
use HeavenExt\Wordpress\PackageImporter;

$args = array_slice($argv, 1);
$opts = [];
$files = [];
foreach ($args as $a) {
    if (preg_match('~^--([a-z-]+)(?:=(.*))?$~', $a, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        $files[] = $a;
    }
}
$command = array_shift($files);
$fail = static function (string $message): never {
    fwrite(STDERR, '✗ ' . $message . "\n");
    exit(1);
};
if ($command !== 'import' || !$files) {
    fwrite(STDERR, "Usage : php app/bundled/wordpress/lib/cli.php import paquet.zip [paquet-part2.zip …] [--dry-run] [--drafts] [--no-site] [--no-menus] [--no-remote] [--user=EMAIL] [--json]\n        php app/bundled/wordpress/lib/cli.php import export.xml [--drafts] [--no-images] [--user=EMAIL] [--json]\n");
    exit(1);
}
if (!Settings::installed()) {
    $fail('Heaven n’est pas encore installé dans ' . HEAVEN_ROOT . '.');
}
$user = isset($opts['user']) ? Users::findByEmail((string) $opts['user']) : (array_values(Users::admins())[0] ?? null);
if ($user === null) {
    $fail('Compte introuvable : précise --user=EMAIL d’un compte existant.');
}
$job = PackageImporter::job();
$wxrJob = Importer::job();
if (($job !== null && !$job['done']) || ($wxrJob !== null && !$wxrJob['done'])) {
    $fail('Un import est déjà en cours sur ce site : termine-le ou abandonne-le depuis l’administration.');
}
$json = isset($opts['json']);

// Export WordPress (.xml) : lu en flux, puis importé lot après lot comme depuis l'administration.
if (count($files) === 1 && preg_match('~\.xml$~i', $files[0])) {
    if (!is_file($files[0])) {
        $fail('Fichier introuvable : ' . $files[0]);
    }
    $job = Importer::startFile($files[0], !isset($opts['drafts']), !isset($opts['no-images']));
    if (is_string($job)) {
        $fail($job);
    }
    $json || fwrite(STDOUT, 'Import de ' . ($job['site'] ?: '?') . "\n");
    do {
        $job = Importer::step($user);
        $json || fwrite(STDOUT, sprintf("\r  contenus   %d / %d   ", (int) ($job['next'] ?? 0), (int) ($job['total'] ?? 0)));
    } while ($job !== [] && !$job['done']);
    $r = $job['report'];
    if ($json) {
        echo json_encode(['source' => $job['site'], 'report' => $r], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        exit(0);
    }
    echo "\n";
    printf("  Contenus : %d articles, %d pages (%d déjà présents)\n", $r['posts'], $r['pages'], $r['skipped']);
    printf("  Adresses : %d gardées à l'identique, %d alias ?p=\n", $r['routes'], $r['aliases']);
    printf("  Images : %d copiées, %d laissées en lien ; %d codes courts restés en texte, %d éléments non repris\n", $r['images'], $r['missed'], $r['shortcodes'], $r['dropped']);
    foreach ($r['errors'] as $error) {
        echo "  ✗ $error\n";
    }
    echo "✓ Import terminé.\n";
    exit(0);
}

// Les archives données remplacent celles déjà reçues.
Package::clear();
@mkdir(Package::dir() . '/parts', 0700, true);
foreach ($files as $file) {
    if (!is_file($file) || !preg_match('~\.zip$~i', $file)) {
        $fail("Archive introuvable : $file");
    }
    if (!copy($file, Package::dir() . '/parts/' . preg_replace('~[^A-Za-z0-9._-]~', '-', basename($file)))) {
        $fail("Copie impossible : $file (espace disque ?)");
    }
}
$manifest = Package::open();
if (is_string($manifest)) {
    $fail($manifest);
}
if ($manifest['missing']) {
    $fail('Parties manquantes : ' . implode(', ', $manifest['missing']));
}
$dry = isset($opts['dry-run']);
PackageImporter::start($manifest, ['dry' => $dry, 'keep' => !isset($opts['drafts']), 'site' => !isset($opts['no-site']),
    'menus' => !isset($opts['no-menus']), 'remote' => !isset($opts['no-remote'])]);
$json || fwrite(STDOUT, ($dry ? 'Simulation' : 'Import') . ' de ' . ($manifest['source']['url'] ?? '?') . "\n");
do {
    $job = PackageImporter::step($user);
    [$n, $total, $stage] = PackageImporter::progress($job);
    $json || fwrite(STDOUT, sprintf("\r  %-10s %d / %d   ", $stage, $n, $total));
} while (!$job['done']);
$r = $job['report'];
if ($json) {
    echo json_encode(['dry' => $dry, 'source' => $job['source'], 'report' => $r], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
echo "\n";
printf("  Contenus : %d articles, %d pages, %d produits, %d autres (%d déjà présents, %d en brouillon)\n", $r['posts'], $r['pages'], $r['products'], $r['custom'], $r['skipped'], $r['drafts']);
printf("  Adresses : %d gardées, %d changées (redirigées en 301), %d alias ?p=\n", $r['routes'], $r['moved'], $r['aliases']);
printf("  Médias : %d images, %d documents, %d non repris, %d introuvables\n", $r['images'], $r['documents'], $r['media_skipped'], $r['media_missing']);
printf("  SEO : %d pages ; redirections : %d (%d écartées) ; liens réécrits : %d ; menus : %d liens\n", $r['seo'], $r['redirects'], $r['redirects_skipped'], $r['links'], $r['menu_items']);
foreach ($r['not_migrated'] as [$what, $name, $why]) {
    echo "  ! $name — $why\n";
}
foreach ($r['errors'] as $error) {
    echo "  ✗ $error\n";
}
echo $dry ? "✓ Simulation terminée : rien n'a été écrit.\n" : "✓ Import terminé.\n";
exit(0);
