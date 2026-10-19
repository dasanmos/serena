#!/usr/bin/env python3
"""
Capturas automáticas para el manual de Serena (usa el MODO DEMOSTRACIÓN: datos ficticios).

Qué hace
  Lee src/ayuda/contenido.json, busca cada imagen declarada que aún no existe en public/assets/img/ayuda/
  y la toma entrando con cada rol de la demo, en la página (ruta) del capítulo que la ilustra.

Requisitos (una vez):   pip install playwright   y luego   playwright install chromium
Uso:
  python tools/capturar_ayuda.py --url https://serena.infinityfreeapp.com/public  # toma las que faltan
  python tools/capturar_ayuda.py --url https://... --forzar                       # rehace todas
  python tools/capturar_ayuda.py --url https://... --solo doc-seguimiento         # solo las que empiecen así
  python tools/capturar_ayuda.py --lista                                          # solo regenera CAPTURAS_PENDIENTES.md

Todas las capturas son automáticas: login y registro se toman en un navegador aparte sin sesión
(con el DANE de la demo, 999999999999), y la pantalla general y el micrófono con el estudiante de la demo.
Guía completa: docs/GUIA_CAPTURAS.md

Se abre un Chrome visible. Inicia sesión TÚ con la cuenta MAESTRA (así se resuelve el reCAPTCHA y la
verificación en dos pasos) y pulsa Enter en la terminal: desde ahí el script hace el resto solo.
Superadministrador y Secretaría de Educación se saltan (muestran colegios reales); usa --incluir-reales
si de verdad quieres capturarlos, y revisa que no salgan datos reales.
"""
import argparse, json, os, re, sys, time

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CONTENIDO = os.path.join(RAIZ, 'src', 'ayuda', 'contenido.json')
CARPETA = os.path.join(RAIZ, 'public', 'assets', 'img', 'ayuda')
REALES = {'superadmin', 'secretaria'}
DANE_DEMO = '999999999999'
# Capturas sin página propia en el manual: aquí se dice dónde tomarlas, para que ninguna quede a mano.
# Las «públicas» se toman sin sesión (navegador aparte); las demás, con el rol indicado de la demo.
PUBLICAS = {'gen-login-1.jpg': '/login.php', 'gen-registro-codigo-1.jpg': '/registro.php', 'gen-registro-1.jpg': '/registro.php'}
CON_ROL = {'gen-pantalla-1.jpg': ('estudiante', '/estudiante/dashboard.php'), 'gen-voz-1.jpg': ('estudiante', '/estudiante/emociones.php')}
INIT_COOKIES = ("try{localStorage.setItem('serena_cookies','necesarias');"
                "localStorage.setItem('serena_cookies_fecha',new Date().toISOString().slice(0,10));}catch(e){}")


def cargar():
    return json.load(open(CONTENIDO, encoding='utf-8'))


def entradas(d):
    """[(rol, capitulo_id, capitulo_titulo, seccion_titulo, ruta, archivo, pie)]"""
    salida = []
    bloques = [('general', d['general']), ('cuenta', d['cuenta'])] + list(d['roles'].items())
    for rol, bloque in bloques:
        for c in bloque['capitulos']:
            for s in c['secciones']:
                for im in s.get('imagenes', []):
                    salida.append((rol, c['id'], c['titulo'], s['titulo'], im.get('ruta') or c.get('ruta') or '', im['archivo'], im.get('pie', '')))  # una imagen puede tener su propia página
    vistos, unicas = set(), []
    for e in salida:  # una imagen compartida por varios roles se toma una sola vez
        if e[5] not in vistos:
            vistos.add(e[5]); unicas.append(e)
    return unicas


def generar_md(d):
    filas = [e for e in entradas(d) if not os.path.exists(os.path.join(CARPETA, e[5]))]
    por_rol = {}
    for e in filas:
        por_rol.setdefault(e[0], []).append(e)
    md = ['# Capturas pendientes para el Centro de ayuda', '',
          'Guarda cada captura en `public/assets/img/ayuda/` con **exactamente** el nombre indicado (JPG, ~1000-1400 px de ancho, menos de 400 KB). '
          'Mientras un archivo no exista, la ayuda no muestra nada en ese lugar (no da error).', '',
          'Forma rápida: `python tools/capturar_ayuda.py --url https://tu-sitio` las toma solas con el modo demostración (datos ficticios). '
          'Las que no tienen página (`—`) hay que tomarlas a mano.', '',
          f'**{len(filas)} pendientes.** Este archivo se regenera con `python tools/capturar_ayuda.py --lista`.', '']
    for rol, lista in por_rol.items():
        md += [f'## {rol} ({len(lista)})', '', '| Archivo | Capítulo → sección | Página | Qué mostrar |', '|---|---|---|---|']
        for r, cid, ct, st, ruta, arch, pie in lista:
            md.append(f'| `{arch}` | {ct} → {st} | `{ruta or "—"}` | {pie} |')
        md.append('')
    open(os.path.join(RAIZ, 'docs', 'CAPTURAS_PENDIENTES.md'), 'w', encoding='utf-8').write('\n'.join(md))
    print(f'CAPTURAS_PENDIENTES.md regenerado: {len(filas)} pendientes.')


