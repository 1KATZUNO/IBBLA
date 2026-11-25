# Informe E2 · Equipo R2 — reportes de cultos e ingresos, asistencia en el centro, plantilla única

Rama `feat/reportes-cultos-ingresos` (worktree `IBBLA-wt\reportes-nuevos`, BD `ibbla_rn`, puerto 8124).
Base `integracion/oct-2026` (e3ffae5). Fecha: 24/11/2026.

## Qué se hizo, por historia

### HU-R10 Reporte de cultos (nuevo) — HECHO
- Pantalla `reportes.cultos` (`/reportes/cultos`), PDF `reportes.cultos.pdf` (`?accion=ver|descargar`) y Excel
  `reportes.cultos.excel`. Grupo `reportes.` (admin y tesorero).
- Filtros: rango de fechas (por defecto el mes actual; atajos Este mes / Mes anterior / Últimos 3 meses / Este año),
  tipos de culto (casillas, ninguno = todos) y «Elegir cultos» (lista de los cultos del rango con fecha, tipo y
  estado; ninguno marcado = todos). Todo viaja en la URL (`fecha_inicio`, `fecha_fin`, `tipos[]`, `cultos[]`).
- Vista previa: una fila por culto (fecha con enlace al culto, tipo, estado abierto/cerrado, sobres, efectivo,
  transferencias, suelto, egresos, total, firmas completas Sí/No) con fila de totales; tarjetas de resumen; desglose
  del rango por categoría y por método de pago.
- PDF (carta horizontal) y Excel con la plantilla única. Excel: hojas **Resumen** (una fila por culto + totales),
  **Por categoría y método** y **Detalle** (una fila por culto y categoría, más dinero suelto).
- Cifras: cada culto pasa por `App\Support\ResumenCulto` (la del encabezado del culto y del PDF de recuento). Test que
  compara culto por culto al céntimo (`ReporteCultosTest::test_cada_culto_cuadra_al_centimo_con_el_recuento`), con
  transferencia, suelto en USD, egreso y una categoría que la iglesia ya no tiene.
- Auditoría de PDF y Excel con `AuditoriaReportes` (`reporte: cultos`, periodo, tipos, cultos elegidos, total).

### HU-R11 Reporte de ingresos (nuevo) — HECHO
- Pantalla `reportes.ingresos`, PDF `reportes.ingresos.pdf`, Excel `reportes.ingresos.excel` (admin y tesorero).
- Rango de fechas (+ tipos de culto opcional). Ingresos por categoría y por método de pago, cada línea con: este
  periodo, periodo anterior, diferencia (con signo) y variación %. El periodo anterior es el de
  `RangoFechas::anterior()` (mismos meses antes si el rango son meses completos; si no, los mismos días justo antes)
  y se rotula siempre: «comparado con el periodo anterior de igual duración, del 01/01/2026 al 31/01/2026».
  Sin ingresos antes: «sin datos antes» (no se divide entre cero).
- Tarjetas: ingresos del periodo, periodo anterior, variación, egresos, neto.
- Excel: **Resumen** (las dos tablas comparativas) y **Detalle** (una fila por culto).
- Mismas cifras que el reporte de cultos (test `test_cuadra_con_el_reporte_de_cultos`).
- Rutas viejas: `ingresos-asistencia.ingresos`, `.pdf-ingresos` y `.excel-ingresos` redirigen a la pantalla/PDF/Excel
  nuevos con las mismas fechas (si falta una fecha se completa: inicio = 1/1 del año de la fecha fin; fin = 31/12 del
  año de inicio o hoy). `pdf-ingresos-transferencias` queda funcionando tal cual (lo usa el recuento). El parámetro
  viejo `tipo_reporte` (culto/semana/mes) se ignora: el detalle por culto está en el Reporte de cultos.
- Se borró la vista `ingresos-asistencia/ingresos.blade.php` (ya nadie la usa).

### Centro de reportes y buscador — HECHO
- Tarjetas nuevas «Reporte de cultos» y «Reporte de ingresos» (la tarjeta vieja «Ingresos» se reemplazó por la nueva).
- Las tarjetas de asistencia ahora las ve también el tesorero (decisión 5). Regla en la vista:
  `canAccessAsistencia() || canAccessRecuento()`, la misma que usa `role:admin,tesorero,asistente` (el tesorero entra
  por el permiso `recuento`).
