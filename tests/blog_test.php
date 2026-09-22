<?php
// Run against the NEW isolated preview from setup_blog_preview.php only.
if(PHP_SAPI!=='cli')exit(1);
$dir=realpath($argv[1]??'');if(!$dir||!is_file($dir.'/login.txt'))throw new RuntimeException('Isolated fixture required');
require $dir.'/crm/src/bootstrap.php';require $dir.'/crm/src/blog_render.php';
$checks=0;function check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label."\n";$checks++;}
function rejects(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){check(true,$label);return;}check(false,$label);}
$initial=db()->query('SELECT * FROM blog_posts ORDER BY id')->fetchAll();
check(count($initial)===3,'three draft seeds');check(count(array_filter($initial,fn($p)=>$p['published_json']!==null))===0,'no seed published');
foreach(['../escape','a/b','<script>','media','page']as$slug){$bad=$initial[0];$bad['slug']=$slug;rejects(fn()=>blog_validate($bad),'invalid slug '.$slug);}
$attack=blog_body('## <script>alert(1)</script>' . "\n\n" . '<img src=x onerror=alert(1)>' . "\n\n" . '[bad](javascript:alert)' . "\n\n" . '**<svg onload=x>**')[0];
check(!str_contains($attack,'<script>')&&!str_contains($attack,'<svg')&&!str_contains($attack,'href="javascript:'),'Markdown XSS escaped');
check(!blog_image_ref('https://evil.test/a.svg')&&!blog_image_ref('/../config.php'),'image allowlist');
rejects(fn()=>blog_transition(1,'publish',1),'editor notes block publication');
$p=$initial[0];$p['editor_notes']='';blog_save($p,1,1);rejects(fn()=>blog_save($p,1,1),'optimistic edit conflict');
blog_transition(1,'publish',2);$p=blog_get(1);check($p['published_json']!==null,'publish snapshot');
$target=blog_config()['site_root'].'/blog/'.$p['slug'].'/index.html';$page=file_get_contents($target);
check(substr_count($page,'<h1>')===1&&str_contains($page,'BlogPosting')&&str_contains($page,'rel="canonical"'),'article SEO and single H1');
check(str_contains(file_get_contents(blog_config()['site_root'].'/sitemap.xml'),'/blog/'.$p['slug'].'/'),'published sitemap entry');
check(count(json_decode(file_get_contents(blog_config()['site_root'].'/blog/search-index.json'),true))===1,'search index excludes drafts');
$p['title']='Новая редакция только в черновике';blog_save($p,1,(int)$p['version']);check(file_get_contents($target)===$page,'draft edit preserves published HTML');
$p=blog_get(1);$p['slug']='new-article-url';blog_save($p,1,(int)$p['version']);$p=blog_get(1);blog_transition(1,'publish',(int)$p['version']);
check(!is_file($target)&&is_file(blog_config()['site_root'].'/blog/new-article-url/index.html'),'slug change removes old page');
$p=blog_get(1);blog_transition(1,'unpublish',(int)$p['version']);check(!is_file(blog_config()['site_root'].'/blog/new-article-url/index.html'),'unpublish removes HTML');check(!str_contains(file_get_contents(blog_config()['site_root'].'/sitemap.xml'),'new-article-url'),'unpublish removes sitemap entry');
$p=blog_get(1);blog_transition(1,'publish',(int)$p['version'],date('Y-m-d\TH:i',time()+3600));$p=blog_get(1);check($p['scheduled_json']!==null&&$p['published_json']===null,'schedule keeps draft private');
$preview=blog_article($initial[0],[],true,true);check(str_contains($preview,'noindex,nofollow')&&!str_contains($preview,'BlogPosting'),'authenticated preview noindex and no public schema');
$synthetic=[];for($i=0;$i<14;$i++){$p=blog_snapshot($initial[0],blog_now());$p['id']=$i+100;$p['slug']='fixture-'.$i;$synthetic[]=$p;}
$temp=$dir.'/pagination-check';blog_render_tree($synthetic,$temp,true);check(is_file($temp.'/page/3/index.html')&&is_file($temp.'/category/smartphones/page/3/index.html'),'static pagination and categories');
// Restore all fixture posts to their original drafts after tests.
db()->exec('DELETE FROM blog_posts');foreach($initial as $p){$keys=array_keys($p);db()->prepare('INSERT INTO blog_posts ('.implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($keys),'?')).')')->execute(array_values($p));}
blog_lock(fn()=>blog_build());
$drafts=array_map(fn($p)=>blog_snapshot($p,blog_now()),$initial);blog_remove_tree(blog_config()['site_root'].'/blog');blog_render_tree($drafts,blog_config()['site_root'].'/blog',true);
echo "$checks checks passed. Draft demo restored.\n";
