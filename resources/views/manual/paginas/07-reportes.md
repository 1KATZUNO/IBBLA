---
titulo: Reportes
orden: 70
icono: reporte
resumen: Sacar reportes de cultos, ingresos, asistencia (por culto y por clase), cumplimiento de promesas y estados de cuenta, en pantalla, PDF o Excel.
roles: admin, tesorero, asistente
rutas: ingresos-asistencia.*, reportes.*, reportes.cumplimiento*#cumplimiento-de-promesas-con-corte, reportes.estados-cuenta*#estados-de-cuenta-en-lote, reportes.cultos*#reporte-de-cultos, reportes.ingresos*#reporte-de-ingresos, ingresos-asistencia.ingresos#reporte-de-ingresos, ingresos-asistencia.asistencia#reporte-de-asistencia, ingresos-asistencia.asistencia-clases*#asistencia-por-clase-con-promedio
palabras: cumplimiento de promesas, al día, corte, estados de cuenta en lote, lote, zip, reportes, informes, reporte de cultos, reporte de ingresos, ingresos, comparativo, periodo anterior, asistencia por clase, promedio, estado de cuenta, pdf, excel, csv, transferencias, descargar, rango de fechas, reporte general, dado contra esperado, selector de personas, vista previa, último aporte
---

## Para qué sirve

Los **reportes** convierten lo registrado (sobres, asistencia, promesas) en cuadros listos para revisar, imprimir o compartir: en pantalla, en **PDF** y en **Excel**. Todos usan la misma plantilla (encabezado de la iglesia, periodo, resumen, totales y pie con la fecha de generación) y las mismas reglas de cálculo, así que las cifras coinciden entre un reporte y otro.

## Quién puede usarlo

| Reporte | Quién |
|---|---|
| **Reporte de cultos** y **Reporte de ingresos** | Administrador, Tesorero |
| **Promesas**, **Cumplimiento de promesas**, **Estados de cuenta en lote**, **Reporte de contribuciones**, **Reporte General** y **Estado de cuenta** de una persona | Administrador, Tesorero |
| **Asistencia por culto** y **Asistencia por clase** | Administrador, Asistente y Tesorero |
| **Reporte de Servidores** | Administrador |

El menú lateral solo muestra los que tu rol puede abrir. El Asistente entra directo al reporte de asistencia.

## Cómo llegar

Menú lateral → **Consultas** → **Reportes**. Es el **centro de reportes** (la pantalla se titula **Ingresos y Asistencia**): una tarjeta por cada reporte que puedes abrir, con una línea de qué contiene y en qué formatos sale. Desde cualquier pantalla llegas a cualquier reporte en dos clics.

![Pantalla de reportes](img:reportes-indice.jpg "Cada tarjeta abre un reporte; abajo hay un resumen de la semana")

| Tarjeta | Qué muestra | Formatos | Quién |
|---|---|---|---|
| **Cumplimiento de promesas** | Quién está al día y quién debe, con fecha de corte. | Pantalla, PDF y Excel | Administrador, Tesorero |
| **Estados de cuenta en lote** | El estado de cuenta de muchas personas a la vez. | PDF, ZIP y Excel | Administrador, Tesorero |
| **Reporte General** | Lo dado contra lo esperado de todas las personas activas. | PDF | Administrador, Tesorero |
| **Reporte de contribuciones** | Contribuciones de todas las personas, de enero al mes actual. | PDF | Administrador, Tesorero |
| **Reporte de cultos** | Cada culto de un rango: sobres, efectivo, transferencias, suelto, egresos y firmas, con totales por categoría y método de pago. | Pantalla, PDF y Excel | Administrador, Tesorero |
| **Reporte de ingresos** | Ingresos de un rango por categoría y método de pago, comparados con el periodo anterior. (Reemplaza a la antigua tarjeta «Ingresos»; los enlaces viejos llevan a esta pantalla.) | Pantalla, PDF y Excel | Administrador, Tesorero |
| **Promesas** | Promesas y compromisos por persona. | Pantalla, PDF y Excel | Administrador, Tesorero |
| **Asistencia por culto** | Asistencia de cada culto en un rango de fechas y resumen por mes. | Pantalla, PDF y Excel | Administrador, Asistente, Tesorero |
| **Asistencia por clase** | Niños, niñas, maestros y promedio por clase en un rango de fechas; una clase o todas. | Pantalla, PDF, CSV y Excel | Administrador, Asistente, Tesorero |

