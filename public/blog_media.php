<?php
require __DIR__.'/../src/blog_admin.php';
$id=(string)($_GET['id']??'');$size=(int)($_GET['size']??480);
if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!in_array($size,[480,960,1600],true)){http_response_code(404);exit;}
$file=blog_config()['private_dir'].'/media/'.$id.'-'.$size.'.webp';
if(!is_file($file)){http_response_code(404);exit;}
header('Content-Type: image/webp');header('Content-Length: '.filesize($file));readfile($file);
