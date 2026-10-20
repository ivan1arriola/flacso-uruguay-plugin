# Planes de articulación académica

Este módulo organiza la información académica que se muestra en **las páginas públicas de las Ofertas Académicas y en sus cartas**. No interviene en preinscripciones, matrículas, facturación, cohortes ni control de acceso de estudiantes.

## Cómo cargar un plan

En WordPress, ir a **FLACSO → Planes de articulación → Añadir nuevo**.

1. Escribir un nombre editorial para la formación articulada.
2. Crear las **etapas**. Cada una tiene un identificador interno único (p. ej. `genero-c1`), número de ciclo, nombre visible, Oferta Académica existente, mención opcional, URL HTTPS de calendario y descripción.
3. Crear las **transiciones** desde etapas del Ciclo N hacia el N+1. Elegir opcionalmente una mención de la Oferta Académica de origen para condicionar la rama. Cada transición significa que **hay que aprobar la etapa de origen para poder cursar la etapa de destino**.
4. Añadir, de forma opcional, **textos por Oferta** para explicar su papel en la articulación y las condiciones de continuidad y contratación.
5. Guardar como borrador hasta confirmar nombres, ramificaciones, títulos y calendarios; publicar el plan cuando esté listo.

El editor permite varios nodos con el mismo número de ciclo (ramificaciones) y varios nodos que desembocan en uno de un ciclo superior (convergencia). Las transiciones siempre avanzan exactamente un nivel, por lo que el grafo nunca forma ciclos dirigidos.

No se admiten claves duplicadas, Ofertas inexistentes, menciones ajenas a la Oferta, transiciones a etapas inexistentes, aristas repetidas ni etapas superiores sin al menos una transición entrante. Cuando se detecta un error, la configuración previa no se sobrescribe y aparece un aviso administrativo.

## Cómo configurar los casos reales

### Género

Un ejemplo de **estructura**, sin presuponer cuáles son las menciones exactas o las denominaciones de los títulos:

- Ciclo 1: Oferta **Diploma en Género** (una etapa común, sin mencionar una mención obligatoria).
- Ciclo 2: una etapa por cada Diplomado o Especialización que corresponda. Vincularlas desde el Ciclo 1 con la mención habilitante respectiva.
- Ciclo 3: Oferta **Maestría en Género**, con una transición desde cada etapa del segundo ciclo que permita continuar.
- Asociar el calendario de cada etapa a su URL oficial.
- En la presentación de la **Maestría**, explicar que su plan de cuotas contempla los tres ciclos y que se requiere aprobar cada etapa.
- En la presentación del **Diploma**, explicar que la continuación a un ciclo superior debe contratarse y abonarse por separado.

Las denominaciones y menciones de cada rama se seleccionan del catálogo existente, no se crean nuevas Ofertas desde el plan.

### Abordaje de la Violencia hacia Niños, Niñas y Adolescentes

Usar un plan de dos ciclos cuando se confirmen sus equivalencias y precedencias:

- Ciclo 1: Diploma correspondiente.
- Ciclo 2: Diplomado correspondiente.
- Transición `ciclo-1 → ciclo-2`, con la aprobación del primero como requisito.

La transición debe cargarse únicamente si esa precedencia está confirmada institucionalmente.

## Publicación

El tema obtiene la articulación con `FLACSO_Academic_Articulations::for_offer($offer_id)` y usa el mismo componente para la web y la carta.

- Las Ofertas sin plan publicado conservan sus páginas y cartas.
- Solo aparecen planes publicados y Ofertas de destino publicadas; los borradores y sus transiciones no se muestran.
- Cada título enlaza a su Oferta Académica.
- Los calendarios son enlaces independientes por etapa. Por ahora **no se importan automáticamente desde las cohortes**.
- Las condiciones de contratación son **textos editoriales por Oferta** y no afectan las tablas de aranceles.
- El campo previo de `titulos_intermedios` sigue disponible para Ofertas sin planes. No se convierte ni migra automáticamente.

## Alcance y límites

El plan describe **rutas académicas posibles**, no el historial individual de estudiantes. No se calcula aprobación real, inscripción ni habilitación administrativa a partir de él. Una transición condicionada por mención expresa un requisito editorial y no constituye una regla de acceso implementada en el portal de preinscripciones.

Los ciclos se numeran desde 1 y sus transiciones conectan niveles consecutivos. Esta es la configuración actual de las formaciones por ciclos de FLACSO; otras reglas de equivalencias o rutas sin secuencia deberán tratarse de forma explícita antes de ampliar el motor.