- Buscador (`Menu.php`, solo entradas de reportes): «Reporte de ingresos» apunta al nuevo; nuevo «Reporte de cultos»;
  «Reporte de asistencia» y «Asistencia por clase» también para el tesorero.

### HU-R12 Asistencia en el centro — HECHO (integración, sin cálculo nuevo)
- «Asistencia por clase» entiende `fecha_inicio`/`fecha_fin` (además de `desde`/`hasta`), los mismos nombres de los
  demás reportes.
- Asistencia por culto ↔ por clase se enlazan entre sí con las mismas fechas («Ver la asistencia por clase de estas
  fechas» / «Ver la asistencia por culto de estas fechas»).
- El tesorero consulta la asistencia sin los botones Editar/Eliminar (son de quien registra: admin y asistente;
  esas rutas le daban 403).

### HU-R14 Plantilla única en lo viejo — PARCIAL
- Hecho: PDF `pdf-asistencia` (rango) y `pdf-asistencia-mes`, Excel `excel-asistencia` y `excel-asistencia-mes`
  pasan a `DocumentoPdf` / `LibroExcel` (`App\Services\Reportes\Exportaciones\AsistenciaPdf` y `AsistenciaExcel`):
  encabezado con iglesia, periodo y fecha de generación, tarjetas (cultos, asistencia total, promedio), totales y
  pie. El Excel deja la hoja «Asistencia» desde la fila 1 (Roy hace gráficos con ella) y una hoja «Resumen».
  Test de que los totales salen de los mismos métodos de `Asistencia` (`AsistenciaPdfPlantillaTest`).
- Se borró `resources/views/pdfs/asistencia.blade.php` (sin uso).
- Pendiente (ver abajo): `pdf-asistencia-culto`, `AsistenciaPorClaseExport` (+ PDF de asistencia por clase),
  `IngresosExport` y `pdfs/asistencia-mes` que siguen usándose desde `EnviarReporteMensual`.

## Archivos
Nuevos: `app/Http/Controllers/Reportes/{CultosController,IngresosController}.php`,
`app/Services/Reportes/Cultos/{FiltroCultos,FilaCulto,ReporteCultos,GeneradorReporteCultos}.php`,
`app/Services/Reportes/Ingresos/ReporteIngresos.php`,
`app/Services/Reportes/Exportaciones/{CultosPdf,CultosExcel,IngresosPdf,IngresosExcel,AsistenciaPdf,AsistenciaExcel}.php`,
`resources/views/reportes/{cultos,ingresos,_filtro-cultos,_desgloses-cultos}.blade.php`,
`tests/Feature/Reportes/{ReporteCultosTest,ReporteIngresosTest,AsistenciaEnCentroTest,AsistenciaPdfPlantillaTest}.php`.
Tocados: `routes/web.php` (6 rutas nuevas en `reportes.`), `IngresosAsistenciaController.php` (ingresos viejos →
redirección; PDF/Excel de asistencia con la plantilla; se quitaron helpers sin uso; `excelPromesas`,
`pdfRecuentoIndividual` y `pdfRecuentoTransferencias` sin tocar), `AsistenciaClasesReporteController.php`,
`resources/views/ingresos-asistencia/{index,asistencia,asistencia-clases}.blade.php`, `app/Support/Navegacion/Menu.php`
(solo `extras()`), tests `CentroReportesTest`, `BuscadorGlobalTest`, `Reportes/PermisosDescargasTest`.
Borrados: `resources/views/ingresos-asistencia/ingresos.blade.php`, `resources/views/pdfs/asistencia.blade.php`.
`DocumentoPdf` y `LibroExcel` no se tocaron.

## Cómo probarlo a mano
Usuario tesorero (o admin) de `database/seeders`, BD con `VolumenDevSeeder`.
1. Reportes (centro) → «Reporte de cultos». Elegir «Este año» o fechas; marcar un tipo; abrir «Elegir cultos» y marcar
   dos; Consultar. Comparar el total de un culto con su pantalla de Recuento (deben ser iguales). «Ver PDF»,
   «Descargar PDF», «Descargar Excel».
2. Reportes → «Reporte de ingresos». Elegir «Mes anterior»: ver el comparativo contra el mes previo y su rótulo.
   Probar un rango sin datos antes (p. ej. el primer mes con cultos): «sin datos antes».
