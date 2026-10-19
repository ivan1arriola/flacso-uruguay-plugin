# Referencia Rápida para Agentes de IA - FLACSO Uruguay Plugin

Esta referencia técnica resume identificadores, CPTs, campos de metadatos, taxonomías, opciones de WordPress y hooks propios del plugin para que los agentes de IA no tengan que inferirlos por prueba y error.

---

## 1. Custom Post Types (CPTs) y Taxonomías

| Entidad | CPT (`post_type`) | Tipo / Jerarquía | Slug / URL pública | Taxonomías asociadas |
|---|---|---|---|---|
| **Programa Académico** | `programa-academico` | Raíz | `/programas-academicos/` | N/A |
| **Oferta Académica** | `oferta-academica` | Raíz | `/ofertas/` | `tipo-oferta-academica` (`doctorado`, `maestria`, `especializacion`, `diplomado`, `diploma`) |
| **Cohorte** | `cohorte` | Entidad dependiente de Oferta | Interno (`show_in_menu => false`, sin URL pública) | N/A |
| **Seminario** | `seminario` | Raíz | `/seminarios/` | `area-tematica`, `modalidad` |
| **Edición** | `edicion` | Entidad dependiente de Seminario | Interno (`show_in_menu => false`, sin URL pública) | N/A |
| **Tabla de Precios** | `tabla-precio` | Entidad de precios | Interno (`show_in_menu => false`) | N/A |
| **Docente** | `flacso_docente` | Entidad de personas | `/docentes/` | `area-academica`, `rol-docente` |
| **Evento** | `flacso_evento` | Actividades y charlas | `/eventos/` | `categoria-evento` |

> **Regla de Invariantes**:
> - `Cohorte` pertenece EXCLUSIVAMENTE a una `OfertaAcademica` mediante `META_PARENT_ID` (`_flacso_oferta_id` o `parent_id`).
> - `Edicion` pertenece EXCLUSIVAMENTE a un `Seminario` mediante `META_PARENT_ID` (`_flacso_seminario_id` o `parent_id`).
> - La etiqueta de cohorte se deriva siempre con números romanos (`Cohorte I`, `Cohorte II`, etc.) a partir de su número entero.

---

## 2. Metadatos Clave (`postmeta`)

### Oferta Académica (`oferta-academica`)
- `_flacso_programa_id`: ID del `programa-academico` contenedor.
- `_flacso_oferta_correo`: Correo electrónico de contacto académico.
- `_flacso_oferta_presentacion`: Texto HTML de presentación.
- `_flacso_oferta_objetivo_general`: Objetivo general HTML.
- `_flacso_oferta_objetivos_especificos`: Array JSON/serializado de objetivos específicos.
- `_flacso_oferta_composicion`: Estructura académica / módulos (`[{titulo, contenido}]`).
- `_flacso_oferta_forma_aprobacion`: Texto HTML.
- `_flacso_oferta_carga_horaria`: Número entero o decimal de horas.
- `_flacso_oferta_creditos`: Créditos asignados.
- `_flacso_oferta_acreditacion`: Certificación o reconocimiento oficial.
- `_flacso_oferta_seminarios`: Array JSON de seminarios asociados `[{seminario_id, orden, caracter, creditos_reconocidos}]`.

### Cohorte (`cohorte`)
- `_flacso_oferta_id`: ID de la oferta académica padre.
- `_flacso_cohorte_numero`: Número entero (ej. `7` para `Cohorte VII`).
- `_flacso_cohorte_fecha_inicio`: Fecha de inicio (`YYYY-MM-DD`).
- `_flacso_cohorte_fecha_cierre_inscripcion`: Fecha límite de preinscripción.
- `_flacso_cohorte_estado`: `abierta`, `en_curso`, `finalizada`, `proxima`.
- `_flacso_cohorte_link_preinscripcion`: URL hacia `https://preinscripciones.flacso.edu.uy/...`
- `_flacso_tabla_precio_id`: ID de la `tabla-precio` vinculada.

