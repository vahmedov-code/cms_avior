"""Only run against the disposable loopback fixture from setup_blog_preview.php."""
from pathlib import Path
import hashlib,json,secrets,sqlite3,sys,urllib.request,urllib.error
root=Path(sys.argv[1]).resolve()
assert (root/'login.txt').is_file()
db=sqlite3.connect(root/'crm/preview.sqlite')
db.execute('CREATE TABLE IF NOT EXISTS settings (`key` TEXT PRIMARY KEY,value TEXT)')
key=secrets.token_hex(32)
db.execute('INSERT OR REPLACE INTO settings VALUES (?,?)',('blog_ai_token_hash',hashlib.sha256(key.encode()).hexdigest()))
db.commit()
client=urllib.request.build_opener(urllib.request.ProxyHandler({}))
def request(data=None,token=key,query='',method=None):
    headers={'Content-Type':'application/json'}
    if token:headers['Authorization']='Bearer '+token
    req=urllib.request.Request('http://127.0.0.1:4177/api/ai/blog.php'+query,data=json.dumps(data).encode() if data is not None else None,headers=headers,method=method)
    try:
        with client.open(req) as r:return r.status,json.load(r)
    except urllib.error.HTTPError as e:return e.code,json.load(e)
count=0
def check(ok,label):
    global count
    assert ok,label
    count+=1;print('PASS',label)
created=None
try:
    check(request(token=None)[0]==401,'missing Bearer rejected')
    check(request(token='0'*64)[0]==401,'other token rejected')
    check(request(token=None,query='?token='+key)[0]==401,'query token rejected')
    status,catalog=request();check(status==200 and catalog['permissions']==['read_blog','save_draft'],'blog-only catalog')
    draft=json.loads((root.parent/'cms_avior/resources/blog/drafts.json').read_text('utf-8'))[0]
    draft['slug']='api-test-'+secrets.token_hex(4);draft['editor_notes']=''
    status,result=request(draft);check(status==201 and result['saved']=='draft','API creates draft');created=result['id']
    row=db.execute('SELECT published_json,scheduled_json,editor_notes FROM blog_posts WHERE id=?',(created,)).fetchone()
    check(row[0] is None and row[1] is None and bool(row[2]),'AI draft private and review required')
    status,read=request(query='?id='+str(created));check(status==200 and read['post']['slug']==draft['slug'],'read saved draft')
    draft.update(id=created,version=result['version'],title='Исправленный черновик')
    check(request(draft)[0]==200,'update draft by version')
    check(request(draft)[0]==409,'stale version rejected')
    check(request(dict(draft,published_json='{}'))[0]==422,'publication fields rejected')
    check(request(method='DELETE')[0]==405,'delete forbidden')
    check(request(query='?id=999999999')[0]==404,'unknown article not found')
    db.execute('DELETE FROM settings WHERE `key`=?',('blog_ai_token_hash',));db.commit()
    check(request()[0]==403,'revocation blocks access')
finally:
    if created:db.execute('DELETE FROM blog_posts WHERE id=?',(created,))
    db.execute('DELETE FROM settings WHERE `key`=?',('blog_ai_token_hash',));db.commit();db.close()
print(count,'API checks passed')
