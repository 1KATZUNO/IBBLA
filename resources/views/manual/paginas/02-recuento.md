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
3. En **Persona**, escribe el nombre o el PIN y elígela de la lista. Si el sobre no trae nombre, déjalo en **Anónimo**. Si la persona no existe, toca **+ Agregar Persona Rápida**, escribe su nombre completo y se crea como persona activa sin salir de la pantalla.
4. Elige el **Método de pago**: **Efectivo** o **Transferencia**. Con transferencia aparece el campo **N° Comprobante**.
5. Si el sobre viene en dólares, cambia la **Moneda** a **$ Dólares**: el sistema usa el tipo de cambio del día.
6. Escribe el monto de cada categoría (diezmo, misiones, construcción…). Las que no lleven dinero se quedan en cero. El **Total** abajo se va sumando.
7. Si hace falta, escribe una nota.
8. Toca **Guardar Sobre**. El botón **Guardar** y el total siempre están a la vista, aunque la pantalla sea larga.

![Formulario de un sobre nuevo](img:recuento-nuevo-sobre.jpg "Persona (1), método de pago (2), montos por categoría (3) y la barra con el total y Guardar Sobre (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Persona** | Busca por nombre o PIN. Vacía = sobre anónimo. | Administrador, Tesorero | El sobre queda ligado a la persona y cuenta para su estado de cuenta. |
| **2. Método de pago** | Efectivo o transferencia. | Administrador, Tesorero | Con transferencia puedes anotar el comprobante. |
| **3. Detalles del sobre** | Un monto por categoría. | Administrador, Tesorero | El total del sobre se calcula solo. |
| **4. Guardar Sobre** | Guarda el sobre. | Administrador, Tesorero | Vuelves al recuento del culto con el mensaje «Sobre registrado correctamente». |

> **Cuidado:** si registras dos sobres de la misma persona en el mismo culto con menos de unos 30 segundos de diferencia, el sistema no guarda el segundo y te avisa: «Ya se registró un sobre para esta persona en este culto hace unos segundos». Lo mismo pasa con dos sobres **anónimos** seguidos. Revisa que no sea un duplicado; si de verdad son dos sobres, espera unos segundos y vuelve a guardarlo (el formulario no conserva lo que escribiste).

## Paso a paso: cerrar un culto

1. Revisa los totales y la lista de sobres.
2. Opcional: llena y guarda las **Firmas de Recuento** (pastor y tesoreros) más abajo en la pantalla, con **Guardar Firmas**. Las firmas **se pueden corregir después de firmadas**: vuelve a abrir el culto, cambia la firma y toca otra vez **Guardar Firmas**.
3. Toca **Cerrar Culto** y confirma con **Sí, Cerrar**.

Al cerrar:

- **No se puede** agregar sobres, dinero suelto ni egresos, ni editar o eliminar los que ya están. El **Administrador** sí puede corregir un sobre de un culto cerrado.
- Se guarda el **tipo de cambio** del día para los sobres en dólares.
- Las **firmas** siguen pudiéndose corregir.
- El culto sale de la lista de abiertos y pasa a **Cultos Cerrados (Archivo)**, al final de la pantalla, donde puedes verlo (**Ver**) y bajar su PDF.

> **Cuidado:** en cada tarjeta del archivo, el icono del **basurero** elimina el culto **con todos sus sobres**. Solo el Administrador puede hacerlo; al Tesorero el sistema le responde que no tiene permiso.

> **Importante:** cerrar es el paso que deja listos los totales para los reportes. Cierra el culto cuando el recuento esté completo y firmado.

## Preguntas frecuentes

**Me equivoqué en un sobre.** Toca **Editar** en su renglón, corrige y guarda. Si el culto ya está cerrado, solo el Administrador puede corregirlo.

**El culto de hoy no es el primero de la lista.** La lista va de la fecha más lejana a la más antigua, así que el culto de hoy puede quedar más abajo. Escribe su fecha en el buscador de la barra superior para llegar directo.

**El culto que busco no está en la lista.** La lista solo trae cultos abiertos. Si ya lo cerraron, está en **Cultos Cerrados (Archivo)**. Si todavía no existe, pídele al administrador que lo cree en **Gestionar Cultos**.

**¿Puedo eliminar un sobre?** Sí, mientras el culto esté abierto, con el icono del basurero y confirmando. En un culto cerrado no se puede eliminar.

**El sobre es de una persona que no está en la lista.** Usa **+ Agregar Persona Rápida** en el formulario del sobre para crearla con su nombre; después el administrador puede completar sus datos en **Personas**.