## Los filtros de fechas (cultos e ingresos)

El **Reporte de cultos** y el **Reporte de ingresos** comparten el mismo bloque de filtros.

![Reporte de cultos](img:reportes-cultos.jpg "Desde y Hasta (1), atajos (2), Consultar (3) y descargas (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Desde y Hasta** | Fijan el rango de fechas. | Administrador, Tesorero | Se recalcula el reporte con ese rango. |
| **Tipo de culto (ninguno marcado = todos)** | Una casilla por tipo de culto. | Administrador, Tesorero | Solo entran los cultos de los tipos marcados. |
| **2. Atajos** | **Este mes**, **Mes anterior**, **Últimos 3 meses**, **Este año**. | Administrador, Tesorero | Aplican el rango de un toque. |
| **3. Consultar / Limpiar** | **Consultar** aplica los filtros; **Limpiar** vuelve al mes actual con todos los tipos. | Administrador, Tesorero | La pantalla se actualiza. |
| **4. Ver PDF, Descargar PDF, Descargar Excel** | Sacan el reporte con los mismos filtros (la etiqueta dice «Exportar N cultos»). | Administrador, Tesorero | **Ver PDF** lo abre en otra pestaña; los otros dos descargan el archivo. |

> **Importante:** el rango máximo es de **un año**. Si eliges más, el reporte muestra solo un año y te avisa («El rango máximo es de un año…»). Para más tiempo, saca un reporte por año.

## Reporte de cultos

Cada culto del rango, con lo que se recibió y gastó en él.

- **Elegir cultos:** el desplegable **Elegir cultos (todos los del rango)** te deja marcar solo los cultos que quieres; sin ninguno marcado salen todos los del rango. Cambia a «(N elegidos)».
- **Tarjetas de resumen:** **Cultos**, **Sobres**, **Ingresos**, **Egresos**, **Total del rango** y **Sin firmas completas** (cuántos cultos no tienen la firma del pastor y la de al menos un tesorero).
- **Tabla «Cultos (N)»:** **Fecha**, **Tipo**, **Estado**, **Sobres**, **Sobres en efectivo**, **Transferencias**, **Suelto**, **Egresos**, **Total** y **Firmas completas** (el Total es sobres en efectivo + transferencias + suelto − egresos, así que las columnas suman), más los cuadros **Ingresos por categoría** e **Ingresos por método de pago**.
- Si no hay cultos con esos filtros, la pantalla lo dice y sugiere ampliar las fechas (por ejemplo con el atajo **Este año**), quitar tipos o desmarcar cultos.
- El **Excel** trae las hojas **Resumen** (una fila por culto y totales) y detalle; el **PDF** sale horizontal.

## Reporte de ingresos

Lo recibido en el rango, **comparado con el periodo anterior** de la misma duración. Si el periodo todavía está en curso (por ejemplo, el mes actual), la pantalla lo avisa y compara solo **los mismos días ya transcurridos** del periodo anterior, para no mostrar como caída un mes que va a medias.

![Reporte de ingresos](img:reportes-ingresos.jpg "Desde y Hasta (1), atajos (2), Consultar (3) y descargas (4), con los mismos filtros que el reporte de cultos")

- **Tarjetas:** **Ingresos del periodo**, **Periodo anterior**, **Variación**, **Egresos** y **Neto (ingresos − egresos)**.
- **Tablas por categoría y por método de pago**, con las columnas **Concepto**, **Este periodo**, **Periodo anterior**, **Diferencia** y **Variación**. Si el periodo anterior no tuvo ingresos, la variación dice «sin datos antes».
- Una nota azul enlaza al **Reporte de cultos** para ver el detalle culto por culto.
- **PDF** y **Excel** (hojas **Resumen** y **Detalle**, una fila por culto).

## Reporte de asistencia

Lista la asistencia por culto, con filtros de **Mes**, **Año** y rango de fechas, y botones **PDF** y **Excel** para todo el periodo (el Tesorero también los puede usar). Abajo hay un resumen por mes con su propio PDF y Excel. El enlace **Ver la asistencia por clase de estas fechas** abre el reporte por clase con el mismo rango. El PDF y el Excel llevan el formato común de los reportes.

## Asistencia por clase (con promedio)

Elige un rango de fechas (**Desde** y **Hasta**) y, si quieres, una **Clase**. El reporte muestra niños, niñas y maestros de cada fecha y, al final, el **promedio** de la clase y el **promedio general**. El enlace **Ver la asistencia por culto de estas fechas** abre el otro reporte con el mismo rango.

![Asistencia por clase](img:reportes-asistencia-clases.jpg "Rango y clase (1), descargas (2) y la tabla con promedios (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Desde, Hasta, Clase y Consultar** | Elige el rango y la clase. **Limpiar** vuelve a los últimos dos meses y todas las clases. | Administrador, Asistente, Tesorero | Se recalcula el reporte. |
| **2. PDF / CSV / Excel** | Descarga el reporte **con el promedio ya calculado**. | Administrador, Asistente, Tesorero | Se descarga el archivo. |
| **3. Tabla** | Una fila por fecha con niños, niñas, maestros y total; al final el **Promedio general**. | Administrador, Asistente, Tesorero | Abajo se listan las fechas sin registro. |

**Cómo se calcula el promedio:** suma de la clase en el rango ÷ fechas en que la clase tiene registro. Las fechas sin registro no bajan el promedio. Las fechas con todo en cero se tratan como «sin registro» (porque el formulario guarda ceros aunque la clase no se reúna); marca **Contar también las fechas en 0** si quieres incluirlas.

## Reporte de promesas

Ver la página *Promesas*: ahí se explica el reporte de promesas y cómo trata el mes en curso.

## Cumplimiento de promesas (con corte)

Dice, a una **fecha de corte**, quién va **al día** con sus promesas y quién tiene pendiente. Sale en pantalla, en **PDF** y en **Excel**. Lo ven el Administrador y el Tesorero.

![Cumplimiento de promesas con su selector de personas y vista previa](img:reportes-cumplimiento.jpg "Fecha de corte (1), filtros de personas (2) y seleccionar todos los filtrados (3); la vista previa está más abajo")

1. Elige la **Fecha de corte** (por defecto, hoy).
2. Elige **Personas** con el selector (ver «El selector de personas» más abajo).
3. Toca **Consultar**. Para volver al corte de hoy con todas las personas activas, **Limpiar**.
4. Mira la **Vista previa** (ver abajo) y baja el **PDF** o el **Excel** con los mismos filtros.

**Cómo se calcula:**

- **Le corresponde** = los **meses completos** anteriores al corte. El mes en curso **no se cobra todavía**, pero lo que la persona ya dio en ese mes **sí cuenta** como dado.
- La persona está **AL DÍA** si lo **dado ≥ le corresponde**; si no, muestra lo que le **falta**.
- Solo cuentan las **categorías con promesa**. El **diezmo** se muestra pero **no cuenta** para el cumplimiento.
- Lo dado en dólares se **convierte** a colones con el tipo de cambio del recuento.
- La columna **Último aporte a sus compromisos** muestra la fecha del último sobre que cuenta para lo que la persona prometió (lo que dio en categorías sin promesa no cuenta).

## Estados de cuenta en lote

Genera el estado de cuenta de **muchas personas a la vez**, con la misma fecha de corte. Lo ven el Administrador y el Tesorero.

- **PDF:** un solo archivo con portada e índice y una persona por sección.
- **ZIP:** un PDF por persona, hasta **40 personas** por descarga (si eliges más, el sistema te pide acotar la lista).
- **Excel:** las personas del grupo en una hoja de cálculo.
- Si el filtro no deja a nadie, el sistema avisa que no hay nada que exportar y te pide cambiar el grupo o la selección.

### El selector de personas

Sirve igual en Cumplimiento y en Estados de cuenta. Los filtros se **combinan**: la lista incluye solo a quien cumple todos.

| Control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **Estado** | **Activas**, **Inactivas** o **Activas e inactivas**. | Administrador, Tesorero | La lista y el contador se ajustan. |
| **Con promesa** | **Todas**, **Sí, con promesa** o **No, sin promesa**. | Administrador, Tesorero | Igual. |
| **Aportó en el periodo** | **Todas**, **Sí aportó** o **No aportó** (en el periodo del reporte). | Administrador, Tesorero | Igual. |
| **Ministerios** | Casillas por ministerio (**Cualquier ministerio**; ninguno marcado = no filtrar). Con más de 12 aparece **Buscar ministerio**. | Administrador, Tesorero | Solo quienes sirven en los ministerios marcados. |
| **Buscar en la lista (nombre, PIN o teléfono)** | Encuentra a una persona; **Enter** agrega a la primera de la lista. | Administrador, Tesorero | Queda elegida (aparece como etiqueta que puedes quitar). |
| **Elegir las N de la lista** | Marca de una vez todas las personas que se ven. **Mostrar 50 más** carga más filas. | Administrador, Tesorero | Quedan elegidas. |
| **Seleccionar todos los filtrados** | Elige a **todas** las personas que cumplen los filtros, aunque no se vean en la lista. | Administrador, Tesorero | El contador dice **N personas seleccionadas**. |
| **Quitar todos** | Deja la selección vacía. | Administrador, Tesorero | Sin nadie elegido a mano, el reporte incluye a **todas las que cumplen los filtros** (el aviso lo dice). |

El **contador** («N personas seleccionadas») y los avisos te dicen siempre a cuántas personas va a llegar el reporte: por ejemplo «No eligió a nadie a mano: el reporte incluye a las N personas que cumplen los filtros».

### La vista previa

Debajo del selector, la **Vista previa** muestra a las personas del reporte antes de bajarlo.

- **Toque un título de columna para ordenar** (otro toque invierte el orden).
- Las pestañas **Todos**, **Pendientes**, **Al día** y **Sin promesa** limitan lo que ves.
- Se pagina con **Anterior**, los números y **Siguiente**, y dice «Filas X a Y de Z».
- La fila de totales («Total con promesa (N personas, todas las páginas)») suma **todas las páginas**, no solo la que ves.

## Reportes de dinero por persona

Estos también salen del centro de reportes, para Administrador y Tesorero:

- **Reporte General:** el «dado contra esperado» de todas las personas activas, de enero al mes actual (PDF).
- **Reporte de contribuciones:** las contribuciones de todas las personas, de enero al mes actual (PDF).
- **Estado de cuenta de una persona:** el icono del documento en su renglón de **Gestionar Personas** (o **Estado de cuenta** en su ficha) abre su PDF; **Descargar** lo guarda.

## Preguntas frecuentes

**El PDF no se abre.** Revisa que tu navegador no esté bloqueando ventanas emergentes: los PDF se abren en otra pestaña.

**No veo el reporte que busco.** Tu rol no lo usa. Búscalo por su nombre en el buscador de la barra superior: solo aparece lo que puedes abrir.

**El reporte de cultos o de ingresos no muestra todo el rango.** El máximo es un año; el aviso dice qué fechas se están mostrando.

**El cumplimiento no cobra este mes.** Es a propósito: el mes en curso no se cobra todavía; lo que la persona ya dio en él sí cuenta.
