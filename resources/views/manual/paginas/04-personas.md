---
titulo: Personas y compromisos
orden: 40
icono: personas
resumen: Buscar, agregar y editar a los hermanos; ver su ficha, sus compromisos y su estado de cuenta en PDF.
roles: admin
rutas: personas.*, compromisos.*, personas.mantenimiento#mantenimiento-borrados-masivos
palabras: personas, miembros, hermanos, pin, teléfono, correo, fecha de nacimiento, ficha, compromisos, estado de cuenta, activo, inactivo, eliminar persona, clases, ministerios, acceso, contraseña
---

## Para qué sirve

**Gestión de Personas** es la lista de todos los hermanos de la iglesia. Desde aquí se agregan y editan sus datos, se les asignan clases y ministerios, se configuran sus **promesas** y se consulta su **ficha**, sus **compromisos** y su **estado de cuenta**.

## Quién puede usarlo

Solo el **Administrador** abre la lista y las fichas de personas. El Tesorero puede *registrar* sobres a nombre de una persona (en *Recuento y sobres*) y **agregar a alguien que todavía no está en la lista** con el alta rápida (ver *Agregar una persona rápido*), pero no abre la lista de personas.

## Cómo llegar

Menú lateral → **Administración** → **Gestionar Personas**, la tarjeta **Personas** de Principal, o buscando el nombre, el PIN o el teléfono en el buscador de la barra superior.

## Agregar una persona rápido (alta rápida)

Para alguien que todavía no está en la lista, por ejemplo una visita que trajo su sobre. La pueden usar el **Administrador** y el **Tesorero** (el Asistente no).

- **Desde el menú:** **Registro** → **Agregar persona** (para el Tesorero; el Administrador usa **Gestionar Personas** → **+ Nueva Persona**).
- **Desde un sobre:** en el formulario del sobre, **+ Nueva persona** (ver *Recuento y sobres*), sin salir de la pantalla.

Solo piden el **Nombre completo**; el **Teléfono** y el **Correo** son opcionales. Toca **Agregar persona**. La persona queda **activa y sin usuario**, lista para elegirla en los sobres. El administrador completa después sus datos, promesas, clases y ministerios en **Gestionar Personas**.

## La lista de personas

