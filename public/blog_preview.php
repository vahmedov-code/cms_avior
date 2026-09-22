<?php
require __DIR__.'/../src/blog_admin.php';
$p=blog_get((int)($_GET['id']??0));
// No public preview token, no draft API: uses the existing authenticated CRM session.
$html=blog_article($p,[],true,true);
$base=rtrim(blog_config()['site_url'],'/');
$html=preg_replace('~(href|src)="/(?!/)~','$1="'.blog_e($base).'/', $html);
echo $html;