### Seminario (`seminario`)
- `_flacso_programa_id`: ID del programa si aplica.
- `_flacso_seminario_es_integrado`: Boolean (`1` o `0`). Si es integrado, sus docentes y carga se componen de sus componentes.
- `_flacso_seminario_componentes`: Array de IDs de seminarios componentes.
- `_flacso_seminario_creditos`: Créditos académicos.
- `_flacso_seminario_carga_horaria`: Horas totales.

### Edición (`edicion`)
- `_flacso_seminario_id`: ID del seminario padre.
- `_flacso_edicion_numero`: Identificador de edición o año.
- `_flacso_edicion_fecha_inicio`: Fecha de inicio.
- `_flacso_edicion_docentes`: Array de IDs de `flacso_docente`.
- `_flacso_edicion_link_preinscripcion`: URL de preinscripción externa.
- `_flacso_edicion_estado`: `abierta`, `en_curso`, `cerrada`.

---

## 3. Opciones Globales de WordPress (`wp_options`)

- `flacso_main_page_settings`: Configuración de secciones, visibilidad y orden de la portada.
- `flacso_main_page_hero`: Datos editoriales del Hero de portada.
- `flacso_system_settings`: Configuración técnica y flags del sistema.
- `flacso_integrations_settings`: Integraciones externas (Mautic URL, credenciales, etc.).
- `flacso_inquiry_settings`: Configuración de bandeja y recepción de consultas.
- `flacso_preinscription_links`: Mapeo centralizado de enlaces hacia la plataforma de preinscripciones.

---

## 4. Endpoints REST Clave (`/wp-json/flacso/v1`)

| Endpoint | Métodos | Permisos | Descripción |
|---|---|---|---|
| `/programas-academicos` | GET, POST | Lectura pública, POST `edit_posts` | Colección de programas |
| `/ofertas` | GET, POST | Lectura pública, POST `edit_posts` | Colección de ofertas académicas |
| `/ofertas/{id}` | GET, PUT, PATCH, DELETE | Lectura pública, Modificación `edit_posts` | Detalle de oferta |
| `/cohortes` | GET, POST | Lectura pública, POST `edit_posts` | Filtra con `?parent_id={oferta_id}` |
| `/cohortes/{id}` | GET, PUT, PATCH, DELETE | Lectura pública, Modificación `edit_posts` | Detalle de cohorte |
| `/seminarios` | GET, POST | Lectura pública, POST `edit_posts` | Colección de seminarios |
| `/ediciones` | GET, POST | Lectura pública, POST `edit_posts` | Filtra con `?parent_id={seminario_id}` |
| `/tablas-precio` | GET, POST | Lectura pública, POST `edit_posts` | Tablas de aranceles y cuotas |
| `/preinscripciones` | GET | Público (Cache max-age=60) | Catálogo unificado para el portal de preinscripciones |
| `/main-page/config` | GET, POST | Lectura pública, POST `manage_options` | Configuración editorial de portada |
| `/consultas` | POST | Público con nonce / rate-limit | Recepción de consultas web y sync con Mautic |

---

## 5. Hooks y Filtros Personalizados

### Filtros
- `flacso_homepage_sections`: Permite a los módulos registrar secciones para la portada.
  ```php
  add_filter('flacso_homepage_sections', function(array $sections): array {
      $sections['mi_clave'] = [
          'label' => __('Mi Sección', 'flacso-uruguay'),
          'function' => 'mi_render_function',
          'owner' => 'mi-modulo',
      ];
      return $sections;
  });
  ```
- `flacso_main_page_component_files`: Lista de archivos de renderizado para componentes de portada.
- `flacso_mautic_contact_payload`: Permite modificar el payload enviado a Mautic antes de sincronizar un contacto.
- `flacso_preinscriptions_catalog_item`: Filtra cada item del catálogo de preinscripciones expuesto en `/preinscripciones`.

### Acciones
- `flacso_modules_loaded`: Se dispara una vez que el registry cargó todos los módulos activos.
- `flacso_inquiry_saved`: Se dispara tras persistir una consulta en la base de datos (`$inquiry_id`, `$data`).
- `flacso_mautic_sync_completed`: Se dispara tras sincronizar exitosamente un contacto o evento con Mautic.
