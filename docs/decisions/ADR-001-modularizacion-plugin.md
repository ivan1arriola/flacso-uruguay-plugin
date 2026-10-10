# ADR-001: Modularización progresiva del plugin FLACSO Uruguay

**Estado:** Propuesto · 2026-10-10  
**Alcance:** Todo el plugin WordPress; no involucra reescribir las aplicaciones externas.

## Contexto verificado

- El punto de entrada `flacso-uruguay.php` declara compatibilidad con PHP >= 7.4, importa numerosas clases de `includes/core` y carga módulos en un orden manual.
- `modules/consultas/init.php` incorpora directamente repositorios PostgreSQL, el cliente Mautic, servicios, CLI y UI.
- El panel administrativo contiene coordinación de navegación, consultas de datos y renderizado.
- No se encontró `composer.json` en la raíz de `main` al efectuar esta revisión.
- El CI tiene PHP Lint en 7.4, pruebas de contrato autónomas y despliegue de `main` a producción.

## Decisión

Adoptar un **monolito modular** con organización *por dominio* y arquitectura hexagonal ligera (dominio + aplicación + infraestructura + presentación). No implementar microservicios ni un contenedor DI complejo. Migrar usando patrón *strangler*, por módulos, conservando el API, slugs, hooks, nombres de metas, estados, endpoints, jobs y cron actuales.

## Dominios sugeridos

| Dominio | Responsabilidad |
|---|---|
| Academic | Programas, ofertas, cohortes, seminarios y ediciones |
| People | Personas, perfiles, equipos, roles institucionales |
| Pricing | Aranceles y convenios aplicables |
| Inquiries | Consultas, consentimiento y estados de entrega |
| Communications | Mautic, plantillas, campañas y registros de comunicación |
| Enrollments | Integración con preinscripciones, sin duplicar su fuente de verdad |
| Content | Portada, páginas, FAQs, bloques y shortcodes |
| Events | Eventos y charlas abiertas |
| Core | Contratos, seguridad, registro, logging y errores transversales |

Definir propietarios y reglas de datos antes de mover los repositorios. `Core` no depende de módulos funcionales. Los dominios no llaman directamente a WordPress, PDO, HTTP ni WP-Cron: definen interfaces y los adaptadores implementan estas interfaces. Evitar circularidad entre dominios. Usar casos de uso para coordinar la lógica y controladores WordPress delgados. El acceso a capacidades y nonces se comprueba en la frontera de entrada, y se repite la autorización según corresponda al caso de uso.

## Estructura objetivo orientativa

```text
flacso-uruguay.php
composer.json
config/
  modules.php
src/
  Core/{Bootstrap,Contracts,Security,Support}/
  Academic/{Programs,Offers,Cohorts,Seminars,Editions}/
  People/
  Inquiries/{Domain,Application,Infrastructure,Presentation}/
  Communications/
  Pricing/
  Enrollments/
  Content/
  Events/
resources/{views,css,js}/
database/migrations/
tests/{Unit,Integration,Contract,E2E}/
docs/{architecture,decisions,modules}/
.github/{workflows,scripts}/
```

Cada módulo puede utilizar `Domain`, `Application`, `Infrastructure`, `Presentation` solo cuando esa separación tenga valor. No introducir carpetas vacías ni capas sin lógica real.

## Contratos de compatibilidad (invariantes)

1. No cambiar identificadores de CPT, meta keys, taxonomías, URLs, shortcodes ni nombres de opciones sin migración y pruebas.
2. Conservar la semántica de consentimiento; las consultas transaccionales no implican suscripción comercial.
3. Mautic no es una fuente de verdad sobre programas/cohortes; WordPress no reemplaza el origen de preinscripciones.
4. No duplicar envíos: mantener idempotencia y reintentos controlados en la cola transaccional.
5. No modificar ni mover tablas PostgreSQL o datos persistidos mediante refactorizaciones de código.
6. Mantener permisos de asistentes académicas y administradores; crear controles de autorización y regresión.
7. El CI debe cubrir la versión mínima de PHP anunciada y la versión real de producción; decidir la actualización de `Requires PHP` mediante ADR independiente.
8. Cualquier cambio al cargador/autoload debe mantener puntos de entrada públicos y `class_alias`/adaptadores si los usan dependientes.
9. Despliegues reversibles con respaldo y smoke test de las funcionalidades críticas.