# ---- pestañas de SIMAT / SIMPADE: la misma página con ?vista=... ----
VISTAS_SIMAT = {'1': 'resumen', '2': 'revision', '3': 'datos', '4': 'conciliar', '5': 'novedades', '6': 'ajustes'}
VISTAS_SIMPADE = {'1': 'panel', '2': 'deteccion', '3': 'casos', '4': 'retiros'}


def ruta_para(archivo, ruta):
    ruta = ruta.replace('{rol}', 'estudiante')  # capítulos de «Tu cuenta»: se toman con el estudiante
    m = re.search(r'(?:^|-)simat-(\d)\.jpg$', archivo)
    if m and m.group(1) in VISTAS_SIMAT:
        return ruta + '?vista=' + VISTAS_SIMAT[m.group(1)]
    m = re.search(r'(?:^|-)simpade-(\d)\.jpg$', archivo)
    if m and m.group(1) in VISTAS_SIMPADE:
        return ruta + '?vista=' + VISTAS_SIMPADE[m.group(1)]
    return ruta


# ---- pasos especiales: páginas que necesitan estado antes de la foto ----
def preparar(page, archivo):
    def elegir(nombre, indice=1):
        sel = page.locator(f'select[name="{nombre}"]').first
        if sel.count() and sel.locator('option').count() > indice:
            with page.expect_navigation(wait_until='load', timeout=15000):
                sel.select_option(index=indice)

    if archivo.startswith('doc-seguimiento'):
        try:
            elegir('grupo'); elegir('asig'); elegir('eval')
        except Exception:
            pass
        if archivo.endswith('-2.jpg'):
            fila = page.locator('table tbody tr').first
            if fila.count(): fila.click()
        elif archivo.endswith('-3.jpg'):
            v = page.locator('#seg-voz')
            if v.count(): v.scroll_into_view_if_needed()
        elif archivo.endswith('-4.jpg'):
            page.evaluate("var d=document.getElementById('seg-actividades'); if(d){d.open=true; d.scrollIntoView();}")
    elif archivo == 'sup-respaldos-1.jpg':
        # La sección de respaldos está al final de Configuración.
        page.evaluate("var h=[...document.querySelectorAll('h2,h3')].find(x=>/respaldo/i.test(x.textContent)); if(h){h.scrollIntoView({block:'start'}); window.scrollBy(0,-90);}")
    elif archivo == 'gen-voz-1.jpg':
        # Cuadro de texto con el micrófono a la vista (sin pulsarlo: pediría permiso de micrófono).
        page.evaluate("var t=document.querySelector('textarea'); if(t){t.scrollIntoView({block:'center'}); t.focus();}")
    elif archivo == 'cta-ayuda-ia-1.jpg':
        page.fill('#ayuda-ia-pregunta', '¿Cómo registro una emoción?')
        page.click('#ayuda-ia-enviar')
        try:
            page.wait_for_selector('#ayuda-ia-respuesta:not([hidden]) p:not(.ayuda-ia-espera)', timeout=30000)
            time.sleep(1)
        except Exception:
            pass
        page.evaluate("document.getElementById('ayuda-ia').scrollIntoView()")