3. Enlaces viejos: `/ingresos-asistencia/ingresos?fecha_inicio=2026-09-01&fecha_fin=2026-09-30` lleva al reporte nuevo.
4. Como tesorero: el centro muestra «Asistencia por culto» y «Asistencia por clase»; en la asistencia por culto no hay
   Editar/Eliminar; el enlace «Ver la asistencia por clase de estas fechas» conserva el rango.
5. Como asistente: `/reportes/cultos` y `/reportes/ingresos` dan 403.

## Tiempos medidos (VolumenDevSeeder: 67 cultos, 2.347 sobres en un año; `artisan serve` local con otros 4 equipos corriendo suites)
| Petición (rango 23/11/2025–22/11/2026, tesorero) | Tiempo |
|---|---|
| `/reportes/cultos` | 6,6 s (generador solo: 0,6 s; el resto es la vista de 67 filas + selector, con la máquina cargada) |
| `/reportes/cultos/pdf` | 3,0 s |
| `/reportes/cultos/excel` | 4,3 s |
| `/reportes/ingresos` (dos periodos) | 2,6 s |
| `/reportes/ingresos/pdf` | 2,7 s |
| `/reportes/ingresos/excel` | 3,2 s |
| `/ingresos-asistencia/pdf-asistencia` (un año) | 2,5 s |
| `/ingresos-asistencia/excel-asistencia` (un año) | 3,2 s |
| `/ingresos-asistencia` (centro, referencia) | 2,7 s |
Todos bajo 10 s. Para cumplirlo, el generador lee sobres/detalles/sueltos/egresos como filas simples (sin modelos
Eloquent; con modelos era 4 s solo el cálculo) y no trae las imágenes de las firmas. El rango se limita a un año
(`FiltroCultos::MAX_DIAS = 366`) con aviso «El rango máximo es de un año: …».

## Decisiones tomadas
- «Firmas completas» = firmó el pastor (firma dibujada) y al menos un tesorero (firma dibujada). Se calcula en SQL
  (`JSON_SEARCH` sobre `firmas_tesoreros_imagenes`) para no cargar las imágenes. Si Roy quiere «dos tesoreros», es un
  cambio de una línea en `GeneradorReporteCultos::consulta()`.
- Total de un culto = el de `ResumenCulto::total()` (efectivo = sobres en efectivo + suelto − egresos; más
  transferencias). «Ingresos» = sobres + suelto (antes de egresos); el reporte de ingresos compara ingresos y muestra
  egresos y neto aparte.
- Por método de pago: «Sobres en efectivo», «Sobres por transferencia», «Dinero suelto (efectivo)» (el suelto se
  entrega en efectivo con el recuento, como en `ResumenCulto`).
- Categorías que la iglesia ya no tiene activas (p. ej. `pro_templo`) van a «Otras categorías (ya no activas)» para
  que la suma por categoría cuadre con el total.
- Formato del porcentaje: «+12.5 %» (punto decimal, igual que `Dinero::formato`).
- El reporte de ingresos no usa «Elegir cultos» (es por rango), sí el filtro de tipos.
- La auditoría guarda `personas: 0` (el campo es de los reportes por persona) y en `cultos` cuántos cultos salieron.

## Pendientes y riesgos
- HU-R14 sin hacer: `pdf-asistencia-culto` (PDF de un culto con detalle por clase), `AsistenciaPorClaseExport` y el
  PDF `pdfs/asistencia-clases-rango` (asistencia por clase), `IngresosExport` y `pdfs/asistencia-mes` (los usa
  `app/Console/Commands/EnviarReporteMensual.php`, que no es de este equipo; propuesta: que ese comando use
  `AsistenciaPdf`/`AsistenciaExcel` y `ReporteIngresos` + `IngresosExcel` para el mes, y entonces borrar
  `IngresosExport`, `AsistenciaExport` y `pdfs/asistencia-mes`). `PlantillaAsistenciaExport` NO se toca: es la
  plantilla de importación y `AsistenciaImport` depende de sus columnas.
- `pdf-ingresos-transferencias` sigue con su PDF viejo (`pdfs/ingresos.blade.php`); candidato a pasar al reporte de
  ingresos con un filtro de método.
