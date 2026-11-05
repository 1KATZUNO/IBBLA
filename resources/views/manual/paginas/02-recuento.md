---
titulo: Recuento y sobres
orden: 20
icono: documento
resumen: Registrar los sobres, el dinero suelto y los egresos de cada culto, sacar sus PDF y cerrar el culto.
roles: admin, tesorero
rutas: recuento.*
palabras: sobres, ofrendas, diezmos, misiones, transferencia, efectivo, dinero suelto, egresos, cerrar culto, firmas, comprobante, dólares, tipo de cambio, contar, tesorería
---

## Para qué sirve

**Recuento** es donde se cuenta el dinero de cada culto: cada sobre se registra con la persona que lo entregó (o como anónimo), cómo pagó y cuánto dio por categoría. Al terminar se cierra el culto: el recuento queda protegido y los totales listos para los reportes.

## Quién puede usarlo

El **Tesorero** y el **Administrador**. El Asistente y los demás roles no ven esta pantalla.

El Tesorero trabaja aquí sin abrir el módulo *Personas*: si el sobre es de alguien que todavía no está en la lista, lo da de alta en el momento con **+ Agregar Persona Rápida**.

## Cómo llegar

Menú lateral → **Registro** → **Recuento**. También desde **Principal** con la tarjeta **Registrar sobres**, o buscando el culto por su fecha en el buscador.

## La pantalla de un culto

Primero elige el culto en **Seleccionar Culto**: la lista trae los cultos que todavía están abiertos. Al elegirlo aparecen sus botones y, abajo, los sobres ya registrados con sus totales.

