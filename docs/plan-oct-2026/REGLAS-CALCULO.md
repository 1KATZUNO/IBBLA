# Reglas de cálculo de los reportes de dinero (aplicadas en el código)

Fuente: sección 2 de `04-DECISIONES.md` (reglas fijadas por el líder el 04/11/2026). Este documento
dice **cómo quedaron implementadas** y dónde. Si una pantalla o un PDF da otro número, es un error.

## 1. Un solo cálculo

Todo sale de `App\Services\Reportes\MotorReportes` (una persona o un grupo, un periodo):

| Salida | Cómo llega al motor |
|---|---|
| Estado de cuenta PDF (ficha, Compromisos, lista, WhatsApp, Mi perfil) | `InformePersonaService::datos()` |
| Pantalla de Compromisos (admin) | `InformePersonaService::datos()` (año en curso: corte hoy; años pasados: al 31/12) |
| Mi perfil (miembro) | `InformePersonaService::datos()` |
| Ficha de la persona ("Dado en el año", estado) | `InformePersonaService::datos()` |
| Estados de cuenta en lote (PDF, ZIP, Excel) | `EstadoCuentaLoteService` → `InformePersonaService::datosDeCuenta()` |
| Cumplimiento de promesas con corte (pantalla, PDF, Excel) | `CumplimientoPromesasService` |
| Reporte General (PDF) | `ReporteGeneralService` |
| Reporte PDF de contribuciones | `PersonaController::reportePdf` + `ContribucionesPdf` |
| Reporte de promesas por categoría (pantalla, PDF mensual y anual): lo prometido | `PromesasReporteController::datosDelAnio()` |
| Tabla `compromisos` (Dashboard de promesas, Excel de promesas, Servidores) | `SincronizadorCompromisos` |

El test `tests/Feature/Reportes/ContratoReportesTest.php` compara, para 3 personas y 2 días, todas
estas salidas al céntimo.

## 2. Periodo y corte

- **Nada posterior a hoy cuenta.** Si el periodo pedido termina después de hoy, el motor lo recorta a
  hoy (`PeriodoReporte::recortadoAHoy()`): el título pasa a "Corte al dd/mm/aaaa".
- **Lo dado** cuenta todos los sobres de los cultos con fecha entre el inicio y el corte, **incluido el
  mes en curso**.
- **Lo esperado (le corresponde)** se cobra solo por **meses completos anteriores al corte**: el mes en
  curso no se cobra (salvo que el corte sea su último día). Un mes a medias al inicio de un rango de
  fechas exactas tampoco se cobra.
- Los reportes "del año" van de enero al corte. Un año pasado va de enero a diciembre.

## 3. Lo esperado por promesa

- Si la persona tiene **varias promesas en la misma categoría**, lo esperado es la **suma** de todas,
  cada una con su frecuencia y su moneda; el detalle las lista ("₡10.000 cada domingo + $20 mensual").
  En la tabla `compromisos`, si mezclan colones y dólares, `monto_prometido` se guarda en colones.
- Solo categorías de compromiso: las de la iglesia **no** marcadas `excluir_de_promesas` (el diezmo
  prometido no se espera).
- Por mes: **semanal × domingos del mes**, **quincenal × 2**, **mensual × 1**.
- Promesas en dólares: cada mes al **tipo de cambio de venta vigente el último día de ese mes**; el mes
  en curso (o uno futuro) al de hoy.

## 4. Lo dado

- Se muestra todo: el estado de cuenta, el Excel y el reporte de contribuciones traen **todas** las
  categorías (diezmo, ofrendas, categorías que la iglesia ya no usa).
- Para el estado (al día / pendiente) solo cuenta lo dado en las **categorías en que la persona tiene
  promesa** (monto > 0). El diezmo y las categorías sin promesa no cuentan.
- El Reporte General ("dado vs esperado") solo muestra lo dado en las categorías prometidas de cada
  persona; quien no tiene promesa no aparece (lo que dio está en el reporte de contribuciones y en su
  estado de cuenta).
- En el cumplimiento, quien no tiene promesa pero aportó en categorías de compromiso sale aparte
  ("Aportan sin promesa registrada") con ese monto, solo informativo.

## 5. Estado

- **AL DÍA** si `dado acumulado + ₡1 ≥ esperado acumulado` (tolerancia de 1 colón por redondeos de
  dólares). **PENDIENTE** si no. **SIN PROMESA** si no tiene promesas.
