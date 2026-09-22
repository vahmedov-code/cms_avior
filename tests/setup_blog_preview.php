<?php
// Creates an isolated, loopback-only demo. Does not load production config.
if(PHP_SAPI!=='cli')exit(1);
$site=realpath($argv[1]??'');$out=$argv[2]??'';
if(!$site||!is_file($site.'/index.html')||$out===''||file_exists($out))throw new RuntimeException('Provide site checkout and a NEW preview directory.');
mkdir($out,0700,true);$out=realpath($out);$crm=dirname(__DIR__);
function cp_tree(string $src,string $dest):void{if(!is_dir($dest))mkdir($dest,0755,true);foreach(new DirectoryIterator($src)as$f){if($f->isDot()||$f->getFilename()==='.git'||$f->getFilename()==='.github')continue;$target=$dest.'/'.$f->getFilename();if($f->isDir())cp_tree($f->getPathname(),$target);else copy($f->getPathname(),$target);}}
cp_tree($site,$out.'/site');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out.'/site',FilesystemIterator::SKIP_DOTS)) as $f){if($f->isFile()&&$f->getExtension()==='html')file_put_contents($f->getPathname(),str_replace('rel="canonical" href="https://avior.moscow','rel="canonical" href="http://127.0.0.1:4176',file_get_contents($f->getPathname())));}
mkdir($out.'/crm/src',0700,true);mkdir($out.'/crm/config',0700,true);mkdir($out.'/crm/public',0700,true);mkdir($out.'/private',0700,true);
foreach(['auth.php','functions.php','layout_header.php','layout_footer.php','blog.php','blog_render.php','site_sitemap.php','blog_admin.php']as$f)copy($crm.'/src/'.$f,$out.'/crm/src/'.$f);
foreach(['blog.php','blog_edit.php','blog_preview.php','blog_media.php','blog_access.php']as$f)copy($crm.'/public/'.$f,$out.'/crm/public/'.$f);
mkdir($out.'/crm/public/api/ai',0700,true);copy($crm.'/public/api/ai/blog.php',$out.'/crm/public/api/ai/blog.php');
cp_tree($crm.'/public/assets',$out.'/crm/public/assets');
$password=bin2hex(random_bytes(10));file_put_contents($out.'/login.txt',"Local preview only\nUser: preview\nPassword: $password\n");
$cfg=['enabled'=>true,'site_root'=>$out.'/site','private_dir'=>$out.'/private','site_url'=>'http://127.0.0.1:4176'];
file_put_contents($out.'/crm/config/blog.php',"<?php return ".var_export($cfg,true).';');
$bootstrap=<<<'PHP'
<?php
session_start();date_default_timezone_set('Europe/Moscow');
function db():PDO{static $db;if(!$db){$db=new PDO('sqlite:'.__DIR__.'/../preview.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);}return $db;}
require __DIR__.'/auth.php';require __DIR__.'/functions.php';
PHP;
file_put_contents($out.'/crm/src/bootstrap.php',$bootstrap);
$db=new PDO('sqlite:'.$out.'/crm/preview.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$sql=file_get_contents($crm.'/sql/migrations/2026_09_22_blog.sql');
$sql=preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
$sql=preg_replace('/,\s*INDEX blog_schedule \(scheduled_at\)/','',$sql);
$sql=str_replace(' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4','',$sql);$db->exec($sql);
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,username TEXT,password_hash TEXT,full_name TEXT,role TEXT)');
$q=$db->prepare('INSERT INTO users VALUES(1,?,?,?,?)');$q->execute(['preview',password_hash($password,PASSWORD_DEFAULT),'Предпросмотр Avior','owner']);
$login=<<<'PHP'
<?php
require __DIR__.'/../src/bootstrap.php';header('X-Robots-Tag: noindex,nofollow');header('Cache-Control: no-store');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals(csrf_token(),(string)($_POST['csrf_token']??''))){http_response_code(403);exit('CSRF');}
 if(($_SESSION['tries']??0)>15){http_response_code(429);exit('Too many attempts. Restart preview session.');}
 $_SESSION['tries']=($_SESSION['tries']??0)+1;
 if(attempt_login((string)($_POST['username']??''),(string)($_POST['password']??''))){$_SESSION['tries']=0;header('Location: blog.php');exit;}$error='Неверные данные';
}
?><!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Локальный вход — Avior</title><style>body{background:#0b0e10;color:#e9e5dc;font:16px system-ui;max-width:420px;margin:10vh auto;padding:24px}input,button{display:block;box-sizing:border-box;width:100%;padding:14px;margin:12px 0 24px;border-radius:8px}button{background:#e0b45c;border:0}</style><h1>Avior · предпросмотр</h1><p>Изолированная тестовая база. Рабочая CRM не подключена.</p><p><?=e($error)?></p><form method="post"><?=csrf_field()?><label>Логин<input name="username" autocomplete="username" required></label><label>Пароль<input type="password" name="password" autocomplete="current-password" required></label><button>Войти</button></form></html>
PHP;
file_put_contents($out.'/crm/public/login.php',$login);
file_put_contents($out.'/crm/public/index.php','<?php header("Location: blog.php");');
file_put_contents($out.'/crm/public/logout.php','<?php require __DIR__."/../src/bootstrap.php";do_logout();header("Location: login.php");');
require $out.'/crm/src/bootstrap.php';require $out.'/crm/src/blog_render.php';
foreach(json_decode(file_get_contents($crm.'/resources/blog/drafts.json'),true) as $p)blog_save($p,0,0);
$posts=[];foreach(db()->query('SELECT * FROM blog_posts')as$p)$posts[]=blog_snapshot($p,blog_now());
// Build a separate demo of drafts: noindex, no schema, no public deployment.
if(is_dir($out.'/site/blog'))blog_remove_tree($out.'/site/blog');
blog_render_tree($posts,$out.'/site/blog',true);
file_put_contents($out.'/site/robots.txt',"User-agent: *\nDisallow: /\n");
echo "Preview created: $out\nCredentials: $out/login.txt\n";
