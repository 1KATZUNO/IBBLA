---
titulo: Auditoría e historial de ingresos
orden: 110
icono: lupa
resumen: Saber quién entró al sistema y cuándo, y revisar las acciones que hicieron los administradores y tesoreros.
roles: admin
rutas: admin.auditoria.*
palabras: auditoría, bitácora, historial de ingresos, quién entró, hora, inicio de sesión, cierre de sesión, intentos fallidos, ip, navegador, csv, acciones, registro de cambios
---

## Para qué sirve

Hay dos pantallas para dar **transparencia**:

- **Historial de ingresos:** quién entró al sistema, cuándo y a qué hora, desde qué equipo; también cuando alguien se equivocó de contraseña.
- **Auditoría:** el registro de las acciones que hacen los administradores y tesoreros (qué pantalla abrieron o qué guardaron).

## Quién puede usarlo

Solo el **Administrador**.

## Historial de ingresos

Menú lateral → **Administración** → **Historial de ingresos**.

![Historial de ingresos](img:auditoria-ingresos.jpg "Filtros (1), descargar el CSV (2) y la lista de ingresos (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Usuario, Evento, Desde, Hasta y Filtrar** | Limita la lista a un usuario (nombre o correo), a un tipo de **Evento** (**Inicios de sesión**, **Cierres de sesión** o **Intentos fallidos**) y a un rango de fechas. | Administrador | La lista muestra solo esos registros. **Quitar filtros** vuelve a todo. |
| **2. Descargar CSV** | Baja la lista (con los filtros aplicados) en un archivo para Excel. | Administrador | Se descarga el archivo. |
| **3. Lista** | **Fecha y hora**, **Usuario**, **Correo**, **Evento**, **IP** y **Navegador** de cada ingreso. | Administrador | Pasa el cursor sobre el navegador para ver el detalle completo. |

> **Consejo:** los **intentos fallidos** repetidos de un mismo correo pueden indicar que alguien intenta adivinar una contraseña. Revisa quién es y, si hace falta, cambia su contraseña en *Usuarios y roles*.

## Auditoría

Menú lateral → **Administración** → **Auditoría**.

![Auditoría](img:auditoria-lista.jpg "Filtros (1) y el registro de acciones (2)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Usuario, Acción, Método, Ruta, IP, Desde y Hasta** | Filtran el registro. | Administrador | La lista se limita a lo que buscas. |
| **2. Registro** | Cada renglón dice **Fecha**, **Usuario**, **Método**, **Ruta**, **Acción**, **IP**, **Agente** y **Detalles** de lo que hizo un administrador o tesorero. | Administrador | Sirve para saber quién registró o cambió algo. |

## Cuánto tiempo se guarda

El detalle de las acciones se conserva unos **30 días**; el historial de ingresos, **un año**. Lo más antiguo se limpia periódicamente (una vez al mes).

## Preguntas frecuentes

**No veo el ingreso de un usuario.** Revisa el rango de fechas y que escribas su nombre o correo tal como está en *Usuarios*. Los ingresos de más de un año ya no están.

**¿Se guarda la contraseña?** No. Ni el historial ni la auditoría guardan contraseñas.
