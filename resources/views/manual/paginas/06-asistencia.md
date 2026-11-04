---
titulo: Asistencia y clases
orden: 60
icono: grupo
resumen: Registrar cuántas personas asistieron a cada culto, administrar las clases, marcar la asistencia con QR y llevar la lista de cada clase.
roles: admin, asistente, servidor
rutas: asistencia.*, admin.clases.*, clase-app.*, marcar-asistencia.*
palabras: asistencia, capilla, clases, salvos, bautismos, visitas, maestros, escuela dominical, qr, marcar asistencia, cerrar asistencia, cargar histórico, importar excel, alumnos, visitación
---

## Para qué sirve

Esta página reúne todo lo que tiene que ver con **cuánta gente asistió**: el conteo de cada culto (capilla, clases, salvos, bautismos y visitas), las clases de la escuela dominical, la asistencia de los servidores con código QR y el registro digital de cada clase.

## Quién puede usarlo

| Pantalla | Quién |
|---|---|
| **Asistencia** (conteo de cada culto) | Administrador, Asistente |
| **Gestionar Clases** | Administrador |
| **App de Clases** | Administrador y los maestros asignados a una clase |
| **Marcar Asistencia** (QR) | Servidor |
| **Cargar Histórico** | Administrador |

## Asistencia de un culto

Menú lateral → **Registro** → **Asistencia**. La lista muestra los cultos con su asistencia; si un culto todavía no la tiene, aparece el botón **+ Registrar**.

![Lista de asistencia](img:asistencia-lista.jpg "Nueva asistencia (1), filtros (2) y las acciones de cada renglón (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. + Nueva Asistencia** | Abre el formulario para registrar la asistencia de un culto. | Administrador, Asistente | Al guardar vuelves a la lista con un mensaje. |
| **2. Mes, Año y Filtrar** | Limita la lista a un mes y año. **Limpiar** quita el filtro. | Administrador, Asistente | La lista muestra solo esos cultos. |
| **3. Editar / Cerrar / Eliminar** | Corrige, cierra o elimina la asistencia de un culto. | Administrador, Asistente | **Cerrar** pide confirmación y deja la asistencia en **Asistencias Cerradas (Archivo)**. |
| **+ Registrar** | Aparece en un culto sin asistencia y abre el formulario con ese culto elegido. | Administrador, Asistente | Llenas y guardas. |

### Paso a paso: registrar la asistencia de un culto

1. Toca **+ Nueva Asistencia** (o **+ Registrar** en el culto).
2. En **Seleccionar Culto**, elige el culto.
3. Abre cada sección tocando su título y llena las casillas: **Capilla** (adultos hombres y mujeres, jóvenes…), cada **clase** (hombres, mujeres y los **maestros que dieron clase**), **Salvos**, **Bautismos**, **Visitas** y, si tu iglesia los usa, otros registros.
4. Las casillas vacías cuentan como cero. El **Total Asistencia** se suma solo.
5. Toca **Guardar**. El total y el botón siempre están a la vista abajo, aunque las secciones estén abiertas.

![Formulario de asistencia](img:asistencia-formulario.jpg "Culto (1), secciones plegables (2), total (3) y Guardar (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Seleccionar Culto** | Elige a qué culto pertenece la asistencia. | Administrador, Asistente | Solo se ofrecen cultos sin asistencia. |
| **2. Secciones** | Capilla, clases, salvos, bautismos, visitas. | Administrador, Asistente | Tocar el título abre o cierra la sección. |
| **3. Total Asistencia** | Suma de capilla, clases y maestros. | Administrador, Asistente | No necesita editarse. |
| **4. Guardar** | Guarda la asistencia. | Administrador, Asistente | Vuelves a la lista con el mensaje de confirmación. |

> **Importante:** una asistencia **cerrada** ya no se puede editar, ni siquiera el Administrador: al guardar, el sistema responde que no se puede editar una asistencia cerrada y se pierde lo escrito. Cierra solo cuando el conteo esté completo.

## Gestionar Clases

Menú lateral → **Administración** → **Gestionar Clases**. Aquí se crean las clases de la escuela dominical (por ejemplo «Clase 2-6» o «Clase 12-15 Años»): nombre, color, orden de visualización y si **tiene maestros**.

![Lista de clases](img:asistencia-clases.jpg "Nueva clase (1) y las acciones de cada renglón (2)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. + Nueva Clase** | Abre el formulario de una clase nueva. | Administrador | La clase aparece en el formulario de asistencia y en *Personas*. |
| **2. Editar / Eliminar** | Cambia el nombre, color u orden; o elimina la clase. | Administrador | Eliminar pide confirmación. |

Para que una persona sea alumna o maestra de una clase, asígnala desde su formulario en **Gestionar Personas** (ver *Personas y compromisos*).

## App de Clases

Menú lateral → **App de Clases**. La maestra elige su clase y entra a su registro digital: **Inicio**, **Miembros**, **Visitas**, **Asistencia** (toca el círculo de cada persona para marcar que asistió), **Visitación** (las semanas en que visitó a cada miembro) y **Reportes**. Reemplaza el cuaderno de registro.

## Marcar Asistencia (servidores)

Los servidores marcan su propia asistencia al culto escaneando el **código QR** del culto.

1. Menú lateral → **Marcar Asistencia**.
2. Toca el botón grande **Marcar Asistencia** y acepta el permiso de la cámara.
3. Apunta al código QR del culto. Aparece «Asistencia Marcada» y queda guardada con la hora.

Más abajo ves **Mi Historial de Asistencia**. Si la cámara no se abre, el mensaje aparece bajo el botón; revisa el permiso de cámara del navegador. El administrador genera el QR de cada culto en **Servidores** (ver *Ministerios y servidores*).

## Cargar Histórico

Menú lateral → **Administración** → **Cargar Histórico** (solo Administrador). Sirve para cargar asistencias de meses anteriores desde un Excel: descargas la **plantilla**, llenas una fila por culto y subes el archivo. Las filas válidas se guardan y las que tengan error se listan para corregirlas. Lo que se carga por aquí queda marcado como *retroactivo* en la auditoría.

## Preguntas frecuentes

**No puedo editar una asistencia.** Probablemente está cerrada. Pídele al administrador que la corrija.

**El culto no aparece al registrar la asistencia.** Solo aparecen cultos sin asistencia registrada. Si el culto no existe, el administrador debe crearlo en *Cultos*.

**La lista de cultos es muy larga.** Va de la fecha más lejana a la más antigua y no marca el culto de hoy: busca la fecha que necesitas, o escríbela en el buscador de la barra superior para llegar directo.

**La maestra no ve su clase en la App de Clases.** El administrador debe asignarla como **Maestro(a)** de esa clase en su formulario de persona.
