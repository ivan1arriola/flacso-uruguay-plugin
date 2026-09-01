import json, csv, os
BASE=os.path.dirname(__file__)
def loadj(p): return json.load(open(os.path.join(BASE,p),encoding='utf-8'))
def cmap(name):
    rows=list(csv.DictReader(open(os.path.join(BASE,'staging_local',f'mapa_{name}.csv'),encoding='utf-8-sig')))
    return {r['source_key']:int(r['new_id']) for r in rows if r.get('new_id')}
programs=cmap('programas'); offers=cmap('ofertas'); seminars=cmap('seminarios'); cohorts=cmap('cohortes'); editions=cmap('ediciones')
# This renderer deliberately fails until required parent/new IDs and unresolved relation enums are filled.
out=os.path.join(BASE,'payload_final'); os.makedirs(out,exist_ok=True)
for fname,map_parent,parent_field,parent_map in [
 ('ofertas_academicas.json','programa_source_key','programa_academico_id',programs),
 ('seminarios.json','programa_source_key','programa_academico_id',programs),
 ('cohortes.json','oferta_source_key','oferta_academica_id',offers),
 ('ediciones_seminario.json','seminario_source_key','seminario_id',seminars)]:
    rows=loadj('staging_local/'+fname); final=[]
    for r in rows:
        key=r[map_parent]
        if key not in parent_map: raise SystemExit(f'Falta new_id para {key}')
        d=r['data']; d[parent_field]=parent_map[key]; final.append(d)
    json.dump(final,open(os.path.join(out,fname),'w',encoding='utf-8'),ensure_ascii=False,indent=2)
print('Payloads de entidades renderizados en payload_final/. Relaciones y tablas de precio deben resolverse antes de carga final.')
