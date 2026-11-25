---
titulo: Promesas
orden: 50
icono: check
resumen: Qué es una promesa, cómo se configura, cómo se calcula lo que cada persona debía dar y dónde ver el cumplimiento.
roles: admin, tesorero
rutas: dashboard.promesas, ingresos-asistencia.promesas, ingresos-asistencia.promesas-por-clase, promesas.*, promesas-futuras.*#promesas-futuras
palabras: promesa, promesas futuras, corte de promesas, promesas 2027, importar excel, plantilla, compromiso, prometido, esperado, dado, cumplimiento, semanal, quincenal, mensual, misiones, seminario, construcción, faltante, excedente
---

## Para qué sirve

Una **promesa** es lo que una persona se compromete a dar de forma periódica para una categoría (misiones, seminario, construcción…). El sistema compara cada mes lo que la persona **prometió** con lo que **realmente dio** en sus sobres, y muestra si cumplió.

## Quién puede usarlo

- **Configurar** las promesas de una persona: el **Administrador**, desde el formulario de la persona.
- **Ver el cumplimiento** (dashboard y reportes): **Administrador** y **Tesorero**.
- **Ver las propias**: cualquier persona con acceso, en *Mi perfil*.

## Cómo se configura una promesa

Se configura dentro del formulario de la persona, en la sección **Promesas de ofrenda**. Al **Guardar** la ficha solo cambia lo que tocaste: las promesas que no cambiaste conservan su historial (antes se borraban y se volvían a crear). Si dejas el monto vacío o en cero, esa promesa se quita (menú **Gestionar Personas** → **Editar**). Hay una fila por categoría, sin el diezmo, que no lleva promesa.

![Sección de promesas del formulario de una persona](img:promesas-formulario.jpg "Monto (1), moneda (2) y frecuencia (3) por categoría")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Monto** | Cuánto promete la persona cada vez. Vacío o cero = sin promesa en esa categoría. | Administrador | Se usa para calcular lo esperado de cada mes. |
| **2. Moneda** | Colones (₡) o dólares ($). | Administrador | Los dólares se convierten a colones con el tipo de cambio. |
| **3. Frecuencia** | **Semanal**, **Quincenal** o **Mensual**. | Administrador | Define cuántas veces al mes se espera el monto. |

## Cómo se calcula lo esperado

| Frecuencia | Lo esperado en el mes |
|---|---|
| Semanal | El monto por cada **domingo** que tiene ese mes |
| Quincenal | El monto por **dos** |
| Mensual | El monto **una vez** |

Ejemplo: una promesa semanal de ₡5.000 en un mes con cuatro domingos espera ₡20.000. **Lo dado** es todo lo que la persona entregó en sobres de esa categoría durante el mes. Cada mes es independiente: **Diferencia = Dado − Esperado**, sin arrastrar saldos de meses anteriores.

> **Consejo:** semanal ₡100 son unos ₡400 a ₡500 al mes. Si dudas, elige la frecuencia con la que la persona suele dar.

## Dónde ver el cumplimiento

### Compromisos de una persona

En **Gestionar Personas** → el icono de **Compromisos** de la persona. Ver la página *Personas y compromisos*.

### Dashboard de promesas

Menú **Consultas** → **Dashboard de promesas** (Administrador y Tesorero). Resume un período con las mismas reglas que Cumplimiento: **Prometido (meses completos)**, **Dado (hasta hoy)**, **Saldo** y **Cumplimiento** en porcentaje, y una tarjeta aparte con el **mes en curso** («<Mes> (en curso, todavía no se cobra)»). Una nota azul explica la regla y enlaza a *Cumplimiento de promesas*. Abajo, la gráfica de lo prometido contra lo dado por mes y los **Top 10 contribuyentes**. El período se elige con **Mes y año** o con un **Rango de fechas** (ver *Dashboards*).

### Reporte de promesas

Menú **Consultas** → **Reportes** → **Promesas** (Administrador y Tesorero).

