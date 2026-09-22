<?php
declare(strict_types=1);
require __DIR__.'/../../../src/bootstrap.php';
require __DIR__.'/../../../src/blog.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
function blog_api_reply(int $status,array $data): never {http_response_code($status);echo blog_json($data);exit;}
$hash=get_setting('blog_ai_token_hash')?:'';
$auth=$_SERVER['HTTP_AUTHORIZATION']??'';
if(!$hash)blog_api_reply(403,['error'=>'Доступ ИИ к блогу выключен.']);
if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$match)||!hash_equals($hash,hash('sha256',$match[1])))blog_api_reply(401,['error'=>'Неверный или отсутствующий токен блога.']);
try{blog_config();}catch(Throwable $e){blog_api_reply(503,['error'=>'Модуль блога не настроен.']);}
$method=$_SERVER['REQUEST_METHOD'];
try{
    if($method==='GET'){
        if(isset($_GET['id'])){
            if(!is_string($_GET['id'])||!ctype_digit($_GET['id']))blog_api_reply(400,['error'=>'Некорректный id.']);
            $q=db()->prepare('SELECT * FROM blog_posts WHERE id=?');$q->execute([(int)$_GET['id']]);$post=$q->fetch();
            if(!$post)blog_api_reply(404,['error'=>'Статья не найдена.']);
            unset($post['published_json'],$post['scheduled_json']);
            blog_api_reply(200,['post'=>$post]);
        }
        $posts=db()->query('SELECT id,slug,title,version,updated_at FROM blog_posts ORDER BY id DESC LIMIT 100')->fetchAll();
        blog_api_reply(200,['posts'=>$posts,'categories'=>blog_categories(),'services'=>blog_services(),'permissions'=>['read_blog','save_draft'],'required_fields'=>array_keys(blog_validate_example())]);
    }
    if($method!=='POST'){header('Allow: GET, POST');blog_api_reply(405,['error'=>'Допустимы GET и POST.']);}
    if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')blog_api_reply(415,['error'=>'Требуется application/json.']);
    $raw=file_get_contents('php://input',false,null,0,262145);
    if(strlen($raw)>262144)blog_api_reply(413,['error'=>'Статья слишком большая.']);
    $input=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($input)||array_is_list($input))blog_api_reply(400,['error'=>'Ожидается JSON-объект.']);
    $allowed=array_merge(array_keys(blog_validate_example()),['id','version']);
    if(array_diff(array_keys($input),$allowed))blog_api_reply(422,['error'=>'Неизвестные поля. API сохраняет только черновики.']);
    foreach(['id','version']as$key)if(isset($input[$key])&&(!is_int($input[$key])||$input[$key]<0))blog_api_reply(422,['error'=>'id и version должны быть целыми неотрицательными числами.']);
    $id=$input['id']??0;$version=$input['version']??0;
    if($id&&$version<1)blog_api_reply(422,['error'=>'Для редактирования передайте текущую version.']);
    // New generated text always requires an editorial review. Existing live snapshots are untouched.
    if(isset($input['editor_notes'])&&!is_string($input['editor_notes']))blog_api_reply(422,['error'=>'editor_notes должен быть строкой.']);
    $input['editor_notes']=trim($input['editor_notes']??'');
    if($input['editor_notes']==='')$input['editor_notes']='Материал подготовлен ИИ. Проверьте факты, рекомендации, подписи изображений и SEO перед публикацией.';
    $saved=blog_save($input,$id,$version);$post=blog_get($saved);
    blog_api_reply($id?200:201,['id'=>$saved,'version'=>(int)$post['version'],'saved'=>'draft','edit_url'=>'/blog_edit.php?id='.$saved,'preview_url'=>'/blog_preview.php?id='.$saved]);
}catch(JsonException|InvalidArgumentException $e){blog_api_reply(422,['error'=>$e->getMessage()]);}
catch(PDOException $e){blog_api_reply(409,['error'=>'Не удалось сохранить. Проверьте уникальность URL и состояние модуля.']);}
catch(RuntimeException $e){blog_api_reply(409,['error'=>$e->getMessage()]);}
catch(Throwable $e){blog_api_reply(500,['error'=>'Ошибка сохранения черновика.']);}
function blog_validate_example():array {return array_diff_key(blog_empty(),array_flip(['id','version','published_json','scheduled_json','scheduled_at']));}
