<?php
// SPDX-License-Identifier: AGPL-3.0-only
/**
 * Outil de développement (jamais livré) : installe et active un thème du dépôt dans l'installation locale de heaven/.
 *   php tools/dev-theme.php examples/themes/atelier-lumiere
 */
declare(strict_types=1);

$src = realpath($argv[1] ?? '') ?: exit("Usage : php tools/dev-theme.php <dossier du thème>\n");
define('HEAVEN', true);
define('HEAVEN_VERSION', trim((string) (preg_match("/HEAVEN_VERSION', '([^']+)/", (string) file_get_contents(dirname(__DIR__) . '/heaven/index.php'), $m) ? $m[1] : 'dev')));
define('HEAVEN_ROOT', dirname(__DIR__) . '/heaven');
define('HEAVEN_DATA', HEAVEN_ROOT . '/data');
define('HEAVEN_SITE', HEAVEN_ROOT . '/site');
define('HEAVEN_DEBUG', false);
spl_autoload_register(static function (string $c): void {
    if (str_starts_with($c, 'Heaven\\') && is_file($f = HEAVEN_ROOT . '/app/lib/' . substr($c, 7) . '.php')) {
        require $f;
    }
});
require HEAVEN_ROOT . '/app/lib/helpers.php';
$r = Heaven\Themes::install($src);
if ($r['ok'] ?? false) {
    $r = Heaven\Themes::activate(basename($src));
}
echo json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
exit(($r['ok'] ?? false) ? 0 : 1);