- Clases Tailwind nuevas (`has-[:checked]:…`, `bg-amber-*` en las casillas) necesitan `npm run build` al integrar.
- No pude hacer la revisión visual en el navegador: el panel del navegador lo usaba otro equipo al mismo tiempo
  (saltaba a otro puerto). Las pantallas se probaron con tests y con curl.
- `/reportes/cultos` con un año completo tardó 6,6 s con la máquina cargada; en Hostinger debería ser menor, pero es el
  más cercano al límite. Si hiciera falta: paginar la tabla de la pantalla (el PDF/Excel ya son rápidos).

## Notas para el manual de usuario (textos exactos)
**Centro de reportes** (Reportes):
- Tarjeta **«Reporte de cultos»**: «Cada culto de un rango: sobres, efectivo, transferencias, suelto, egresos y firmas,
  con totales por categoría y método de pago.» · «Pantalla, PDF y Excel». Admin y tesorero.
- Tarjeta **«Reporte de ingresos»**: «Ingresos de un rango por categoría y método de pago, comparados con el periodo
  anterior.» · «Pantalla, PDF y Excel». Admin y tesorero. (Reemplaza a la tarjeta «Ingresos».)
- Tarjeta **«Asistencia por culto»** (antes «Asistencia»): «Asistencia de cada culto en un rango de fechas y resumen
  por mes.» Ahora también la ve el tesorero.
- Tarjeta **«Asistencia por clase»**: «Niños, niñas, maestros y promedio por clase en un rango de fechas; una clase o
  todas.» Ahora también la ve el tesorero.

**Pantalla «Reporte de cultos»** y **«Reporte de ingresos»** (mismo bloque de filtros):
- Campos **«Desde»** y **«Hasta»**; **«Tipo de culto (ninguno marcado = todos)»** con una casilla por tipo.
- **«Atajos:»** «Este mes», «Mes anterior», «Últimos 3 meses», «Este año».
- Solo en cultos: desplegable **«Elegir cultos (todos los del rango)»** / «(N elegidos)»: «Marque solo los cultos que
  quiere en el reporte; sin ninguno marcado salen todos los del rango.»
- Botones **«Consultar»**, **«Limpiar»** y, a la derecha, «Exportar N cultos:» **«Ver PDF»**, **«Descargar PDF»**,
  **«Descargar Excel»**.
- Aviso si el rango pasa de un año: «El rango máximo es de un año: se muestra del … al …. Para más tiempo, genere un
  reporte por año.»
- Cultos: tarjetas «Cultos», «Sobres», «Ingresos», «Egresos», «Total del rango», «Sin firmas completas»; tabla
  «Cultos (N)» con columnas Fecha, Tipo, Estado, Sobres, Efectivo, Transferencias, Suelto, Egresos, Total, Firmas
  completas; cuadros «Ingresos por categoría» e «Ingresos por método de pago». Estado vacío: «No hay cultos en este
  rango con los filtros elegidos.» + «Amplíe las fechas (por ejemplo con el atajo «Este año»), quite los tipos de
  culto marcados o desmarque los cultos elegidos.»
- Ingresos: tarjetas «Ingresos del periodo», «Periodo anterior», «Variación», «Egresos», «Neto (ingresos − egresos)»;
  tablas «Ingresos por categoría» e «Ingresos por método de pago» con columnas Concepto, Este periodo, Periodo
  anterior, Diferencia, Variación («sin datos antes» cuando el periodo anterior no tuvo ingresos). Enlace
  «Reporte de cultos» en la nota azul.

**Reportes de asistencia**: enlaces nuevos «Ver la asistencia por clase de estas fechas» (Asistencia por culto) y
«Ver la asistencia por culto de estas fechas» (Asistencia por clase). El PDF y el Excel de asistencia tienen el
formato nuevo (encabezado de la iglesia, periodo, resumen, totales y pie con la fecha de generación).

**Buscador**: «Reporte de cultos» (palabras: cultos, recuento, sobres, firmas, transferencias…), «Reporte de ingresos»
(ingresos, categoría, método de pago, periodo anterior…) abre la pantalla nueva.

## Cambios propuestos a archivos de otros equipos
- `app/Console/Commands/EnviarReporteMensual.php` (no asignado a R2): pasar al formato nuevo, ver «Pendientes».
- `resources/views/manual/paginas/07-reportes.md` (G): documentar lo de «Notas para el manual».
