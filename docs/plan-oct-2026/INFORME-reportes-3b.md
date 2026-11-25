# Informe · Equipo R1 (reportes-3b) · E2 · 24/11/2026

Rama `feat/reportes-3b`, base `integracion/oct-2026` (e3ffae5). Encargo: HU-R2, HU-R4, QE-008, QE-011 y
HU-R14 (solo los PDF de promesas).

## 1. Qué se hizo, por historia

### QE-011 · Último aporte con la misma base que «Ha dado»
- El motor (`MotorReportes`) guarda la fecha del último aporte **por categoría**; `CuentaPersona::ultimoAporteACompromisos()`
  mira solo las categorías que la persona prometió (sin promesa: las de compromiso, igual que su «ha dado»).
- Cumplimiento (pantalla, PDF y Excel) usa esa fecha y rotula la columna **«Último aporte a sus compromisos»**
  (`FilaCumplimiento::TITULO_ULTIMO_APORTE`). La observación «sin aportes en N meses» ya usaba esa base: ahora
  las dos coinciden (quien dio ₡0 en lo prometido sale con «-»).
- Test: `tests/Feature/Reportes/UltimoAporteCompromisosTest.php` (persona que dio a una categoría no prometida).

### HU-R2 · Selector de personas completo
- `FiltroPersonas` admite filtros rápidos combinables: `grupo=filtros&estado=activos|inactivos|todos&promesa=si|no|todos&aporto=si|no|todos&ministerios=3,7|todos`.
  «Aportó en el periodo» usa el periodo del reporte (`personas($periodo)`).
- **Compatibilidad**: los enlaces de siempre (`grupo=todas|con_promesa|ministerio|seleccion`, `personas=`, `ministerio=`,
  `corte=`) dan lo mismo, y unos filtros que equivalen a un grupo viejo se normalizan a él (misma descripción
  y mismos parámetros en las descargas). No cambió ninguna firma: `FiltroPersonas::desde(...)`, `todas()`,
  `descripcion()`, `aParametros()`, `personas()` (el periodo es opcional).
- «Todos los filtrados» viaja como filtros (nunca como ids); la selección a mano como `grupo=seleccion&personas=…`
  y además recuerda los filtros para volver a ellos.
- Parcial único `_filtro-personas` + `_selector-personas-js` en Cumplimiento y Estados de cuenta. Datos para filtrar
  en el navegador: `CandidatasSelector` (4 consultas en total).
- Tests: `tests/Feature/Reportes/SelectorPersonasTest.php`.

### HU-R4 · Vista previa
- `VistaPrevia`: ordena las MISMAS filas del PDF/Excel por columna (`?orden=&dir=`), pagina de 100 en 100
  (`?pagina=`), todo en la URL. Cabeceras con `aria-sort` y objetivos de 44 px.
- Cumplimiento: columnas Persona, Le correspondía, Ha dado, Diferencia, Estado, Último aporte a sus compromisos;
  pestañas por estado ahora en la URL (`?ver=pendiente|al-dia|sin-promesa`). Fila de totales **del conjunto
  completo** (todas las páginas), fija al pie.
- Estados de cuenta: Persona, Total contribuido, Le corresponde, Ha dado en compromisos, Diferencia, Estado,
  Último aporte a sus compromisos; totales de todo el lote (= fila TOTAL del Excel).
- Test de contrato: `tests/Feature/Reportes/VistaPreviaReportesTest.php` (vista previa = PDF = Excel por persona y en
  totales; orden; paginación con 103 personas).

### QE-008 · Dashboard y Reporte de promesas con la regla del motor
- Nuevo `ResumenPromesasService` / `ResumenPromesas`: suma las cuentas del motor (no calcula nada propio).
  Prometido = meses completos; el mes en curso aparte, rotulado «<Mes> (en curso, todavía no se cobra)», fuera de
  faltante, excedente y porcentaje; dado = lo dado por las personas con promesa en lo que prometieron (base del
  «Ha dado»).
- `DashboardPromesasController` ya no lee la tabla `compromisos`. KPIs «Prometido (meses completos)», «Dado (hasta hoy)»,
  «Saldo», «Cumplimiento» y tarjeta del mes en curso; formato `Dinero::formato` en KPIs y top 10.
