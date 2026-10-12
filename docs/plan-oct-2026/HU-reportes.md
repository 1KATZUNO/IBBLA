# Centro de Reportes — historias de usuario (pedido de David, 12/10/2026)

Pedido textual: revisar el apartado de reportes por persona (como se genera el PDF, como se busca),
que sea eficiente, funcional y que no confunda; y que el administrador pueda sacar reportes de un
GRUPO de personas (filtro tipo `WHERE IN`, pero visual), con descarga en PDF (individual y general)
y Excel, "como en paginas profesionales".

## 1. Diagnostico de lo que hay hoy (rama integracion/oct-2026, tras B y E)

| # | Hallazgo | Por que confunde / cuesta |
|---|---|---|
| D1 | Hay CUATRO motores que calculan "lo dado" y "lo esperado" por su cuenta: `PersonaController::reportePdf` (Reporte PDF de contribuciones), `ReporteGeneralService` (Reporte General, agente A), `InformePersonaService` (informe individual, agente E) y `CalculoPromesasService` (pantalla Compromisos). | Mismo dato, numeros distintos segun donde se mire. `reportePdf` suma `monto` crudo sin convertir USD a colones; los otros tres si convierten. |
| D2 | El periodo esta fijo: enero -> mes actual (`date('Y')`, `date('n')`). No se puede pedir otro anio ni un rango. | No sirve para cerrar un anio ni para ver un trimestre. |
| D3 | El PDF individual (E) solo se alcanza por el boton de WhatsApp, que redirige a wa.me. El admin no tiene "Ver / Descargar informe" en la ficha ni en Compromisos; el miembro tampoco en Mi perfil. | El reporte existe pero "no se encuentra". |
| D4 | Los reportes estan repartidos: dos botones en modales dentro de Gestion de Personas, el resto en Ingresos y Asistencia, servidores en Admin. | No hay un lugar unico donde ir a "sacar un reporte". |
| D5 | El buscador de Personas solo filtra por nombre/PIN. No hay filtro por ministerio, estado, con/sin promesa. No existe seleccion multiple. | Imposible sacar "el reporte de estas 12 personas". |
| D6 | Reglas de inclusion distintas: Reporte PDF muestra solo quien dio > 0; Reporte General muestra a todos los activos. | Dos listas de personas distintas para el mismo mes. |
| D7 | Rendimiento: el informe individual usa DomPDF con vista Blade (~0,5-1 s por persona). Generar 50 en lote por esa via rondaria 40 s: el mismo 504 que arreglo A. El Reporte General ya va sobre canvas (~2 s el anio completo). | Un lote de individuales necesita la misma tecnica de canvas o un tope. |

## 2. Referencia de UX (patrones de paneles profesionales)

- Barra de filtros arriba + chips de lo seleccionado + tabla de vista previa + boton "Exportar" con menu
  (PDF / Excel / CSV) y confirmacion con el conteo ("Exportar 12 personas"). Es el patron de Stripe,
  Shopify y QuickBooks en sus pantallas de reportes.
- Selector de personas tipo "tokens": caja de busqueda con autocompletado, cada persona elegida queda
  como chip con su X; banner "Se muestran 25 de 140 filtradas — Seleccionar las 140" (patron Gmail).
- Estado vacio, estado "generando..." y mensajes de limite claros. Todo con objetivos de 44 px.
- La URL guarda filtros y seleccion (`?personas=1,5,9&anio=2026&desde=1&hasta=9`): se puede compartir y
  volver atras sin perder nada.

## 3. Historias

### HU-R1 Centro de Reportes
Como admin/tesorero quiero una entrada "Reportes" en el menu con todos los reportes en un solo lugar.
- Tarjetas: Estado de cuenta por persona (individual o lote), Contribuciones del grupo, Reporte general
  (dado vs esperado), y accesos a los ya existentes (ingresos, promesas, asistencia, asistencia por
  clase, servidores). Cada tarjeta dice en una linea que contiene y en que formatos sale.
- Los dos modales de Gestion de Personas desaparecen; sus botones llevan al centro con el filtro listo.
- Criterio: desde cualquier pantalla se llega a cualquier reporte en <= 2 clics.

### HU-R2 Selector de personas (el `WHERE IN` visual)
Como admin quiero elegir a quienes entran en el reporte.
- Busqueda con autocompletado (nombre, PIN, telefono), chips con X, "Seleccionar todos los filtrados",
  "Quitar todos", contador "N personas seleccionadas".
