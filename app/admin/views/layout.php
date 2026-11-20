<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
/** Structure de l'administration : menu latéral + contenu. */
defined('HEAVEN') || exit;

use Heaven\Auth;
use Heaven\I18n;
use Heaven\Icons;
use Heaven\Media;
use Heaven\Messages;
use Heaven\Security;
use Heaven\Settings;

$user = $v['user'];
$section = $v['section'];
$nav = [
    ['dashboard', '', 'home', t('Tableau de bord'), 'content'],
    ['content', 'content', 'file', t('Contenus'), 'content'],
    ['media', 'media', 'image', t('Médias'), 'media'],
    ['categories', 'categories', 'tag', t('Catégories'), 'categories'],
    ['menus', 'menus', 'menu', t('Menus'), 'menus'],
    ['messages', 'messages', 'mail', t('Messages'), 'messages'],
    ['redirects', 'redirects', 'redirect', t('Redirections'), 'redirects'],
    ['ads', 'ads', 'target', t('Publicité'), 'settings'],
];
// Entrées des capacités actives (droit vérifié par Extensions::nav, comme par la route elle-même).
foreach (\Heaven\Extensions::nav() as [$extPath, $extIcon, $extLabel, $extRight]) {
    $nav[] = [$extPath, $extPath, $extIcon, $extLabel, $extRight];
}
$adminNav = [
    ['settings', 'settings', 'settings', t('Réglages'), 'settings'],
    ['themes', 'themes', 'panel', t('Thèmes'), 'settings'],
    ['extensions', 'extensions', 'plus', t('Fonctions'), 'settings'],
    ['users', 'users', 'users', t('Utilisateurs'), 'users'],
    ['security', 'security', 'shield', t('Sécurité'), 'security'],
    ['update', 'update', 'refresh', t('Mises à jour'), 'security'],
];
$update = $user['role'] === 'admin' ? \Heaven\Update::pending() : null;
// Thèmes et fonctions : relecture des canaux au plus toutes les 12 h, puis bandeau si une mise à jour est proposée.
if ($user['role'] === 'admin') {
    \Heaven\PackageUpdates::check();
}
$pkgUpdates = $user['role'] === 'admin' ? \Heaven\PackageUpdates::pending() : [];
$unread = Messages::unread();
$avatar = Media::get((string) ($user['avatar'] ?? ''));
$initials = mb_strtoupper(mb_substr((string) $user['name'], 0, 1) . mb_substr((string) (explode(' ', (string) $user['name'])[1] ?? ''), 0, 1));
?><!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($v['page_title'] ?: 'Heaven') ?> · <?= e(Settings::get('name')) ?></title>
<?= admin_css() ?>
<link rel="icon" href="<?= e(asset('admin/favicon.svg')) ?>" type="image/svg+xml">
<script src="<?= e(asset('admin/admin.js')) ?>" defer></script>
<?php if (\Heaven\Ai::allowed($user)): ?><script src="<?= e(asset('admin/assistant.js')) ?>" defer></script><?php endif ?>
<?= $v['scripts'] ?? '' ?>
</head>
<body>
<div class="shell" id="shell">
  <aside class="sidebar" aria-label="<?= e(t('Navigation de l’administration')) ?>">
    <a class="logo" href="<?= e(admin_url()) ?>">
      <span class="logo-mark"><?= Icons::mark() ?></span>
      <span><b>Heaven</b><small><?= e(Settings::get('name')) ?></small></span>
    </a>
    <nav class="nav">
      <?php foreach ($nav as [$key, $path, $icon, $label, $area]): ?>
        <?php if (($key !== 'categories' || Settings::get('blog.enabled')) && Auth::can($area)): ?>
          <a href="<?= e(admin_url($path)) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= Icons::svg($icon) ?><?= e($label) ?>
            <?php if ($key === 'messages' && $unread > 0): ?><span class="count"><?= $unread ?></span><?php endif ?></a>
        <?php endif ?>
      <?php endforeach ?>
      <?php if ($user['role'] === 'admin'): ?>
        <span class="nav-title"><?= e(t('Administration')) ?></span>
        <?php foreach ($adminNav as [$key, $path, $icon, $label]): ?>
          <a href="<?= e(admin_url($path)) ?>"<?= $section === $key ? ' aria-current="page"' : '' ?>><?= Icons::svg($icon) ?><?= e($label) ?>
            <?php if ($key === 'update' && ($update || $pkgUpdates)): ?><span class="count"<?= $update ? ' title="' . e(t('Heaven {v} est disponible.', ['v' => $update['version']])) . '"' : '' ?>><?= ($update ? 1 : 0) + count($pkgUpdates) ?></span><?php endif ?></a>
        <?php endforeach ?>
      <?php endif ?>
      <span class="nav-title"><?= e(t('Raccourcis')) ?></span>
      <?php if (\Heaven\Ai::allowed($user)): ?>
        <a href="#" data-ai-open aria-keyshortcuts="Control+J"><?= Icons::svg('spark') ?><?= e(t('Assistant IA')) ?><kbd>Ctrl J</kbd></a>
      <?php endif ?>
      <a href="<?= e(path_to('/')) ?>" target="_blank" rel="noopener"><?= Icons::svg('external') ?><?= e(t('Voir le site')) ?></a>
    </nav>
    <div class="sidebar-foot">
      <a class="me" href="<?= e(admin_url('profile')) ?>"<?= $section === 'profile' ? ' aria-current="page"' : '' ?>>
        <span class="avatar"><?php if ($avatar): ?><img src="<?= e(path_to(Media::thumb($avatar))) ?>" alt=""><?php else: ?><?= e($initials) ?><?php endif ?></span>
        <span><b><?= e($user['name']) ?></b><small><?= e($user['role'] === 'admin' ? t('Administrateur') : t('Rédacteur')) ?></small></span>
      </a>
      <form method="post" action="<?= e(admin_url('logout')) ?>" data-logout>
        <?= Security::csrfField() ?>
        <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= Icons::svg('logout') ?><?= e(t('Se déconnecter')) ?></button>
      </form>
    </div>
  </aside>
  <main class="main" id="main">
    <button class="btn btn-sm menu-toggle" type="button" data-toggle-nav><?= Icons::svg('menu') ?><?= e(t('Menu')) ?></button>
    <?php foreach ($v['flash'] as [$type, $message]): ?>
      <?php if ($type === 'secret'): ?>
        <div class="notice warn" role="alert"><span><?= Icons::svg('key') ?></span><p><?= e($message) ?></p></div>
      <?php else: ?>
        <div class="notice <?= e($type) ?>" role="status" data-flash><span><?= Icons::svg(in_array($type, ['bad', 'warn'], true) ? 'alert' : 'circle-check') ?></span><p><?= e($message) ?></p></div>
      <?php endif ?>
    <?php endforeach ?>
    <?php if ($pkgUpdates && $section !== 'update'): ?>
      <div class="notice warn" role="status"><span><?= Icons::svg('refresh') ?></span>
        <p><?php foreach (array_slice($pkgUpdates, 0, 3) as $i => $pk): ?><?= $i > 0 ? ' · ' : '' ?><strong><?= e(t('Mise à jour disponible : {name} {from} → {to}', ['name' => $pk['name'], 'from' => $pk['from'], 'to' => $pk['to']])) ?></strong><?php endforeach ?>
        <?= count($pkgUpdates) > 3 ? e(' (+' . (count($pkgUpdates) - 3) . ')') : '' ?>
        <a class="btn btn-sm" href="<?= e(admin_url('update')) ?>"><?= e(t('Voir et installer')) ?></a></p></div>
    <?php endif ?>
    <?= $v['content'] ?>
  </main>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<?= \Heaven\Admin::jsData(($v['js'] ?? []) + ['updateCheck' => $user['role'] === 'admin' && \Heaven\Update::stale(), 'driveDue' => $user['role'] === 'admin' && \Heaven\Extensions::invoke('drive', 'due') === true, 'planDue' => $user['role'] === 'admin' && \Heaven\Extensions::invoke('planning', 'due') === true]) ?>
</body>
</html>
