"""Integration checks against the isolated loopback preview; no production calls."""
from pathlib import Path
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, sqlite3, sys, json
fixture=Path(sys.argv[1]).resolve()
assert (fixture/'login.txt').is_file(), 'Isolated fixture required'
base='http://127.0.0.1:4177/'
password=re.search(r'Password: (.+)',(fixture/'login.txt').read_text()).group(1)
def session():return urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def req(client,path,data=None,headers={}):
    if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
    try:
        r=client.open(urllib.request.Request(base+path,data=data,headers=headers));return r.status,r.read().decode(errors='replace'),r.geturl(),r.headers
    except urllib.error.HTTPError as e:return e.code,e.read().decode(errors='replace'),e.url,e.headers
def token(html):return re.search(r'name="csrf_token" value="([a-f0-9]+)"',html).group(1)
checks=0
def check(value,label):
    global checks
    assert value,label
    checks+=1;print('PASS',label)
anon=session()
for path in ['blog.php','blog_edit.php?id=1','blog_preview.php?id=1','blog_media.php?id='+'a'*32]:
    status,body,url,h=req(anon,path);check(url.endswith('login.php'), 'unauthenticated protected '+path.split('?')[0])
admin=session();_,html,_,_=req(admin,'login.php');status,html,url,h=req(admin,'login.php',{'csrf_token':token(html),'username':'preview','password':password});check(url.endswith('blog.php'),'authenticated login')
check('noindex' in h.get('X-Robots-Tag','') and 'no-store' in h.get('Cache-Control',''),'private headers')
check(req(admin,'blog.php',{'action':'delete_media','media_id':'a'*32})[0]==403,'CSRF rejection')
_,html,_,_=req(admin,'blog.php');csrf=token(html)
def upload(payload,name,mime):
    boundary='AviorLocalTestBoundary';b=[]
    for k,v in [('csrf_token',csrf),('action','upload')]:b.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    b.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+payload+b'\r\n');b.append(f'--{boundary}--\r\n'.encode())
    return req(admin,'blog.php',b''.join(b),{'Content-Type':'multipart/form-data; boundary='+boundary})
status,html,_,_=upload(b'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>','fake.jpg','image/jpeg')
check('Неподдерживаемое изображение' in html,'SVG disguised as JPEG rejected')
asset=(fixture/'site/apple-touch-icon.png').read_bytes();status,html,_,_=upload(asset,'preview.png','image/png');check('Изображение загружено' in html,'real image upload')
conn=sqlite3.connect(fixture/'crm/preview.sqlite');row=conn.execute('SELECT id FROM blog_media ORDER BY created_at DESC LIMIT 1').fetchone();assert row;mid=row[0]
check(all((fixture/'private/media'/f'{mid}-{w}.webp').is_file() for w in (480,960,1600)),'WebP variants generated')
check(req(admin,f'blog_media.php?id={mid}&size=480')[3].get('Content-Type')=='image/webp','authenticated image content type')
conn.execute('UPDATE blog_posts SET cover=? WHERE id=1',('media:'+mid,));conn.commit()
_,html,_,_=req(admin,'blog.php',{'csrf_token':csrf,'action':'delete_media','media_id':mid});check('Изображение используется' in html,'referenced media cannot be deleted')
conn.execute("UPDATE blog_posts SET cover='/glass-photo.webp' WHERE id=1");conn.commit()
_,html,_,_=req(admin,'blog.php',{'csrf_token':csrf,'action':'delete_media','media_id':mid});check('Изображение удалено' in html,'unused media delete')
check(not (fixture/'private/media'/f'{mid}-480.webp').exists(),'deleted media removed from disk')
conn.execute("UPDATE users SET role='engineer' WHERE id=1");conn.commit()
engineer=session();_,html,_,_=req(engineer,'login.php');status,_,_,_=req(engineer,'login.php',{'csrf_token':token(html),'username':'preview','password':password});check(status==403,'engineer cannot manage blog')
conn.execute("UPDATE users SET role='owner' WHERE id=1");conn.commit();conn.close()
print(checks,'HTTP checks passed')
