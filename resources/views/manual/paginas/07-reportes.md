---
titulo: Reportes
orden: 70
icono: reporte
resumen: Sacar reportes de ingresos, asistencia (por culto y por clase) y estados de cuenta, en pantalla, PDF o Excel.
roles: admin, tesorero, asistente
rutas: ingresos-asistencia.*
palabras: reportes, informes, ingresos, asistencia por clase, promedio, estado de cuenta, pdf, excel, csv, transferencias, descargar, rango de fechas, reporte general, dado contra esperado
---

## Para qué sirve

Los **reportes** convierten lo registrado (sobres, asistencia, promesas) en cuadros listos para revisar, imprimir o compartir: en pantalla, en **PDF** y en **Excel**.

## Quién puede usarlo

| Reporte | Quién |
|---|---|
| **Ingresos** y **Promesas** | Administrador, Tesorero |
| **Asistencia** y **Asistencia por clase** | Administrador, Asistente |
| **Reporte de contribuciones**, **Reporte General** y **Estado de cuenta** de una persona | Administrador |
| **Reporte de Servidores** | Administrador |

El menú lateral solo muestra los que tu rol puede abrir. El Tesorero ve en **Reportes** dos tarjetas (Ingresos y Promesas) y el Asistente entra directo al reporte de asistencia.

## Cómo llegar

Menú lateral → **Consultas** → **Reportes**. Allí aparece una tarjeta por cada reporte que puedes abrir.

![Pantalla de reportes](img:reportes-indice.jpg "Cada tarjeta abre un reporte; abajo hay un resumen de la semana")

## Reporte de ingresos

Muestra lo recibido en los cultos de un período, con una columna por categoría.

![Reporte de ingresos](img:reportes-ingresos.jpg "Tipo de reporte y fechas (1), descargas (2) y detalle por culto (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Tipo de Reporte, Fecha Inicio y Fecha Fin** | Elige agrupar **Por Culto**, **Por Semana** o **Por Mes** y el rango de fechas. | Administrador, Tesorero | La tabla se arma con ese período. |
| **2. PDF Completo / Excel / Solo Transferencias (PDF)** | Descarga el reporte. **Solo Transferencias** incluye solo los sobres pagados por transferencia. | PDF: Administrador y Tesorero. Excel: por ahora solo el Administrador | Se abre en otra pestaña o se descarga el archivo. Si eres Tesorero y el Excel te dice que no tienes permiso, usa el PDF. |
| **3. Detalle de Ingresos** | Una fila por culto con su total y las acciones **Recuento** y **Transferencias**. | Administrador, Tesorero | Cada acción baja el PDF de ese culto. |

## Reporte de asistencia

Lista la asistencia por culto, con filtros de **Mes**, **Año** y rango de fechas, y botones **PDF** y **Excel** para todo el período. Abajo hay un resumen por mes con su propio PDF y Excel.

## Asistencia por clase (con promedio)

Elige un rango de fechas (**Desde** y **Hasta**) y, si quieres, una **Clase**. El reporte muestra niños, niñas y maestros de cada fecha y, al final, el **promedio** de la clase y el **promedio general**.

![Asistencia por clase](img:reportes-asistencia-clases.jpg "Rango y clase (1), descargas (2) y la tabla con promedios (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Desde, Hasta, Clase y Consultar** | Elige el rango y la clase. **Limpiar** vuelve a los últimos dos meses y todas las clases. | Administrador, Asistente | Se recalcula el reporte. |
| **2. PDF / CSV / Excel** | Descarga el reporte **con el promedio ya calculado**. | Administrador, Asistente | Se descarga el archivo. |
| **3. Tabla** | Una fila por fecha con niños, niñas, maestros y total; al final el **Promedio general**. | Administrador, Asistente | Abajo se listan las fechas sin registro. |

**Cómo se calcula el promedio:** suma de la clase en el rango ÷ fechas en que la clase tiene registro. Las fechas sin registro no bajan el promedio. Las fechas con todo en cero se tratan como «sin registro» (porque el formulario guarda ceros aunque la clase no se reúna); marca **Contar también las fechas en 0** si quieres incluirlas.

## Reporte de promesas

Ver la página *Promesas*.

## Reportes de personas (solo Administrador)

En **Gestionar Personas** hay tres salidas relacionadas con los reportes:

- **Reporte PDF:** el reporte de contribuciones de todas las personas, de enero al mes actual.
- **Reporte General:** el «dado contra esperado» de todas las personas activas.
- **Estado de cuenta de una persona:** el icono del documento en su renglón (o **Estado de cuenta** en su ficha) abre su PDF; **Descargar** lo guarda.

## Preguntas frecuentes

**El PDF no se abre.** Revisa que tu navegador no esté bloqueando ventanas emergentes: los PDF se abren en otra pestaña.

**No veo el reporte que busco.** Tu rol no lo usa. Búscalo por su nombre en el buscador de la barra superior: solo aparece lo que puedes abrir.
