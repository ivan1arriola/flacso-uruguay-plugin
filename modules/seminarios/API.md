# API Seminarios - FLACSO Uruguay

> [!IMPORTANT]
> Esta documentación se ha consolidado en el archivo principal [API.md](../../API.md) de la raíz del proyecto. Se recomienda consultar ese archivo para la versión más completa y actualizada de todos los módulos.

## Endpoints del Módulo

### Seminarios (`flacso/v1`)

- `GET /wp-json/flacso/v1/seminarios`: Listado de seminarios.
- `GET /wp-json/flacso/v1/seminarios/{id}`: Detalle de seminario.
- `POST /wp-json/flacso/v1/seminarios`: Crear (requiere auth).
- `PUT /wp-json/flacso/v1/seminarios/{id}`: Actualizar (requiere auth).
- `DELETE /wp-json/flacso/v1/seminarios/{id}`: Eliminar (requiere auth).

### Consultas (`flacso/v1`)

#### POST `/wp-json/flacso/v1/consulta-seminario`
Envía una consulta desde el formulario.

**Parámetros (JSON):**
- `seminario_id` (int): ID del seminario.
- `seminario_titulo` (string): Título para referencia.
- `nombre` (string): Nombre del interesado.
- `correo` (email): Dirección de contacto.
- `telefono` (string): Teléfono.
- `pais` (string): País de origen.
- `consulta` (string): Texto de la consulta.

---
**Última actualización:** Mayo 2026
