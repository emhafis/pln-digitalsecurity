import json
with open('tmp_routes.json',encoding='utf-8-sig') as f: r=json.load(f)
for x in r:
    m=' '.join(x.get('middleware',[]))
    if 'auth' not in m and x.get('method','')!='HEAD':
        print(x['method'],'|',x['uri'],'|',x.get('name'),'|',m)
