# API REST - FLACSO Uruguay

## OfertaAcademica e InstanciaOferta

`oferta-academica` admite seis tipos: `doctorado`, `maestria`,
`especializacion`, `diplomado`, `diploma` y `seminario`. No se crea una entidad
de dominio separada para seminarios.

Las APIs administrativas canónicas mantienen CRUD completo para los proyectos
que gestionan el dominio académico:

```text
GET|POST             /wp-json/flacso/v1/ofertas
GET|PUT|PATCH|DELETE /wp-json/flacso/v1/ofertas/{oferta_wp_id}

GET|POST             /wp-json/flacso/v1/instancias-oferta
GET|PUT|PATCH|DELETE /wp-json/flacso/v1/instancias-oferta/{instancia_wp_id}
```

En ambas APIs, `DELETE` mueve a la papelera por defecto y devuelve el objeto
anterior en `previous`. El borrado permanente requiere `?force=true` y el
permiso WordPress correspondiente. Una oferta con instancias no puede borrarse
permanentemente: la API responde `409` para evitar referencias huérfanas. Los
IDs siguen siendo los IDs de WordPress.

Durante Release A continúan disponibles, sin cambios de ruta, los contratos
legacy `/flacso/v1/seminarios`, `/flacso/v1/cohortes` y
`/flacso-posgrados/v1/posgrados`. Los consumidores deben migrar gradualmente a
`/ofertas` e `/instancias-oferta`; no es necesario coordinarlos en un único
despliegue.

`status` es sólo el estado académico: `planificada`, `en_curso`, `finalizada` o
`cancelada`. `preinscriptionOpening` y `preinscriptionManualClosing` gobiernan la
apertura; para seminarios el cierre efectivo se deriva siete días después de la
apertura. `number`, fechas y precisiones pueden ser `null`.

El campo `preinscriptionFlow` admite `legacy_editor` y
`gestor_preinscripciones`; el default es `legacy_editor`. WordPress bloquea el
cambio de flujo después de una apertura real.

El contrato público mínimo consumido por Django es:

```text
GET /wp-json/flacso/v1/preinscripciones/catalogo
```

Devuelve `schema_version: 1` y sólo instancias que
`acepta_preinscripciones()`, públicas y asignadas a `gestor_preinscripciones`.
Consume únicamente OfertaAcademica e InstanciaOferta.

Esta documentación describe todos los endpoints disponibles en la API REST del plugin unificado de FLACSO Uruguay.

## Información General

*   **URL Base:** `https://flacso.edu.uy/wp-json/`
*   **Formato de Respuesta:** JSON
*   **Autenticación:**
    *   **Lectura (GET):** Generalmente pública (salvo que se indique lo contrario).
    *   **Escritura (POST/PUT/DELETE):** Requiere token de autenticación.

---

## 1. Módulo de Seminarios y Oferta (`flacso/v1`)

### Seminarios (compatibilidad Release A)

Los endpoints siguientes permanecen temporalmente para el CPT legacy. No deben
usarse para crear seminarios nuevos. Un seminario nuevo se crea en la API de
OfertaAcademica con tipo `seminario`, y su edición en `/instancias-oferta`.

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

#### CRUD canónico `/flacso/v1/ofertas`

* `GET /flacso/v1/ofertas`: lista ofertas editables, incluidos borradores y privadas.
* `POST /flacso/v1/ofertas`: crea una OfertaAcademica de cualquiera de los seis tipos.
* `GET /flacso/v1/ofertas/{id}`: obtiene el DTO administrativo.
* `PUT|PATCH /flacso/v1/ofertas/{id}`: actualiza parcialmente la oferta.
* `DELETE /flacso/v1/ofertas/{id}`: mueve la oferta a la papelera.
* `DELETE /flacso/v1/ofertas/{id}?force=true`: borrado permanente explícito.

El endpoint histórico singular se conserva como contrato de compatibilidad:

#### GET `/flacso/v1/oferta-academica`
Listado de programas con metadatos completos.
*   **Filtros:** `tipo` (slug de la taxonomía `tipo-oferta-academica`).

#### GET `/flacso/v1/oferta-academica/{id}`
Obtiene los datos estructurados de un programa.

#### PUT `/flacso/v1/oferta-academica/{id}`
Actualiza los metadatos de un programa (requiere permisos).

### Instancias de Oferta (Gestión)

* `GET /flacso/v1/instancias-oferta`: lista cohortes y ediciones.
* `POST /flacso/v1/instancias-oferta`: crea una instancia.
* `GET /flacso/v1/instancias-oferta/{id}`: obtiene una instancia.
* `PUT|PATCH /flacso/v1/instancias-oferta/{id}`: actualiza una instancia.
* `DELETE /flacso/v1/instancias-oferta/{id}`: mueve la instancia a la papelera.
* `DELETE /flacso/v1/instancias-oferta/{id}?force=true`: borrado permanente explícito.

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
