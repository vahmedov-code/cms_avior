<?php
require __DIR__.'/../src/blog_admin.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(($_POST['action']??'')==='upload'){blog_upload($_FILES['image']??[]);flash_set('Изображение загружено.');}
        elseif(($_POST['action']??'')==='delete_media'){blog_delete_media((string)($_POST['media_id']??''));flash_set('Изображение удалено.');}
        else throw new InvalidArgumentException('Неизвестное действие.');
        redirect('blog.php');
    }catch(Throwable $e){$error=$e instanceof PDOException?'Не удалось сохранить данные. Проверьте миграцию блога.':$e->getMessage();}
}
$pageTitle='Советы мастера — публикации';require __DIR__.'/../src/layout_header.php';
?>
<link rel="stylesheet" href="assets/css/blog-admin.css">
<div class="blog-admin">
<?php if(is_owner()): ?><p><a href="blog_access.php">Доступ ИИ к черновикам →</a></p><?php endif; ?>
<div class="blog-admin-heading"><div><h2>Советы мастера</h2><p>Публикации сайта Avior. Сохранение черновика не меняет опубликованную версию.</p></div><a class="ba-button" href="blog_edit.php">+ Новая статья</a></div>
<?php if($error): ?><p class="ba-error" role="alert"><?=e($error)?></p><?php endif; ?>
<div class="ba-posts">
<?php foreach(db()->query('SELECT id,title,slug,published_json,scheduled_at,updated_at FROM blog_posts ORDER BY updated_at DESC,id DESC') as $p): ?>
<article class="ba-post"><div><small><?= $p['published_json']?'Опубликовано':'Черновик' ?><?= $p['scheduled_at']?' · Запланировано: '.e($p['scheduled_at']):'' ?></small><h3><a href="blog_edit.php?id=<?=(int)$p['id']?>"><?=e($p['title'])?></a></h3><p>/blog/<?=e($p['slug'])?>/</p></div><a href="blog_preview.php?id=<?=(int)$p['id']?>" target="_blank" rel="noopener">Предпросмотр ↗</a></article>
<?php endforeach; ?>
</div>
<section class="ba-media" id="media"><h2>Медиатека</h2><p>JPEG, PNG, WebP до 8 МБ и 20 Мп. Изображения преобразуются в WebP; исходные метаданные не сохраняются. Черновые файлы доступны только после входа.</p>
<form method="post" enctype="multipart/form-data"><?=csrf_field()?><input type="hidden" name="action" value="upload"><label>Изображение <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></label><button class="ba-button">Загрузить</button></form>
<div class="ba-media-grid"><?php foreach(db()->query('SELECT * FROM blog_media ORDER BY created_at DESC,id DESC') as $m): ?><figure><img src="blog_media.php?id=<?=e($m['id'])?>&amp;size=480" loading="lazy" alt="<?=e($m['original_name'])?>"><figcaption><?=e($m['original_name'])?><label>Код для статьи<input readonly value="media:<?=e($m['id'])?>" onclick="this.select()"></label><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="media_id" value="<?=e($m['id'])?>"><button>Удалить неиспользуемое</button></form></figcaption></figure><?php endforeach; ?></div>
</section></div>
<?php require __DIR__.'/../src/layout_footer.php'; ?>