- Se decide **siempre por el acumulado** al corte, nunca mes a mes: quien no dio un mes y repuso
  después está al día.
- El mes a mes se muestra **solo como detalle informativo** (Compromisos y Mi perfil, plegado): "dado /
  del mes", con el mes en curso marcado "en curso, no se cobra". No hay "deudas" por mes.

## 6. Dólares (`App\Support\Moneda\ConversorUsd`)

Helper público, también para el recuento y sus PDF (equipo 2, QA-026):

```php
$conversor = app(\App\Support\Moneda\ConversorUsd::class);           // lee tipo_cambios una vez
$colones = $conversor->sobreAColones($detalle->monto, $sobre->moneda, $sobre->tipo_cambio_venta, $culto->fecha);
$tc = $conversor->tipoCambioDeSobre($sobre->tipo_cambio_venta, $culto->fecha); // para mostrarlo
$mes = $conversor->promesaAColones($montoDelMes, $promesa->moneda, $anio, $mes);
```

- Sobre en USD: tipo de cambio **guardado en el sobre**; si no tiene, el de venta **vigente a la fecha
  del culto** (el de ese día o el último anterior, tabla `tipo_cambios`); si tampoco hay, el de **hoy**.
- Se redondea a 2 decimales **por monto convertido**. Para que una tabla y su total cuadren hay que
  convertir cada detalle y sumar los convertidos (no convertir el total del sobre aparte).
- Sin ningún tipo de cambio cargado el monto queda igual (no hay con qué convertir).
- Nota: `cultos.tipo_cambio_venta` (el que se congela al cerrar el culto) **no** se usa para convertir
  sobres; la regla es la fecha del culto. Si el cliente prefiere el del cierre, se cambia solo en
  `ConversorUsd::tipoCambioDeSobre()`.

## 7. Tabla `compromisos` (QA-025)

- **Ninguna pantalla escribe** en ella. La llena solo `SincronizadorCompromisos`:
  - al **cerrar un culto** (su mes; `App\Observers\CierreCultoObserver`),
  - con el botón **"Recalcular compromisos del año"** (admin, en el Dashboard de promesas),
  - con `php artisan compromisos:sincronizar [--tenant=ID] [--anio=AAAA] [--mes=M]`.
- **Idempotente** (una segunda corrida no escribe nada) y **acotado** a los meses del año con al menos un
  culto hasta hoy. Personas: activas con promesa. Borra filas de meses posteriores al mes en curso y las
  de promesas que ya no existen.
- Columnas: `monto_prometido` (moneda de la promesa, por la frecuencia), `monto_dado` (como se anotó en
  los sobres), `monto_prometido_crc` / `monto_dado_crc` en colones con estas reglas (lo prometido del
  mes en curso se guarda como dato), `saldo_actual = dado_crc - prometido_crc` del mes,
  `tipo_cambio_usado` = el de fin de mes si la promesa es en dólares.
- El Dashboard de promesas suma las columnas en colones (`*_crc`).

## 8. Reporte de promesas por categoría

- Prometido: lo del motor (personas activas, suma de las promesas de cada categoría, dólares a fin de mes). Meses
  futuros: 0.
- Dado: **todo lo que entró** a cada categoría de compromiso en el mes (incluidos anónimos y quien no
  prometió), con los dólares convertidos con `ConversorUsd`. Por eso su "dado" es mayor que el del
  Dashboard de promesas, que solo cuenta a quien prometió. "Profit" pasó a llamarse "Excedente".
- Pendiente (no tocado): el reporte **por clase** (`porClase`) todavía suma los sobres en dólares sin
  convertir.

## 9. Permisos de los reportes (regla 2.5, QA-010)

| Reporte / descarga | Admin | Tesorero | Asistente | Miembro |
|---|---|---|---|---|
| Reporte General, contribuciones, estado de cuenta de cualquier persona, cumplimiento, lote | Sí | Sí | No | No |
| Excel de ingresos y de promesas | Sí | Sí | No | No |
| Reportes de asistencia (pantalla, PDF, Excel, por clase) | Sí | Sí | Sí | No |
| Registrar / cerrar asistencia | Sí | No | Sí | No |
| Recalcular compromisos | Sí | No | No | No |
| Su propio estado de cuenta y Mi perfil | — | — | — | Sí |

(El Dashboard de asistencia para el asistente, regla 2.7, queda para el equipo 2.)
