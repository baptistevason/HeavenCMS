<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
defined('HEAVEN') || exit;

use Heaven\Content;
use Heaven\Icons;
use Heaven\Settings;
use Heaven\Shop;

$cls = static fn (int $s): string => $s >= 80 ? 'good' : ($s >= 50 ? 'ok' : 'bad');
$shop = Shop::enabled() || array_filter(Content::index(), fn ($e) => $e['type'] === 'product');
$filters = array_merge(['' => t('Tout'), 'type=page' => t('Pages'), 'type=post' => t('Articles')], $shop ? ['type=product' => t('Produits')] : [], [
    'status=draft' => t('Brouillons'), 'status=scheduled' => t('Programmés'),
]);
$types = ['page' => t('Page'), 'post' => t('Article'), 'product' => t('Produit')];
$currentFilter = $v['type'] !== '' ? 'type=' . $v['type'] : ($v['status'] !== '' ? 'status=' . $v['status'] : '');
$home = (string) Settings::get('home');
?>
<div class="page">
  <div class="topbar">
    <div><h1><?= e(t('Contenus')) ?></h1><p><?= e($shop ? t('Pages, articles et produits de ton site.') : t('Pages et articles de ton site.')) ?></p></div>
    <div class="row">
      <a class="btn" href="<?= e(admin_url('new/page')) ?>"><?= Icons::svg('plus') ?><?= e(t('Page')) ?></a>
      <?php if ($shop): ?><a class="btn" href="<?= e(admin_url('new/product')) ?>"><?= Icons::svg('plus') ?><?= e(t('Produit')) ?></a><?php endif ?>
      <?php if (Settings::get('blog.enabled')): ?><a class="btn btn-primary" href="<?= e(admin_url('new/post')) ?>"><?= Icons::svg('pen') ?><?= e(t('Article')) ?></a><?php endif ?>
    </div>
  </div>
  <div class="row between">
    <nav class="filters" aria-label="<?= e(t('Filtres')) ?>">
      <?php foreach ($filters as $query => $label): ?>
        <a href="<?= e(admin_url('content' . ($query !== '' ? '?' . $query : ''))) ?>"<?= $query === $currentFilter ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach ?>
    </nav>
    <form method="get" action="<?= e(admin_url('content')) ?>" role="search">
      <label class="sr-only" for="q"><?= e(t('Rechercher')) ?></label>
      <input id="q" type="search" name="q" value="<?= e($v['q']) ?>" placeholder="<?= e(t('Rechercher…')) ?>">
    </form>
  </div>
  <?php if (!$v['entries']): ?>
    <div class="card empty-state"><span class="ico"><?= Icons::svg('pen') ?></span><p><?= e(t('Rien par ici pour l’instant.')) ?></p>
      <a class="btn btn-primary" href="<?= e(admin_url('new/' . (Settings::get('blog.enabled') ? 'post' : 'page'))) ?>"><?= e(t('Créer un contenu')) ?></a></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="list">
      <thead><tr><th><?= e(t('Titre')) ?></th><th><?= e(t('Statut')) ?></th><th>SEO</th><th>GEO</th><th><?= e(t('Modifié')) ?></th><th><span class="sr-only"><?= e(t('Actions')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($v['entries'] as $e): ?>
        <?php $visible = Content::visible($e); $path = Content::path($e); ?>
        <tr data-row="<?= e($e['id']) ?>">
          <td class="title-cell">
            <a href="<?= e(admin_url('edit/' . $e['id'])) ?>"><?= e($e['title']) ?></a>
            <small><?= e($types[$e['type']] ?? t('Page')) ?><?= ($e['price'] ?? '') !== '' ? ' · ' . e(Shop::money((string) $e['price'], \Heaven\I18n::lang() === 'zh' ? 'en' : \Heaven\I18n::lang())) : '' ?><?= $e['id'] === $home ? ' · ' . Icons::svg('home') . e(t('Accueil')) : '' ?> · <?= e($path) ?><?= $e['lang'] !== Settings::get('lang') ? ' · ' . e(strtoupper($e['lang'])) : '' ?><?= $e['noindex'] ? ' · noindex' : '' ?></small>
          </td>
          <td><?= $visible ? '<span class="badge ok">' . e(t('Publié')) . '</span>' : ($e['status'] !== 'published' ? '<span class="badge">' . e(t('Brouillon')) . '</span>'
              : ((int) $e['date'] > time() ? '<span class="badge brand">' . e(t('Programmé')) . ' · ' . e(fmt_date((int) $e['date'], \Heaven\I18n::lang())) . '</span>' : '<span class="badge warn">' . e(t('Boutique fermée')) . '</span>')) ?></td>
          <td><span class="score <?= $cls((int) $e['score']['seo']) ?>"><?= (int) $e['score']['seo'] ?></span></td>
          <td><span class="score <?= $cls((int) $e['score']['geo']) ?>"><?= (int) $e['score']['geo'] ?></span></td>
          <td class="small muted"><?= e(ago((int) $e['updated'])) ?></td>
          <td>
            <div class="actions">
              <?php if ($visible): ?><a class="btn btn-sm btn-ghost btn-icon" href="<?= e(path_to($path)) ?>" target="_blank" rel="noopener" title="<?= e(t('Voir')) ?>" aria-label="<?= e(t('Voir')) ?>"><?= Icons::svg('eye') ?></a><?php endif ?>
              <button class="btn btn-sm btn-ghost btn-icon" type="button" data-duplicate="<?= e($e['id']) ?>" title="<?= e(t('Dupliquer')) ?>" aria-label="<?= e(t('Dupliquer')) ?>"><?= Icons::svg('copy') ?></button>
              <button class="btn btn-sm btn-ghost btn-icon btn-danger" type="button" data-delete="<?= e($e['id']) ?>" data-title="<?= e($e['title']) ?>" data-visible="<?= $visible ? '1' : '' ?>" title="<?= e(t('Supprimer')) ?>" aria-label="<?= e(t('Supprimer')) ?>"><?= Icons::svg('trash') ?></button>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php if ($v['pages'] > 1): ?>
    <?php
      $pageUrl = static fn (int $n): string => admin_url('content?' . http_build_query(array_filter(['type' => $v['type'], 'status' => $v['status'], 'q' => $v['q'], 'p' => $n > 1 ? $n : ''], static fn ($x): bool => $x !== '')));
      $near = array_values(array_unique(array_filter([1, $v['page'] - 2, $v['page'] - 1, $v['page'], $v['page'] + 1, $v['page'] + 2, $v['pages']], static fn (int $n): bool => $n >= 1 && $n <= $v['pages'])));
    ?>
    <nav class="pager" aria-label="<?= e(t('Pagination')) ?>">
      <span class="small muted"><?= e(t('Page {n} sur {total}', ['n' => $v['page'], 'total' => $v['pages']])) ?> · <?= e(t('{n} contenus', ['n' => $v['total']])) ?></span>
      <span class="pager-links">
        <?php if ($v['page'] > 1): ?><a class="btn btn-sm" href="<?= e($pageUrl($v['page'] - 1)) ?>" rel="prev" aria-label="<?= e(t('Page précédente')) ?>">‹</a><?php endif ?>
        <?php foreach ($near as $i => $n): ?>
          <?php if ($i > 0 && $n - $near[$i - 1] > 1): ?><span class="muted">…</span><?php endif ?>
          <a class="btn btn-sm<?= $n === $v['page'] ? ' btn-primary' : '' ?>" href="<?= e($pageUrl($n)) ?>"<?= $n === $v['page'] ? ' aria-current="page"' : '' ?>><?= $n ?></a>
        <?php endforeach ?>
        <?php if ($v['page'] < $v['pages']): ?><a class="btn btn-sm" href="<?= e($pageUrl($v['page'] + 1)) ?>" rel="next" aria-label="<?= e(t('Page suivante')) ?>">›</a><?php endif ?>
      </span>
    </nav>
  <?php endif ?>
  <?php endif ?>
</div>