def capturar_publicas(p, base, lista):
    """Login y registro: se toman en un navegador aparte, SIN sesión iniciada."""
    if not lista:
        return 0
    nav = p.chromium.launch(headless=True)
    ctx = nav.new_context(viewport={'width': 1280, 'height': 800}, locale='es-CO', reduced_motion='reduce')
    ctx.add_init_script(INIT_COOKIES)
    page = ctx.new_page()
    hechas = 0
    print(f'\n== páginas públicas ({len(lista)} capturas)')
    for archivo in lista:
        try:
            page.goto(base + PUBLICAS[archivo], wait_until='load')
            if archivo.startswith('gen-registro'):
                page.fill('#codigo_colegio', DANE_DEMO)
                if archivo == 'gen-registro-1.jpg':
                    with page.expect_navigation(wait_until='load'):
                        page.click('form button[type="submit"]')
                    page.fill('#nombre', 'Laura Gómez Ríos')
                    page.fill('#correo', 'laura.gomez@correo.test')
                    page.fill('#fecha_nacimiento', '2013-05-10')
                    if page.locator('#grupo_id option').count() > 1:
                        page.select_option('#grupo_id', index=1)
                    page.evaluate('document.activeElement && document.activeElement.blur()')
            page.wait_for_timeout(500)
            page.screenshot(path=os.path.join(CARPETA, archivo), type='jpeg', quality=80)
            hechas += 1
            print('  ✓', archivo)
        except Exception as ex:
            print('  ✗', archivo, '->', str(ex)[:90])
    ctx.close(); nav.close()
    return hechas


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--url'); ap.add_argument('--forzar', action='store_true')
    ap.add_argument('--solo', default=''); ap.add_argument('--lista', action='store_true')
    ap.add_argument('--incluir-reales', action='store_true')
    a = ap.parse_args()
    d = cargar()
    generar_md(d)
    if a.lista:
        return
    if not a.url:
        sys.exit('Falta --url (por ejemplo https://serena.infinityfreeapp.com)')
    base = a.url.rstrip('/')

    try:
        from playwright.sync_api import sync_playwright
    except ImportError:
        sys.exit('Instala Playwright:  pip install playwright   y   playwright install chromium')

    pendientes = [e for e in entradas(d) if (a.forzar or not os.path.exists(os.path.join(CARPETA, e[5]))) and e[5].startswith(a.solo)]
    publicas_hechas = 0
    publicas = [e[5] for e in pendientes if e[5] in PUBLICAS]
    pendientes = [(CON_ROL[e[5]][0], e[1], e[2], e[3], CON_ROL[e[5]][1], e[5], e[6]) if e[5] in CON_ROL else e
                  for e in pendientes if e[5] not in PUBLICAS]
    sin_ruta = [e for e in pendientes if not e[4]]
    por_rol = {}
    for e in pendientes:
        if not e[4]:
            continue
        rol = 'estudiante' if e[0] in ('general', 'cuenta') else e[0]
        if rol in REALES and not a.incluir_reales:
            continue
        por_rol.setdefault(rol, []).append(e)

    os.makedirs(CARPETA, exist_ok=True)
    with sync_playwright() as p:
        ctx = p.chromium.launch_persistent_context(os.path.join(RAIZ, '.perfil_capturas'), headless=False,
                                                   viewport={'width': 1280, 'height': 800}, locale='es-CO',
                                                   reduced_motion='reduce')  # sin animaciones a medias ni texto «escribiéndose»
        # Sin esto el aviso de cookies tapaba parte de cada captura: se da por respondido
        # ("solo necesarias") antes de cargar cualquier página.
        ctx.add_init_script(
            "try{localStorage.setItem('serena_cookies','necesarias');"
            "localStorage.setItem('serena_cookies_fecha',new Date().toISOString().slice(0,10));}catch(e){}"
        )
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        publicas_hechas = capturar_publicas(p, base, publicas)
        page.goto(base + '/login.php')
        input('\n1) Inicia sesión con la cuenta MAESTRA en el navegador que se abrió.\n2) Cuando veas tu panel, vuelve aquí y pulsa Enter... ')
        hechas = 0
        for rol, lista in por_rol.items():
            # El selector de la cuenta maestra (elegir_rol.php) deja la sesión «eligiendo rol»: todo lo que
            # venga después redirige a él. Por eso esa captura va siempre al final de su grupo.
            lista = sorted(lista, key=lambda e: e[4] == '/elegir_rol.php')
            page.goto(base + '/demo.php', wait_until='load')
            boton = page.locator(f'form:has(input[name="rol"][value="{rol}"]):not(:has(input[name="guion"])) button[type="submit"]').first
            if not boton.count():
                print(f'  · {rol}: no encontré el botón de entrar a la demo (¿preparaste los datos de demostración?)')
                continue
            with page.expect_navigation(wait_until='load'):
                boton.click()
            print(f'\n== {rol} ({len(lista)} capturas)')
            for _, cid, ct, st, ruta, archivo, pie in lista:
                try:
                    page.goto(base + ruta_para(archivo, ruta).replace('{rol}', rol), wait_until='load')
                    page.wait_for_timeout(600)
                    preparar(page, archivo)
                    # La barra «Modo demostración» no forma parte de la pantalla real: no sale en el manual.
                    page.add_style_tag(content='.barra-demo{display:none!important}')
                    page.wait_for_timeout(500)
                    page.screenshot(path=os.path.join(CARPETA, archivo), type='jpeg', quality=80)
                    hechas += 1
                    print('  ✓', archivo)
                except Exception as ex:
                    print('  ✗', archivo, '->', str(ex)[:90])
        try:
            page.goto(base + '/demo.php', wait_until='load')
            salir = page.locator('button:has-text("Salir de la demo"), a:has-text("Salir de la demo")').first
            if salir.count(): salir.click()
        except Exception:
            pass
        ctx.close()
    hechas += publicas_hechas
    print(f'\nListo: {hechas} capturas nuevas. Revísalas antes de publicar (que no muestren datos reales).')
    if sin_ruta:
        print('\nSin página asociada (tómalas a mano):', ', '.join(e[5] for e in sin_ruta))
    generar_md(d)


if __name__ == '__main__':
    main()
