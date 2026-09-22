<?php
declare(strict_types=1);

function blog_config(): array {
    $file=__DIR__.'/../config/blog.php';
    if (!is_file($file)) throw new RuntimeException('Блог ещё не настроен: config/blog.php отсутствует.');
    $cfg=require $file;
    if (empty($cfg['enabled'])) throw new RuntimeException('Модуль блога выключен.');
    return $cfg;
}
function blog_categories(): array { return ['smartphones'=>'Смартфоны','laptops'=>'Ноутбуки','computers'=>'Компьютеры','repair'=>'Диагностика и ремонт','care'=>'Советы по эксплуатации']; }
function blog_services(): array { return ['pereklejka-stekla'=>'Переклейка стекла','obsluzhivanie-ohlazhdeniya-noutbukov'=>'Обслуживание охлаждения','sborka-apgrejd-pk'=>'Сборка и апгрейд ПК','remont-materinskih-plat'=>'Ремонт материнских плат','modulnyj-remont'=>'Модульный ремонт','bga-pajka'=>'BGA-пайка']; }
function blog_e(string $s): string {return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function blog_json($value): string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);}
function blog_now(): string {return date('Y-m-d H:i:s');}
function blog_lock(callable $fn) {
    $dir=blog_config()['private_dir'];
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Не удалось создать закрытое хранилище.');
    $private=str_replace('\\','/',realpath($dir));
    foreach([blog_config()['site_root'],__DIR__.'/../public'] as $public){$public=realpath($public);if($public&&str_starts_with(strtolower($private.'/'),strtolower(str_replace('\\','/',$public).'/')))throw new RuntimeException('Хранилище черновиков должно быть вне публичных папок.');}
    $lock=fopen($dir.'/publish.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Не удалось заблокировать публикацию.');
    try {return $fn();} finally {flock($lock,LOCK_UN);fclose($lock);}
}
function blog_get(int $id): array {
    $q=db()->prepare('SELECT * FROM blog_posts WHERE id=?');$q->execute([$id]);
    $row=$q->fetch();if(!$row) throw new RuntimeException('Статья не найдена.');return $row;
}
function blog_empty(): array {return array_fill_keys(['slug','title','category','tags','excerpt','body','cover','cover_alt','cover_caption','author','seo_title','seo_description','service','editor_notes'],'')+['id'=>0,'version'=>0,'published_json'=>null,'scheduled_json'=>null,'scheduled_at'=>null];}
function blog_image_ref(string $ref): bool {
    return (bool)preg_match('~^(?:media:[a-f0-9]{32}|/(?:glass|cooling|pcbuild|plata|bga|modul|dannye|pristavka|monoblok|master(?:-[1-4])?)-photo\.webp)$~D',$ref);
}
function blog_validate(array $input): array {
    $out=[];
    foreach (['slug'=>120,'title'=>200,'category'=>40,'tags'=>500,'excerpt'=>1200,'body'=>100000,'cover'=>200,'cover_alt'=>250,'cover_caption'=>500,'author'=>160,'seo_title'=>200,'seo_description'=>350,'service'=>100,'editor_notes'=>5000] as $key=>$limit) {
        if(!is_string($input[$key]??'')) throw new InvalidArgumentException('Неверный формат поля '.$key);
        $out[$key]=trim($input[$key]??'');
        if(mb_strlen($out[$key])>$limit) throw new InvalidArgumentException('Слишком длинное поле '.$key);
    }
    if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$out['slug']) || in_array($out['slug'],['media','category','page','search','index'])) throw new InvalidArgumentException('URL: латинские буквы, цифры и дефисы; служебные имена запрещены.');
    foreach(['title','excerpt','body','cover_alt','author','seo_title','seo_description'] as $key) if($out[$key]==='') throw new InvalidArgumentException('Заполните поле '.$key);
    if(!isset(blog_categories()[$out['category']]) || !isset(blog_services()[$out['service']])) throw new InvalidArgumentException('Выберите категорию и услугу.');
    if(!blog_image_ref($out['cover'])) throw new InvalidArgumentException('Выберите изображение из медиатеки.');
    return $out;
}
function blog_media_ids(array $post): array {
    preg_match_all('/media:([a-f0-9]{32})/',($post['cover']??'').' '.($post['body']??''),$m);return array_unique($m[1]);
}
function blog_check_media(array $post): void {
    foreach(blog_media_ids($post) as $id){$q=db()->prepare('SELECT id FROM blog_media WHERE id=?');$q->execute([$id]);if(!$q->fetch()) throw new InvalidArgumentException('Изображение не найдено: '.$id);}
}
function blog_save(array $input,int $id,int $version): int {
    $data=blog_validate($input);
    return blog_lock(function() use($data,$id,$version){
        blog_check_media($data);
        $keys=array_keys($data);$args=array_values($data);$now=blog_now();
        if($id){
            $q=db()->prepare('UPDATE blog_posts SET '.implode(',',array_map(fn($k)=>$k.'=?',$keys)).',updated_at=?,version=version+1 WHERE id=? AND version=?');
            $q->execute(array_merge($args,[$now,$id,$version]));if($q->rowCount()!==1)throw new RuntimeException('Статья изменена в другой вкладке. Обновите страницу; ваш текст не сохранён.');
        }else{
            $q=db()->prepare('INSERT INTO blog_posts ('.implode(',',$keys).',created_at,updated_at) VALUES ('.implode(',',array_fill(0,count($keys)+2,'?')).')');$q->execute(array_merge($args,[$now,$now]));$id=(int)db()->lastInsertId();
        }
        return $id;
    });
}
function blog_snapshot(array $row,string $date): array {return blog_validate($row)+['id'=>(int)$row['id'],'published_at'=>$date,'modified_at'=>blog_now()];}
function blog_transition(int $id,string $action,int $version,string $date=''): void {
    if(!in_array($action,['publish','unpublish','cancel_schedule'],true))throw new InvalidArgumentException('Неизвестное действие.');
    blog_lock(function()use($id,$action,$version,$date){
        db()->beginTransaction();
        try{
            $row=blog_get($id);if((int)$row['version']!==$version)throw new RuntimeException('Статья уже изменена. Обновите страницу.');
            if($action==='unpublish'){$published=null;$scheduled=null;$when=null;}
            elseif($action==='cancel_schedule'){$published=$row['published_json'];$scheduled=null;$when=null;}
            else{
                if($row['editor_notes']!=='') throw new RuntimeException('Перед публикацией выполните и очистите редакционные замечания.');
                $previous=$row['published_json']?json_decode($row['published_json'],true):null;
                $when=$date!==''?DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date):new DateTimeImmutable($previous['published_at']??'now');
                if(!$when || ($date!=='' && $when->format('Y-m-d\TH:i')!==$date))throw new InvalidArgumentException('Некорректная дата публикации.');
                $snapshot=blog_snapshot($row,$when->format('Y-m-d H:i:s'));blog_check_media($snapshot);
                preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/',$row['body'],$images,PREG_SET_ORDER);
                foreach($images as $image)if(trim($image[1])===''||!blog_image_ref($image[2]))throw new InvalidArgumentException('Проверьте alt и коды изображений внутри статьи.');
                if($when->getTimestamp()>time()){$published=$row['published_json'];$scheduled=blog_json($snapshot);$when=$when->format('Y-m-d H:i:s');}
                else{$published=blog_json($snapshot);$scheduled=null;$when=null;}
            }
            $q=db()->prepare('UPDATE blog_posts SET published_json=?,scheduled_json=?,scheduled_at=?,version=version+1 WHERE id=?');$q->execute([$published,$scheduled,$when,$id]);
            blog_build();db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    });
}
function blog_upload(array $file): string {
    return blog_lock(fn()=>blog_upload_locked($file));
}
function blog_upload_locked(array $file): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>8*1024*1024 || !is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Загрузите JPEG, PNG или WebP размером до 8 МБ.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$size=getimagesize($file['tmp_name']);
    if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!$size||$size[0]*$size[1]>20000000)throw new InvalidArgumentException('Неподдерживаемое изображение или больше 20 мегапикселей.');
    $src=imagecreatefromstring(file_get_contents($file['tmp_name']));if(!$src)throw new InvalidArgumentException('Файл изображения повреждён.');
    $id=bin2hex(random_bytes(16));$dir=blog_config()['private_dir'].'/media';if(!is_dir($dir))mkdir($dir,0700,true);
    try{
        foreach([480,960,1600] as $w){$width=min($w,$size[0]);$height=max(1,(int)round($size[1]*$width/$size[0]));$dst=imagecreatetruecolor($width,$height);imagealphablending($dst,false);imagesavealpha($dst,true);imagecopyresampled($dst,$src,0,0,0,0,$width,$height,$size[0],$size[1]);if(!imagewebp($dst,$dir.'/'.$id.'-'.$w.'.webp',82))throw new RuntimeException('Не удалось сохранить изображение.');imagedestroy($dst);}
        $q=db()->prepare('INSERT INTO blog_media(id,original_name,width,height,created_at) VALUES(?,?,?,?,?)');$q->execute([$id,mb_substr(basename($file['name']),0,255),$size[0],$size[1],blog_now()]);
    }catch(Throwable $e){foreach([480,960,1600]as$w){$path=$dir.'/'.$id.'-'.$w.'.webp';if(is_file($path))unlink($path);}throw $e;}
    finally{imagedestroy($src);}
    return 'media:'.$id;
}
function blog_delete_media(string $id): void {
    if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Неверное изображение.');
    blog_lock(function()use($id){
        $q=db()->prepare('SELECT id FROM blog_posts WHERE cover=? OR body LIKE ? OR published_json LIKE ? OR scheduled_json LIKE ?');$q->execute(['media:'.$id,'%media:'.$id.'%','%media:'.$id.'%','%media:'.$id.'%']);if($q->fetch())throw new RuntimeException('Изображение используется в статье или сохранённой публикации. Сначала удалите ссылки на него.');
        foreach([480,960,1600] as $w){$f=blog_config()['private_dir'].'/media/'.$id.'-'.$w.'.webp';if(is_file($f)&&!unlink($f))throw new RuntimeException('Не удалось удалить файл.');}
        db()->prepare('DELETE FROM blog_media WHERE id=?')->execute([$id]);
    });
}
