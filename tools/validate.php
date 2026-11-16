<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/**
 * Validateur de conformité d'un thème ou d'une fonction (capacité), pour les intégrateurs et les futurs skills.
 * Les mêmes règles que le site applique avant l'installation et l'activation (Themes::validate, Extensions::validate).
 *
 *   php tools/validate.php chemin/vers/mon-theme            (dossier contenant theme.json)
 *   php tools/validate.php chemin/vers/ma-fonction --json   (dossier contenant extension.json ; sortie JSON)
 *   php tools/validate.php paquet.zip                       (archive : décompressée dans un dossier temporaire)
 *
 * Code de sortie : 0 conforme, 1 non conforme, 2 usage. Aucun réseau, aucune écriture hors du dossier temporaire.
 */
declare(strict_types=1);

$args = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => $a !== '--json'));
$json = in_array('--json', $argv, true);
if (count($args) !== 1) {
    fwrite(STDERR, "Usage : php tools/validate.php <dossier ou .zip> [--json]\n");
    exit(2);
}
define('HEAVEN', true);
define('HEAVEN_ROOT', dirname(__DIR__) . '/heaven');
define('HEAVEN_DATA', sys_get_temp_dir() . '/heaven-validate-' . getmypid() . '/data');
define('HEAVEN_SITE', sys_get_temp_dir() . '/heaven-validate-' . getmypid() . '/site');
define('HEAVEN_DEBUG', false);
preg_match("~define\('HEAVEN_VERSION', '([^']+)'\)~", (string) file_get_contents(HEAVEN_ROOT . '/index.php'), $v);
define('HEAVEN_VERSION', $v[1] ?? 'dev');
spl_autoload_register(static function (string $class): void {
    $file = HEAVEN_ROOT . '/app/lib/' . substr($class, 7) . '.php';
    if (str_starts_with($class, 'Heaven\\') && is_file($file)) {
        require $file;
    }
});
require HEAVEN_ROOT . '/app/lib/helpers.php';

$path = $args[0];
$work = sys_get_temp_dir() . '/heaven-validate-' . getmypid();
@mkdir($work . '/data', 0700, true);
$cleanup = static function () use ($work): void {
    exec('rm -rf ' . escapeshellarg($work));
};
if (is_file($path) && str_ends_with(strtolower($path), '.zip')) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        fwrite(STDERR, "✗ Archive illisible.\n");
        exit(1);
    }
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_contains($name, '..') || str_starts_with($name, '/')) {
            fwrite(STDERR, "✗ Archive refusée : chemin suspect ($name).\n");
            exit(1);
        }
        $names[] = $name;
    }
    $zip->extractTo($work . '/unzip');
    $zip->close();
    $path = $work . '/unzip';
    foreach (['theme.json', 'extension.json'] as $manifest) {
        $found = glob($path . '/*/' . $manifest);
        if (!is_file($path . '/' . $manifest) && count($found ?: []) === 1) {
            $path = dirname($found[0]);
        }
    }
}
$kind = is_file($path . '/theme.json') ? 'theme' : (is_file($path . '/extension.json') ? 'extension' : '');
if ($kind === '') {
    $cleanup();
    fwrite(STDERR, "✗ Ni theme.json ni extension.json dans ce dossier.\n");
    exit(1);
}
$errors = $kind === 'theme' ? Heaven\Themes::validate($path, $m) : Heaven\Extensions::validate($path, $m);
if ($kind === 'theme' && ($m['id'] ?? '') !== basename(rtrim($path, '/')) && !str_starts_with($path, $work)) {
    $errors[] = 'Le dossier doit porter le nom de l’identifiant du manifeste (' . ($m['id'] ?? '?') . ').';
}
$cleanup();
if ($json) {
    echo json_encode(['kind' => $kind, 'id' => $m['id'] ?? '', 'version' => $m['version'] ?? '', 'heaven' => HEAVEN_VERSION, 'valid' => $errors === [], 'errors' => $errors],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo ($errors === [] ? '✓' : '✗') . ' ' . ($kind === 'theme' ? 'Thème' : 'Fonction') . ' « ' . ($m['id'] ?? '?') . ' » ' . ($m['version'] ?? '')
        . ($errors === [] ? ' : conforme au contrat (Heaven ' . HEAVEN_VERSION . ").\n" : " : non conforme\n");
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
}
exit($errors === [] ? 0 : 1);
