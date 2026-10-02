# Operacion y piloto

Antes del piloto: respaldar la base de consultas, registrar SHA, mantener el flag de campana apagado y configurar Mailjet como contingencia.

El piloto usa un contacto autorizado y verifica conexion, upsert, campos `flacso_*`, fusion de tags y membership unico. Luego se apaga el flag para comprobar rollback. No se inspeccionan ni modifican contactos existentes masivamente.

El seguimiento permanece deshabilitado (`flacso_inquiry_followup_enabled=0`) hasta contar con un modelo aprobado que preserve el snapshot de cada consulta.
