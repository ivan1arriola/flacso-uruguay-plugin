# Límites PHP para preinscripciones

Estos valores deben configurarse en el `php.ini`, `.user.ini` del directorio
raíz de WordPress o panel del hosting. No deben cambiarse durante el callback
AJAX: para entonces PHP ya recibió y descartó cualquier cuerpo demasiado
grande.

```ini
upload_max_filesize = 4M
post_max_size = 32M
max_file_uploads = 10
max_execution_time = 300
memory_limit = 256M
```

El formulario aplica límites más estrictos:

- 3 MiB por archivo;
- 25 MiB acumulados;
- 7 archivos en total;
- 2 archivos para el documento de identidad.

Después de cambiar la configuración se debe reiniciar PHP-FPM si el hosting lo
requiere y comprobar los valores efectivos desde **Herramientas > Salud del
sitio > Información > Servidor**.
