<?php
require __DIR__.'/../src/site_sitemap.php';
$dir=sys_get_temp_dir().'/avior-sitemap-test-'.bin2hex(random_bytes(8));mkdir($dir);
function put_page(string $path,string $head=''):void{global $dir;$f=$dir.'/'.$path;if(!is_dir(dirname($f)))mkdir(dirname($f),0700,true);file_put_contents($f,'<!doctype html><html><head>'.$head.'</head><body>Test</body></html>');}
function has(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function clean(string $dir):void{foreach(new DirectoryIterator($dir)as$f){if($f->isDot())continue;if($f->isDir())clean($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}
try{
 put_page('index.html');put_page('service/index.html');put_page('privacy.html');put_page('blog/category/care/index.html');put_page('blog/page/2/index.html');
 put_page('draft/index.html','<meta content="noindex,follow" name="robots">');put_page('alias/index.html','<link href="https://avior.moscow/service/" rel="canonical">');put_page('foreign/index.html','<link rel="canonical" href="https://other.test/foreign/">');
 put_page('map-demo.html');put_page('yandex_abc123.html');put_page('.blog-stage-a/test.html');put_page('blocked/index.html');put_page('blocked/allowed/index.html');
 file_put_contents($dir.'/robots.txt',"User-agent: *\nDisallow: /blocked/\nAllow: /blocked/allowed/\n");
 $xml=site_sitemap_xml($dir,'https://avior.moscow');$doc=new DOMDocument();has($doc->loadXML($xml),'valid XML');
 has($doc->getElementsByTagName('loc')->length===6,'all public pages including categories and pagination');
 has(!str_contains($xml,'draft')&&!str_contains($xml,'alias')&&!str_contains($xml,'foreign')&&!str_contains($xml,'map-demo')&&!str_contains($xml,'yandex_')&&!str_contains($xml,'.blog-stage'),'private technical and noncanonical pages excluded');
 has(!str_contains($xml,'https://avior.moscow/blocked/</loc>')&&str_contains($xml,'/blocked/allowed/'),'robots allow/disallow respected');
 put_page('new-service/index.html');has(str_contains(site_sitemap_xml($dir,'https://avior.moscow'),'/new-service/'),'new page discovered without manual list');
 unlink($dir.'/new-service/index.html');has(!str_contains(site_sitemap_xml($dir,'https://avior.moscow'),'/new-service/'),'removed page removed from sitemap');
 put_page('blog/old/index.html');mkdir($dir.'/.next-blog');file_put_contents($dir.'/.next-blog/index.html','<html><body>Blog</body></html>');
 has(!str_contains(site_sitemap_xml($dir,'https://avior.moscow',$dir.'/.next-blog'),'/blog/old/'),'publishing uses replacement tree, excludes old articles');
 has($xml===site_sitemap_xml($dir,'https://avior.moscow',$dir.'/blog')?false:true,'updated content changes sitemap');
}finally{clean($dir);}