- `PromesasReporteController` (index, pdfPromesas, pdfAnual, porClase) sobre el mismo servicio; vistas con
  `Dinero::formato` y la nota de la regla.
- Tests: `tests/Feature/Dashboard/DashboardPromesasRangoTest.php` (reescrito sobre sobres reales; incluye
  prometido del dashboard = «le correspondía» del cumplimiento al mismo corte) y `tests/Feature/Reportes/ReportePromesasTest.php`.

### HU-R14 · PDF de promesas con la plantilla única
- `pdf-promesas` y `pdf-promesas-anual` se dibujan con `DocumentoPdf` (encabezado con iglesia, periodo y filtros;
  tarjetas; tabla por categoría con TOTAL; mes a mes en el anual; pie con fecha de generación y «Página N de M»).
  Se auditan con `AuditoriaReportes` (`promesas` / `promesas-anual`). Se borraron las vistas Blade
  `pdfs/promesas*.blade.php` (ya nadie las usa).

## 2. Decisiones
- **Formato de miles**: se usa `Dinero::formato` (₡ con coma de miles, como el resto de los reportes nuevos y el
  test de contrato). El encargo dice «punto de miles»; cambiar `Dinero` cambiaría todas las salidas y los
  contratos de otros equipos. Si el cliente quiere punto, es un cambio de una línea en `Dinero::formato` para toda
  la app (decisión del líder).
- **Reporte de promesas, «Dado»**: antes sumaba todo lo que entraba a la categoría (anónimos y quien no prometió).
  Ahora es lo dado por quienes prometieron, en lo que prometieron (motor). Por eso cambian las cifras de «dado»
  (p. ej. febrero del escenario de prueba: ₡30.200 → ₡22.200). Lo recibido total por categoría está en el
  reporte de ingresos.
- **Dashboard**: lo dado incluye el mes en curso (regla: «lo dado en él sí cuenta»); lo prometido no.
- Un mes en curso visto solo (Reporte de promesas, mes actual): prometido 0, faltante 0, excedente 0, y lo prometido del
  mes aparte.
- «Quitar todos» deja sin selección a mano: el reporte incluye a todas las que cumplen los filtros y la pantalla lo dice.
- «Aportó en el periodo» en el navegador se calcula con el periodo mostrado; si se cambia el periodo sin consultar,
  el servidor lo vuelve a aplicar al consultar.

## 3. Cómo probarlo a mano (admin o tesorero, puerto 8121)
1. `/reportes/cumplimiento`: en «Personas», cambiar Estado a «Activas e inactivas», Con promesa «Sí», marcar un
   ministerio → Consultar. La URL lleva `grupo=filtros&…`; «Descargar Excel» y el PDF repiten el filtro.
2. Desmarcar a alguien en la lista → el contador baja; «Seleccionar todos los filtrados» vuelve a todas; marcar la
   casilla de cabecera con más de 50 filtradas muestra el aviso «Seleccionar las N que cumplen los filtros».
3. Tocar «Ha dado» en la cabecera de la vista previa (ordena), otra vez (invierte). Con más de 100 filas aparece
   la paginación; la fila de totales no cambia entre páginas.
4. `/reportes/estados-de-cuenta`: igual.
5. `/dashboard/promesas` (año en curso): tarjeta «<Mes> (en curso, todavía no se cobra)»; «Prometido (meses completos)»
   igual a «Le correspondía» de Cumplimiento con corte hoy.
6. `/ingresos-asistencia/promesas` mes actual: nota azul con la regla; PDF del mes y anual con la plantilla única.

## 4. Tiempos (base `ibbla_r3b`, 150 personas, un año de cultos; como tesorero)
Medidos con el kernel HTTP en proceso (`r1_tiempos.php`), **con otros cuatro equipos corriendo suites en la misma
máquina** (la CPU estaba compartida; los tiempos reales en una máquina libre son menores).

