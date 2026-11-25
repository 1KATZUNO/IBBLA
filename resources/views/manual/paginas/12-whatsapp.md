---
titulo: WhatsApp
orden: 120
icono: mensaje
resumen: Enviar el estado de cuenta de una persona por WhatsApp con un toque y atender los avisos a los líderes (culto creado, sobres alcanzados).
roles: admin, tesorero
rutas: avisos.*#avisos-de-whatsapp, personas.whatsapp#enviar-el-estado-de-cuenta-de-una-persona
palabras: whatsapp, enviar por whatsapp, avisos, líderes, enlace, informe, estado de cuenta, wa.me, culto creado, sobres alcanzados, mensaje, teléfono
---

## Para qué sirve

El sistema usa **WhatsApp** de dos formas:

- **Enviar por WhatsApp** el estado de cuenta de una persona (un enlace a su PDF) con el mensaje ya escrito.
- **Avisos de WhatsApp** a los líderes cuando se crea un culto o cuando un culto llega a cierta cantidad de sobres.

> **Importante:** el sistema escribe **solo** a quien **activó WhatsApp en su perfil** (con el bot gratuito CallMeBot; ver *WhatsApp: cómo activar tus avisos* en *Mi perfil y mi cuenta*). A quien no lo activó no se le manda nada solo: el sistema **abre WhatsApp** con el mensaje escrito y tú solo tocas **enviar**. Si un envío automático falla, el aviso queda en el panel para mandarlo a mano; nunca se pierde.

## Quién puede usarlo

| Función | Quién |
|---|---|
| **Enviar por WhatsApp** a una persona | Administrador |
| **Avisos de WhatsApp** (panel) | Administrador, Tesorero |
| **Recibir avisos** | Administradores y tesoreros que tengan teléfono en su usuario |

Si quieres recibir **tus propios avisos y tu estado de cuenta** sin tocar nada, activa WhatsApp en *Mi perfil* o *Mi cuenta* (ver *WhatsApp: cómo activar tus avisos* en esa página).

## Enviar el estado de cuenta de una persona

El botón de WhatsApp está en tres lugares: el icono verde en el renglón de la lista de **Personas**, **Enviar por WhatsApp** en la ficha de la persona y el mismo botón en sus **Compromisos**.

![El botón de WhatsApp en la lista de personas](img:whatsapp-boton.jpg "El icono de WhatsApp (1) abre el chat con el mensaje ya escrito")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Enviar por WhatsApp** | Prepara un enlace al estado de cuenta en PDF de la persona y abre WhatsApp con el mensaje escrito. | Administrador | Solo falta tocar **enviar**. El enlace funciona **7 días** y luego vence. |
| **Botón gris** | Indica que no se puede enviar. | Administrador | La persona no tiene un teléfono válido: edítala y escribe un número de 8 dígitos (el +506 se agrega solo). |

Si la persona activó WhatsApp en su perfil, el enlace **le llega solo**; si no, se abre el chat con el mensaje escrito. Quien recibe el enlace puede abrir el PDF sin entrar al sistema; pasados los 7 días el enlace deja de abrir y hay que mandar uno nuevo.

## Avisos de WhatsApp

Menú lateral → **Consultas** → **Avisos de WhatsApp**. Cuando se **crea un culto** o un culto **llega a 10 sobres**, el sistema prepara un aviso para cada líder que tenga teléfono. El menú muestra con un número cuántos avisos están pendientes.

**Los avisos ya no salen al guardar.** Al crear el culto o guardar el sobre que llega al umbral, el sistema solo **deja el aviso listo** y tu pantalla responde enseguida (antes podía tardar). Un proceso del sistema, el **planificador**, revisa los avisos **cada minuto** y manda los que pueden salir solos (a quien activó WhatsApp en su perfil): suele tardar **cerca de un minuto**. Mientras tanto, el aviso aparece en **Pendientes**. Si el envío falla lo reintenta hasta 3 veces (esperando 1 y luego 5 minutos); si no sale, o pasan más de 12 horas, el aviso se queda en **Pendientes** para atenderlo a mano. Cuando el envío automático está activo, el panel dice «Envío automático activo: los avisos nuevos se envían solos. Aquí quedan los que no se pudieron enviar.»

![Panel de avisos de WhatsApp](img:whatsapp-avisos.jpg "Pestañas Pendientes e Historial (1) y las acciones de cada aviso (2)")

| Botón o control | Qué hace | Quién puede usarlo | Qué pasa después |
|---|---|---|---|
| **1. Pendientes / Historial** | Cambia entre los avisos por atender y los ya atendidos. | Administrador, Tesorero | **Historial** muestra quién atendió cada aviso y cuándo. |
| **2. Abrir WhatsApp** | Abre el chat del líder con el mensaje escrito. | Administrador, Tesorero | Tocas **enviar** en WhatsApp. |
| **Enviar ahora** | Aparece en los avisos de quien tiene WhatsApp automático pero cuyo aviso no salió: reintenta el envío. | Administrador, Tesorero | Si sale, pasa al historial; si falla, queda pendiente y el panel muestra el motivo. |
| **Marcar enviado** | Pasa el aviso al historial. | Administrador, Tesorero | Ya no aparece como pendiente. |
| **Descartar** | Descarta el aviso. | Administrador, Tesorero | Pide confirmación; **no se enviará**. |
| **Ver mensaje** | Muestra el texto del aviso. | Administrador, Tesorero | — |

### Paso a paso: atender un aviso

1. Entra a **Avisos de WhatsApp** (o toca el número rojo en el menú).
2. Toca **Abrir WhatsApp** en el aviso.
3. En WhatsApp toca **enviar**.
4. Regresa al sistema y toca **Marcar enviado**.

Si el líder tiene WhatsApp automático, el aviso dice «Tiene WhatsApp automático, pero este aviso no salió: pulse «Enviar ahora» para reintentarlo.»; si no lo tiene, dice «No tiene WhatsApp automático: se manda con «Abrir WhatsApp».»

## Preguntas frecuentes

**El botón de WhatsApp está gris.** La persona no tiene teléfono válido (8 dígitos). Edítala en *Personas y compromisos*.

**El aviso no me llegó.** Revisa que tu usuario tenga teléfono en *Usuarios y roles* y que tengas rol de administrador o tesorero.

**El enlace que envié ya no abre.** Los enlaces duran 7 días. Vuelve a tocar **Enviar por WhatsApp** para generar uno nuevo.
