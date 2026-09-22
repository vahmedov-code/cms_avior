<?php
require __DIR__.'/../src/blog_admin.php';
$id=max(0,(int)($_GET['id']??0));$error='';$p=$id?blog_get($id):blog_empty();
if(!$id){$p['author']='Вейс';$p['category']='smartphones';$p['cover']='/glass-photo.webp';$p['service']='pereklejka-stekla';}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $action=$_POST['action']??'';
        if($action==='save'){$id=blog_save($_POST,$id,(int)($_POST['version']??0));flash_set('Черновик сохранён. Публичная версия не изменена.');}
        elseif($id&&in_array($action,['publish','unpublish','cancel_schedule'],true)){blog_transition($id,$action,(int)($_POST['version']??0),(string)($_POST['publish_at']??''));flash_set('Состояние публикации обновлено.');}
        else throw new InvalidArgumentException('Неизвестное действие.');
        redirect('blog_edit.php?id='.$id);
    }catch(Throwable $e){$error=$e instanceof PDOException?'URL уже занят или база недоступна. Изменения не сохранены.':$e->getMessage();if(($_POST['action']??'')==='save')$p=array_merge($p,array_filter(array_intersect_key($_POST,blog_empty()),'is_string'));}
}
$pageTitle=$id?'Редактирование статьи':'Новая статья';require __DIR__.'/../src/layout_header.php';
?>
<link rel="stylesheet" href="assets/css/blog-admin.css"><script src="assets/js/blog-editor.js" defer></script>
<div class="blog-admin"><div class="blog-admin-heading"><div><a href="blog.php">← Все публикации</a><h2><?=e($pageTitle)?></h2></div><?php if($id): ?><a href="blog_preview.php?id=<?=$id?>" target="_blank" rel="noopener">Предпросмотр сохранённой версии ↗</a><?php endif; ?></div>
<?php if($error): ?><p class="ba-error" role="alert"><?=e($error)?></p><?php endif; ?>
<form method="post" class="ba-editor" id="blog-editor"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="version" value="<?=(int)$p['version']?>">
<div class="ba-main">
<label>Заголовок<input name="title" maxlength="200" required value="<?=e($p['title'])?>"></label>
<label>Краткое описание<textarea name="excerpt" maxlength="1200" rows="3" required><?=e($p['excerpt'])?></textarea></label>
<div class="ba-toolbar" aria-label="Форматирование текста"><button type="button" data-format="h2">H2</button><button type="button" data-format="h3">H3</button><button type="button" data-format="bold">Жирный</button><button type="button" data-format="italic">Курсив</button><button type="button" data-format="list">Список</button><button type="button" data-format="link">Ссылка</button><button type="button" data-format="image">Фото</button><button type="button" data-format="note">Совет</button></div>
<label>Текст статьи · Markdown<textarea id="blog-body" name="body" rows="24" maxlength="100000" required><?=e($p['body'])?></textarea></label>
<details><summary>Как форматировать</summary><p>## Заголовок, ### Подзаголовок, **жирный**, *курсив*, - пункт списка, [текст](https://адрес).</p><p>Изображение: ![описание](media:код) "Подпись". Код скопируйте из медиатеки. HTML не исполняется.</p></details>
<label>Редакционные замечания (не видны читателям)<textarea name="editor_notes" rows="4" maxlength="5000"><?=e($p['editor_notes'])?></textarea></label><small>Пока замечания не выполнены и поле не очищено, публикация заблокирована.</small>
</div><aside class="ba-side">
<label>URL статьи<input name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="120" required value="<?=e($p['slug'])?>"></label>
<label>Категория<select name="category"><?php foreach(blog_categories() as $key=>$name): ?><option value="<?=$key?>" <?=$p['category']===$key?'selected':''?>><?=$name?></option><?php endforeach; ?></select></label>
<label>Теги через запятую<input name="tags" maxlength="500" value="<?=e($p['tags'])?>"></label>
<label>Автор<input name="author" maxlength="160" required value="<?=e($p['author'])?>"></label>
<label>Обложка: путь или код медиатеки<input name="cover" list="blog-images" required value="<?=e($p['cover'])?>"></label>
<datalist id="blog-images"><?php foreach(['glass','cooling','pcbuild','plata','bga','modul'] as $image): ?><option value="/<?=$image?>-photo.webp"><?php endforeach; ?><?php foreach(db()->query('SELECT id FROM blog_media') as $m): ?><option value="media:<?=e($m['id'])?>"><?php endforeach; ?></datalist>
<a href="blog.php#media" target="_blank" rel="noopener">Открыть медиатеку ↗</a>
<label>Alt обложки<input name="cover_alt" maxlength="250" required value="<?=e($p['cover_alt'])?>"></label>
<label>Подпись обложки<input name="cover_caption" maxlength="500" value="<?=e($p['cover_caption'])?>"></label>
<label>Связанная услуга<select name="service"><?php foreach(blog_services() as $key=>$name): ?><option value="<?=$key?>" <?=$p['service']===$key?'selected':''?>><?=$name?></option><?php endforeach; ?></select></label>
<h3>Поисковая выдача</h3><label>SEO Title<input name="seo_title" maxlength="200" required value="<?=e($p['seo_title'])?>"></label><label>Meta Description<textarea name="seo_description" rows="4" maxlength="350" required><?=e($p['seo_description'])?></textarea></label>
<button class="ba-button" type="submit">Сохранить черновик</button><p id="editor-state" role="status"></p></aside></form>
<?php if($id): ?><section class="ba-publish"><h3>Публикация</h3><p><?= $p['published_json']?'На сайте есть опубликованная версия. Сохранение черновика её не меняет.':'Статья ещё не опубликована.' ?> <?= $p['scheduled_at']?'Запланирована на '.e($p['scheduled_at']).' (Москва).':'' ?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="version" value="<?=(int)$p['version']?>"><label>Дата и время, Москва (пусто — сейчас)<input type="datetime-local" name="publish_at"></label><p>Будет опубликована последняя <strong>сохранённая</strong> версия. Будущая дата ставит её в очередь.</p><button class="ba-button" name="action" value="publish">Опубликовать / запланировать</button><?php if($p['published_json']): ?><button name="action" value="unpublish">Снять с публикации</button><?php endif; ?><?php if($p['scheduled_at']): ?><button name="action" value="cancel_schedule">Отменить расписание</button><?php endif; ?></form></section><?php endif; ?>
</div><?php require __DIR__.'/../src/layout_footer.php'; ?>
