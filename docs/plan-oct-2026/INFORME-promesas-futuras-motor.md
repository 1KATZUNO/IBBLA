# Informe · Promesas futuras · Equipo M (motor y activación)

Rama `feat/promesas-futuras-motor` (sale de `feat/promesas-futuras`, b185fad). Especificación:
`06-PROMESAS-FUTURAS.md`, secciones 3 y 5. No se tocó ningún archivo del Equipo P.

## Qué se hizo

| Punto | Estado | Archivos |
|---|---|---|
| 3.1 Activación | Hecho y probado | `app/Services/Promesas/ActivadorCortes.php`, `app/Console/Commands/ActivarCortesPromesas.php`, `app/Http/Middleware/ActivarCortesPromesas.php`, `routes/console.php`, `bootstrap/app.php` |
| 3.2 Motor | Hecho y probado | `app/Services/Reportes/PromesasPorPeriodo.php` (nuevo), `MotorReportes`, `CuentaPersona`, `CumplimientoPromesasService` (sin cambios: ya usa `CuentaPersona`), `ResumenPromesasService`, `CandidatasSelector`, `FiltroPersonas`, `SincronizadorCompromisos` |
| 3.3 Pruebas de aceptación | Todas, verdes | `tests/Feature/Reportes/PromesasFuturasMotorTest.php` (11 pruebas) |
| 4 `ibbla:verificar` | Hecho y probado | `app/Console/Commands/VerificarIbbla.php` |

## Cómo funciona el tramo por mes

`PromesasPorPeriodo` es la única clase que decide qué promesas rigen cada mes:

- **Frontera** de una iglesia = `inicia_el` del último corte **activado** (`MAX(inicia_el)` de
  `cortes_promesas` con `activado_at`). Se saca con una consulta liviana por lote de personas
  (join `personas` ↔ `cortes_promesas`), así funciona con personas de varias iglesias y sin sesión.
- Mes **anterior a la frontera** → las filas de `promesas_historial` de la persona cuyo tramo
  [`vigente_desde`, `vigente_hasta`] cubre el mes (los tramos van de día 1 a fin de mes, así que se
  comparan por clave `2026-12`). Si la persona no tiene ninguna, ese mes no tenía promesa. **No** se
  cae a `promesas` (eso pondría las de 2027 en 2026 a quien entró nuevo en el corte).
- Mes **desde la frontera** (o cualquier mes si la iglesia nunca activó un corte) → `promesas`.
- Camino feliz: si el periodo no tiene ningún mes anterior a la frontera (o no hay cortes
  activados), el motor hace exactamente lo de siempre (las mismas promesas todos los meses,
  `CuentaPersona` recibe `promesasPorMes = null`). Con historial: una consulta más por lote.

En `CuentaPersona`, cuando las promesas cambian dentro del periodo:
- lo esperado de cada mes sale de las promesas de ESE mes;
- «ha dado» (estado) suma, mes por mes, lo dado en las categorías prometidas ESE mes;
- `tienePromesa()` = tuvo alguna con monto en algún mes del periodo;
- `categoriasConPromesa()` sin mes = unión en el orden de la iglesia; con mes = las de ese mes;
- `promesas()` = el juego del **último mes** del periodo (lo que se muestra como «la promesa»);
- nuevo `promesasDelMes($clave)` (lo usa el sincronizador de compromisos).

«Con promesa» en el selector y en el filtro de personas (`conPromesaEnPeriodo`): con un periodo
que toca meses anteriores a la frontera, es tener historial con monto que se solape con el periodo
o, si el periodo llega a la frontera, tener promesa hoy. Sin cortes, la regla de siempre.

## Activación (`ActivadorCortes`)

`activar($corte, $por)`: en una transacción, bloquea el corte (`FOR UPDATE`); si ya está activado
no hace nada (idempotente, devuelve `false`). Copia las `promesas` de las personas de la iglesia a
`promesas_historial` (`vigente_hasta` = víspera del corte, `vigente_desde`/`corte_id` = el corte
activado anterior o null), las borra, inserta las de `promesas_programadas` (que se conservan),
activa `categorias_nuevas` y marca `activado_at`/`activado_por`. Tras el commit sincroniza
`compromisos` mes a mes desde `inicia_el` hasta el mes actual y deja «Activar corte de promesas» en
la auditoría con la iglesia del corte (también desde el cron, sin sesión). Si la sincronización
falla no se deshace la activación: queda en el log (se arregla con `compromisos:sincronizar`).

`activarPendientes($tenantId = null)`: los programados con fecha cumplida **y al menos una promesa
programada**, del más antiguo al más nuevo. Todo con `withoutGlobalScopes()` y `tenant_id` explícito.

Disparadores:
- `php artisan promesas:activar-cortes [--tenant=N]`, en el planificador `everyTenMinutes()` con
  `withoutOverlapping(10)`.
- Middleware `ActivarCortesPromesas` (grupo web, después de `ResolveTenant`): solo con usuario de
  una iglesia; `Cache::add` de 60 s por iglesia como candado; cuando no hay nada que activar es una
  sola consulta (probado contando consultas). Nunca rompe la petición (errores al log).