## Etapas de migración

### 0. Inventario, contratos y baseline (sin refactor)
- Extraer mapa de módulos, rutas y hooks, CPT, metas, opciones, tablas, endpoints REST y cron/CLI.
- Identificar importaciones, efectos secundarios del bootstrap y dependencias circulares.
- Medir linters, tests, tiempo de carga, consultas SQL y errores actuales.
- Crear smoke tests de registro de entidades, permisos, cartas, consultas y correos.

**Salida:** mapa de dependencias verificable y pruebas de regresión ejecutables.

### 1. Infraestructura de desarrollo
- Introducir Composer con PSR-4, *sin exigir despliegue de dependencias externas* hasta validar el empaquetado.
- Configurar PHPCS + WordPress Coding Standards, PHPStan con stubs de WP, PHPUnit/WP integration según factibilidad.
- Añadir revisión estática de dependencias prohibidas y una matriz CI que incluya la mínima versión de PHP y la versión de producción.
- Dividir pasos de CI (lint, static analysis, tests, build) y condicionar despliegue a todas las verificaciones.
- Implementar ADR, CODEOWNERS si aplica, guía de contribución y plantilla de PR.

**Salida:** checks independientes que distinguen sintaxis, calidad, arquitectura y comportamiento.

### 2. Core y bootstrap
- Registrar módulos y sus dependencias explícitamente, con detección de ciclos.
- Convertir el archivo principal en un bootstrap pequeño; mover registro de hooks a providers por módulo.
- Encapsular logging, acceso a WordPress y configuración; no centralizar reglas funcionales en Core.
- Mantener compatibilidad con funciones y clases legadas durante la transición.

**Salida:** bootstrap probado y cargador determinista.

### 3. Piloto por dominio: People
- Extraer listado/edición de personas a capas de entrada, aplicación, repositorio y vistas, sin cambiar el CPT `docente`.
- Proteger relaciones con ofertas y roles; pruebas de edición para asistentes académicas.
- Mover CSS/JS incrustado a assets específicos de pantalla; aplicar responsive y accesibilidad.

**Salida:** un módulo migrado de punta a punta y patrón documentado.

### 4. Dominios críticos
- Inquiries y Communications: separar procesamiento transaccional, marketing, consentimiento, envío y reporting.
- Academic: programas, ofertas, cohortes, seminarios y ediciones con servicios de lectura/escritura explícitos.
- Pricing, Enrollments, Content y Events según mapa real de dependencias.
- Migrar en PR pequeños con adaptadores, sin sustitución masiva de API.

### 5. Limpieza, endurecimiento y observabilidad
- Eliminar compatibilidad obsoleta solo después de medir referencias.
- Revisión de seguridad: permisos, nonces, SQL parametrizado, sanitización, escapes, secretos e integraciones.
- Añadir métricas operativas y procedimientos de recuperación ante falla.

## Política de PR

- Un módulo o responsabilidad por PR; sin mezclar refactor con cambios de comportamiento o diseño.
- Describir dependencias y contratos afectados; incluir tests y plan de reversión.
- No desplegar una reestructuración total en un solo commit.
- Preferir cambios mecánicos y comprobables; comparación funcional antes/después.

## Riesgos a resolver antes de implementación

- PHP mínimo 7.4 frente al código existente que ya usa algunas características más recientes.
- Ubicación y propietario de las consultas PostgreSQL compartidas.
- Compatibilidad entre WP-Cron, WP-CLI, hooks y procesos en Mautic.
- Permisos de asistente editorial en funcionalidades distintas del editor clásico.
- Empaquetado/actualización de autoload en el workflow de despliegue.

## Primera tarea ejecutable

Implementar solo la etapa 0: inventario generado a partir del código y tests de regresión básicos. No mover carpetas hasta aprobar el mapa de límites, compatibilidad y datos.
