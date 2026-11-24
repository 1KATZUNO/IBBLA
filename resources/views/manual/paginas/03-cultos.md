---
titulo: Cultos
orden: 30
icono: calendario
resumen: Crear, editar y eliminar los cultos. Cada culto debe existir antes de registrar sus sobres y su asistencia.
roles: admin
rutas: cultos.*
palabras: culto, servicio, domingo, miércoles, sábado, especial, crear culto, fecha, hora, tipo de culto, eliminar culto
---

## Para qué sirve

Un **culto** es cada reunión de la iglesia (domingo, miércoles, sábado o especial). El culto es el “contenedor” de todo lo demás: sus **sobres** se registran en *Recuento* y su **asistencia** en *Asistencia*. Por eso el culto se crea primero.

## Quién puede usarlo

Solo el **Administrador**. El Tesorero y el Asistente trabajan sobre los cultos que el administrador ya creó.

## Cómo llegar

Menú lateral → **Administración** → **Gestionar Cultos**, o la tarjeta **Cultos** de Principal.

## La lista de cultos

![Lista de cultos](img:cultos-lista.jpg "Crear un culto (1) y las acciones de cada renglón: Ver (2), Editar (3) y Eliminar (4)")

La lista muestra los cultos del más reciente al más antiguo, de 20 en 20: fecha, tipo (con la etiqueta **Cerrado** si el culto ya se cerró en Recuento), **Total ingresos** (en colones) y, en pantallas anchas, la hora y la asistencia.

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. + Nuevo Culto** | Abre el formulario para crear un culto. | Administrador | Al guardar vuelves a la lista con el mensaje «Culto registrado correctamente». |
| **2. Ver** | Abre el recuento de ese culto con sus sobres y totales. | Administrador | Ves la misma pantalla de *Recuento y sobres* con ese culto seleccionado. |
| **3. Editar** | Cambia la fecha, la hora, el tipo o las notas. | Administrador | Al guardar vuelves a la lista con «Culto actualizado correctamente». |
| **4. Eliminar culto** | Elimina el culto. | Administrador | Pide confirmación. Si el culto ya tiene sobres o está cerrado, además exige escribir un **Motivo (obligatorio)**, marcar que entiendes lo que se borra y queda en la auditoría. **Se borran también sus sobres, su dinero suelto, sus egresos, su asistencia y sus totales.** |

> **Cuidado:** eliminar un culto no se puede deshacer y se lleva todo lo registrado en él. Si solo te equivocaste en la fecha, usa **Editar**.

## Paso a paso: crear un culto

1. Toca **+ Nuevo Culto**.
2. Revisa la **Fecha** (por omisión, hoy) y la **Hora** (10:00).
3. Elige el **Tipo de Culto**: Domingo AM, Domingo PM, Miércoles, Sábado o Especial. Los tipos de tu iglesia los configura el administrador de la plataforma.
4. Si quieres, anota algo en **Notas (Opcional)**, por ejemplo el nombre de una actividad.
5. Toca **Guardar**. El botón está siempre visible al final del formulario.

![Formulario de un culto nuevo](img:cultos-nuevo.jpg "Fecha (1), hora (2), tipo de culto (3), notas (4) y Guardar (5)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Fecha** | Día del culto. | Administrador | Con esta fecha aparece en la lista, en los reportes y en el buscador. |
| **2. Hora** | Hora del culto. | Administrador | Solo informativa. |
| **3. Tipo de Culto** | Clasifica el culto. | Administrador | Se usa en los reportes y en el buscador (por ejemplo, buscar «domingo»). |
| **4. Notas** | Texto libre. | Administrador | Se guarda con el culto. |
| **5. Guardar** | Crea el culto. | Administrador | El culto queda abierto y ya se puede usar en Recuento y Asistencia. |

## Preguntas frecuentes

**Creé el culto pero no lo veo en Recuento.** En Recuento solo aparecen los cultos **abiertos** (sin cerrar). Revisa que la fecha sea la correcta y que el culto no esté cerrado.

**Puse mal la fecha.** Toca **Editar** en el renglón del culto, corrige la fecha y guarda.

**¿Cómo se cierra un culto?** Se cierra desde **Recuento**, con el botón **Cerrar Culto** (ver *Recuento y sobres*).