| Petición | Tiempo |
|---|---|
| `/reportes/cumplimiento` (selector + vista previa) | 3,3 s |
| `/reportes/cumplimiento?grupo=filtros&estado=todos&promesa=si&aporto=si&orden=dado&dir=desc` | 1,9 s |
| `/reportes/cumplimiento/pdf` | 3,3 s |
| `/reportes/cumplimiento/excel` | 6,8 s |
| `/reportes/estados-de-cuenta` (selector + vista previa) | 1,7 s |
| `/reportes/estados-de-cuenta?…&pagina=2` | 1,2 s |
| `/reportes/estados-de-cuenta/excel?grupo=con_promesa` (código sin cambios de esta tanda) | **12,6 – 17,9 s** |
| `/dashboard/promesas` (año) | 3,9 s |
| `/ingresos-asistencia/promesas?mes=todos` | 2,5 s |
| `/ingresos-asistencia/promesas` (mes) | 0,5 s |
| `/ingresos-asistencia/pdf-promesas` | 0,8 s |
| `/ingresos-asistencia/pdf-promesas-anual` | 1,4 s |
| `/ingresos-asistencia/promesas-por-clase?clase_id=capilla&mes=todos` | 2,3 s |

El Excel del lote de estados de cuenta (86 personas con promesa, hoja Detalle con todos los sobres) pasó de 10 s
bajo carga: el perfil da lote 0,5 s, armar el libro ~20 s y escribirlo ~6 s (PhpSpreadsheet). Ni el lote ni
`EstadoCuentaExcel` cambiaron en esta tanda; QA-030 lo midió en menos de 10 s con la máquina libre. **Hay que
volver a medirlo con la máquina libre**; si pasa de 10 s, la salida es topar la hoja Detalle o generar el Excel
del lote en segundo plano.

## 5. Pendientes y riesgos
- Riesgo de tiempo del Excel del lote (arriba).
- Las cifras de «Dado» del Reporte de promesas cambian (ya no suman anónimos ni categorías no prometidas): avisar a
  Roy en las notas de la entrega.
- Formato de miles con coma (`Dinero`), no punto: decisión pendiente del cliente (sección 2).
- Se probó el selector en el navegador (Chrome, tesorero, datos de volumen): filtros, «Quitar todos», elegir a mano,
  casilla de cabecera con aviso tipo Gmail y «Seleccionar todos los filtrados»; no se probó en una tablet real.

## 6. Notas para el manual de usuario (textos exactos)
**Selector «Personas»** (Cumplimiento de promesas y Estados de cuenta):
- Filtros: «Estado» (Activas / Inactivas / Activas e inactivas), «Con promesa» (Todas / Sí, con promesa / No, sin
  promesa), «Aportó en el periodo» (Todas / Sí aportó / No aportó), «Ministerios» (casillas; «Cualquier
  ministerio»; ninguno = no filtrar). Con más de 12 ministerios aparece «Buscar ministerio».
- Botones: «Seleccionar todos los filtrados», «Quitar todos». Contador: «N personas seleccionadas».
- Avisos: «Están todas las que cumplen los filtros (N)…», «No eligió a nadie a mano: el reporte incluye a las N
  personas que cumplen los filtros.», y tipo Gmail «Se eligieron las N personas que se ven en la lista.
  Seleccionar las M que cumplen los filtros».
- Lista: «Buscar en la lista (nombre, PIN o teléfono)», casilla «Elegir las N de la lista», «Mostrar 50 más».
  Enter en la búsqueda agrega a la primera persona.
**Vista previa**: título «Vista previa», «Toque un título de columna para ordenar.», pestañas Todos / Pendientes /
Al día / Sin promesa, paginación «Anterior» / números / «Siguiente» y «Filas X a Y de Z», fila «Total con promesa
(N personas, todas las páginas)» (Cumplimiento) y «Total (N personas, todas las páginas)» (Estados de cuenta).
Columna nueva «Último aporte a sus compromisos» (solo cuenta lo dado en lo que la persona prometió).
**Dashboard de promesas**: «Prometido (meses completos)», «Dado (hasta hoy)», «Saldo», «Cumplimiento» y la tarjeta
«<Mes> (en curso, todavía no se cobra)»; nota azul que explica la regla y enlaza a Cumplimiento.
**Reporte de promesas**: tarjeta «Prometido (meses completos)» y nota azul con la regla y el mes en curso aparte;
PDF «Reporte de promesas» y «Reporte anual de promesas» con columna «En curso (no se cobra)».

## 7. Cambios propuestos a archivos de otros equipos
- Ninguno obligatorio. `resources/views/manual/paginas/07-reportes.md` (equipo G) debería tomar la sección 6.
