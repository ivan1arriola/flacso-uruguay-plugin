# Seminarios integrados

Este documento complementa `modelo-academico-final.md` y define las reglas canónicas de composición.

## Composición académica

Un `Seminario` es integrado cuando su campo `componentes` contiene uno o más seminarios.

```text
Seminario Integrado
├── Seminario A
├── Seminario B
└── Seminario C
```

La composición es estable y vive en el Seminario. No referencia Ediciones.

### Créditos

`creditos` es un valor derivado para un Seminario integrado. Es la suma de los créditos de sus componentes. Si un componente también es integrado, se resuelve recursivamente.

El valor no debe editarse como un dato propio del Seminario integrado ni usarse como una copia independiente.

## Composición temporal

Cada Edición de un Seminario integrado selecciona las Ediciones concretas de sus componentes mediante `ediciones_componentes`.

```text
Edición Integrada 2026
├── Edición A-2026  -> Seminario A
├── Edición B-2026  -> Seminario B
└── Edición C-2026  -> Seminario C
```

Reglas de integridad:

1. cada `edicion_id` debe ser un post de tipo `edicion`;
2. su `seminario_id` debe pertenecer a `componentes` del Seminario integrado;
3. no se admite la propia Edición como componente;
4. sólo puede seleccionarse una Edición por cada Seminario componente directo;
5. el orden de `ediciones_componentes` se conserva;
6. una Edición de un seminario que no sea componente se descarta.

No se selecciona automáticamente la edición vigente de cada componente: la relación temporal es explícita.

## Datos derivados de la Edición integrada

### Docentes

`docentes` es la unión sin duplicados de los docentes de todas las `ediciones_componentes`. No se edita manualmente en la Edición integrada.

### Encuentros sincrónicos

`encuentros_sincronicos` es la unión sin duplicados de los encuentros de todas las `ediciones_componentes`, ordenada por fecha y hora de inicio. No se edita manualmente en la Edición integrada.

Si una Edición componente pertenece a un Seminario que también es integrado, la resolución continúa de forma recursiva.

## Consecuencia operativa

Cambiar créditos en un Seminario componente, o docentes/encuentros en una Edición componente, cambia inmediatamente los valores visibles del integrado. No requiere sincronizar ni copiar datos al registro padre.