- Filtros rapidos combinables: estado (activos / inactivos / todos), ministerio (multiple), con promesa
  (si / no / todos), aporto en el periodo (si / no / todos).
- Sin seleccion = todos los que cumplen los filtros (se avisa en pantalla).
- Criterio: usable con teclado y en tablet; la seleccion viaja en la URL; no hay desplegables de mas de
  ~50 opciones sin busqueda.

### HU-R3 Periodo
Como admin quiero fijar el periodo del reporte.
- Anio + de mes a mes (por defecto enero -> mes actual) o fechas exactas. El periodo elegido se imprime
  en todos los encabezados y nombres de archivo (`estado-cuenta_2026-01_2026-09.pdf`).
- Criterio: anios anteriores completos; validacion inicio <= fin; no mas de 24 meses.

### HU-R4 Vista previa antes de exportar
Como admin quiero ver en pantalla lo que va a salir.
- Tabla: persona, por categoria dado, esperado, diferencia, estado (al dia / atrasado), total; fila de
  totales; ordenable por columna; paginada a partir de 100.
- Criterio: los numeros de la vista previa son identicos a los del PDF y del Excel (test).

### HU-R5 Exportaciones
Como admin quiero descargar el reporte en PDF o Excel.
- **PDF individual en lote:** un solo PDF con portada (iglesia, periodo, N personas), indice y una
  seccion por persona (salto de pagina). Opcion "un archivo por persona (ZIP)".
- **PDF general del grupo:** mismo diseno del Reporte General actual pero solo con las personas elegidas.
- **Excel:** hoja Resumen (una fila por persona, columnas por categoria, esperado, dado, diferencia,
  estado), hoja Detalle (un renglon por sobre: fecha, culto, categoria, monto, moneda, tipo de cambio,
  colones), hoja Compromisos (promesa, frecuencia, esperado, dado). Formato de moneda real, encabezados
  fijos, anchos ajustados, filtros automaticos. CSV opcional de la hoja Resumen.
- Rendimiento: lote de 50 personas en <= 10 s y < 128 MB en Hostinger. El PDF de lote se dibuja con la
  misma tecnica de canvas de `ReporteGeneralPdf`; si no, tope de 30 personas por lote con aviso claro.
- Criterio: nombres de archivo con periodo; auditoria de cada exportacion (quien, filtros, cuantas).

### HU-R6 Informe individual al alcance
Como admin quiero ver/descargar el informe de UNA persona sin pasar por WhatsApp; como miembro, el mio.
- Boton "Ver informe PDF" en ficha de persona y en Compromisos (junto a "Enviar por WhatsApp").
- "Descargar mi informe" en Mi perfil, con selector de mes de corte.
- Criterio: mismo PDF que viaja por WhatsApp; el miembro solo puede pedir el suyo.

### HU-R7 Una sola calculadora
Como equipo queremos que todos los reportes usen el mismo motor de calculo.
- Un servicio unico (base: `ReporteGeneralService`, que ya es el rapido) alimenta Reporte General,
  informe individual, lote, Excel, vista previa y la pantalla de Compromisos. `reportePdf` deja de
  sumar USD sin convertir.
- Criterio: test que compara, para 3 personas y 2 periodos, los totales de las 5 salidas: deben ser
  identicos al centimo.

### HU-R8 Permisos y auditoria
Solo admin y tesorero entran al centro; el miembro solo ve lo suyo. Cada exportacion queda en
`audit_logs` (accion, filtros, conteo, formato).

### HU-R9 Calidad visual (B)
Responsive de tablet a PC, barra de exportar fija abajo en tablet, estados vacio/cargando/limite,
sin botones "muy abajo". QA visual con capturas antes/despues.

## 4. Orden sugerido y estimacion
1. HU-R7 (motor unico) + HU-R3 (periodo): base de todo. 2. HU-R2 + HU-R4 (selector y vista previa).
3. HU-R5 (exportaciones). 4. HU-R1 + HU-R6 (centro y botones). 5. HU-R8/R9 + QA.
Un agente opus para R7/R5 (calculo y PDF/Excel), uno sonnet para R1/R2/R4/R6 (pantallas), QA opus al
final. Arranque: tras el reinicio semanal del plan (13/10, 13:00), por la regla del 70 %.
