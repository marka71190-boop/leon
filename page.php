<?php
require_once __DIR__ . '/inc/bootstrap.php';

$page = q('SELECT * FROM pages WHERE slug = ?', [(string)($_GET['p'] ?? '')])->fetch();
if (!$page) {
    page_404();
}
// Старый адрес page.php?p=about переезжает на /about с кодом 301
seo_canonical_redirect(page_url($page['slug']), ['p']);
$legal = in_array($page['slug'], ['privacy', 'consent', 'oferta'], true);
page_header($page['title'], [
    'title_raw' => ($page['seo_title'] ?? '') ?: null,
    'description' => ($page['seo_description'] ?? '') ?: seo_cut(render_content((string)$page['content'])),
    'canonical' => page_url($page['slug']),
    'jsonld' => [ld_breadcrumbs([['Главная', ''], [$page['title'], page_url($page['slug'])]])]
        + ($page['slug'] === 'about' ? [1 => ['@context' => 'https://schema.org', '@type' => 'AboutPage', 'name' => $page['title'], 'url' => abs_url(page_url($page['slug'])), 'about' => ['@id' => abs_url('') . '#organization']]] : []),
]);
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / <?= e($page['title']) ?></nav>
  <h1><?= e($page['title']) ?></h1>
  <div class="content"><?= render_content($page['content']) /* HTML из админки */ ?></div>
  <?php if (!$legal && $page['slug'] !== 'delivery'): ?>
    <p style="margin-top:28px"><a class="btn btn-accent" href="<?= url('catalog') ?>">Перейти в каталог</a></p>
  <?php endif; ?>
</div>
<?php page_footer();
