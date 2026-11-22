#!/usr/bin/env python3
"""Mide la uniformidad del CSS propio del tema (lee reglas completas, no líneas sueltas).

Uso:  python uniformidad.py RUTA_AL_TEMA [--json salida.json]

Cuenta colores fijos frente a variables de Kadence, radios, sombras, anchos de contenedor, tamaños de H1,
breakpoints y !important. Es la medida de avance del sistema de diseño: cada familia migrada debe mejorarla.
"""
import collections
import glob
import json
import os
import re
import sys

FAMILIAS = {
    'front-page': 'Portada', 'formacion-page': 'Formación', 'taxonomy-oferta': 'Taxonomía',
    'seminarios-listado': 'Seminarios', 'seminarios-listado-current': 'Seminarios', 'seminarios-listado-polish': 'Seminarios',
    'single-seminario': 'Seminario ficha', 'style': 'Raíz', 'single-carta': 'Carta', 'single-carta-editorial': 'Carta',
    'single-carta-reference': 'Carta', 'archive-docente': 'Equipo', 'single-docente': 'Docente', 'programa-academico': 'Programas',
    'convenio': 'Convenios', 'page-contacto': 'Contacto', 'page-faq': 'FAQ', 'category-novedades': 'Novedades',
    'single-post': 'Noticia/Evento', 'site-chrome': 'Cabecera/pie', 'mobile-ux': 'Móvil', 'type-listing-cards': 'Tarjetas listado',
    'preinscripcion-seminario': 'Preinscripción', 'academic-articulations': 'Articulaciones', 'featured-images': 'Imágenes',
}


def familia(ruta):
    if ruta.replace('\\', '/').endswith('oferta-academica/style.css'):
        return 'Ficha oferta'
    return FAMILIAS.get(os.path.splitext(os.path.basename(ruta))[0], os.path.basename(ruta))


def leer_reglas(tema):
    archivos = sorted(glob.glob(os.path.join(tema, 'assets', 'css', '*.css'))) + [
        os.path.join(tema, 'style.css'), os.path.join(tema, 'oferta-academica', 'style.css')]
    reglas, media = [], collections.Counter()
    for ruta in archivos:
        if not os.path.exists(ruta):
            continue
        texto = re.sub(r'/\*.*?\*/', '', open(ruta, encoding='utf-8', errors='ignore').read(), flags=re.S)
        for m in re.finditer(r'@media[^{]*\(\s*(?:max|min)-width\s*:\s*([\d.]+)px', texto):
            media[m.group(1)] += 1
        for m in re.finditer(r'([^{}@][^{}]*)\{([^{}]*)\}', texto):
            props = {}
            for decl in m.group(2).split(';'):
                if ':' in decl:
                    k, v = decl.split(':', 1)
                    props[k.strip().lower()] = v.strip()
            reglas.append((familia(ruta), ' '.join(m.group(1).split()), props))
    return reglas, media, len(archivos)


def medir(tema):
    reglas, media, n_archivos = leer_reglas(tema)
    radios, sombras, hexes = collections.Counter(), collections.Counter(), collections.Counter()
    anchos, h1, importante = collections.defaultdict(collections.Counter), collections.defaultdict(collections.Counter), collections.Counter()
    usos_palette = 0
    for fam, sel, props in reglas:
        if 'border-radius' in props:
            radios[props['border-radius'].replace('!important', '').strip()] += 1
        v = props.get('box-shadow')
        if v and v != 'none':
            sombras[re.sub(r'\s+', ' ', v.replace('!important', '')).strip()] += 1
        mw = props.get('max-width', '').replace('!important', '').strip()
        m = re.search(r'(\d{3,4})px', mw)
        if (m and 900 <= int(m.group(1)) <= 1400) or 'content-width' in mw:
            anchos[fam][mw] += 1
        if 'font-size' in props and re.search(r'(^|[\s,>+~])h1($|[\s,.:\[>+~])', sel):
            h1[fam][props['font-size'].replace('!important', '').strip()] += 1
        for valor in props.values():
            for h in re.findall(r'#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b', valor):
                hexes[h.lower()] += 1
            usos_palette += len(re.findall(r'var\(--global-palette\d', valor))
            if '!important' in valor:
                importante[fam] += 1
    return {
        'archivos_css': n_archivos,
        'reglas': len(reglas),
        'colores': {'hex_distintos': len(hexes), 'usos_hex': sum(hexes.values()), 'usos_var_palette': usos_palette,
                    'pct_var_palette': round(100 * usos_palette / max(1, usos_palette + sum(hexes.values())), 1),
                    'mas_repetidos': hexes.most_common(10)},
        'radios': {'distintos': len(radios), 'usos': sum(radios.values()), 'mas_repetidos': radios.most_common(8)},
        'sombras': {'distintas': len(sombras), 'usos': sum(sombras.values())},
        'anchos_contenedor': {k: dict(v) for k, v in sorted(anchos.items())},
        'h1_font_size': {k: dict(v) for k, v in sorted(h1.items())},
        'breakpoints': dict(sorted(media.items(), key=lambda kv: -kv[1])),
        'important_total': sum(importante.values()),
        'important_por_familia': importante.most_common(6),
    }


if __name__ == '__main__':
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    r = medir(sys.argv[1])
    if '--json' in sys.argv:
        with open(sys.argv[sys.argv.index('--json') + 1], 'w', encoding='utf-8') as f:
            json.dump(r, f, ensure_ascii=False, indent=1)
    c = r['colores']
    anchos_distintos = sorted({v for fam in r['anchos_contenedor'].values() for v in fam})
    print(f"CSS propio: {r['archivos_css']} archivos, {r['reglas']} reglas, !important: {r['important_total']}")
    print(f"Colores: {c['hex_distintos']} hex distintos, {c['usos_hex']} usos fijos vs {c['usos_var_palette']} var(--global-palette*) ({c['pct_var_palette']} % de Kadence)")
    print(f"Radios: {r['radios']['distintos']} valores distintos en {r['radios']['usos']} usos")
    print(f"Sombras: {r['sombras']['distintas']} valores distintos en {r['sombras']['usos']} usos")
    print(f"Anchos de contenedor (>=900 px o variable): {len(anchos_distintos)} distintos -> {', '.join(anchos_distintos)}")
    print(f"Breakpoints distintos: {len(r['breakpoints'])}")