## Decisiones

1. `activar()` devuelve `bool` (la especificación decía `void`): permite contar y probar la
   idempotencia. P no lo llama, no afecta el contrato.
2. Un corte anterior a otro ya activado (misma fecha o posterior) no se activa: rompería la cadena
   de tramos. Queda en el log.
3. «Ha dado» por mes con las categorías de ese mes (y no la unión del periodo), igual en
   `ResumenPromesasService` («Dado» del reporte de promesas = «Ha dado» del cumplimiento). Sin
   cortes es idéntico a antes.
4. `ResumenPromesasService` sin personas ahora pasa el periodo a `FiltroPersonas::personas()`, para
   que «activas con promesa» sea la del periodo (si no, un reporte de 2026 tomaría a la gente de 2027).
5. `SincronizadorCompromisos` toma también a las personas activas con historial en ese año
   (subconsulta extra en el `where`, mismo resultado sin historial).

## Cómo probarlo

- Pruebas: `DB_CONNECTION=mysql DB_DATABASE=ibbla_r3b_test php -d memory_limit=1G vendor/bin/phpunit --filter PromesasFuturasMotorTest`.
  Cubren: (1) corte programado con categoría nueva inactiva → huella completa (motor por persona y
  mes, cumplimiento, resumen de promesas, selector, filtro con/sin promesa, Reporte General y
  estado de cuenta) y `compromisos` idénticos; (2) activado → periodo anterior idéntico persona por
  persona y mes por mes (y `compromisos` de esos meses idénticos aun resincronizando el año),
  periodo posterior con las promesas nuevas (Carla sin esperado ni «con promesa», Nora nueva sí),
  periodo que cruza mes por mes, tablas `promesas`/`promesas_historial`/programadas y categoría
  activa; (3) doble activación, corte vacío, 2027→2028 encadenados, corte fuera de orden;
  (4) compromisos antes/después y segunda sincronización sin cambios, comando con dos iglesias,
  entrada del planificador (`*/10 * * * *`), disparo perezoso con candado, una sola consulta cuando
  no hay nada, e `ibbla:verificar`.
- A mano (sin pantallas de P): crear un corte y sus `promesas_programadas` en BD con `inicia_el` ya
  cumplido, `php artisan promesas:activar-cortes`, y comparar un reporte de meses anteriores.
- Ensayo en `ibbla_r3b` (150 personas, script en transacción con rollback): corte al 01/11/2026 con
  dos tercios de las promesas (+10 %) → huella de Cumplimiento ene–oct **idéntica** para las 140
  personas listadas.

## Tiempos medidos (ibbla_r3b, 150 personas)

| Qué | Tiempo | Consultas |
|---|---|---|
| Cumplimiento ene–oct, sin cortes | 81 ms | 7 (antes 6: +1 de la frontera) |
| Cumplimiento ene–oct, con corte activado | 85 ms | 8 |
| Cumplimiento ene–nov (cruza el corte) | 90 ms | 8 |
| Activar el corte (con compromisos de noviembre) | 156 ms | 170 |

## Suite

Suite completa en MySQL (`ibbla_r3b_test`): 991 pruebas, 5.829 aserciones, 14 fallos, todos los de
Breeze (Auth/*, ProfileTest, ExampleTest). 6 min 33 s. Lint de Blade vacío (no se tocaron vistas).

## Pendientes y riesgos

- **Reporte General**: lista como columnas las categorías de compromiso **activas**, así que tras
  activar un corte con una categoría nueva, un Reporte General de meses anteriores trae esa columna
  en cero (los números son idénticos; la prueba lo comprueba). Es lo mismo que pasa hoy al crear
  cualquier categoría. Si se quiere ocultar: en `ReporteGeneralService` (no es de M), mostrar solo
  categorías con algo prometido o dado en el periodo.
- **Estado de cuenta en un periodo que cruza el corte** (`InformePersonaService`, no es de M): la
  tabla «Compromisos» se arma con `promesas()` (juego del último mes), mientras el resumen (le
  corresponde / ha dado) suma todos los meses. En un periodo que cruza, una categoría que solo se
  prometió antes del corte no tendría fila y la tabla no cuadraría con el resumen. Hoy el estado de
  cuenta es «del año» y el corte de 2027 es el 1 de enero, así que no cruza; si se quiere cubrir:
  iterar `categoriasConPromesa()` y usar `esperadoCentimos(categoria: …)` (ya es por mes) en vez de
  `promesas()`; la descripción, con la promesa del último mes en que existió. Lo mismo, en menor
  grado, en `ReporteGeneralService` (usa la unión de categorías para lo dado de cada mes).
- `promesas` no se lee por fecha en la ficha, Servidores ni el Excel de personas: siguen viendo lo
  que rige hoy (como dice la especificación).
- El disparo perezoso activa con el usuario de la petición en `user_id` de la auditoría; los
  detalles dicen «sistema (fecha de inicio cumplida)».
- Producción: nada exclusivo de MySQL 8 (solo `MAX`, `GROUP BY`, `FOR UPDATE`, subconsultas).
  Después del deploy, `php artisan ibbla:verificar` muestra la línea «Cortes de promesas».
