<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/db.php';require __DIR__.'/../src/blog_render.php';
date_default_timezone_set('Europe/Moscow');
blog_lock(fn()=>blog_build());
echo "Published blog and sitemap rebuilt.\n";
