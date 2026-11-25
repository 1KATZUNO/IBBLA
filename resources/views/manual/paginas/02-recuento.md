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

El Tesorero trabaja aquí sin abrir el módulo *Personas*: si el sobre es de alguien que todavía no está en la lista, lo da de alta en el momento con **+ Nueva persona**.

## Cómo llegar

Menú lateral → **Registro** → **Recuento**. También desde **Principal** con la tarjeta **Registrar sobres**, o escribiendo la fecha del culto en el buscador de la barra superior.

## La lista de cultos

Recuento abre con **una línea por culto**. Toca cualquier línea para entrar a ese culto.

![La lista de cultos de Recuento](img:recuento-lista.jpg "Buscar por fecha, mes o tipo (1), Todos / Abiertos / Cerrados (2) y una línea por culto (3)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Buscar por fecha, Mes y Tipo de culto** | Filtran la lista. **Buscar** aplica; **Limpiar** quita los filtros. | Administrador, Tesorero | La lista muestra solo los cultos que coinciden. |
| **2. Todos · Abiertos · Cerrados** | Muestra todos los cultos, solo los que siguen abiertos o solo los ya cerrados, con cuántos hay de cada uno. | Administrador, Tesorero | La lista cambia; los filtros de arriba se conservan. |
| **3. Una línea por culto** | Fecha (el de hoy dice **Hoy**), día, tipo, cantidad de **Sobres**, **Total** y **Estado** (**Abierto**, o **Cerrado** con la fecha y quién lo cerró). | Administrador, Tesorero | Al tocar la línea se abre la pantalla de ese culto. |
| **+ Nuevo culto** | Abre el formulario para crear un culto. | Solo el Administrador (al Tesorero le aparece apagado) | Al guardar vuelves a Recuento. Si eres Tesorero y falta el culto, pídeselo al administrador. |

## La pantalla de un culto

Arriba dice **qué culto es**, si está **Abierto** o **Cerrado**, y un resumen en una línea: cuántos **Sobres**, el **Efectivo**, las **Transferencias** y el **Total**. Los botones de acción están a la derecha (en tablet, debajo del resumen).

![El recuento de un culto abierto](img:recuento-culto.jpg "Volver a la lista (1); agregar sobre (2), dinero suelto (3) y egreso (4); PDF (5) y cerrar el culto (6)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Todos los cultos** | Vuelve a la lista de cultos. | Administrador, Tesorero | Ves la lista de cultos. |
| **2. + Agregar sobre** | Abre el formulario de un sobre nuevo en este culto. | Administrador, Tesorero (en un culto cerrado, solo el Administrador o quien tenga un acceso de edición) | Ver *Paso a paso: registrar un sobre*. |
| **3. + Dinero suelto** | Registra dinero sin sobre (por ejemplo, la ofrenda de la bandeja) con su monto y descripción. | Administrador, Tesorero | Aparece en **Dinero suelto y egresos** y suma al **Efectivo**. |
| **4. − Egreso** | Registra una salida de dinero del culto (por ejemplo, el pago de un servicio). | Administrador, Tesorero | Aparece en **Dinero suelto y egresos** y se resta del **Efectivo**. |
| **5. PDF** | Menú con dos opciones: **Recuento completo** (todos los sobres, el resumen por categorías y las firmas) y **Solo transferencias** (para conciliar con el banco). | Administrador, Tesorero | Se abre el PDF en otra pestaña. |
| **6. Cerrar culto** | Cierra el culto. | Administrador, Tesorero | Pide confirmación (ver *Paso a paso: cerrar un culto*). Solo aparece mientras el culto está abierto. |

Más abajo, en este orden:

- **Sobres:** la lista de sobres del culto, uno por línea, con su número, la persona, el método de pago, el total y el detalle por categoría. Arriba tiene un buscador (**Buscar por número o persona**) y, si hay sobres sin nombre, el filtro **Solo anónimos** con su cantidad. En cada línea: **Editar** y el basurero (**Eliminar**, siempre con confirmación). Los sobres anónimos se ven en rojo y tienen **Asignar persona**.
- **Dinero suelto y egresos:** cada movimiento con su monto, con **Editar** y **Eliminar**.
- **Resumen por categorías:** el subtotal de cada categoría y los totales del culto. Los sobres en dólares muestran debajo el monto original.
- **Firmas del recuento:** el pastor y los tesoreros que firman (ver más abajo).

## Paso a paso: registrar un sobre

1. Abre el culto (toca su línea en la lista) y toca **+ Agregar sobre**.
2. **1 · Persona:** escribe el nombre o el PIN y elige a la persona de la lista. Su nombre aparece **en grande abajo, junto al botón Guardar**, para que confirmes que es la correcta. Si el sobre no trae nombre, marca **Sobre anónimo (sin persona)**: la barra de abajo dice **ANÓNIMO** en rojo. El sistema nunca guarda un sobre anónimo por descuido: sin persona y sin la casilla marcada no deja guardar.
3. Si la persona no está en la lista, toca **+ Nueva persona** (o elige **Crear «nombre» como persona nueva** en el buscador): escribe el **Nombre completo**, el **Teléfono** si lo tienes (opcional) y toca **Agregar y elegir**. Queda activa y sin usuario; el administrador completa el resto de sus datos después.
4. **2 · Montos:** escribe el monto de cada categoría (diezmo, misiones, construcción…). Lo que no traiga el sobre se deja vacío. El **Total** de la barra de abajo se va sumando.
5. Elige el **Método de pago**: **Efectivo** o **Transferencia** (con transferencia aparece **N° de comprobante**). Si el sobre viene en dólares, cambia la **Moneda** a **$ Dólares**: el sistema usa el tipo de cambio del día. Con **+ Agregar nota** puedes escribir una nota del sobre.
6. Toca **Guardar y siguiente** (azul): guarda el sobre y abre uno nuevo **en el mismo culto**, listo para el siguiente. Cuando termines, toca **Guardar y terminar**: guarda y vuelve a la pantalla del culto. La barra con el **Total** y los dos botones siempre está a la vista, aunque el formulario sea largo.

Arriba del formulario, **Último guardado** muestra el sobre anterior (número, persona y monto) con **Editar ese sobre**, por si te equivocaste en el que acabas de guardar.

![Formulario de un sobre nuevo](img:recuento-nuevo-sobre.jpg "Persona (1), sobre anónimo (2), montos por categoría (3) y la barra con el total y Guardar y siguiente (4)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Persona** | Busca por nombre o PIN. **+ Nueva persona** la crea sin salir de la pantalla. | Administrador, Tesorero | El sobre queda ligado a la persona y cuenta para su estado de cuenta. |
| **2. Sobre anónimo (sin persona)** | Marca que el sobre no trae nombre. | Administrador, Tesorero | El sobre queda como anónimo (rojo); después se le puede asignar persona. |
| **3. Montos** | Un monto por categoría. | Administrador, Tesorero | El total del sobre se calcula solo. |
| **4. Guardar y terminar / Guardar y siguiente** | Guarda el sobre. | Administrador, Tesorero | **Terminar** vuelve al culto con el mensaje de confirmación; **siguiente** abre otro formulario en el mismo culto. |

> **Cuidado:** si registras dos sobres de la misma persona en el mismo culto con menos de unos 30 segundos de diferencia, el sistema no guarda el segundo y te avisa: «Ya se registró un sobre para esta persona en este culto hace unos segundos». Revisa que no sea un duplicado; si de verdad son dos sobres, espera unos segundos y vuelve a guardarlo.
>
> Los sobres **anónimos** no se rechazan como duplicados: puedes registrar varios seguidos.

## Corregir un sobre

En la línea del sobre toca **Editar**, cambia lo que haga falta y toca **Guardar cambios** (o **Cancelar**). Mientras el culto está abierto lo puede hacer el Tesorero o el Administrador; si el culto ya está cerrado, ver *Un culto cerrado*.

## Paso a paso: cerrar un culto

1. Revisa los totales y la lista de sobres. Si quedan sobres **anónimos**, asígnales su persona ahora (ver *Sobres anónimos*).
2. Opcional: llena y guarda las **Firmas del recuento** (más abajo).
3. Toca **Cerrar culto**. El mensaje de confirmación te dice cuántos sobres lleva, el total y, si los hay, cuántos están como anónimos. Confirma con **Sí, cerrar**.

El sistema **no deja cerrar un culto futuro ni uno vacío** (sin sobres ni dinero).

Al cerrar:

- **No se puede** agregar sobres, dinero suelto ni egresos, ni editar o eliminar los que ya están. El **Administrador**, o quien tenga un **acceso de edición**, sí puede corregirlo.
- Se guarda el **tipo de cambio** del día para los sobres en dólares.
- Las **firmas** siguen pudiéndose corregir.
- El culto sigue en la lista de cultos, con el estado **Cerrado** y la fecha y quién lo cerró; ábrelo desde ahí.

> **Cuidado:** eliminar un culto cerrado borra el culto **con todos sus sobres**. Solo el Administrador puede hacerlo, escribiendo un motivo, y queda en la auditoría.

> **Importante:** cerrar es el paso que deja listos los totales para los reportes. Cierra el culto cuando el recuento esté completo y firmado.

## Las firmas del recuento

Al final de la pantalla del culto están las **Firmas del recuento**: el **Pastor** y los **Tesoreros**.

1. Escribe el nombre y toca **Firmar**. Se abre un recuadro: dibuja la firma con el mouse o con el dedo y toca **Guardar firma** (o **Limpiar** para empezar de nuevo).
2. Para más de un tesorero, toca **Agregar tesorero**; cada uno tiene su nombre y su **Firmar**.
3. Toca **Guardar firmas**.

Las firmas **se pueden corregir después de firmadas**, aun con el culto cerrado: vuelve a firmar y toca otra vez **Guardar firmas**. Cada cambio queda en la auditoría. Salen en el PDF del recuento.

## Un culto cerrado

Un culto cerrado muestra un aviso con quién lo cerró y cuándo, y qué puedes hacer:

- **Tesorero:** ve todo en **solo lectura** (y puede descargar el PDF). Si hay que corregir algo, pídele al administrador que lo corrija o que le dé un **acceso de edición**.
- **Tesorero con acceso de edición** (módulo *Accesos*, ver su página): el aviso dice «tienes acceso de edición» y hasta cuándo; puede corregir sobres, dinero suelto y egresos, y cada cambio recalcula los totales y queda registrado con su nombre.
- **Administrador:** lo ve «corrigiendo como administrador», con el enlace **Dar acceso a un tesorero para corregirlo**. Cada cambio recalcula los totales con el tipo de cambio congelado al cerrar y queda en la auditoría.

## Sobres anónimos

Un sobre es **anónimo** solo cuando lo marcas a propósito. En la lista de sobres del culto, el filtro **Solo anónimos** muestra únicamente los sobres sin persona. Cuando después sepas de quién era uno, usa **Asignar persona** en su línea: eliges a la persona y el sobre pasa a contar en su estado de cuenta.

## Preguntas frecuentes

**Me equivoqué en un sobre.** Toca **Editar** en su línea, corrige y toca **Guardar cambios**. Si el culto ya está cerrado, solo el Administrador (o quien tenga un acceso de edición) puede corregirlo.

**El culto de hoy no es el primero de la lista.** Búscalo por su fecha en **Buscar por fecha**, o con el filtro **Abiertos**; el de hoy trae la etiqueta **Hoy**.

**El culto que busco no está en la lista.** Revisa que no tengas filtros puestos (**Limpiar**). Si todavía no existe, pídele al administrador que lo cree en **Gestionar Cultos**.

**¿Puedo eliminar un sobre?** Sí, mientras el culto esté abierto, con el basurero de su línea y confirmando. En un culto cerrado solo el Administrador, o quien tenga un acceso de edición.

**¿Puedo eliminar un culto ya cerrado?** Solo el **Administrador**, desde la lista de cultos, con **Eliminar culto**. El sistema pide confirmar y escribir un **motivo** (obligatorio), y queda en la auditoría qué culto era, su fecha, su total y cuántos sobres tenía. El Tesorero no ve ese botón.

**El sobre es de una persona que no está en la lista.** Usa **+ Nueva persona** en el formulario del sobre para crearla con su nombre; después el administrador puede completar sus datos en **Personas**.

**No me deja guardar el sobre.** Falta elegir la persona o marcar **Sobre anónimo (sin persona)**.
