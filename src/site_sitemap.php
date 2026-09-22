<?php
declare(strict_types=1);

/** Only public static HTML; drafts live outside the site root. Caller holds blog_lock. */
function site_sitemap_xml(string $root,string $base,?string $blogStage=null):string {
    $base=rtrim($base,'/');$urls=[];$rules=[];$agents=[];$directives=false;
    foreach(explode("\n",is_file($root.'/robots.txt')?file_get_contents($root.'/robots.txt'):'')as$line){
        $line=trim(explode('#',$line,2)[0]);if(!str_contains($line,':'))continue;
        [$key,$value]=array_map('trim',explode(':',$line,2));$key=strtolower($key);
        if($key==='user-agent'){if($directives){$agents=[];$directives=false;}$agents[]=strtolower($value);}
        elseif(in_array($key,['allow','disallow'],true)){$directives=true;if(in_array('*',$agents,true)&&$value!=='')$rules[]=[$key,$value];}
    }
    $scan=function(string $dir,string $prefix='',bool $top=false)use(&$scan,&$urls,$base,$blogStage,$rules):void{
        foreach(new DirectoryIterator($dir)as$file){
            $name=$file->getFilename();
            if($file->isDot()||$file->isLink()||str_starts_with($name,'.'))continue;
            if($file->isDir()){
                if(in_array(strtolower($name),['node_modules','vendor','uploads','assets','blog-assets','backup','backups','tests','preview'],true))continue;
                if($top&&$name==='blog'&&$blogStage!==null)continue;
                $scan($file->getPathname(),$prefix.$name.'/');continue;
            }
            if(strtolower($file->getExtension())!=='html'||preg_match('/^(?:map-demo|404|50[023]|yandex_[a-z0-9]+|google[a-z0-9]+)\.html$/i',$name))continue;
            $path='/'.$prefix.($name==='index.html'?'':$name);
            $best=-1;$allowed=true;
            foreach($rules as[$kind,$pattern]){
                $end=str_ends_with($pattern,'$');$pattern=$end?substr($pattern,0,-1):$pattern;
                $regex='~^'.str_replace('\\*','.*',preg_quote($pattern,'~')).($end?'$':'').'~';
                $length=strlen(str_replace('*','',$pattern));
                if(preg_match($regex,$path)&&($length>$best||($length===$best&&$kind==='allow'))){$best=$length;$allowed=$kind==='allow';}
            }
            if(!$allowed)continue;
            $url=$base.implode('/',array_map('rawurlencode',explode('/',$path)));
            $doc=new DOMDocument();$before=libxml_use_internal_errors(true);
            try{$ok=$doc->loadHTML(file_get_contents($file->getPathname()),LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
            finally{libxml_clear_errors();libxml_use_internal_errors($before);}
            if(!$ok)throw new RuntimeException('Не удалось проверить HTML для sitemap.');
            foreach($doc->getElementsByTagName('meta')as$meta){
                if(in_array(strtolower($meta->getAttribute('name')),['robots','googlebot','yandex'],true)&&preg_match('/\b(noindex|none)\b/i',$meta->getAttribute('content')))continue 2;
            }
            foreach($doc->getElementsByTagName('link')as$link){
                if(!in_array('canonical',preg_split('/\s+/',strtolower($link->getAttribute('rel'))),true))continue;
                $canonical=trim($link->getAttribute('href'));
                if(str_starts_with($canonical,'/')&&!str_starts_with($canonical,'//'))$canonical=$base.$canonical;
                if($canonical!==$url)continue 2; // Do not invent URLs from canonical tags.
            }
            $urls[$url]=true;
        }
    };
    $scan($root,'',true);
    if($blogStage!==null)$scan($blogStage,'blog/');
    if(!isset($urls[$base.'/']))throw new RuntimeException('Главная страница отсутствует: прежний sitemap сохранён.');
    if(count($urls)>50000)throw new RuntimeException('Для более 50000 страниц нужен sitemap index.');
    ksort($urls);$xml=new DOMDocument('1.0','UTF-8');$xml->formatOutput=true;
    $set=$xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9','urlset');$xml->appendChild($set);
    foreach(array_keys($urls)as$url){$node=$xml->createElement('url');$loc=$xml->createElement('loc');$loc->appendChild($xml->createTextNode($url));$node->appendChild($loc);$set->appendChild($node);}
    return $xml->saveXML();
}
function site_sitemap_refresh():void {
    $cfg=blog_config();$root=realpath($cfg['site_root']);if(!$root)throw new RuntimeException('Корень сайта недоступен.');
    $xml=site_sitemap_xml($root,$cfg['site_url']);$file=$root.'/sitemap.xml';
    if(is_file($file)&&file_get_contents($file)===$xml)return;
    $temporary=$root.'/.sitemap-'.bin2hex(random_bytes(8));
    try{
        if(file_put_contents($temporary,$xml)===false||!rename($temporary,$file))throw new RuntimeException('Не удалось обновить sitemap.');
    }finally{if(is_file($temporary))unlink($temporary);}
}
