# IBBLA · Briefs de la E2-E4 (cierre de la segunda ronda) — 24/11/2026

Lider tecnico: Claude (sesion principal). Base: `integracion/oct-2026` DESPUES del tag `v2026.11.24-e1`
(E1 ya cerrada: todo lo de 02-PLAN-MAESTRO.md menos lo listado aqui). Objetivo: dejar TODO lo pedido
terminado en una sola entrega mas (`v2026.xx-e2`), con QA integral antes del tag.

## Reglas para todos (las de 00-PLAN.md siguen vigentes)
- Leer CLAUDE.md, README_SISTEMA.md, docs/ROLES.md, docs/UI.md, docs/plan-oct-2026/REGLAS-CALCULO.md y
  docs/plan-oct-2026/QA-ENTREGA.md (secciones 2 y 3) antes de tocar nada.
- Worktree propio YA CREADO en `C:\Users\rgonh\Documents\IBBLA-wt\<slug>` con vendor copiado, .env, base
  propia migrada y sembrada (`ibbla_<x>` y `ibbla_<x>_test`) y puerto asignado. Receta en docs/LOCAL_SETUP.md.
  MySQL de Laragon ya corre en 3306 (root sin clave).
- Tests SIEMPRE en MySQL: `DB_CONNECTION=mysql DB_DATABASE=ibbla_<x>_test php -d memory_limit=1G -d max_execution_time=0 vendor/bin/phpunit --filter ...` (sin el limite de tiempo la suite completa aborta a los 180 s en Windows)
  (linea base: los 15 fallos de Breeze en tests/Feature/Auth/*, ProfileTest y ExampleTest son normales).
  Cada cambio lleva su test. Antes de terminar: suite completa del worktree (solo los 15 de Breeze).
- Blade: `grep -rnE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' resources/views` debe
  salir vacio. `php -l` en cada PHP tocado.
- Regla de calculo (REGLAS-CALCULO.md): esperado = meses COMPLETOS antes del corte; el mes en curso NO se
  cobra; USD con tipo de cambio del sobre; `compromisos` la escribe SOLO `SincronizadorCompromisos`.
  Todo numero que se muestre sale de `App\Services\Reportes\MotorReportes` / `CumplimientoPromesasService`;
  nunca una cuarta calculadora.
- Hostinger: toda peticion < 10 s. PDF grandes con la tecnica de ReporteGeneralPdf (canvas) o paginando.
- Commits en espanol, estilo oracion, explicando el porque. SIN trailer Co-Authored-By. NO push: el lider
  mergea. No tocar `resources/views/layouts/*` ni `app/Support/Navegacion/Menu.php` salvo que el brief lo diga.
- Al terminar: informe corto en `docs/plan-oct-2026/INFORME-<slug>.md` (que se hizo, que falto, como
  probarlo, decisiones tomadas) y ultimo commit. Si una decision es del cliente, se toma la opcion mas
  conservadora y se anota.

## Equipo R — Centro de reportes 3b (opus) — `feat/reportes-3b` — `IBBLA-wt\reportes-3b` — `ibbla_r3b` — puerto 8121
Lo que hay: `app/Services/Reportes/*` (motor unico, `PeriodoReporte`, `FiltroPersonas` con
TODAS/CON_PROMESA/MINISTERIO/SELECCION, `CumplimientoPromesasService`, `EstadoCuentaLoteService`,
`AuditoriaReportes`), exportaciones en `app/Services/Reportes/Exportaciones/*` (plantilla unica PDF/Excel),
controladores `app/Http/Controllers/Reportes/{CumplimientoController,EstadosCuentaController}`, vistas
`resources/views/reportes/*` (selector de personas con chips y autocompletado en `_filtro-personas` y
`_selector-personas-js`), centro de reportes en `resources/views/ingresos-asistencia/index.blade.php`
(tarjetas por rol, del equipo 4). Reportes viejos en `IngresosAsistenciaController` (ingresos, asistencia,
promesas) y `PromesasReporteController` / `DashboardPromesasController`.

HU (de 02-PLAN-MAESTRO.md y HU-reportes.md), en este orden:
1. **HU-R2 selector completo**: "Seleccionar todos los filtrados" / "Quitar todos", contador
   "N personas seleccionadas", filtros rapidos combinables (estado activos/inactivos/todos, ministerio
   multiple, con promesa si/no/todos, aporto en el periodo si/no/todos). Sin seleccion = todos los que
   cumplen los filtros (avisarlo). Seleccion y filtros viajan en la URL. Teclado y tablet (44 px).
   Mismo parcial en Cumplimiento y Estados de cuenta.
2. **HU-R4 vista previa**: tabla ordenable por columna (persona, prometido, dado, diferencia, estado,
   ultimo aporte) y paginada a partir de 100 filas, en Cumplimiento y Estados de cuenta; fila de totales
   siempre visible. Test: los numeros de la vista previa = PDF = Excel.
3. **HU-R10 Reporte de cultos**: por rango de fechas o por cultos elegidos (selector de cultos por fecha y
   tipo); por culto: sobres, totales por categoria, metodo de pago, sueltos/egresos, firmas; totales del
   rango. Pantalla con vista previa + PDF + Excel con la plantilla unica. Tarjeta nueva en el centro.
4. **HU-R11 Reporte de ingresos**: rango de fechas, por categoria y por metodo de pago, comparativo con
   el periodo anterior de igual duracion (monto y %), PDF y Excel con la plantilla unica. Sustituye o
   envuelve el reporte de ingresos viejo (`ingresos`, `pdf-ingresos`, `excel-ingresos`) sin romper sus
   rutas (redireccion o misma ruta con la nueva pantalla).
5. **HU-R12 Asistencia en el centro**: las tarjetas de asistencia del centro llevan rango y clases
   (reusar `AsistenciaClasesReporteController`); nada nuevo de calculo, solo integracion y consistencia.
6. **HU-R14 plantilla unica en lo viejo**: `pdf-promesas`, `pdf-promesas-anual`, `pdf-asistencia*` y los
   Excel de `app/Exports/*` pasan a la plantilla unica (encabezado iglesia/periodo/filtros, totales,
   pie con fecha). Sin cambiar las cifras.
7. **QE-008**: Dashboard de promesas (`DashboardPromesasController`, `dashboards/promesas`) y Reporte de
   promesas (`PromesasReporteController`) usan la regla del motor (meses completos; el mes en curso se
   muestra aparte y rotulado "en curso, no se cobra") y el mismo formato de miles (`Dinero`).
8. **QE-011**: en Cumplimiento, "Ultimo aporte" con la misma base que "Ha dado" (solo categorias
   prometidas) y rotulo "Ultimo aporte a sus compromisos".
9. **QE-020 (parte)**: `/promesas` (admin) da 500 "Call to a member function sum() on null": arreglar o
   redirigir a la pantalla nueva.
Criterios: cada reporte nuevo < 10 s con el VolumenDevSeeder (150 personas, un anio de cultos);
auditoria de cada exportacion con `AuditoriaReportes`; permisos segun docs/ROLES.md (admin y tesorero);
tarjetas nuevas en el centro con una linea de que contiene y formatos; entradas en el buscador global
(`app/Support/Navegacion/Menu.php` SI se puede tocar solo para agregar entradas de reportes) y pagina
del manual `resources/views/manual/paginas/07-reportes.md` actualizada.

## Equipo F — Pendientes de QA (opus) — `fix/qa-pendientes` — `IBBLA-wt\qa-pendientes` — `ibbla_fix` — puerto 8122
Detalle de cada hallazgo en docs/plan-oct-2026/QA-ENTREGA.md (QE-xxx, seccion 2) y QA-INICIAL.md (QA-xxx).
1. **QE-009** avisos de WhatsApp fuera de la peticion: `app/Jobs/EnviarAvisoWhatsApp` nunca corre dentro
   del guardado. Diseno: tabla/cola propia de avisos pendientes + comando `whatsapp:enviar-pendientes`
   programado cada minuto en `routes/console.php` (el cron `schedule:run` existe en Hostinger) y
   `dispatch()->afterResponse()` como atajo cuando el servidor lo soporte. Test de que crear culto y
   guardar el 10.º sobre no llaman al proveedor.
2. **QE-012** el tesorero no ve ni descarga reportes de asistencia (segun docs/ROLES.md): rutas 403 y
   botones ocultos; tests por rol.
3. **QE-013** auditoria: `recuento.cerrar-culto` deja registro semantico; sin duplicados (cuando hay
   registro semantico, el middleware generico no escribe el suyo); las visitas GET y columnas tecnicas
   fuera de la pantalla de auditoria (o filtro "solo acciones" por defecto); un acceso sobre un culto
   borrado deja de verse "vigente".
4. **QE-014** barra del sobre: "Sin persona" hasta marcar «Sobre anonimo»; con un resultado, Flecha abajo +
   Enter elige la persona, no "crear nueva"; el buscador del sobre muestra PIN y telefono.
5. **QE-015 / QA-021** asistencia cerrada = solo lectura como en Recuento (el asistente ve en gris con
   motivo; el admin corrige con aviso y queda auditado).
6. **QE-016** App de Clases: tesorero sin la opcion y 403 en `/app/clase`; miembro sin clase ve un mensaje
   claro ("No tenes una clase asignada; pedile al administrador...").
7. **QE-018** tildes y gramatica: "Miercoles"/"Sabado" (tipos de culto), "Nino/Nina/Asignalos",
   "confirmacion", "con sus 1 sobres" -> singular/plural. Categorias "Construccion"/"Prestamo" son datos:
   migracion de datos que corrige SOLO esos nombres exactos en `tenant_categories` + seeder.
8. **QE-019** Principal: subtitulos de los accesos en dos lineas, sin "…".
9. **QE-020 (parte)** `/usuarios/{id}` -> 404 limpio o redireccion a la lista; revisar que ninguna ruta
   vieja muestre la pagina de error de Laravel (lista en QA-019).
10. **QA-018** Usuarios: al crear/editar un usuario se puede vincular la persona; Personas > Editar que crea
    el usuario le pone `tenant_role_id` (`PersonaController` ~l. 268).
11. **QA-034** "Eliminar personas inactivas" y "Borrar todas las promesas": pantalla de confirmacion propia
    (que se va a borrar, cuantos, escribir CONFIRMAR), solo admin, auditoria con detalle; quitar el
    comentario "temporal".
12. **QA-036** `PromesaController::update` no borra y recrea las promesas (actualiza en sitio, conserva
    ids e historial); mensaje "Compromisos reiniciados correctamente desde ..." en espanol con mes en
    espanol.
13. **QA-043** Dashboard: "Los ingresos bajaron 74,8 % respecto a octubre" el dia 5 -> comparar con el
    mismo dia del mes anterior o rotular "mes en curso (5 dias)"; "202 de 206 promesas aun pendientes
    este mes" -> el mes en curso no se cobra (regla del motor): mostrar pendientes al ultimo mes completo.
14. **QE-017 (resto)** `php artisan ibbla:verificar` ademas avisa si `QUEUE_CONNECTION` no es `sync` y si
    el cron no corre (una tarea programada cada minuto escribe `cache('ibbla:ultimo_schedule')`; verificar
    FALLA si tiene mas de 10 min). Documentar en docs/DEPLOY-HOSTINGER.md.
Criterios: un commit por hallazgo (o por grupo pequeno) con "QE-xxx"/"QA-xxx" en el mensaje; tests por
cada uno; docs/ROLES.md y las paginas del manual afectadas al dia; nada de cambios de diseno general.

## Equipo G — Guia de usuario final y ambiente QA (sonnet) — `feat/guia-ambiente-qa` — `IBBLA-wt\guia-ambiente-qa` — `ibbla_gqa` — puerto 8123
Fase 1 (ahora):
1. **Ambiente QA en Hostinger** (02-PLAN-MAESTRO E3): `deploy/hostinger.sh --qa` despliega la rama
   `integracion/oct-2026` (o `--branch`) en `/home/u598753224/domains/qa.ibbla.eu/public_html` con su
   propio `.env` (BD `u598753224_ibbla_qa`), respaldo y chequeo contra `https://qa.ibbla.eu`;
   `--allow-path` ya no hace falta para esa ruta. Banner "AMBIENTE DE PRUEBAS" en el layout cuando
   `APP_ENV=qa` (unico toque permitido en layouts: un parcial incluido desde `layouts/admin` y `guest`),
   `robots.txt`/meta noindex en QA, `ibbla:verificar` lo reporta. Documento docs/AMBIENTE-QA.md con los
   pasos de hPanel que hace David (subdominio, BD, usuario, clonar, `.env`, cron) y como refrescar QA con
   una copia de produccion (`mysqldump` + import, sin datos de WhatsApp: vaciar claves CallMeBot).
2. **Manual completo en PDF**: `ManualController`/`App\Support\Manual\ManualPdf` generan el manual entero
   (indice, todas las paginas, capturas) en un solo PDF descargable desde el manual y desde el centro de
   reportes; < 10 s o generado por comando `manual:pdf` y servido como archivo.
3. **Revision de cada pagina del manual** contra la app tal como esta en la base (E1): cada boton
   explicado (que hace, quien puede, que pasa despues), capturas regeneradas con `tools/manual/capturar.mjs`
   donde cambiaron; "?" contextual con ancla propia en Cumplimiento y Estados de cuenta (pendiente de QE-003).
Fase 2 (cuando el lider avise, tras integrar R y F): documentar lo nuevo (reportes de cultos e ingresos,
selector completo, avisos en cola, asistencia cerrada, confirmaciones) y regenerar las capturas afectadas.

## Equipo Q — QA integral (opus) — `qa/revision-final` — `IBBLA-wt\qa-revision-inicial` — `ibbla_qa` — puerto 8111
Arranca cuando R, F y G esten integrados. Misma metodologia que QA-ENTREGA.md (roles admin/tesorero/
asistente/miembro, escritorio/tablet/movil, cifras cruzadas al centimo entre pantalla/PDF/Excel/WhatsApp,
tiempos < 10 s con VolumenDevSeeder). Verifica uno por uno los 20 QE y los 43 QA (estado final), prueba lo
nuevo (R, F, G) y entrega docs/plan-oct-2026/QA-FINAL.md con veredicto GO/NO-GO y hallazgos numerados QF-xxx.
No corrige: reporta.

## Orden de integracion y cierre (lider)
R -> F -> G (fase 1) -> suite + build -> G fase 2 + Q en paralelo -> correcciones de los QF que frenen ->
suite + build -> merge a main + tag `v2026.xx-e2` + push -> David despliega con `deploy/hostinger.sh --tag`
-> `compromisos:sincronizar`, `ibbla:verificar` -> nota a Roy.
Fuera de alcance de los agentes (depende del cliente): lista de 30 ministerios, volcado de produccion para
`ingresos:analizar`, cuenta Meta (bot WA-2 solo si sale gratis; CallMeBot es lo vigente), rotacion de
credenciales viejas de IBBSC en Hostinger.
