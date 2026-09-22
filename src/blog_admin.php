<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/blog_render.php';
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: SAMEORIGIN');
if(!current_user()){header('Location: login.php');exit;}
if(!is_admin()){http_response_code(403);exit('Доступ только для владельца и администратора.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $sent=$_POST['csrf_token']??'';
    if(!is_string($sent)||$sent===''||!hash_equals(csrf_token(),$sent)){http_response_code(403);exit('Недействительный CSRF-токен. Обновите страницу.');}
}
try{blog_config();}catch(Throwable $e){http_response_code(503);exit(blog_e($e->getMessage()));}
$activeNav='blog';
