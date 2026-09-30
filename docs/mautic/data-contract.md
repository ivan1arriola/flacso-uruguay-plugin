# Contrato de datos WordPress a Mautic

Los campos estandar son `email`, `firstname` y `lastname`. El resto pertenece al contrato de consultas y se crea con alias `flacso_`:

| Alias | Tipo | Fuente | Regla |
| --- | --- | --- | --- |
| `flacso_consulta_id` | text | id persistido | requerido |
| `flacso_consulta_fecha` | datetime | inquiryAt | ISO 8601 UTC |
| `flacso_origen` | text | formulario | `web-consultas` |
| `flacso_tipo` | select | consulta | `oferta` o `seminario` |
| `flacso_oferta_codigo` | text | abreviacion | kebab-case |
| `flacso_oferta_nombre` | text | snapshot | opcional |
| `flacso_oferta_articulo` | text | snapshot | opcional |
| `flacso_oferta_url` | url | snapshot | URL valida de información, con sufijo `carta` |
| `flacso_cohorte_codigo` | text | snapshot | `{codigo}-c{numero}` o vacio |
| `flacso_cohorte_numero` | number | snapshot | positivo o vacio |
| `flacso_cohorte_nombre` | text | snapshot | opcional |
| `flacso_cohorte_estado` | select | snapshot | `abierta`, `cerrada` o `sin_cohorte` |
| `flacso_modalidad` | select | snapshot | `virtual`, `presencial` o `hibrida` |
| `flacso_fecha_inicio` | date | snapshot | `YYYY-MM-DD` con precision suficiente |
| `flacso_duracion` | text | snapshot | opcional |
| `flacso_creditos` | number | snapshot | opcional |
| `flacso_preinscripcion_url` | url | snapshot | URL valida o vacia |
| `flacso_consulta_texto` | textarea | consulta | no se registra en logs |
| `flacso_pais` | text | `pais` | opcional; cadena vacia si falta |
| `flacso_nivel_academico` | text | `nivel_academico` | opcional; cadena vacia si falta |
| `flacso_profesion` | text | `profesion` | opcional; cadena vacia si falta |