![Lista de personas con sus acciones](img:personas-lista.jpg "Buscar (1), + Nueva Persona (2), reportes (3 y 4) y las acciones de cada renglón (5 a 10)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Buscar por nombre, PIN o teléfono** | Filtra la lista. | Administrador | Muestra solo las coincidencias; aparece **Limpiar** para volver a ver todas. |
| **2. + Nueva Persona** | Abre el formulario de una persona nueva. | Administrador | Al guardar vuelves a la lista con un mensaje. |
| **3. Reporte PDF** | Abre el **Reporte de Contribuciones** (de enero al mes actual, de todas las personas). | Administrador | Eliges **Ver PDF** o **Descargar**. |
| **4. Reporte General** | Genera el reporte «dado contra esperado» de todos los activos. | Administrador | Se abre el PDF en otra pestaña. |
| **5. Ver (ojo)** | Abre la ficha de la persona. | Administrador | Ves sus datos, promesas y sobres. |
| **6. Compromisos (portapapeles)** | Abre los compromisos de la persona. | Administrador | Ves, mes por mes, lo esperado y lo dado. |
| **7. WhatsApp** | Abre WhatsApp con el estado de cuenta de la persona. | Administrador | Ver la página *WhatsApp*. Aparece apagado si la persona no tiene teléfono válido. |
| **8. Estado de cuenta (documento)** | Abre el PDF con el estado de cuenta de la persona. | Administrador | Se abre en otra pestaña. |
| **9. Editar** | Abre el formulario de edición. | Administrador | Al guardar vuelves a la lista. |
| **10. Eliminar (basurero)** | Elimina a la persona. | Administrador | Pide confirmación. Se borran sus promesas, sus compromisos y su usuario (si tenía); **sus sobres se conservan** como sobres sin nombre. |

### Mantenimiento: borrados masivos

Al final de la lista hay un bloque plegado, **Mantenimiento**, con dos acciones que afectan a todas las personas a la vez. Ninguna borra al tocarla: cada una abre **su propia pantalla de confirmación**.

![Pantalla de confirmación de Eliminar personas inactivas](img:personas-mantenimiento.jpg "Escribe CONFIRMAR (1) para habilitar el borrado")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **Eliminar personas inactivas** | Abre la pantalla que dice cuántas personas inactivas se borran (puedes ver sus nombres con **Ver las N personas**), con sus promesas, compromisos y usuarios, y **lo que NO se borra** (los sobres quedan, sin persona). | Administrador | Para continuar escribes **CONFIRMAR** y tocas **Eliminar personas inactivas**; con **Cancelar** o **Volver a Personas** no se borra nada. |
| **Borrar todas las promesas** | Abre la pantalla que dice cuántas promesas y compromisos mensuales se borran de cuántas personas. Las personas y los sobres se conservan. | Administrador | Igual: escribes **CONFIRMAR** y confirmas. |

Sin la palabra **CONFIRMAR** no se borra nada. Todo se hace de una vez (si algo falla no queda a medias) y en la **Auditoría** queda quién lo hizo, cuándo y el detalle de lo que se borró. Si no hay nada que borrar, la pantalla lo dice y te devuelve a Personas. **No se puede deshacer**: úsalas solo si sabes exactamente por qué.

## La ficha de la persona

Al tocar el ojo o el nombre ves la **ficha**: datos de contacto, sus promesas, y la lista de sus sobres con un filtro por fechas. Arriba tienes los botones **Estado de cuenta (ver)**, **Descargar**, **Compromisos**, **Volver** y **Editar**.

![Ficha de una persona](img:personas-ficha.jpg "Estado de cuenta (1), descargar (2), compromisos (3) y editar (4)")

## Paso a paso: agregar o editar una persona

El formulario tiene tres secciones y el botón **Guardar** siempre a la vista.

![Formulario de una persona](img:personas-formulario.jpg "Datos personales (1), clases y ministerios (2), promesas (3) y Guardar (4)")

1. **Datos personales.** El **Nombre completo** es obligatorio. El **PIN** es un identificador único para encontrar a la persona rápido en el recuento (puede quedar vacío). Marca **Activo** si la persona sigue asistiendo: las personas inactivas no aparecen en *Cumpleañeros* ni en el *Reporte General*.
2. **Acceso como miembro.** Si escribes un **Correo**, aparece la casilla **Contraseña** (mínimo 8 caracteres): con ese correo la persona podrá entrar al sistema como **Miembro** y ver su progreso en *Mi perfil*. El usuario queda ya ligado a esta persona y con el rol Miembro, igual que si lo crearas en *Usuarios y roles*.
3. **Clases y ministerios.** Con **Agregar clase** asignas hasta cuatro clases; marca **Maestro(a)** si da clase. Si no se asigna clase, la persona pertenece a **Capilla**. Con **Agregar ministerio** la asignas a uno o varios ministerios (marca **Líder** si lo dirige).
4. **Promesas de ofrenda.** Una fila por categoría: monto, moneda y frecuencia. Ver *Promesas*.
5. Toca **Guardar**. Al guardar solo cambia lo que tocaste: las promesas que no modificaste siguen con su historial.

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Datos personales** | Nombre, teléfono, correo, nacimiento, PIN, estado y notas. | Administrador | El teléfono se usa para **WhatsApp**; la fecha de nacimiento para **Cumpleañeros**. |
| **2. Clases y ministerios** | Asigna clases (hasta 4) y ministerios. | Administrador | La persona aparece en las listas de su clase y de su ministerio. |
| **3. Promesas de ofrenda** | Configura lo que la persona se compromete a dar. | Administrador | Alimenta los compromisos y los reportes de promesas. |
| **4. Guardar** | Guarda todos los cambios. | Administrador | Vuelves a la lista con el mensaje de confirmación. |

Al editar una persona aparecen, al final, dos acciones aparte para casos especiales. **No se pueden deshacer** y cada una pide confirmación:

- **Reiniciar Compromisos:** borra el historial de compromisos *anterior* al mes que elijas y empieza a calcular desde ese mes.
- **Limpiar Todo:** elimina todas sus promesas, su historial de compromisos y sus deudas; la persona queda como si nunca hubiera tenido promesas.

## Compromisos de una persona

**Compromisos** muestra, mes por mes, lo que la persona **debía dar** según sus promesas y lo que **realmente dio**.

![Compromisos de una persona](img:personas-compromisos.jpg "Año y mes (1), el resumen del mes (2) y el detalle por categoría (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Año y Mes** | Cambia el mes que se muestra. | Administrador | Los totales y el detalle se actualizan. |
| **2. Resumen del mes** | **Total Esperado**, **Total Dado** y **Diferencia del Mes**. | Administrador | Verde = dio de más; rojo = faltó. |
| **3. Detalle por categoría** | Esperado, dado y diferencia por categoría, con su estado. | Administrador | El **Historial por Mes** abajo muestra los meses anteriores. |

> **Consejo:** cada mes es independiente: la **Diferencia** es *Dado − Esperado* de ese mes, no un saldo acumulado.

## Preguntas frecuentes

**¿Qué pasa si elimino a una persona?** Se borran sus promesas, sus compromisos y su usuario (si lo tenía). Sus sobres se quedan registrados, pero sin nombre. Si solo dejó de asistir, es mejor marcarla como **inactiva** en lugar de eliminarla.

**El botón de WhatsApp está gris.** La persona no tiene un teléfono válido. Edítala y escribe un número de 8 dígitos.

**Cambié una promesa pero no se ve en Compromisos.** Los compromisos se calculan a partir de las promesas y los sobres; abre **Compromisos** otra vez. Evita **Reiniciar Compromisos** salvo que quieras borrar el historial anterior a un mes.
