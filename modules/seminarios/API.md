# API Seminarios - FLACSO Uruguay

**Namespace:** `flacso/v1`

**Versión:** 1.0.1

## Descripción General

La API REST de Seminarios proporciona endpoints para gestionar seminarios, programas, posgrados y consultas relacionadas con formación académica en FLACSO Uruguay.

---

## Endpoints

### Programas

#### GET /wp-json/flacso/v1/programas
Obtiene la lista de programas (taxonomía).

**Permiso:** Público

**Respuesta (200):**
```json
[
  {
    "id": 1,
    "name": "Maestría en Ciencia Política",
    "slug": "maestria-ciencia-politica",
    "description": "Descripción del programa",
    "count": 5
  }
]
```

---

#### POST /wp-json/flacso/v1/programas
Crea un nuevo programa.

**Permiso:** Requerido (gestor de términos)

**Parámetros:**
```json
{
  "name": "string (requerido)",
  "slug": "string (opcional)",
  "description": "string (opcional)"
}
```

**Respuesta (201):**
```json
{
  "id": 2,
  "name": "Nuevo Programa",
  "slug": "nuevo-programa",
  "description": "Descripción",
  "count": 0
}
```

---

### Posgrados

#### GET /wp-json/flacso/v1/posgrados
Obtiene la lista de posgrados (taxonomía).

**Permiso:** Público

**Respuesta (200):**
```json
[
  {
    "id": 1,
    "name": "Diplomado en Gestión",
    "slug": "diplomado-gestion",
    "description": "Descripción",
    "count": 3
  }
]
```

---

#### POST /wp-json/flacso/v1/posgrados
Crea un nuevo posgrado.

**Permiso:** Requerido (gestor de términos)

**Parámetros:**
```json
{
  "name": "string (requerido)",
  "slug": "string (opcional)",
  "description": "string (opcional)"
}
```

---

### Seminarios

#### GET /wp-json/flacso/v1/seminarios
Obtiene la lista de seminarios.

**Permiso:** Público

**Parámetros de Query:**
- `page` (integer): Número de página (default: 1)
- `per_page` (integer): Resultados por página (default: 10)
- `search` (string): Buscar por título
- `programa` (integer): Filtrar por ID de programa
- `posgrado` (integer): Filtrar por ID de posgrado

**Respuesta (200):**
```json
[
  {
    "id": 1,
    "title": "Seminario de Introducción",
    "description": "Descripción del seminario",
    "programa_id": 1,
    "posgrado_id": 2,
    "docentes": [
      {
        "id": 10,
        "name": "Dr. Juan Pérez",
        "slug": "juan-perez"
      }
    ],
    "fecha_inicio": "2026-02-15",
    "fecha_fin": "2026-03-15",
    "url": "https://example.com/seminario-intro"
  }
]
```

---

#### POST /wp-json/flacso/v1/seminarios
Crea un nuevo seminario.

**Permiso:** Requerido (editor/administrador)

**Parámetros:**
```json
{
  "title": "string (requerido)",
  "description": "string (opcional)",
  "programa_id": "integer (opcional)",
  "posgrado_id": "integer (opcional)",
  "docentes": "array (opcional)",
  "fecha_inicio": "date (opcional)",
  "fecha_fin": "date (opcional)"
}
```

**Respuesta (201):**
Retorna el objeto seminario creado con ID asignado.

---

#### GET /wp-json/flacso/v1/seminarios/{id}
Obtiene un seminario específico por ID.

**Permiso:** Público

**Parámetros:**
- `id` (integer, requerido): ID del seminario

**Respuesta (200):**
```json
{
  "id": 1,
  "title": "Seminario de Introducción",
  "description": "Descripción completa",
  "programa_id": 1,
  "programa_nombre": "Maestría en Ciencia Política",
  "posgrado_id": 2,
  "posgrado_nombre": "Diplomado en Gestión",
  "docentes": [
    {
      "id": 10,
      "name": "Dr. Juan Pérez",
      "slug": "juan-perez",
      "email": "juan@flacso.edu.uy"
    }
  ],
  "fecha_inicio": "2026-02-15",
  "fecha_fin": "2026-03-15",
  "url": "https://example.com/seminario-intro",
  "contenido": "HTML del contenido",
  "meta": {}
}
```

**Errores:**
- `404`: Seminario no encontrado
- `403`: Acceso denegado

