<?php
declare(strict_types=1);
require_once __DIR__.'/blog.php';
require_once __DIR__.'/site_sitemap.php';

function blog_link(string $url): string {
    if(preg_match('~^/(?!/)[a-zA-Z0-9/_#?=&.%-]*$~D',$url))return $url;
    if(filter_var($url,FILTER_VALIDATE_URL)&&strtolower(parse_url($url,PHP_URL_SCHEME)??'')==='https')return $url;
    return '#';
}
function blog_inline(string $text): string {
    // Parse a deliberately small Markdown subset. Raw HTML is always escaped.
    $parts=preg_split('/(\*\*[^*\n]+\*\*|\*[^*\n]+\*|\[[^\]\n]+\]\([^\s)]+\))/', $text,-1,PREG_SPLIT_DELIM_CAPTURE);
    $html='';foreach($parts as $part){
        if(preg_match('/^\*\*(.+)\*\*$/sD',$part,$m))$html.='<strong>'.blog_e($m[1]).'</strong>';
        elseif(preg_match('/^\*(.+)\*$/sD',$part,$m))$html.='<em>'.blog_e($m[1]).'</em>';
        elseif(preg_match('/^\[([^\]]+)\]\(([^)]+)\)$/D',$part,$m))$html.='<a href="'.blog_e(blog_link($m[2])).'" rel="noopener">'.blog_e($m[1]).'</a>';
        else $html.=blog_e($part);
    }return $html;
}
function blog_image_url(string $ref,int $width=960,bool $admin=false): string {
    if(!blog_image_ref($ref))return '';
    if(str_starts_with($ref,'media:'))return $admin?'blog_media.php?id='.substr($ref,6).'&size='.$width:'/blog/media/'.substr($ref,6).'-'.$width.'.webp';
    return $admin?rtrim(blog_config()['site_url'],'/').$ref:$ref;
}
function blog_image(string $ref,string $alt,string $caption='',bool $hero=false,bool $admin=false): string {
    if(!blog_image_ref($ref))return '<p>Изображение не выбрано.</p>';
    $src=blog_image_url($ref,960,$admin);$srcset='';
    if(str_starts_with($ref,'media:'))$srcset=' srcset="'.blog_e(blog_image_url($ref,480,$admin)).' 480w, '.blog_e($src).' 960w, '.blog_e(blog_image_url($ref,1600,$admin)).' 1600w" sizes="(max-width: 700px) 100vw, 900px"';
    return '<figure class="blog-figure"><img src="'.blog_e($src).'"'.$srcset.' alt="'.blog_e($alt).'" '.($hero?'fetchpriority="high"':'loading="lazy"').' decoding="async"><figcaption>'.blog_e($caption).'</figcaption></figure>';
}
function blog_body(string $body,bool $admin=false): array {
    $html='';$toc=[];$paragraph=[];$list='';$n=0;
    $flush=function()use(&$html,&$paragraph,&$list){if($paragraph){$html.='<p>'.blog_inline(implode(' ',$paragraph)).'</p>';$paragraph=[];}if($list){$html.='</'.$list.'>';$list='';}};
    foreach(explode("\n",str_replace("\r",'',$body)) as $line){$line=trim($line);
        if($line===''){$flush();continue;}
        if(preg_match('/^(#{2,3}) (.+)$/u',$line,$m)){$flush();$id='section-'.++$n;$level=strlen($m[1]);$html.='<h'.$level.' id="'.$id.'">'.blog_e($m[2]).'</h'.$level.'>';$toc[]=['id'=>$id,'text'=>$m[2],'level'=>$level];}
        elseif(preg_match('/^!\[([^\]]+)\]\(([^)]+)\)(?:\s+"([^"]*)")?$/u',$line,$m)){$flush();$html.=blog_image($m[2],$m[1],$m[3]??'',false,$admin);}
        elseif(preg_match('/^(?:([-*]) |(\d+)\. )(.+)$/u',$line,$m)){$type=$m[1]!==''?'ul':'ol';if($list!==$type){$flush();$list=$type;$html.='<'.$list.'>';}$html.='<li>'.blog_inline($m[3]).'</li>';}
        elseif(str_starts_with($line,'> ')){$flush();$html.='<aside class="blog-callout">'.blog_inline(substr($line,2)).'</aside>';}
        else {if($list)$flush();$paragraph[]=$line;}
    }$flush();return [$html,$toc];
}
function blog_chrome(): array {
    $root=blog_config()['site_root'];$source=file_get_contents($root.'/index.html');
    preg_match('~<header>.*?</header>~s',$source,$header);preg_match('~<footer class="site">.*?</footer>~s',$source,$footer);
    if(!$header||!$footer)throw new RuntimeException('Не найдены общие шапка и подвал сайта.');
    $h=preg_replace('~href="#([^"]*)"~','href="/#$1"',$header[0]);
    $h=preg_replace('~ onclick="openStatus\(event\)"~','',$h);
    $h=str_replace('href="/#"','href="/#status"',$h);
    if(!str_contains($h,'href="/blog/"'))$h=str_replace('</nav>','<a href="/blog/">Блог</a></nav>',$h);
    $f=str_replace('href="privacy.html"','href="/privacy.html"',$footer[0]);
    preg_match('~<!-- Yandex.Metrika counter -->.*?<!-- /Yandex.Metrika counter -->~s',$source,$metric);
    return [$h,$f,$metric[0]??''];
}
function blog_page(string $title,string $description,string $path,string $content,array $schema=[],bool $preview=false,string $image='/avior-storefront.jpg',string $ogType='website'): string {
    [$header,$footer,$metric]=blog_chrome();$base=rtrim(blog_config()['site_url'],'/');$canonical=$base.$path;
    $head='<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.blog_e($title).'</title><meta name="description" content="'.blog_e($description).'">';
    $head.=$preview?'<meta name="robots" content="noindex,nofollow">':'<link rel="canonical" href="'.blog_e($canonical).'">';
    $head.='<meta property="og:locale" content="ru_RU"><meta property="og:site_name" content="AVIOR"><meta property="og:type" content="'.$ogType.'"><meta property="og:title" content="'.blog_e($title).'"><meta property="og:description" content="'.blog_e($description).'"><meta property="og:url" content="'.blog_e($canonical).'"><meta property="og:image" content="'.blog_e(str_starts_with($image,'https:')?$image:$base.$image).'"><meta name="twitter:card" content="summary_large_image">';
    foreach($schema as $item)$head.='<script type="application/ld+json">'.blog_json($item).'</script>';
    $head.='<link rel="icon" href="/favicon.svg"><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css"><link rel="stylesheet" href="/premium.css"><link rel="stylesheet" href="/blog-assets/blog.css"><script src="/premium.js" defer></script>';
    if(!$preview)$head.='<script src="/blog-assets/analytics.js" defer></script>';
    return '<!doctype html><html lang="ru" data-theme="dark"><head>'.$head.'</head><body class="blog-page">'.($preview?'':$metric).$header.($preview?'<div class="blog-preview-label">Предпросмотр черновика · не опубликовано · индексация отключена</div>':'').$content.$footer.'</body></html>';
}
function blog_card(array $p): string {
    return '<a class="blog-card" href="/blog/'.blog_e($p['slug']).'/"><img src="'.blog_e(blog_image_url($p['cover'],480)).'" alt="'.blog_e($p['cover_alt']).'" loading="lazy" decoding="async"><div class="blog-card-copy"><span class="blog-kicker">'.blog_e(blog_categories()[$p['category']]).'</span><h2>'.blog_e($p['title']).'</h2><p>'.blog_e($p['excerpt']).'</p><div class="blog-card-meta"><time datetime="'.substr($p['published_at'],0,10).'">'.date('d.m.Y',strtotime($p['published_at'])).'</time><span>Читать ↗</span></div></div></a>';
}
function blog_article(array $p,array $posts=[],bool $preview=false,bool $admin=false): string {
    [$body,$toc]=blog_body($p['body'],$admin);$base=rtrim(blog_config()['site_url'],'/');$url='/blog/'.$p['slug'].'/';$date=$p['published_at']??blog_now();
    $html='<main class="blog-article wrap" data-article-id="'.(int)($p['id']??0).'"><div class="crumbs"><a href="/">Главная</a> / <a href="/blog/">Советы мастера</a></div><div class="blog-kicker">'.blog_e(blog_categories()[$p['category']]).' · '.($preview?'Черновик':'<time datetime="'.substr($date,0,10).'">'.date('d.m.Y',strtotime($date)).'</time>').'</div><h1>'.blog_e($p['title']).'</h1><p class="blog-deck">'.blog_e($p['excerpt']).'</p><p class="blog-byline">Автор: '.blog_e($p['author']).' · сервисный центр Avior</p>'.blog_image($p['cover'],$p['cover_alt'],$p['cover_caption'],true,$admin).'<div class="blog-reading">';
    if(count($toc)>=3){$html.='<nav class="blog-toc" aria-label="Оглавление"><b>В этой статье</b><ol>';foreach($toc as $h)$html.='<li class="toc-h'.$h['level'].'"><a href="#'.$h['id'].'">'.blog_e($h['text']).'</a></li>';$html.='</ol></nav>';}
    $html.='<div class="blog-prose">'.$body.'<div class="blog-tags">';foreach(explode(',',$p['tags']) as $tag)if(trim($tag)!=='')$html.='<span>'.blog_e(trim($tag)).'</span>';
    $html.='</div><aside class="blog-service"><span class="blog-kicker">ПРОФИЛЬНАЯ УСЛУГА</span><h2>'.blog_e(blog_services()[$p['service']]).'</h2><a data-blog-service href="/'.blog_e($p['service']).'/">Подробнее об услуге ↗</a></aside></div></div><aside class="blog-help"><div><span class="blog-kicker">МАСТЕР РЯДОМ</span><h2>Нужна помощь с ремонтом?</h2><p>Специалисты Avior проведут диагностику и помогут определить причину неисправности.</p><small>Москва, Можайское шоссе, 4, корп. 1 · рядом с метро Кунцевская</small></div><a class="btn btn-main" data-blog-lead href="/#leadForm">Оставить заявку на ремонт</a></aside>';
    $related=array_values(array_filter($posts,fn($x)=>$x['id']!==($p['id']??0)));usort($related,fn($a,$b)=>(int)($b['category']===$p['category'])<=>(int)($a['category']===$p['category']));
    if($related){$html.='<section class="blog-related"><div class="sec-head"><h2>Читайте также</h2></div><div class="blog-grid">';foreach(array_slice($related,0,3) as $r)$html.=blog_card($r);$html.='</div></section>';}$html.='</main>';
    $schema=[['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$p['title'],'description'=>$p['seo_description'],'mainEntityOfPage'=>$base.$url,'image'=>[$base.blog_image_url($p['cover'],1600)],'datePublished'=>date(DATE_ATOM,strtotime($date)),'dateModified'=>date(DATE_ATOM,strtotime($p['modified_at']??$date)),'author'=>['@type'=>'Person','name'=>$p['author']],'publisher'=>['@type'=>'Organization','name'=>'Avior','url'=>$base.'/']],['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'Главная','item'=>$base.'/'],['@type'=>'ListItem','position'=>2,'name'=>'Советы мастера','item'=>$base.'/blog/'],['@type'=>'ListItem','position'=>3,'name'=>$p['title'],'item'=>$base.$url]]]];
    return blog_page($p['seo_title'],$p['seo_description'],$url,$html,$preview?[]:$schema,$preview,blog_image_url($p['cover'],1600),'article');
}
function blog_listing(array $posts,string $category='',int $page=1,bool $preview=false): string {
    $all=$posts;$filtered=$category===''?$posts:array_values(array_filter($posts,fn($p)=>$p['category']===$category));$count=count($filtered);$pages=max(1,(int)ceil($count/6));
    $base='/blog/'.($category!==''?'category/'.$category.'/':'');$path=$base.($page>1?'page/'.$page.'/':'');
    $html='<main class="blog-index wrap"><div class="blog-intro"><div><div class="eyebrow">ЗНАНИЯ И ОПЫТ AVIOR</div><h1>Советы мастера</h1><p class="blog-deck">Полезные статьи о ремонте смартфонов, ноутбуков и компьютеров от специалистов Avior</p></div><div class="blog-intro-mark" aria-hidden="true">A<span>ДЕТАЛИ ИМЕЮТ ЗНАЧЕНИЕ</span></div></div><div class="blog-controls"><label class="blog-search">Поиск по статьям<input id="blog-search" type="search" placeholder="Например, перегрев или стекло" autocomplete="off"></label><nav class="blog-filters" aria-label="Категории"><a href="/blog/" data-category=""'.($category===''?' aria-current="page"':'').'>Все статьи</a>';
    foreach(blog_categories() as $id=>$name)$html.='<a href="/blog/category/'.$id.'/" data-category="'.$id.'"'.($category===$id?' aria-current="page"':'').'>'.$name.'</a>';
    $html.='</nav></div><div class="blog-list-heading"><h2>'.($category!==''?blog_e(blog_categories()[$category]):'Все публикации').'</h2><span id="blog-count" role="status" aria-live="polite">Материалов: '.$count.'</span></div><div id="blog-results" class="blog-grid">';
    foreach(array_slice($filtered,($page-1)*6,6) as $p)$html.=blog_card($p);
    if(!$count)$html.='<div class="blog-empty"><h2>Готовим полезные материалы</h2><p>Скоро здесь появятся советы по ремонту и уходу за техникой.</p><a href="/#services">А пока — посмотрите услуги мастерской ↗</a></div>';
    $html.='</div><nav id="blog-pagination" class="blog-pagination" aria-label="Страницы">';if($pages>1)for($i=1;$i<=$pages;$i++)$html.='<a href="'.$base.($i>1?'page/'.$i.'/':'').'"'.($page===$i?' aria-current="page"':'').'>'.$i.'</a>';$html.='</nav>';
    if($all){$html.='<section class="blog-new"><div class="sec-head"><div class="eyebrow">СВЕЖИЙ ВЗГЛЯД</div><h2>Новые публикации</h2></div><div class="blog-grid">';foreach(array_slice($all,0,3) as $p)$html.=blog_card($p);$html.='</div></section>';}
    $html.='<aside class="blog-help"><div><h2>У каждой поломки есть причина.</h2><p>Поможем найти её и обсудим варианты ремонта.</p></div><a class="btn btn-main" href="/#leadForm">Обсудить ремонт</a></aside></main><script src="/blog-assets/blog.js" defer></script>';
    return blog_page('Советы мастера'.($category!==''?' — '.blog_categories()[$category]:'').($page>1?' — страница '.$page:'').' | Avior','Статьи Avior о ремонте, диагностике и уходе за смартфонами, ноутбуками и компьютерами.',$path,$html,[],$preview);
}
function blog_put(string $file,string $data): void {if(!is_dir(dirname($file)))mkdir(dirname($file),0755,true);if(file_put_contents($file,$data)===false)throw new RuntimeException('Не удалось записать '.basename($file));}
function blog_remove_tree(string $path): void {if(is_link($path))throw new RuntimeException('Символические ссылки запрещены.');foreach(new DirectoryIterator($path) as $f){if($f->isDot())continue;if($f->isDir())blog_remove_tree($f->getPathname());else unlink($f->getPathname());}rmdir($path);}
function blog_render_tree(array $posts,string $out,bool $preview=false): void {
    $slugs=[];foreach($posts as $p){if(isset($slugs[$p['slug']]))throw new RuntimeException('URL уже занят опубликованной версией: '.$p['slug']);$slugs[$p['slug']]=true;}
    usort($posts,fn($a,$b)=>strcmp($b['published_at'],$a['published_at']) ?: $b['id']<=>$a['id']);
    blog_put($out.'/.avior-blog-generated','Managed by AviorCMS blog publisher.');
    foreach(array_merge([''],array_keys(blog_categories())) as $cat){$count=count($cat===''?$posts:array_filter($posts,fn($p)=>$p['category']===$cat));for($i=1;$i<=max(1,ceil($count/6));$i++){blog_put($out.'/'.($cat!==''?'category/'.$cat.'/':'').($i>1?'page/'.$i.'/':'').'index.html',blog_listing($posts,$cat,$i,$preview));}}
    $index=[];foreach($posts as $p){blog_put($out.'/'.$p['slug'].'/index.html',blog_article($p,$posts,$preview));$index[]=['id'=>$p['id'],'slug'=>$p['slug'],'title'=>$p['title'],'excerpt'=>$p['excerpt'],'category'=>$p['category'],'category_label'=>blog_categories()[$p['category']],'date'=>substr($p['published_at'],0,10),'cover'=>blog_image_url($p['cover'],480),'alt'=>$p['cover_alt'],'text'=>strip_tags(blog_body($p['body'])[0]).' '.$p['tags']];foreach(blog_media_ids($p) as $id)foreach([480,960,1600] as $w){$src=blog_config()['private_dir'].'/media/'.$id.'-'.$w.'.webp';if(!is_file($src))throw new RuntimeException('Изображение отсутствует в хранилище.');blog_put($out.'/media/'.$id.'-'.$w.'.webp',file_get_contents($src));}}
    blog_put($out.'/search-index.json',blog_json($index));
}
function blog_build(): void {
    $cfg=blog_config();$root=realpath($cfg['site_root']);
    if(!$root||!is_file($root.'/index.html')||!is_dir($root.'/blog-assets'))throw new RuntimeException('Неверный корень сайта или не установлены blog-assets.');
    $target=$root.'/blog';if(is_link($target)||(is_dir($target)&&!is_file($target.'/.avior-blog-generated')))throw new RuntimeException('Папка blog не принадлежит генератору.');
    $posts=[];foreach(db()->query('SELECT published_json FROM blog_posts WHERE published_json IS NOT NULL') as $r)$posts[]=json_decode($r['published_json'],true,512,JSON_THROW_ON_ERROR);
    $stage=$root.'/.blog-stage-'.bin2hex(random_bytes(8));$backup=$root.'/.blog-old-'.bin2hex(random_bytes(8));
    // These staging directories contain only publishable content, never drafts.
    try{
        blog_render_tree($posts,$stage);
        blog_put($stage.'/sitemap.next',site_sitemap_xml($root,$cfg['site_url'],$stage));
        if(is_dir($target)&&!rename($target,$backup))throw new RuntimeException('Не удалось сохранить предыдущую версию блога.');
        if(!rename($stage,$target)){if(is_dir($backup))rename($backup,$target);throw new RuntimeException('Не удалось опубликовать новую версию.');}
        if(!rename($target.'/sitemap.next',$root.'/sitemap.xml')){blog_remove_tree($target);if(is_dir($backup))rename($backup,$target);throw new RuntimeException('Не удалось обновить sitemap.');}
        if(is_dir($backup))blog_remove_tree($backup);
    }finally{if(is_dir($stage))blog_remove_tree($stage);}
}