![Reporte de promesas](img:promesas-reporte.jpg "Filtros por año, mes y categoría (1); botones para el reporte por clase, el anual y el PDF mensual (2)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Año, Mes y Categoría** + **Buscar** | Elige el período (o todos los meses) y, si quieres, una sola categoría. | Administrador, Tesorero | Las tarjetas muestran **Prometido (meses completos)**, **Total Dado**, **Total Faltante** y **Total excedente**; la tabla, por categoría, lo prometido, lo dado, el **Faltante** y el **Excedente**. Una nota azul explica que el **mes en curso** no se cobra todavía y se muestra aparte (columna **En curso (no se cobra)** en los PDF). |
| **2. Promesas por Clase** | Muestra el cumplimiento agrupado por clase. | Administrador, Tesorero | Ves cada clase con sus totales. |
| **Reporte Anual (12 meses)** | Genera un PDF con los doce meses del año. | Administrador, Tesorero | Se abre el PDF en otra pestaña. |
| **PDF Mensual** | Genera el PDF del mes elegido. | Administrador, Tesorero | Se abre el PDF en otra pestaña. |
| **Excel** | Baja el cumplimiento del periodo. | Administrador, Tesorero | Es el mismo Excel de *Cumplimiento de promesas* (con corte al último día del año o mes elegido, sin pasar de hoy), así que dice lo mismo que el estado de cuenta. |

## Promesas futuras

Sirve para cargar **desde ahora** las promesas que empiezan en una fecha futura (por ejemplo, las de 2027 desde el 1 de enero). Mientras llega esa fecha **no cambia nada**: los sobres, los reportes y las fichas siguen con las promesas de hoy. El día que el corte **entra en vigor**, sus promesas **reemplazan a todas las anteriores** de la iglesia: quien no está en el corte queda sin promesa y quien es nuevo, entra. Los reportes de meses anteriores siguen dando lo mismo.

**Quién puede usarlo:** solo el **Administrador**. **Cómo llegar:** menú **Administración** → [Promesas futuras](route:promesas-futuras.index), o escribe «promesas futuras» en el buscador.

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **+ Nuevo corte** | Pide un nombre (por ejemplo «Promesas 2027») y el mes en que entran en vigor (siempre el día 1 de un mes que todavía no llegó; un solo corte por mes). | Administrador | Se abre el detalle del corte, vacío. |
| **Estado** en la lista | **Programado** (dice la fecha y cuántos días faltan), **Vigente** (el que rige hoy) o **Anterior**. | Administrador | Un corte **sin promesas no entra en vigor nunca**: la lista lo avisa en rojo. |
| **+ Agregar persona** | Busca a la persona por nombre, PIN o teléfono y toca **Elegir**; si no existe, la das de alta ahí mismo (solo el nombre es obligatorio). Después escribes el monto de cada categoría, con su frecuencia (semanal por defecto) y su moneda (colones por defecto). | Administrador | Vuelves al detalle con la persona en la tabla. Si ya estaba en el corte, se abre para editar. |
| **Editar** / **Quitar** (en cada fila) | Cambia lo que promete en el corte o la quita del corte. | Administrador | Sus promesas de hoy no cambian. Todo queda en la auditoría. |
| **Importar Excel** | Sube el Excel con todas las personas (ver abajo). Eliges la moneda y la frecuencia que se usa cuando una fila no trae la suya. | Administrador | Ves una **revisión**; nada se guarda hasta tocar **Aplicar**. |
| **Descargar plantilla** | Baja el Excel con la fila de títulos (Miembro, Clase, Frecuencia con su lista, y las categorías de promesa de la iglesia) y una hoja de **Instrucciones**. | Administrador | Lo llenas y lo subes con **Importar Excel**. |
| **Editar corte** | Cambia el nombre o el mes. Desde ahí también se **elimina** (pide escribir **CONFIRMAR**; borra sus promesas programadas y las categorías nuevas que se crearon para él). | Administrador | Solo mientras está programado: ya en vigor, el corte se ve en solo lectura. |

### Cómo debe venir el Excel

- Primera hoja; la **fila 1** es para los títulos. Columna **A: Miembro** (o **Nombre**), columna **B: Clase**, columna **C: Frecuencia**, y de la **D** en adelante **una columna por categoría** con su nombre.
- **Frecuencia:** en la plantilla se elige de una lista (**Semanal**, **Quincenal** o **Mensual**) y vale para todos los montos de esa persona. Si la celda queda vacía, se usa la frecuencia que elijas al subir el archivo (semanal por defecto). Si dice otra cosa, la fila sale en la revisión como **Frecuencia no válida** y no se aplica hasta que corrijas el archivo o la omitas.
- Un archivo **sin la columna Frecuencia** (Miembro, Clase y enseguida las categorías) también sirve: todas las filas llevan la frecuencia que elijas al subirlo.
- Una persona por fila. Monto vacío o 0 = sin promesa en esa categoría. Los montos se guardan con dos decimales (230,769… queda en 230,77).
- Un título que la iglesia no tiene es una **categoría nueva**: se crea **inactiva** y aparece en los sobres y en los reportes recién el día en que el corte entra en vigor.
- Máximo 2 MB y 2.000 personas.

### La revisión antes de aplicar

1. **Categorías:** cada título se empareja con la categoría de igual nombre (sin importar tildes ni mayúsculas). Para un título que no existe eliges: **crear la categoría nueva** (lo que viene marcado), **usar una que ya existe** (por ejemplo, «Campamento» → «CAMPA») o **no importar esa columna**.
2. **Personas:** cada fila sale con un estado:
   - **Calza:** el nombre es igual al de una persona del sistema. Se agrega.
   - **Ya está en este corte: se deja como está.** No se sobrescribe ni se duplica. Para cambiarla, edítala a mano.
   - **Nombre parecido:** tienes que elegir quién es, crear una persona nueva u omitir la fila. No hay opción marcada: **Aplicar** no se habilita hasta que decides.
   - **No existe:** se crea como persona nueva (con su clase, si hay una clase con ese nombre). También puedes buscar a alguien o omitirla.
   - **Sin montos** y **Repetida en el archivo** (se toma la primera vez que aparece): se ignoran.
   - **Frecuencia no válida:** la columna Frecuencia dice algo distinto de Semanal, Quincenal o Mensual (se muestra el texto). Corrige el archivo y súbelo de nuevo, u omite la fila: mientras tanto **Aplicar** no se habilita.

   Cada fila muestra la frecuencia con la que se va a guardar.
3. Toca **Aplicar**. El mensaje dice cuántas personas se agregaron, cuántas ya estaban y se dejaron igual, cuántas se omitieron y qué categorías se crearon.

> **Consejo:** puedes volver a subir el mismo archivo sin miedo: quien ya está en el corte se deja como está, así que no se agrega nada repetido.

> **Importante:** el sistema nunca crea dos veces a la misma persona por una tilde o una mayúscula. Si el nombre del Excel solo **se parece** al de alguien (por ejemplo «Ana Mora» y «Ana Mora Solís»), te pregunta quién es.

## Preguntas frecuentes

**Abrí una dirección vieja de /promesas.** Esa pantalla ya no existe: te lleva a **Personas**, donde se configuran las promesas (en la ficha de cada persona).

**Una persona dio más de lo prometido.** Se muestra como **excedente** (verde) en sus compromisos. No se arrastra al mes siguiente.

**Cambié la promesa de una persona y los meses pasados no cambiaron.** Es lo esperado: los compromisos de meses anteriores ya están calculados con la promesa de ese momento. Si necesitas empezar de nuevo desde un mes, **Reiniciar Compromisos** (al editar a la persona) borra el historial anterior a esa fecha; úsalo solo si estás seguro.

**¿Por qué el diezmo no tiene promesa?** El diezmo se registra en los sobres pero no se configura como promesa: no tiene una cantidad que prometer.

**Cargué las promesas del año que viene y no las veo en los reportes.** Es lo esperado: están en **Promesas futuras** y empiezan a regir el día de inicio del corte. Hasta entonces todo sigue con las promesas de hoy.