---

#### PUT /wp-json/flacso/v1/seminarios/{id}
Actualiza un seminario existente.

**Permiso:** Requerido (editor/administrador)

**Parámetros:**
- `id` (integer, requerido): ID del seminario
- Cuerpo: Objeto con campos a actualizar

**Respuesta (200):**
Retorna el objeto seminario actualizado.

---

#### DELETE /wp-json/flacso/v1/seminarios/{id}
Elimina un seminario.

**Permiso:** Requerido (editor/administrador)

**Parámetros:**
- `id` (integer, requerido): ID del seminario

**Respuesta (200):**
```json
{
  "deleted": true,
  "previous": {
    "id": 1,
    "title": "Seminario de Introducción"
  }
}
```

---

### Consultas de Seminarios

#### POST /wp-json/flacso/v1/consulta-seminario
Envía una consulta sobre un seminario.

**Permiso:** Público

**Parámetros:**
```json
{
  "nombre": "string (requerido)",
  "email": "email (requerido)",
  "telefono": "string (opcional)",
  "consulta": "string (requerido)",
  "seminario_id": "integer (opcional)"
}
```

**Respuesta (201):**
```json
{
  "success": true,
  "message": "Consulta enviada correctamente",
  "consulta_id": 123
}
```

**Errores:**
- `400`: Parámetros inválidos o faltantes
- `403`: Acceso denegado

---

## Esquema de Datos

### Seminario
```json
{
  "id": "integer",
  "title": "string",
  "description": "string",
  "programa_id": "integer",
  "programa_nombre": "string",
  "posgrado_id": "integer",
  "posgrado_nombre": "string",
  "docentes": "array<Docente>",
  "fecha_inicio": "date (YYYY-MM-DD)",
  "fecha_fin": "date (YYYY-MM-DD)",
  "url": "string (URL)",
  "contenido": "string (HTML)",
  "meta": "object"
}
```

### Docente
```json
{
  "id": "integer",
  "name": "string",
  "slug": "string",
  "email": "string",
  "specialty": "string (opcional)"
}
```

### Programa / Posgrado
```json
{
  "id": "integer",
  "name": "string",
  "slug": "string",
  "description": "string",
  "count": "integer (cantidad de seminarios)"
}
```

### Consulta
```json
{
  "nombre": "string",
  "email": "string",
  "telefono": "string",
  "consulta": "string",
  "seminario_id": "integer",
  "fecha_envio": "datetime"
}
```

---

## Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| 200 | OK - Solicitud exitosa |
| 201 | Created - Recurso creado exitosamente |
| 400 | Bad Request - Parámetros inválidos |
| 403 | Forbidden - Acceso denegado |
| 404 | Not Found - Recurso no encontrado |
| 500 | Internal Server Error - Error del servidor |

---

## Autenticación y Permisos

- **Lectura (GET):** Público (sin autenticación requerida)
- **Escritura (POST/PUT/DELETE):** Requerida autenticación de WordPress
  - Creación de términos: Rol "editor" o superior
  - Gestión de seminarios: Rol "editor" o superior
  - Envío de consultas: Público (sin autenticación)

---

## Ejemplos de Uso

### Obtener todos los seminarios
```bash
curl -X GET https://example.com/wp-json/flacso/v1/seminarios
```

### Obtener seminario específico
```bash
curl -X GET https://example.com/wp-json/flacso/v1/seminarios/1
```

### Filtrar seminarios por programa
```bash
curl -X GET "https://example.com/wp-json/flacso/v1/seminarios?programa=1"
```

### Crear nuevo seminario (requiere autenticación)
```bash
curl -X POST https://example.com/wp-json/flacso/v1/seminarios \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Nuevo Seminario",
    "description": "Descripción",
    "programa_id": 1
  }'
```

### Enviar consulta
```bash
curl -X POST https://example.com/wp-json/flacso/v1/consulta-seminario \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Juan Pérez",
    "email": "juan@example.com",
    "consulta": "¿Cuál es la fecha de inicio?"
  }'
```

---

## Notas

- Todos los endpoints responden en JSON
- La paginación está implementada en GET /seminarios
- Las fechas siguen el formato ISO 8601 (YYYY-MM-DD)
- Los errores incluyen código de error y mensaje descriptivo

---

**Última actualización:** 29 de enero de 2026

**Versión API:** 1.0.1
