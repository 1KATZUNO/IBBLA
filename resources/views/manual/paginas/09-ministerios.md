---
titulo: Ministerios y servidores
orden: 90
icono: estrella
resumen: Administrar los ministerios (áreas de servicio), generar el QR de cada culto y ver el reporte de los servidores.
roles: admin
rutas: admin.ministerios.*, admin.servidores.*
palabras: ministerios, áreas de servicio, alabanza, transmisión, ujieres, servidores, qr, código qr, reporte de servidores, activar, desactivar, líder
---

## Para qué sirve

Un **ministerio** es un área de servicio de la iglesia (Alabanza, Transmisión, Ujieres…). Las personas se asignan a uno o varios ministerios desde su formulario. Los **servidores** son los usuarios con rol *Servidor*, que marcan su asistencia a los cultos con un código QR.

## Quién puede usarlo

Solo el **Administrador**.

## Ministerios

Menú lateral → **Administración** → **Ministerios**. Arriba dice cuántos ministerios hay y cuántos están activos.

![Lista de ministerios](img:ministerios-lista.jpg "Nuevo ministerio (1) y las acciones de cada renglón: Editar (2), Desactivar (3) y Eliminar (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. + Nuevo ministerio** | Abre el formulario de un ministerio nuevo. | Administrador | Al guardar vuelves a la lista con el mensaje «Ministerio … creado». |
| **2. Editar** | Cambia el nombre, color, orden, descripción o estado. | Administrador | Al guardar vuelves a la lista. |
| **3. Desactivar / Activar** | Apaga o enciende el ministerio. | Administrador | Un ministerio **inactivo** no se ofrece al asignar personas, pero conserva a las que ya tiene. |
| **4. Eliminar** | Elimina el ministerio. | Administrador | Pide confirmación. **No se puede eliminar si tiene personas asignadas**: en ese caso desactívalo o quita las asignaciones en *Personas*. |

### Paso a paso: crear un ministerio

1. Toca **+ Nuevo ministerio**.
2. Escribe el **Nombre** (obligatorio) y elige el **Color** con el que se verá.
3. Si quieres, escribe la **Descripción** y el **Orden** (posición en la lista; vacío = al final).
4. Deja marcado **Activo** para poder asignar personas.
5. Toca **Guardar**.

![Formulario de un ministerio](img:ministerios-formulario.jpg "Nombre (1), color (2), orden (3), descripción (4), activo (5) y Guardar (6)")

El **identificador (slug)** es un nombre interno que se genera solo a partir del nombre; normalmente no hace falta escribirlo.

## Servidores

Menú lateral → **Administración** → **Servidores**. Tiene dos tarjetas:

![Servidores: reporte y QR](img:ministerios-servidores.jpg "Reporte de servidores (1) y generar el QR del culto (2); abajo, quiénes marcaron en el culto elegido")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Reporte de Servidores** | Abre el reporte de compromisos y asistencia mensual de los servidores. | Administrador | Eliges **Mes**, **Año** y, si quieres, un **Ministerio**; puedes bajar el PDF general (una página) o el PDF de cada servidor. |
| **2. Generar QR** | Abre una ventana para elegir el culto; con **Mostrar QR** se muestra su código. | Administrador | Proyecta o imprime el QR en el culto: los servidores lo escanean desde *Marcar Asistencia*. |
| **Culto y Ver Asistencia** | Muestra, de un culto, qué servidores marcaron asistencia y a qué hora. | Administrador | La tabla lista **Servidor**, **Email**, **Asistencia** y **Hora de Marca**. |

## Preguntas frecuentes

**No puedo eliminar un ministerio.** Tiene personas asignadas. Desactívalo, o quita primero las asignaciones desde el formulario de cada persona.

**Asigné a alguien a un ministerio y no aparece.** Revisa que el ministerio esté **activo**.

**Un servidor dice que el QR no funciona.** Verifica que sea el QR del culto de hoy y que el servidor tenga el rol **Servidor** y permiso de cámara en el navegador.
