<?php
require __DIR__ . '/inc/bootstrap.php';

$page = q('SELECT * FROM pages WHERE slug = ?', [(string)($_GET['p'] ?? '')])->fetch();
if (!$page) {
    http_response_code(404);
    $page = ['title' => 'Страница не найдена', 'content' => '<p><a href="' . url() . '">На главную</a></p>'];
}
page_header($page['title']);
?>
<div class="container page-pad">
  <nav class="crumbs" aria-label="Навигация"><a href="<?= url() ?>">Главная</a> / <?= e($page['title']) ?></nav>
  <h1><?= e($page['title']) ?></h1>
  <div class="content"><?= render_content($page['content']) /* HTML из админки */ ?></div>
</div>
<?php page_footer();
