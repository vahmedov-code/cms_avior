<?php
require __DIR__.'/../src/blog_admin.php';
if(!is_owner()){http_response_code(403);exit('Доступ только для владельца.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(($_POST['action']??'')==='generate'){
        $token=bin2hex(random_bytes(32));set_setting('blog_ai_token_hash',hash('sha256',$token));
        $_SESSION['blog_new_token']=$token;
    }elseif(($_POST['action']??'')==='revoke'){
        set_setting('blog_ai_token_hash','');unset($_SESSION['blog_new_token']);
    }else{http_response_code(400);exit('Неизвестное действие.');}
    redirect('blog_access.php');
}
$token=$_SESSION['blog_new_token']??'';unset($_SESSION['blog_new_token']);
$enabled=(bool)get_setting('blog_ai_token_hash');
$pageTitle='Доступ ИИ к блогу';require __DIR__.'/../src/layout_header.php';
?>
<link rel="stylesheet" href="assets/css/blog-admin.css">
<div class="blog-admin"><a href="blog.php">← К публикациям</a><h2>Статьи с помощью ИИ</h2>
<p>Задайте ассистенту тему. Он сможет прочитать статьи и сохранить новый текст или правки как черновик. Публикацию вы проверяете и выполняете в редакторе CRM.</p>
<p>Ключ открывает только блог. Заказы, клиенты и финансы по нему недоступны.</p>
<p>Состояние: <strong><?=$enabled?'включён':'выключен'?></strong></p>
<?php if($token): ?><div class="ba-error"><p>Сохраните ключ в защищённом хранилище подключения ассистента. Он показан один раз. Не добавляйте его в статью или адрес ссылки.</p><code style="overflow-wrap:anywhere"><?=e($token)?></code></div><?php endif; ?>
<p>Адрес подключения: <code>/api/ai/blog.php</code>. Передавайте ключ в заголовке <code>Authorization: Bearer</code>.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="generate"><button class="ba-button"><?=$enabled?'Заменить ключ':'Создать ключ'?></button></form>
<?php if($enabled): ?><p>Замена ключа сразу отключает предыдущий.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="revoke"><button>Отключить доступ ИИ</button></form><?php endif; ?>
</div>
<?php require __DIR__.'/../src/layout_footer.php'; ?>
