# API REST - FLACSO Uruguay

## Cohortes y Ediciones

La API administrativa semántica de instancias es:

```text
GET|POST  /wp-json/flacso/v1/instancias-oferta
GET|PATCH /wp-json/flacso/v1/instancias-oferta/{instancia_wp_id}
```

El campo `preinscriptionFlow` admite `legacy_editor` y
`gestor_preinscripciones`; el default es `legacy_editor`. WordPress bloquea el
cambio de flujo después de que la instancia haya abierto preinscripciones.

El contrato público mínimo consumido por Django es:

```text
GET /wp-json/flacso/v1/preinscripciones/catalogo
```

Devuelve `schema_version: 1` y sólo instancias abiertas, públicas y asignadas a
`gestor_preinscripciones`. Ver `docs/preinscription-flow-transition.md`.

Esta documentación describe todos los endpoints disponibles en la API REST del plugin unificado de FLACSO Uruguay.

## Información General

*   **URL Base:** `https://flacso.edu.uy/wp-json/`
*   **Formato de Respuesta:** JSON
*   **Autenticación:**
    *   **Lectura (GET):** Generalmente pública (salvo que se indique lo contrario).
    *   **Escritura (POST/PUT/DELETE):** Requiere token de autenticación.

---

## 1. Módulo de Seminarios y Oferta (`flacso/v1`)

### Seminarios

#### GET `/flacso/v1/seminarios`
Obtiene el listado de seminarios.

*   **Parámetros:**
    *   `page` (int): Página actual.
    *   `per_page` (int): Resultados por página (default 10).
    *   `oferta_academica` (id/slug): Filtrar por oferta académica asociada (antes `posgrado`).
*   **Headers de respuesta:** `X-WP-Total`, `X-WP-TotalPages`.

**Esquema de Respuesta (Ejemplo Completo):**
```json
{
  "id": 123,
  "title": "Nombre del Seminario",
  "content": "Descripción detallada...",
  "status": "publish",
  "featured_image": "https://flacso.edu.uy/wp-content/uploads/2026/05/imagen.jpg",
  "meta": {
    "nombre": "Nombre Académico",
    "periodo_inicio": "2026-05-20",
    "periodo_fin": "2026-06-20",
    "creditos": 5,
    "carga_horaria": 40,
    "valor_uyu": 15000,
    "valor_uyu_15_descuento": 12750,
    "valor_usd": 380,
    "valor_usd_15_descuento": 323,
    "modalidad": "Virtual",
    "objetivos_especificos": [
      "Comprender las bases teóricas...",
      "Aplicar metodologías..."
    ],
    "unidades_academicas": [
      {
        "titulo": "Unidad 1: Introducción",
        "contenido": "Contenido de la unidad..."
      }
    ],
    "encuentros_sincronicos": [
      {
        "fecha": "2026-05-22",
        "hora_inicio": "18:00",
        "hora_fin": "20:00",
        "plataforma": "zoom"
      }
    ],
    "docentes": [10, 15]
  },
  "ofertas_academicas": [
    { "id": 45, "title": "Maestría en Educación", "slug": "maestria-educacion" }
  ],
  "taxonomies": {
    "area_tematica": [ { "id": 5, "name": "Educación", "slug": "educacion" } ]
  }
}
```

#### GET `/flacso/v1/seminarios/{id}`
Obtiene un seminario específico (mismo esquema que el anterior).

#### POST `/flacso/v1/seminarios`
Crea un nuevo seminario. Requiere permisos de edición.

---

### Consultas de Seminarios

#### POST `/flacso/v1/consulta-seminario`
Envía una consulta desde el formulario de un seminario.

*   **Cuerpo (JSON):**
    ```json
    {
      "seminario_id": "int (requerido)",
      "seminario_titulo": "string (requerido)",
      "nombre": "string (requerido)",
      "correo": "email (requerido)",
      "telefono": "string (requerido)",
      "pais": "string (requerido)",
      "consulta": "string (requerido)"
    }
    ```

---

### Ofertas Académicas (Público)

#### GET `/flacso/v1/ofertas-academicas` (Alias: `/posgrados`)
Listado rápido de ofertas académicas para selectores.

---

### Oferta Académica (Gestión)

#### GET `/flacso/v1/oferta-academica`
Listado de programas con metadatos completos.
*   **Filtros:** `tipo` (slug de la taxonomía `tipo-oferta-academica`).

#### GET `/flacso/v1/oferta-academica/{id}`
Obtiene los datos estructurados de un programa.

#### PUT `/flacso/v1/oferta-academica/{id}`
Actualiza los metadatos de un programa (requiere permisos).

---

## 2. Módulo de Posgrados (`flacso-posgrados/v1`)

CRUD completo para la gestión interna de posgrados.

*   `GET /flacso-posgrados/v1/posgrados`: Listado con filtros (`search`, `status`, `tipo`, `activo`, `parent_id`).
*   `POST /flacso-posgrados/v1/posgrados`: Crear posgrado.
*   `GET /flacso-posgrados/v1/posgrados/{id}`: Obtener detalle.
*   `PUT /flacso-posgrados/v1/posgrados/{id}`: Actualizar.
*   `DELETE /flacso-posgrados/v1/posgrados/{id}`: Eliminar.

---

## 3. Módulo de Docentes (`flacso-docentes/v1`)

CRUD completo para la gestión de perfiles docentes.

*   `GET /flacso-docentes/v1/docentes`: Listado con filtros (`search`, `status`).
*   `POST /flacso-docentes/v1/docentes`: Crear docente.
*   `GET /flacso-docentes/v1/docentes/{id}`: Obtener perfil.
*   `PUT /flacso-docentes/v1/docentes/{id}`: Actualizar perfil.
*   `DELETE /flacso-docentes/v1/docentes/{id}`: Eliminar perfil.

---

## 4. Módulo de Charlas Abiertas (`flacso-charlas/v1`)

### Charlas

#### GET `/flacso-charlas/v1/charla-abierta` (Alias: `/charlas`)
Listado de charlas publicadas.

*   **Parámetros:** `page`, `per_page`, `search`, `modalidad` (virtual/presencial/hibrida), `desde` (ISO8601), `hasta` (ISO8601), `order` (asc/desc).

#### GET `/flacso-charlas/v1/charla-abierta/{id}`
Detalle de una charla.

---

### Inscripciones

#### POST `/flacso-charlas/v1/inscripcion`
Registra a un usuario en una charla.

---

## 5. Utilidades de Contenido (`flacso/v1`)

*   `GET /flacso/v1/raw-content?id={id}&type={post_type}`
*   `POST /flacso/v1/update-content`

---
**Última actualización:** Mayo 2026
