---
titulo: Promesas
orden: 50
icono: check
resumen: Qué es una promesa, cómo se configura, cómo se calcula lo que cada persona debía dar y dónde ver el cumplimiento.
roles: admin, tesorero
rutas: dashboard.promesas, ingresos-asistencia.promesas, ingresos-asistencia.promesas-por-clase, promesas.*
palabras: promesa, compromiso, prometido, esperado, dado, cumplimiento, semanal, quincenal, mensual, misiones, seminario, construcción, faltante, excedente
---

## Para qué sirve

Una **promesa** es lo que una persona se compromete a dar de forma periódica para una categoría (misiones, seminario, construcción…). El sistema compara cada mes lo que la persona **prometió** con lo que **realmente dio** en sus sobres, y muestra si cumplió.

## Quién puede usarlo

- **Configurar** las promesas de una persona: el **Administrador**, desde el formulario de la persona.
- **Ver el cumplimiento** (dashboard y reportes): **Administrador** y **Tesorero**.
- **Ver las propias**: cualquier persona con acceso, en *Mi perfil*.

## Cómo se configura una promesa

Se configura dentro del formulario de la persona, en la sección **Promesas de ofrenda** (menú **Gestionar Personas** → **Editar**). Hay una fila por categoría, sin el diezmo, que no lleva promesa.

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

Menú **Consultas** → **Dashboard de promesas** (Administrador y Tesorero). Resume un período: **Prometido**, **Dado**, **Saldo** y **Cumplimiento** en porcentaje, la gráfica de lo prometido contra lo dado por mes y los **Top 10 contribuyentes**. El período se elige con **Mes y año** o con un **Rango de fechas** (ver *Dashboards*).

### Reporte de promesas

Menú **Consultas** → **Reportes** → **Promesas** (Administrador y Tesorero).

![Reporte de promesas](img:promesas-reporte.jpg "Filtros por año, mes y categoría (1); botones para el reporte por clase, el anual y el PDF mensual (2)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Año, Mes y Categoría** + **Buscar** | Elige el período (o todos los meses) y, si quieres, una sola categoría. | Administrador, Tesorero | La tabla muestra, por categoría, lo **Total Prometido**, lo **Total Dado** y el **Faltante**. |
| **2. Promesas por Clase** | Muestra el cumplimiento agrupado por clase. | Administrador, Tesorero | Ves cada clase con sus totales. |
| **Reporte Anual (12 meses)** | Genera un PDF con los doce meses del año. | Administrador, Tesorero | Se abre el PDF en otra pestaña. |
| **PDF Mensual** | Genera el PDF del mes elegido. | Administrador, Tesorero | Se abre el PDF en otra pestaña. |

## Preguntas frecuentes

**Una persona dio más de lo prometido.** Se muestra como **excedente** (verde) en sus compromisos. No se arrastra al mes siguiente.

**Cambié la promesa de una persona y los meses pasados no cambiaron.** Es lo esperado: los compromisos de meses anteriores ya están calculados con la promesa de ese momento. Si necesitas empezar de nuevo desde un mes, **Reiniciar Compromisos** (al editar a la persona) borra el historial anterior a esa fecha; úsalo solo si estás seguro.

**¿Por qué el diezmo no tiene promesa?** El diezmo se registra en los sobres pero no se configura como promesa: no tiene una cantidad que prometer.
