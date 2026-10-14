# Flujo para registrar sobres (HU-REC-4)

Equipo 2, 13/10/2026. Propuesta escrita antes de implementar. Objetivo: meter muchos sobres
seguidos de un culto sin ruido, con el menor número de toques y sin perder datos.

## Cómo es hoy (antes)

1. Recuento → elegir el culto en un selector → pantalla del culto, y al bajar aparecen los
   cuadros de todos los cultos cerrados (ruido de otros cultos). *Ya resuelto en REC-1: la
   pantalla del culto solo muestra ese culto.*
2. "+ Agregar Sobre" → formulario: buscador de persona + un selector con las ~150 personas,
   método de pago, moneda, 8 montos que arrancan en "0" (hay que borrarlo), notas, Guardar.
3. Guardar → vuelve a la pantalla del culto → otra vez "+ Agregar Sobre" → ... Cada sobre son
   dos cargas de página, dos o tres toques y volver a buscar el botón.
4. **Defecto:** para evitar dobles envíos se rechaza cualquier sobre de la misma persona en el
   mismo culto dentro de 30 segundos. Como "Anónimo" cuenta como la misma persona, el segundo
   sobre anónimo seguido se pierde (solo sale un aviso amarillo). Con un flujo rápido esto pasa
   todo el tiempo.
5. **Defecto:** se puede guardar un sobre con todos los montos en cero.

## Cómo queda (después)

Pantalla "Registrar sobre" de **un solo culto**:

```
┌ Domingo 18/10/2026 · Domingo AM          40 sobres · ₡1.432.986 ┐
│ Último guardado: #40 Ana Mora · ₡25.000 · Editar                │
├─────────────────────────────────────────────────────────────────┤
│ 1. Persona   [ buscar nombre o PIN…        ]  [Anónimo] [+ Nueva] │
│ 2. Montos    Diezmo [      ]  Ofrenda especial [      ]  …        │
│ 3. Pago      (•) Efectivo  ( ) Transferencia → N° de comprobante  │
│    Moneda    (•) ₡ Colones ( ) $ Dólares                          │
│    + Agregar nota                                                 │
├─────────────────────────────────────────────────────────────────┤
│ Total ₡0   [Volver al culto] [Guardar y terminar] [Guardar y siguiente] │  ← barra fija
└─────────────────────────────────────────────────────────────────┘
```

Paso a paso con un sobre en la mano:

1. **Persona** (lo primero que se lee del sobre). El cursor ya está en el buscador. Se escribe
   parte del nombre o el PIN; las flechas mueven la selección y **Enter elige**. Al elegir (o al
   tocar "Anónimo") el **foco salta solo al primer monto**. "+ Nueva" crea la persona sin salir.
   El selector con las 150 personas desaparece: el buscador es el único camino.
2. **Montos**. Los campos arrancan vacíos (nada que borrar), con **teclado numérico** en
   tablet y celular (`inputmode="decimal"`). Tab o tocar el siguiente campo para otra categoría.
   El total se ve siempre en la barra fija.
3. **Pago y moneda** como botones de dos opciones (un toque, sin desplegables). Efectivo y
   colones vienen marcados. Transferencia pide el número de comprobante.
4. **Guardar y siguiente** (botón principal; también **Enter** desde un monto). Guarda, vuelve
   al mismo formulario vacío del mismo culto con el aviso "Sobre #41 guardado" y el cursor otra
   vez en Persona. "Guardar y terminar" guarda y vuelve a la pantalla del culto.

Arriba siempre está el culto en cuestión, cuántos sobres lleva y el total, y el último sobre
guardado con un enlace para editarlo (corregir un error del sobre anterior sin buscarlo). Nada
de otros cultos.

### Reglas que cambian

- **Doble envío:** cada formulario lleva un código único de envío. Si el mismo formulario llega dos
  veces (doble toque, volver atrás y reenviar) el segundo se ignora con un aviso. Desaparece la
  regla de "misma persona en 30 segundos": dos sobres anónimos seguidos, o dos sobres de la misma
  persona (uno en colones y otro en dólares), se guardan bien. Sin código (otros clientes) se
  mantiene la regla vieja.
- **Sobre vacío:** si todos los montos están en cero no se guarda: "Escribe al menos un monto".
- **Culto cerrado:** el formulario solo se abre si la regla de REC-2/REC-3 deja editar el culto.

### Editar un sobre

La misma pantalla y los mismos campos (un solo formulario compartido), con el culto en el
encabezado; "Guardar cambios" vuelve a la pantalla del culto.

## Cómo se mide

- Toques por sobre (persona + 1 categoría, efectivo): antes ~6 y dos cargas de página
  (Agregar → escribir/elegir persona → borrar 0 y escribir → Guardar → volver → Agregar);
  después 3 (escribir nombre + Enter, escribir monto, Enter) y una carga.
- Pruebas: guardar y siguiente vuelve al formulario del mismo culto; dos anónimos seguidos se
  guardan; el mismo envío dos veces guarda uno; sobre en cero no se guarda; editar vuelve al culto.
