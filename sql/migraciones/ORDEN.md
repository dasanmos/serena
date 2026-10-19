# Migraciones de la base de datos

> **¿Instalación nueva?** No uses esta carpeta: importa [`../instalacion_completa.sql`](../instalacion_completa.sql), que ya trae la estructura completa (equivale a `schema.sql` + todas estas migraciones). Ver [`docs/INSTALACION.md`](../../docs/INSTALACION.md).

Esta carpeta sirve para **actualizar una instalación existente**: aplica solo las que te falten, en orden numérico, una sola vez cada una y con un respaldo previo.

## Prefijos repetidos

Algunos números tienen dos archivos distintos. **No** son duplicados: se aplican los dos, en este orden.

| Prefijo | Archivos (en orden) |
|---|---|
| 036 | `036_asistencia.sql` → `036_expediente.sql` |
| 037 | `037_certificados.sql` → `037_horarios.sql` |
| 038 | `038_codigo_matricula.sql` → `038_hoja_vida_master.sql` |
| 041 | `041_ia_registro.sql` → `041_simat_simpade.sql` |

## Scripts que no son de estructura

| Archivo | Qué hace | ¿En producción? |
|---|---|---|
| `004_datos_demo.sql` | Datos de ejemplo (clave «password») | ❌ Nunca |
| `005_limpiar_todo.sql` | Borra datos | ❌ Solo si sabes lo que haces |
| `006_reset_admin_y_nuevos.sql` | Rescate de la cuenta admin (trae un marcador de hash que debes reemplazar) | ⚠️ Solo para rescatar acceso |

Para ver datos de ejemplo es mejor el **modo demostración** desde la app (colegio ficticio que se restablece en un clic).

## Las más recientes

- `043_documento_login.sql`: inicio de sesión con número de documento.
- `044_multirol.sql`: una cuenta con varios roles (elige con cuál entrar).

`042_ia_registro.sql` se eliminó: era idéntico a `041_ia_registro.sql`. Para nuevas migraciones usa el siguiente número libre (045 en adelante), uno por archivo.