![El recuento de un culto abierto](img:recuento-culto.jpg "Elegir el culto (1) y registrar sobres, dinero suelto o egresos (2 a 4); los PDF (5 y 6) y cerrar el culto (7)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Seleccionar Culto** | Cambia de culto. | Administrador, Tesorero | La pantalla se recarga con los sobres de ese culto. |
| **2. + Agregar Sobre** | Abre el formulario de un sobre nuevo. | Administrador, Tesorero | Al guardar vuelves a esta pantalla con el culto seleccionado y un mensaje de confirmación. |
| **3. + Dinero Suelto** | Registra dinero sin sobre (por ejemplo, ofrenda de la bandeja) con su monto y descripción. | Administrador, Tesorero | Aparece en el resumen del culto. |
| **4. - Egresos** | Registra una salida de dinero del culto (por ejemplo, el pago de un servicio). | Administrador, Tesorero | Se resta en los totales. |
| **5. Descargar PDF** | Genera el PDF del recuento de este culto. | Administrador, Tesorero | Se abre o se descarga el PDF con todos los sobres y los totales. |
| **6. PDF Transferencias** | Genera el PDF solo con los sobres pagados por transferencia. | Administrador, Tesorero | Útil para conciliar con el banco. |
| **7. Cerrar Culto** | Cierra el culto. | Administrador, Tesorero | Pide confirmación. Al cerrar ya no se agregan ni se editan sobres, y queda guardado el tipo de cambio del día. |

Cada sobre de la lista tiene sus propias acciones: **Editar** y **Eliminar** (el icono del basurero). Eliminar siempre pide confirmación.

## Paso a paso: registrar un sobre

1. Elige el culto en **Seleccionar Culto**.
2. Toca **+ Agregar Sobre**.
3. En **Persona**, escribe el nombre o el PIN y elígela de la lista: su nombre aparece **en grande junto al botón Guardar**, para que confirmes que es la persona correcta. Si el sobre no trae nombre, marca la casilla **Sobre anónimo** (o confirma que es anónimo cuando el sistema te lo pregunte): ya **no existe** el truco de dejar el campo vacío. Si escribes un nombre que no existe, el sistema ofrece **crearla** con el alta rápida sin salir de la pantalla.
4. Elige el **Método de pago**: **Efectivo** o **Transferencia**. Con transferencia aparece el campo **N° Comprobante**.
5. Si el sobre viene en dólares, cambia la **Moneda** a **$ Dólares**: el sistema usa el tipo de cambio del día.
6. Escribe el monto de cada categoría (diezmo, misiones, construcción…). Las que no lleven dinero se quedan en cero. El **Total** abajo se va sumando.
7. Si hace falta, escribe una nota.
8. Toca **Guardar Sobre**. El botón **Guardar** y el total siempre están a la vista, aunque la pantalla sea larga. Con **Guardar y siguiente** el sobre se guarda y se abre uno nuevo **en el mismo culto**: no hace falta volver a elegirlo.

![Formulario de un sobre nuevo](img:recuento-nuevo-sobre.jpg "Persona (1), método de pago (2), montos por categoría (3) y la barra con el total y Guardar Sobre (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Persona** | Busca por nombre o PIN. Para un sobre sin nombre marca **Sobre anónimo**; si el nombre no existe, ofrece crearla con el alta rápida. | Administrador, Tesorero | El sobre queda ligado a la persona (y cuenta para su estado de cuenta) o queda como anónimo. |
| **2. Método de pago** | Efectivo o transferencia. | Administrador, Tesorero | Con transferencia puedes anotar el comprobante. |
| **3. Detalles del sobre** | Un monto por categoría. | Administrador, Tesorero | El total del sobre se calcula solo. |
| **4. Guardar Sobre** | Guarda el sobre. | Administrador, Tesorero | Vuelves al recuento del culto con el mensaje «Sobre registrado correctamente». |

> **Cuidado:** si registras dos sobres de la misma persona en el mismo culto con menos de unos 30 segundos de diferencia, el sistema no guarda el segundo y te avisa: «Ya se registró un sobre para esta persona en este culto hace unos segundos». Revisa que no sea un duplicado; si de verdad son dos sobres, espera unos segundos y vuelve a guardarlo.
>
> Los sobres **anónimos** no se rechazan como duplicados: puedes registrar varios seguidos.

## Paso a paso: cerrar un culto

1. Revisa los totales y la lista de sobres.
2. Opcional: llena y guarda las **Firmas de Recuento** (pastor y tesoreros) más abajo en la pantalla, con **Guardar Firmas**. Las firmas **se pueden corregir después de firmadas**: vuelve a abrir el culto, cambia la firma y toca otra vez **Guardar Firmas**.
3. Toca **Cerrar Culto** y confirma con **Sí, Cerrar**.

Al cerrar:

- **No se puede** agregar sobres, dinero suelto ni egresos, ni editar o eliminar los que ya están. El **Administrador**, o quien tenga un **acceso de edición**, sí puede corregirlo.
- Se guarda el **tipo de cambio** del día para los sobres en dólares.
- Las **firmas** siguen pudiéndose corregir.
- El culto sale de la lista de cultos abiertos y pasa a la **lista por líneas** de cultos. Ábrelo desde ahí: ya no hay una sección «Cultos Cerrados (Archivo)».

> **Cuidado:** eliminar un culto cerrado borra el culto **con todos sus sobres**. Solo el Administrador puede hacerlo, escribiendo un motivo, y queda en la auditoría.

> **Importante:** cerrar es el paso que deja listos los totales para los reportes. Cierra el culto cuando el recuento esté completo y firmado.

## Un culto cerrado

Un culto cerrado se abre desde la **lista por líneas** de cultos. El **Tesorero** lo ve todo en **solo lectura**, con el aviso «Cerrado el dd/mm por X» (fecha y quién lo cerró). El **Administrador**, o quien tenga un **acceso de edición** sobre ese culto (módulo *Accesos*, ver su página), puede **corregir** sobres, dinero suelto y egresos. Las **firmas** se pueden corregir aun después de firmadas y el cambio queda auditado.

## Sobres anónimos

En la lista de sobres del culto, el filtro **Solo anónimos** muestra únicamente los sobres sin persona. Cuando después sepas de quién era uno, usa la acción **Asignar persona** en su renglón: el sobre pasa a contar en el estado de cuenta de esa persona.
## Preguntas frecuentes

**Me equivoqué en un sobre.** Toca **Editar** en su renglón, corrige y guarda. Si el culto ya está cerrado, solo el Administrador (o quien tenga un acceso de edición) puede corregirlo.

**El culto de hoy no es el primero de la lista.** La lista va de la fecha más lejana a la más antigua, así que el culto de hoy puede quedar más abajo. Escribe su fecha en el buscador de la barra superior para llegar directo.

**El culto que busco no está en la lista.** Si ya lo cerraron, ábrelo desde la lista por líneas de cultos. Si todavía no existe, pídele al administrador que lo cree en **Gestionar Cultos**.

**¿Puedo eliminar un sobre?** Sí, mientras el culto esté abierto, con el icono del basurero y confirmando. En un culto cerrado no se puede eliminar.

**¿Puedo eliminar un culto ya cerrado?** Solo el **Administrador**, desde la lista de cultos, con **Eliminar culto**. El sistema pide confirmar y escribir un **motivo** (obligatorio), y queda en la auditoría qué culto era, su fecha, su total y cuántos sobres tenía. El Tesorero no ve ese botón.

**El sobre es de una persona que no está en la lista.** Usa **+ Agregar Persona Rápida** en el formulario del sobre para crearla con su nombre; después el administrador puede completar sus datos en **Personas**.
