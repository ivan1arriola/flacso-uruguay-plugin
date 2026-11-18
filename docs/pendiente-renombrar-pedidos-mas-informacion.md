# Pendiente: terminología «Pedidos de más información»

## Decisión

La funcionalidad que recibe solicitudes de información sobre ofertas académicas debe denominarse **Pedidos de más información** en la interfaz y la documentación. En contextos de espacio reducido se puede usar **Más Info Form** como nombre corto del formulario, sujeto a validación de estilo institucional.

No usar «Consultas» para estos pedidos. **Consulta general** sigue identificando el formulario de contacto institucional y no debe confundirse con este flujo.

## Trabajo pendiente (no incluido en este PR)

- Inventariar y renombrar títulos, menús, etiquetas, mensajes, filtros y reportes visibles al personal.
- Revisar textos accesibles, nombres de eventos analíticos y documentación de API.
- Evaluar una migración gradual de nombres internos (`flacso_consultas`, clases, hooks, opciones y rutas) **sin romper contratos, registros existentes, enlaces ni integraciones**.
- Agregar pruebas de compatibilidad y actualizar documentación y capturas de interfaz.

## Alcance de este PR

Este PR corrige la confirmación de persistencia y la medición de pedidos de más información; **no ejecuta el renombrado general**. Se conservan identificadores internos heredados para evitar cambios incompatibles.
