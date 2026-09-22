<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/db.php';require __DIR__.'/../src/blog.php';
date_default_timezone_set('Europe/Moscow');
foreach(json_decode(file_get_contents(__DIR__.'/../resources/blog/drafts.json'),true,512,JSON_THROW_ON_ERROR) as $post){
    $q=db()->prepare('SELECT id FROM blog_posts WHERE slug=?');$q->execute([$post['slug']]);
    if($q->fetch()){echo "Already exists: ".$post['slug']."\n";continue;}
    blog_save($post,0,0);echo "Draft imported: ".$post['slug']."\n";
}
