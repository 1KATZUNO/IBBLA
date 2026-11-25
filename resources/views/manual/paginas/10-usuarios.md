---
titulo: Usuarios y roles
orden: 100
icono: usuario
resumen: Crear los usuarios del sistema, elegir su rol y entender qué puede hacer cada rol.
roles: admin
rutas: usuarios.*
palabras: usuarios, roles, permisos, administrador, tesorero, asistente, servidor, miembro, contraseña, correo, crear usuario, eliminar usuario, acceso, iniciar sesión
---

## Para qué sirve

Un **usuario** es una persona que entra al sistema con su correo y contraseña. Lo que cada usuario puede ver y hacer depende de su **rol**. Aquí se crean, se editan y se eliminan.

## Quién puede usarlo

Solo el **Administrador**.

## Cómo llegar

Menú lateral → **Administración** → **Gestionar Usuarios**.

## La lista de usuarios

![Lista de usuarios](img:usuarios-lista.jpg "Nuevo usuario (1), el rol de cada uno (2) y las acciones Editar (3) y Eliminar (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. + Nuevo Usuario** | Abre el formulario de un usuario nuevo. | Administrador | Al guardar vuelves a la lista con «Usuario creado exitosamente». |
| **2. Rol** | Muestra el rol de cada usuario. | Administrador | — |
| **3. Editar** | Cambia el nombre, correo, rol, teléfono o contraseña. | Administrador | Al guardar vuelves a la lista con «Usuario actualizado exitosamente». |
| **4. Eliminar** | Elimina el usuario. | Administrador | Pide confirmación. **No puedes eliminar tu propio usuario.** |

## Paso a paso: crear un usuario

1. Toca **+ Nuevo Usuario**.
2. Escribe el **Nombre** y el **Email** (no puede repetirse con otro usuario).
3. Elige el **Rol**.
4. **Persona vinculada** (para el rol Miembro, Servidor y quien deba ver **Mi perfil**): busca a la persona escribiendo su nombre, PIN o teléfono y elígela. Solo salen personas de tu iglesia que **todavía no tienen otro usuario**. **Quitar vínculo** la suelta. Cambiar o quitar el vínculo queda en la auditoría.
5. Opcional: escribe el **Teléfono (WhatsApp)** de 8 dígitos; el prefijo +506 se agrega solo. Los administradores y tesoreros que tengan teléfono reciben los **avisos de WhatsApp** (culto creado, sobres alcanzados).
6. Escribe la **Contraseña** (mínimo 8 caracteres) y repítela en **Confirmar Contraseña**.
7. Toca **Guardar**.

![Formulario de un usuario](img:usuarios-formulario.jpg "Nombre y correo (1), rol (2), teléfono (3), contraseña (4), Guardar (5) y persona vinculada (6)")

Al **editar** un usuario, la contraseña es opcional: déjala vacía para conservar la actual.

> **Consejo:** para que un hermano vea sus propias promesas, lo más simple es agregar un **correo y una contraseña** en su ficha de *Personas*: queda con acceso como **Miembro**, ya ligado a su persona. Si lo creas aquí, elige su **Persona vinculada**; sin ese vínculo su **Mi perfil** saldría vacío («No se encontró información de persona asociada»).

> **Importante:** al sistema solo entran las personas que el administrador agregó, con su correo y contraseña. No hay acceso libre ni de invitados.

## Qué puede hacer cada rol

| Rol | Qué puede abrir | Qué puede hacer |
|---|---|---|
| **Administrador** | Todo el menú | Todo: registrar, administrar, reportes, usuarios y auditoría. |
| **Tesorero** | Recuento, Dashboards, Reportes (ingresos y promesas), Avisos de WhatsApp, Yo* | Registrar sobres, dinero suelto y egresos; cerrar cultos y firmar el recuento; ver y descargar reportes de dinero. **No abre el módulo Personas**, pero puede dar de alta a una persona al registrar un sobre. |
| **Asistente** | Asistencia, Reporte de asistencia, Asistencia por clase, Yo* | Registrar y cerrar la asistencia de cada culto y ver su reporte, también por clase. No ve dashboards. |
| **Servidor** | Marcar Asistencia, Yo* | Para quienes sirven en un ministerio: marcar su asistencia al culto con el QR. |
| **Miembro** | Yo (Mi perfil) | Ver sus promesas, compromisos y estado de cuenta. |

\* **Yo** solo muestra información si el usuario está ligado a una persona de la lista. Si no lo está, la pantalla responde «No se encontró información de persona asociada».

Todos los roles pueden abrir **Principal**, **Cumpleañeros** y **Mi cuenta**. Si un usuario no ve una pantalla, revisa su rol aquí.

## Preguntas frecuentes

**Un usuario dice que no puede entrar a una pantalla.** Su rol no la incluye. Cambia el rol en **Editar** si le corresponde.

**Olvidaron su contraseña.** Edita al usuario y escribe una contraseña nueva.

**¿Cuál es la diferencia entre un usuario y una persona?** La **persona** es el hermano en la lista de la iglesia (con sus promesas y sobres). El **usuario** es quien entra al sistema. Una persona puede tener usuario (como Miembro) y un usuario puede no ser una persona de la lista.
