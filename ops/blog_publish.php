<?php
// Cron/CLI only. Uses existing DB settings, never creates users or changes secrets.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/db.php';require __DIR__.'/../src/blog_render.php';
date_default_timezone_set('Europe/Moscow');
blog_lock(function(){
    db()->beginTransaction();
    try{
        $q=db()->prepare('SELECT id,scheduled_json FROM blog_posts WHERE scheduled_at IS NOT NULL AND scheduled_at<=?');$q->execute([blog_now()]);$due=$q->fetchAll();
        if(!$due){db()->commit();echo "No publications due.\n";return;}
        foreach($due as $row){$p=json_decode($row['scheduled_json'],true,512,JSON_THROW_ON_ERROR);blog_check_media($p);$p['modified_at']=blog_now();db()->prepare('UPDATE blog_posts SET published_json=?,scheduled_json=NULL,scheduled_at=NULL,version=version+1 WHERE id=?')->execute([blog_json($p),$row['id']]);}
        blog_build();db()->commit();echo count($due)." publications activated.\n";
    }catch(Throwable $e){db()->rollBack();throw $e;}
});
