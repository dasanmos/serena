# Guía: rehacer todas las capturas del manual de Serena

Computador con **Linux Mint**. Todas las capturas son **automáticas** (ninguna a mano) e incluyen las de **Superadmin** y **Secretaría de Educación**.

- Tiempo aproximado: 30 a 40 minutos, contando la preparación.
- Capturas totales: unas 125.

---

## Antes de empezar

- [ ] Ya subiste al hosting la **última versión** de `htdocs`.
- [ ] Tienes a mano la **cuenta maestra** (correo y contraseña) y, si la tienes activa, la app de verificación en 2 pasos.
- [ ] Tienes los datos de **FTP** de InfinityFree: en el panel, **FTP Details**.
- [ ] Tienes descomprimido en el computador **el mismo ZIP** que subiste. Esta guía supone que está en `~/Descargas/htdocs`.

> **Importante:** el script de capturas viene en el ZIP nuevo (`tools/capturar_ayuda.py`). Usa ese, no uno anterior.

---

## Paso 1 · Ejecutar la migración 044 (solo una vez)

1. En el panel de InfinityFree abre **MySQL Databases → phpMyAdmin**.
2. En la columna izquierda, haz clic en tu base de datos (empieza por `if0_…`).
3. Abre la pestaña **SQL**.
4. En el computador, abre `sql/migraciones/044_multirol.sql` con el editor de texto, **copia todo** y pégalo en el cuadro.
5. Pulsa **Continuar**.

✅ **Resultado esperado:** un mensaje en verde. Si dice que la tabla `usuario_roles` ya existe, no pasa nada: ya estaba aplicada.

---

## Paso 2 · Preparar los datos de demostración

Las capturas se toman en el **Colegio Demostración SERENA**, con datos ficticios.

1. Abre `https://serena.infinityfreeapp.com/public/login.php` y entra con la **cuenta maestra**.
2. Ve a `https://serena.infinityfreeapp.com/public/demo.php`.
3. Pulsa **✨ Preparar datos de demostración**. Si ya existían, el botón dice **🔄 Restablecer datos de demostración**; púlsalo igual para dejar todo limpio. Confirma y **espera** a que termine.
4. Verifica que aparezca **"✅ Datos de demostración listos"**.
5. Cierra sesión y cierra el navegador.

---

## Paso 3 · Borrar las capturas viejas

### En el computador

Abre una terminal (**Ctrl + Alt + T**):

```bash
cd ~/Descargas/htdocs
find public/assets/img/ayuda -name "*.jpg" -delete
ls public/assets/img/ayuda
```

✅ Solo debe quedar `LEEME.txt`.

### En el servidor

1. Abre **FileZilla** y conéctate:
   - **Servidor:** `ftpupload.net`
   - **Usuario:** el tuyo (`if0_…`)
   - **Contraseña:** la del hosting
   - **Puerto:** `21`
2. A la derecha (servidor), entra a `htdocs/public/assets/img/ayuda/`.
3. Selecciona **todos los `.jpg`** (no el `LEEME.txt`) → clic derecho → **Eliminar** → **Sí**.

---

## Paso 4 · Instalar las herramientas (solo la primera vez)

Si ya lo hiciste antes en este computador, salta al **Paso 5**.

```bash
sudo apt install python3-venv python3-pip filezilla
cd ~/Descargas/htdocs
python3 -m venv .venv
source .venv/bin/activate
pip install playwright
python -m playwright install --with-deps chromium
```

Si el último comando se queja de que Linux Mint no es una distribución soportada, usa en su lugar:

```bash
python -m playwright install chromium
```

---

## Paso 5 · Tomar todas las capturas

1. En la terminal:

   ```bash
   cd ~/Descargas/htdocs
   source .venv/bin/activate
   python tools/capturar_ayuda.py --url https://serena.infinityfreeapp.com/public --forzar --incluir-reales
   ```

   - La URL **debe terminar en `/public`**.
   - `--forzar` rehace todas las capturas.
   - `--incluir-reales` toma también las de Superadmin y Secretaría de Educación.

2. Primero toma solo, sin abrir ventana, las de **iniciar sesión** y **crear cuenta**. Verás:

   ```
   == páginas públicas (3 capturas)
     ✓ gen-login-1.jpg
     ✓ gen-registro-codigo-1.jpg
     ✓ gen-registro-1.jpg
   ```

3. Después se abre un **Chrome**. Inicia sesión con la **cuenta maestra**: resuelve el reCAPTCHA y, si te la pide, la verificación en 2 pasos.
4. Cuando veas tu panel, **vuelve a la terminal y pulsa Enter**.
5. **No toques ese Chrome ni la terminal.** El script entra con cada rol de la demo y va mostrando:

   ```
   == estudiante (N capturas)
     ✓ est-emociones-1.jpg
     ...
   ```

6. Al terminar aparece:

   ```
   Listo: N capturas nuevas.
   ```

   Ya no habrá ninguna lista de capturas "a mano".

### Si algo falla

| Mensaje | Qué hacer |
|---|---|
| `✗ archivo.jpg -> ...` en algunas | Ejecuta otra vez el comando **sin** `--forzar`: solo toma las que faltan. |
| `no encontré el botón de entrar a la demo` | Falta el **Paso 2**: prepara los datos de demostración. |
| Se corta la conexión o se queda quieto | InfinityFree limita las visitas seguidas. Espera 2 o 3 minutos y repite **sin** `--forzar`. |
| `Falta --url` | Revisa que copiaste el comando completo, con la URL. |
| `Instala Playwright` | Te faltó `source .venv/bin/activate`, o el **Paso 4**. |

---

## Paso 6 · Revisar las capturas

Abre la carpeta `~/Descargas/htdocs/public/assets/img/ayuda/` y revisa:

- [ ] Ninguna tiene el **aviso de cookies** encima.
- [ ] Ninguna está en blanco o con un mensaje de error.
- [ ] Todo es del **Colegio Demostración SERENA**.
- [ ] **Superadmin y Secretaría** (`sup-…` y `sec-…`): no muestran nombres de **colegios o personas reales**. Si alguna los muestra, **bórrala** antes de subir; es mejor que falte a que exponga datos reales.

Si quieres repetir solo una o unas pocas, borra esos archivos y ejecuta otra vez **sin** `--forzar`.

---

## Paso 7 · Subir al hosting

1. En **FileZilla**, a la izquierda (tu computador), entra a `~/Descargas/htdocs/public/assets/img/ayuda/`.
2. A la derecha (servidor), entra a `htdocs/public/assets/img/ayuda/`.
3. Selecciona **todos los `.jpg`** de la izquierda y **arrástralos** a la derecha.
4. Espera a que la cola de transferencias quede en **0**.

---

## Paso 8 · Comprobar

1. Abre `https://serena.infinityfreeapp.com/public/ayuda.php`.
2. Recarga con **Ctrl + Shift + R** para no ver las imágenes viejas guardadas en el navegador.
3. Revisa varias guías: la general, la de un estudiante y la de un docente. Entrando con la cuenta maestra, también la de administrador.

✅ ¡Listo!

---

## Al terminar

- **Restablece la demo** si la vas a presentar: en `demo.php`, pulsa **🔄 Restablecer datos de demostración**. El registro de prueba **no crea cuentas**, porque el script solo llena el formulario y toma la foto sin enviarlo.
- **No subas** al hosting las carpetas `.venv/` ni `.perfil_capturas/`: son solo del computador.
- **Para la próxima vez**, si solo cambian algunas pantallas, borra esas imágenes y ejecuta el comando **sin** `--forzar`.
