# Contrato de datos WordPress -> Mautic

Este contrato separa dos conceptos distintos:

1. **Acuse transaccional por consulta**: usa un InquirySnapshot inmutable y tokens suministrados en esa entrega.
2. **Marketing posterior**: sincroniza únicamente perfil estable, tags y pertenencia a campaña cuando existe consentimiento verificable.

Los datos de una consulta concreta **no se almacenan como campos mutables del contacto**.

## Perfil estable permitido en el contacto

Además de los campos estándar email, firstname y lastname, WordPress sólo puede escribir estos aliases desde el flujo de consultas:

| Alias | Tipo | Fuente |
| --- | --- | --- |
| flacso_origen | text | constante web-consultas |
| flacso_pais | text | perfil declarado |
| flacso_nivel_academico | text | perfil declarado |
| flacso_profesion | text | perfil declarado |

El manifiesto versionado está en modules/consultas/services/class-flacso-mautic-contract-manifest.php. El validador comprueba estos aliases y tipos en modo de solo lectura.

## Snapshot de consulta

Cada consulta conserva en WordPress un snapshot con versión de esquema, destinatario, perfil, atribución, contexto académico, cohorte, fecha de inicio, modalidad, enlaces y tags calculados. El snapshot no cambia aunque el contacto consulte otra oferta posteriormente.

Aliases canónicos:

- startDate
- startDatePrecision: day, month o year
- modality: virtual, presencial, semipresencial o hibrida

No existen aliases paralelos startValue / modalityLabel dentro del contrato nuevo.

## Tags

La única fábrica es FLACSO_Inquiry_Tag_Factory:

- interes-{codigo}
- {codigo}-c{numero} cuando existe cohorte
- origen-web-consultas

Las etiquetas no contienen el estado abierto/cerrado de la cohorte.

## Acuse transaccional

La entrega persiste su propio conjunto de tokens antes de contactar Mautic. La plantilla nunca debe depender de contactfield=flacso_oferta_* para reconstruir la consulta.

La identidad de plantilla incluye ID de Mautic, versión funcional y SHA-256 del contenido aprobado. Una huella ausente o distinta deja el contrato en estado blocked.

accepted significa que Mautic aceptó la solicitud HTTP. No demuestra entrega, apertura ni lectura.

## Privacidad

flacso_consulta_texto queda fuera del contrato. El texto libre permanece exclusivamente en el registro de WordPress/PostgreSQL y no se envía a Mautic, logs, alertas ni diagnósticos.

El payload transaccional restringido se anonimiza después de 90 días y pierde las referencias reversibles a email, contacto, consulta y snapshot.

## Consentimiento comercial

Una campaña sólo puede incorporarse si la evidencia contiene simultáneamente:

- granted = true;
- acceptedAt válido;
- source;
- textVersion.

La ausencia o incompletitud del consentimiento no bloquea la creación de la consulta ni su acuse transaccional.
