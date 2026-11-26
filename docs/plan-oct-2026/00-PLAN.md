# IBBLA · Plan octubre 2026

Lider tecnico: Claude (sesion principal). Equipo: 4 agentes dev + 1 QA.
Rama de integracion: `integracion/oct-2026` (sale de `main`). Cada item en `feat/<slug>`.
`main` NO se toca hasta cierre de fase. Deploy a Hostinger (ibbla.eu) al cerrar cada fase.

## Origen (Roy, 28/08 - 23/09/2026)
1. Reporte de asistencia por clase por rango de fechas: ninos, ninas, maestros y
   PROMEDIO del rango. Exportable PDF / CSV / Excel con el promedio ya calculado.
2. Dashboard filtrable por rango de fechas (hoy solo anio + mes).
3. Cargar los 30 ministerios reales (Roy pasa la lista) + poder agregar
   ministerios manualmente desde la app.
4. Historial de ingreso a la plataforma: quien entro, cuando y a que hora.
5. Asistencias: el tope de 100 por casilla se queda corto; subirlo.
6. "Reporte General" (dado vs esperado) en Gestion de Personas no genera:
   504 Gateway Time-out de nginx.
Del lider del proyecto (David):
7. Revision visual/usabilidad completa, tablet a PC: botones muy abajo,
   flujos dificiles. Debe ser facil de usar.
8. WhatsApp desde la plataforma: numero virtual; enviar el informe de una
   persona con un clic; avisos a roles (culto creado, >=10 sobres, etc).
9. Guia de usuario.

## Fases
- FASE 1 (ahora): items 1-7. Entrega al cliente al cerrar.
- FASE 2: item 8 (WhatsApp) + QA integral + ambiente QA.
- FASE 3: item 9 (guia de usuario).

## Asignacion Fase 1
| Agente | Modelo | Rama | Alcance |
|---|---|---|---|
| A | opus | feat/reporte-general-504 | Entorno local (MySQL Laragon, receta en docs/LOCAL_SETUP.md) + fix del 504 del Reporte General (item 6) con medicion antes/despues |
| B | opus | feat/ux-responsive | Item 7: layouts, navegacion, formularios, tablet->PC; rebuild Tailwind |
| C | sonnet | feat/asistencia-reporte-rango | Items 1 y 5 |
| D | sonnet | feat/dashboard-ministerios-ingresos | Items 2, 3 y 4 |
| QA | opus | (sobre integracion) | Visual + funcional al cerrar merges; reporta bugs a los devs |

## Reglas para todos
- Leer CLAUDE.md y README_SISTEMA.md antes de tocar nada.
- Worktree propio (ya creado en C:\Users\rgonh\Documents\IBBLA-wt\<slug>). vendor se copia con
  robocopy desde el repo principal (NUNCA junction). DB propia: ibbla_<slug>.
- Verificar: php -l; Blade sin directivas pegadas a letra
  (grep -nE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' vacio);
  phpunit del feature cuando aplique; prueba manual con artisan serve.
- Commits en espanol, estilo oracion como el historial, explicando el porque.
  Sin trailer Co-Authored-By. No push: el lider mergea a integracion.
- No tocar archivos ajenos al alcance. Los layouts son del agente B.

## Estado
- 2026-10-11: plan creado, vendor instalado en repo principal, rama integracion creada.
- 2026-10-11 (tarde): C MERGEADO en integracion (4952b56). Pendiente al integrar todo: enlace de menu
  "Asistencia por clase" (ruta ingresos-asistencia.asistencia-clases, hub Reportes es admin/tesorero y la
  pantalla admin/asistente), `npm run build`, posible conflicto con B en asistencia create/edit (aceptar C).
  Preguntas para el cliente (de C): fechas con todo en 0 se tratan como sin registro (hay casilla para
  contarlas); app movil guarda todos como ninos; capilla no entra como fila; bug previo: POST
  /asistencia-app/asistencia/capilla da 500 si el culto no tiene asistencia (total_asistencia sin default).
  Tests: correr con MySQL (DB ibbla_<x>_test); SQLite no sirve (MODIFY COLUMN); 15 tests Breeze fallan de base.
- 2026-10-11 (tarde): FASE 2 decidida SIN Meta (nunca llego el codigo de Facebook). Agente E (sonnet) en
  feat/whatsapp-enlace: boton "Enviar por WhatsApp" = PDF en enlace firmado 7 dias + wa.me con mensaje;
  panel "Avisos pendientes" para culto creado / >=10 sobres; capa de proveedor link|meta.
  Plan semanal 57% al lanzar E (tope 70%). Aun corren A, B, D.
- 2026-10-11 (noche): A y D MERGEADOS en integracion (d8c98e9 y 214e082), sin conflictos. Suite completa
  en MySQL: 232 tests, 217 pasan, los 15 fallos son los de Breeze de la linea base. Lint Blade limpio.
  A: Reporte General pasa de 109-240 s a ~2 s (ReporteGeneralService + ReporteGeneralPdf dibujan sobre el
  canvas de DomPDF; la vista pdfs/reporte-general.blade.php se borro); VolumenDevSeeder solo local;
  docs/LOCAL_SETUP.md commiteado. D: dashboards por rango (RangoFechas, parcial dashboards/_filtro-rango,
  pestanas General|Asistencia|Promesas), ministerios editables (config/ministerios.php + seeder idempotente,
  toggle, bloqueo al eliminar con personas), historial de ingresos (RegistrarAccesoSubscriber ->
  audit_logs con tenant_id nuevo, pantalla admin/auditoria/ingresos + CSV, audit:purge conserva 365 dias).
  Repo principal ahora con .env local (ibbla_int / ibbla_int_test; el .env copiado de IBBSC quedo en
  .env.backup, ignorado por git). Servidor de integracion: php artisan serve --port=8100.
  PENDIENTE al entrar B: su menu de layouts/admin es una lista por rol; agregar ahi
  ingresos-asistencia.asistencia-clases, admin.auditoria.ingresos y lo de E (avisos). Luego npm run build.
  Deploy: migrate --force (audit_logs.tenant_id) + config:clear + db:seed --class=MinisterioSeeder --force
  cuando llegue la lista de 30 ministerios (editar config/ministerios.php).
  Decisiones de D a confirmar con el cliente: Asistencia y Promesas abren en el anio (MODO_ANIO), general en
  el mes; promesas se evaluan por meses completos. Hallazgos fuera de alcance: .env.example versionado con
  credenciales reales de IBBSC (rotar y limpiar), vendor/ sin ignorar, login fallido muestra "auth.failed"
  (sin carpeta lang), /admin/auditoria no filtra por iglesia, N+1 en ServidorController::pdfReporteGeneral.
  Uso del plan: semanal 59% (5 h 60%). Siguen B (opus) y E (sonnet). QA solo si al terminar B queda bajo ~65%.
- 2026-10-11 (cierre por tope de uso, 5 h al 71 %, semanal 61 %): B y E DETENIDOS a mitad de integrarse
  entre si; nada perdido, todo commiteado en sus ramas, NADA de B ni E esta en integracion todavia.
  B feat/ux-responsive (8 commits, 63cb2ee..eaa5467): layout con menu lateral completo por rol y ocultable,
  recuento/asistencia con Guardar fijo y acciones de 44 px, personas con buscar/agregar arriba y formularios
  por secciones, cultos/clases/usuarios/servidores en tablet, principal con accesos por rol, app de clases
  con Tailwind, y el menu YA incluye "Asistencia por clase" y "Avisos de WhatsApp" (eaa5467). Capturas
  antes/despues sin commitear en IBBLA-wt/ux-responsive/docs/ux/*.jpg.
  E feat/whatsapp-enlace (5 commits + WIP 906f568): config/whatsapp.php con proveedor link (wa.me) y driver
  meta listo; informe individual PDF con enlace firmado 7 dias; ruta "enviar por WhatsApp" con mensaje;
  avisos a lideres (culto creado, sobres alcanzados) + panel; botones en personas/index, personas/show y
  compromisos/show. El WIP adapta panel y boton al layout de B; falta correr sus tests.
  PROXIMA SESION, en este orden: (1) merge de B en integracion; (2) merge de E encima resolviendo
  personas/index, personas/show y compromisos/show (B reestructuro, E solo agrega el boton: conservar la
  estructura de B e insertar el boton/parcial de E); (3) suite completa en MySQL + lint Blade; (4) npm run
  build (D usa clases emerald/pink/sky que no estan en el build); (5) QA opus sobre integracion con
  revision visual tablet/PC; (6) cierre: merge a main + deploy Hostinger (migrate --force, config:clear,
  view:clear, seed de ministerios cuando llegue la lista).
  DECISIONES ABIERTAS DEL CLIENTE: proveedor real de WhatsApp (1 Meta con otra cuenta + fijo de la iglesia,
  recomendado; 2 puente no oficial con chip + VPS, con riesgo de bloqueo; 3 quedarse en wa.me); a que chat
  van los avances (+506 8676 3570 pedido, pero sin numero propio de la app no hay desde donde mandar);
  lista de 30 ministerios; preguntas de C y D.
- 2026-10-12: B y E INTEGRADOS (4d42c43 y 1766c44). Conflictos en personas/index, personas/show y
  compromisos/show resueltos con la estructura de B mas el boton de E; el componente whatsapp-enviar gano
  el modo compacto (solo icono, clase btn-icon) para la fila de la tabla. Assets reconstruidos (npm run
  build con node_modules en junction a IBBLA-wt/ux-responsive). Suite: solo los 15 fallos de Breeze.
  Humo con VolumenDevSeeder en ibbla_int (150 personas): lista 0,6 s, ficha y compromisos 0,8 s,
  wa.me con mensaje listo, informe firmado PDF 2,6 s, sin firma 403, Reporte General 4,1 s, avisos 200.
  NUEVO PEDIDO (12/10): revisar reportes por persona y hacer un Centro de Reportes con seleccion de grupo,
  PDF individual/lote/general y Excel. Historias y diagnostico en HU-reportes.md. Squad previsto: opus
  (HU-R7 motor unico + HU-R5 exportaciones), sonnet (HU-R1/R2/R4/R6 pantallas), QA opus. Plan semanal al
  66 % al escribir esto: arranque tras el reinicio del 13/10 13:00 salvo que David decida pasar del 70 %.
  Pendiente de la fase 1: QA visual de integracion, cierre a main y deploy a Hostinger.
- 2026-10-12 (noche): HOTFIX DE REPORTES EN PRODUCCION. Por urgencia de Roy: rama hotfix/reportes-oct-2026
  -> main 94a719f (tag v2026.10.12-reportes), desplegado por David via SSH en
  /home/u598753224/domains/ibbla.eu/public_html (clon de git; pull + optimize:clear + config/route/view:cache).
  Contiene solo: fix del 504 (A), informe individual de E (servicio, PDF, enlace firmado, capa proveedor
  sin uso), InformePersonaController (personas/{id}/informe?accion=ver|descargar), mi-perfil/informe y
  botones "Estado de cuenta (PDF)" / "Descargar" en ficha, compromisos, lista y Mi perfil; 6 tests nuevos.
  Verificado desde fuera: rutas nuevas responden (302 a login, 403 el enlace sin firma). main ya esta
  mergeado de vuelta en integracion (cbfbf0e) con los botones en el estilo del layout de B.
  Nota: el primer comando de deploy cayo en ~/public_html = IBBSCMusica (musica.ibbla.eu) y solo le
  recacheo config/rutas/vistas. Plan semanal al 67 %: paro aqui; el squad del Centro de Reportes
  (HU-reportes.md) y el QA de integracion arrancan tras el reinicio del 13/10 13:00.
- 2026-10-13 (18:20): SEGUNDA RONDA LANZADA. Los 5 equipos corren a la vez en sus worktrees (plan semanal
  recien reiniciado: 13 % al lanzar). Equipo 1 QA opus (qa/revision-inicial, 8111), 3a motor de reportes
  opus (feat/reportes-motor, 8112), 2 recuento y permisos opus (feat/recuento-lineas-permisos, 8113),
  4 UI/upbar/manual sonnet (feat/ui-upbar-manual, 8114), 5 WhatsApp/analisis sonnet
  (feat/whatsapp-persona-analisis, 8115). Briefs en 03-BRIEFS-EQUIPOS.md. Orden de integracion previsto:
  3a -> 2 -> 5 -> 4 (layouts al final), luego suite + build + QA de entrega + deploy (E1).
  Pendientes del cliente: definicion de "servidores", volcado de BD de produccion, 30 ministerios,
  cuenta Meta.
- 2026-10-13 (18:40): DECISION WHATSAPP = CallMeBot "como en ARCA" (gestion-app-arca: WhatsAppProvider +
  CallMeBotProvider, cada receptor activa el bot desde su WhatsApp y pega el enlace con phone/apikey en
  su perfil, boton de prueba). Se elimina el bot/webhook de Meta (WA-2); el driver meta queda guardado.
  Personas lo activan solas en Mi perfil con guia ilustrada; usuarios del sistema igual para los avisos
  de rol; el admin manda informes solo cuando la persona tiene clave, si no, wa.me como hoy. Equipo 5
  re-briefeado por mensaje; Equipo 4 agrega la seccion al manual. "Hermanos servidores": David lo averigua.
- 2026-10-13 (20:10): QA INICIAL INTEGRADO (c93d334): 43 hallazgos (1 bloqueante QA-024 cifras distintas
  por persona, 12 altas), repartidos por mensaje a los equipos 2, 3a, 4 y 5. DECISIONES DEL CLIENTE:
  Roy usa Administrador; culto cerrado se borra SOLO admin (motivo + auditoria); tesorero NO ve Personas
  pero tiene alta rapida de personas; se elimina "Entrar como invitado" (queda solo el login de las
  personas agregadas); las firmas del recuento se pueden corregir despues de firmadas (admin/tesorero,
  auditado). Supuesto vigente hasta que David diga otra cosa: los reportes financieros (Reporte General,
  estados de cuenta, cumplimiento) los ven admin y tesorero. Pendientes para el motor: si el diezmo cuenta,
  si el mes en curso se cobra, tipo de cambio del sobre o de hoy.
- 2026-10-13 (20:35): PAUSA ORDENADA POR DAVID. Los 4 equipos detenidos, todo commiteado (WIP donde hacia
  falta), NADA mergeado todavia salvo el QA (c93d334). Estado por equipo:
  * 3a feat/reportes-motor (6 commits, hasta dd2807e): motor unico (989c702), periodo (5203841), plantilla
    PDF/Excel (62a48f7), cumplimiento con corte pantalla+PDF+Excel (7c80b7a), ajustes PDF (f1242ce),
    lote de estados de cuenta PDF/ZIP/Excel (dd2807e). FALTA: puntos del QA (QA-024 verificar que todas
    las pantallas den lo mismo, QA-025 lecturas que escriben -> comando compromisos:sincronizar, QA-026
    helper USD, QA-010 permisos, seeder sin fechas futuras, PDFs lentos), REGLAS-CALCULO.md con las
    reglas definitivas del lider (diezmo no cuenta; mes en curso no se cobra; USD con tc del sobre/fecha
    del culto/hoy; promesa USD con tc de fin de mes), suite e informe.
  * 2 feat/recuento-lineas-permisos (8 commits + WIP 904d443): REC-1 lineas (055c0e2), REC-2 solo lectura
    (aa30180), suelto con descripcion (619c0d6), REC-3 Accesos (ca6fe9e), REC-4 flujo y sobres seguidos
    (9a76aee, 9e2085d), QA-020 (d2d44f9), QA-001/002 (7280ac1). FALTA: REC-5 docs/ROLES.md + regla unica
    de "sin permiso", QA-003 borrar cerrado solo admin con motivo, QA-011/014 redirecciones y Mi perfil,
    QA-012/013 seguridad (super admin editable, API asistencia sin rol), decisiones del cliente (quitar
    invitado -en curso-, tesorero sin Personas + alta rapida, firmas corregibles), USD en PDF de recuento.
  * 4 feat/ui-upbar-manual (3 commits + WIP ee9173b): UI-1 tokens y docs/UI.md (2e85816), UI-2 upbar
    (8fafdda), UI-3 buscador global (fc8d2b8). FALTA: UI-4 manual en la app + "?" + PDF, lang/es,
    login sin invitado (diseno), seccion WhatsApp del manual, npm run build, capturas.
  * 5 feat/whatsapp-persona-analisis (8 commits + WIP 077100f): driver callmebot por receptor (bdfea29),
    activacion con consentimiento (9b16c2a), tarjeta Mi perfil con pasos (0405623), perfil de usuario
    (7c15726), avisos automaticos (85b04ae), informe automatico (b88ca25), whatsapp:probar (628f666),
    acepta clave sola y respuesta real (c3d8adc). FALTA: tests del comando (en curso), AN-1 comando
    ingresos:analizar + ANALISIS-INGRESOS-local.md, .env.example documentado, informe.
  * 6 (NO lanzado): worktree IBBLA-wt/entrega-deploy listo (feat/entrega-deploy, ibbla_dep, 8116) para
    deploy tooling (script Hostinger, DEPLOY-HOSTINGER.md, comando de verificacion, limpiar
    .env.example, vendor en .gitignore).
  CallMeBot: activacion real hecha desde el WhatsApp de David (clave ya existia por ARCA), 1 mensaje real
  de prueba recibido 7:31 PM; clave solo en el .env del worktree del equipo 5 (max 3 pruebas reales).
  Para REANUDAR: mandar "continua" a cada agente (conservan contexto) o relanzar con 03-BRIEFS +
  esta nota; luego integrar 3a -> 2 -> 5 -> 4 (+6), suite, build, QA de entrega, deploy E1.
- 2026-11-04 (tarde): REANUDACION. Agentes relanzados (los del 13/10 perdieron contexto) con 04-DECISIONES.md.
  Integrados: Equipo 6 deploy (2760faa: deploy/hostinger.sh, ibbla:verificar, DEPLOY-HOSTINGER.md,
  .env.example limpio, vendor ignorado) y Equipo 3a motor (d7efce7: reglas definitivas en el motor,
  compromisos:sincronizar + CierreCultoObserver, ConversorUsd, permisos de descargas, test de contrato,
  REGLAS-CALCULO.md; 188 tests de reportes verdes; suite completa necesita -d memory_limit=1G por
  zipstream). Pedido nuevo de Roy (04/11) sobre sobres "Anonimo" ya resuelto en la rama del equipo 2.
  Prueba real de whatsapp:probar del equipo 5 llego a David 16:02 (2 de 3 mensajes reales usados).
  Pendientes de integrar: 2 (recuento), 5 (WhatsApp), 4 (UI, layouts al final); luego npm run build,
  suite completa, QA de entrega, tag y deploy con deploy/hostinger.sh.
- 2026-11-04 (noche): LOS CINCO EQUIPOS INTEGRADOS en integracion/oct-2026: 6 (2760faa), 3a (d7efce7),
  2 (sin conflictos), 5 (82d1898; conflictos en .env.example y MiPerfilController resueltos a favor de 5
  + bloque de reporte mensual), 4 (eb26ed1; 8 vistas en conflicto resueltas conservando la logica de
  2/3a/5 y aplicando los colores de 4; login = version de 4), assets reconstruidos (32ec4fd).
  Suite tras 3a+2+6: 580 tests, solo los 15 de Breeze (correr con -d memory_limit=1G por los PDFs).
  Hallazgo del equipo 5 a revisar antes del QA de entrega: dos promesas de una persona en la misma
  categoria se pisan en compromisos (comprobar que el motor y compromisos:sincronizar las sumen).
  Pendiente de menu (ROLES.md final): "Yo" con tieneMiPerfil(), dashboard de asistencia con
  puedeVerDashboardAsistencia(), entrada "Agregar persona", ocultar Cumpleaneros al miembro.
  Siguiente: suite completa final, revision visual rapida, QA de entrega (opus), tag y deploy E1.
- 2026-11-04 (noche, PAUSA por tope de uso): integracion/oct-2026 en a0c67bb con TODO integrado: equipos
  6, 3a, 2, 5, 4 + build (32ec4fd) + logo propio de IBBLA (a58d443: Logo.png/Logo2.png/SVG/favicon, sin
  el de IBBSC) + arreglo de promesas duplicadas por categoria y de Mi perfil (3a: 30975f0, 369c97d).
  Suite completa ANTES del ultimo merge: 828 tests, 31 fallos = 15 Breeze + 16 de Mi perfil (ya
  corregidos). Tras el merge, el filtro MiPerfil|InformePersonaRuta|InicioPorRol|PerfilUsuarioWhatsApp|
  ContratoReportes|PromesasMismaCategoria da 56 tests con 1 fallo pendiente:
  InicioPorRolTest::test_mi_perfil_solo_se_ofrece_con_persona_vinculada (equipo 2 esperaba que Mi perfil
  no se ofrezca sin persona; el equipo 5 lo cambio para mostrar la pantalla "sin persona": decidir y
  ajustar test o menu). Falta: suite completa final con -d memory_limit=1G, cambiar el seeder a IBBLA
  (AdminUserSeeder sigue sembrando "Santa Cruz"/IBBSC), regenerar las 34 capturas del manual con el logo
  nuevo (tools/manual/capturar.mjs), pendientes de menu de ROLES.md, QA de entrega (opus), tag y deploy
  con deploy/hostinger.sh (hay migraciones: migrate --force), correr ibbla:verificar en el servidor y
  cambiar las contrasenas del seeder si siguen en produccion. Servidor local de integracion: 8100.
- 2026-11-05: integracion/oct-2026 en 6633d70. Suite completa: 829 tests, solo los 15 de Breeze (correr
  con -d memory_limit=1G). Menu segun ROLES.md aplicado, ruta de cumpleaneros cerrada al miembro, seeder
  local = IBBLA, 34 capturas del manual regeneradas con el logo nuevo. QA DE ENTREGA (opus) lanzado sobre
  la integracion (informe en docs/plan-oct-2026/QA-ENTREGA.md de la rama qa/revision-inicial).
  Al GO: merge a main + tag v2026.11.05-e1 + push; en Hostinger `bash deploy/hostinger.sh --tag v2026.11.05-e1`
  (hay migraciones: permisos_edicion, whatsapp, audit_logs.tenant_id...), luego `php artisan
  compromisos:sincronizar`, `php artisan ibbla:verificar` y cambiar las contrasenas del seeder si siguen.
- 2026-11-24: CIERRE DE E1. Suite completa en integracion/oct-2026 (c270e0b): 839 tests, 14 fallos = la linea
  base de Breeze (Auth/*, ProfileTest, ExampleTest; RegistrationTest ya no existe por QE-004). Correr con
  `-d memory_limit=1G -d max_execution_time=0` (en Windows el limite de 180 s aborta la suite al 95 %).
  Merge a main con tag `v2026.11.24-e1` (fecha real del cierre) y push de main, integracion y tags.
  Deploy por David: `cd ~/domains/ibbla.eu/public_html && git fetch --all --tags && bash deploy/hostinger.sh --tag v2026.11.24-e1 && php artisan compromisos:sincronizar && php artisan ibbla:verificar`
  (el script respalda la BD antes de migrar); despues cambiar las contrasenas del seeder, confirmar
  QUEUE_CONNECTION=sync y el cron de schedule:run, y sembrar los 30 ministerios cuando llegue la lista.
  E2-E4 arrancan de inmediato con los briefs de 05-BRIEFS-E2.md (equipos R reportes 3b, F pendientes de QA,
  G guia y ambiente QA; luego Q QA integral) en worktrees reportes-3b (8121, ibbla_r3b), qa-pendientes
  (8122, ibbla_fix) y guia-ambiente-qa (8123, ibbla_gqa), ya armados con VolumenDevSeeder.
- 2026-11-24 (17:30 CR): E2-E4 LANZADA en una sola tanda por pedido de David ("terminemos todo hoy", sin tope
  de uso por hoy). Push de E1 pendiente: dio 403 con la cuenta DavidConsiti; lo corre David con la cuenta
  duena del repo (remoto ya apunta a 1KATZUNO@github.com). Cinco equipos en paralelo, cada uno con archivos
  propios para fusionar sin choques (los equipos R y F de 05-BRIEFS-E2.md se partieron en dos):
  * R1 opus  feat/reportes-3b              IBBLA-wt/reportes-3b      ibbla_r3b :8121  selector completo (HU-R2),
    vista previa (HU-R4), QE-008, QE-011, PDF de promesas a la plantilla unica.
  * R2 opus  feat/reportes-cultos-ingresos IBBLA-wt/reportes-nuevos  ibbla_rn  :8124  reporte de cultos (HU-R10),
    de ingresos (HU-R11), asistencia en el centro (HU-R12), plantilla unica en lo viejo (HU-R14), tarjetas y buscador.
  * F1 opus  fix/qa-pendientes             IBBLA-wt/qa-pendientes    ibbla_fix :8122  QE-009, QE-013, QE-017 resto,
    QE-020, QA-018, QA-034, QA-036.
  * F2 opus  fix/qa-pendientes-ui          IBBLA-wt/qa-pendientes-ui ibbla_fui :8125  QE-012 (manda la decision 5:
    el tesorero SI ve reportes de asistencia; se corrige ROLES.md), QE-014, QE-015/QA-021, QE-016, QE-018, QE-019, QA-043.
  * G sonnet feat/guia-ambiente-qa         IBBLA-wt/guia-ambiente-qa ibbla_gqa :8123  ambiente QA (deploy --qa, aviso,
    noindex, AMBIENTE-QA.md) y manual final (fase 1); fase 2 = documentar lo nuevo tras integrar.
  Reglas comunes de la tanda y propiedad de archivos: se entregaron a cada equipo (hora limite 19:30 CR).
  Luego: integrar R1 -> R2 -> F1 -> F2 -> G, suite + build, QA integral (Q, opus) + G fase 2, corregir lo que
  frene, tag final, push y deploy de David. WA-2 (bot de Meta) sigue descartado por la decision del 13/10.
- 2026-11-24 (noche): E1 EN PRODUCCION Y E2 CERRADA.
  * E1 desplegada por el lider con acceso SSH propio (llave `claude-code-pc-david-ibbla` agregada en hPanel > SSH
    Access a pedido de David): 7 migraciones, compromisos sincronizados, `ibbla:verificar` OK salvo la contrasena
    de semilla de super@admin.com (la cambia David o Roy). Hallazgo: hPanel > GIT tiene auto-deploy por webhook y
    cada push a main jala el codigo sin migrar; el orden correcto quedo en DEPLOY-HOSTINGER.md.
  * Ensayo previo sobre una copia de la base de produccion (fuera del repo, en Documents/IBBLA-respaldos): E1 y E2
    migran limpio y las cifras de cumplimiento por persona son identicas antes y despues, y entre MySQL local y
    MariaDB 11.8.9 del servidor. AN-1 hecho sobre datos reales: 3909 sobres, 179 cultos y 217 estados de cuenta
    sin descuadres; 47 grupos de posibles sobres repetidos y 827 sobres sin persona para que Roy revise.
  * E2: los cinco equipos (R1, R2, F1, F2, G) entregaron y se fusionaron sin conflictos. QA final (QA-FINAL.md):
    GO con condiciones, sin bloqueantes ni altos; QF-001 a QF-008 corregidos por el lider y QF-009 verificado en
    MariaDB. Suite final: 975 tests, 14 fallos = linea base de Breeze. Tag `v2026.11.24-e2`.
  * Deploy de E2 con `--maintenance --manual-pdf`, `QUEUE_CONNECTION=sync` y cron de `schedule:run` cada minuto
    creado en hPanel (no existia ninguno: el planificador nunca habia corrido en produccion).
  * Queda del cliente: lista de los 30 ministerios (IBBLA tiene 7), definicion de "hermanos servidores", cambiar la
    contrasena de super@admin.com, instalar el ambiente QA con docs/AMBIENTE-QA.md cuando se quiera, y rotar las
    credenciales viejas de IBBSC del historial de git. El bot de Meta (WA-2) sigue descartado.
- 2026-11-24 (cierre, 19:45 CR): E2 DESPLEGADA EN PRODUCCION (main 12a55f8, tag v2026.11.24-e2) con el orden nuevo
  (down -> push -> auto-deploy -> script --maintenance --manual-pdf): respaldo, 2 migraciones (72 al dia), caches,
  PDF del manual de las 4 iglesias, `QUEUE_CONNECTION=sync` (copia previa en .env.backup-2026-11-24) y cron
  `* * * * * /usr/bin/php .../artisan schedule:run` creado en hPanel. `ibbla:verificar`: todo OK salvo la contrasena
  de semilla de super@admin.com. Humo en proceso sobre la copia de produccion: 34 pantallas y reportes en 200, el
  Excel mas pesado (estados de cuenta de las 236 personas) tarda 4,3 s en el servidor. El 503 que David vio en
  Servidores fue la ventana de mantenimiento del deploy (19:17-19:20), no un defecto.
  Retoque posterior `v2026.11.24-e2.1`: el Reporte de ingresos compara un periodo en curso con los mismos dias del
  anterior (con datos reales, noviembre pasa de -22,4 % a +1,9 %), igual que el dashboard.
  Incidente del deploy de e2.1 (error del lider): la espera del auto-deploy usaba `pgrep -f composer` en un bucle
  y el bucle se detectaba a si mismo, asi que el sitio quedo en mantenimiento de 19:36 a 19:47 CR (unos 10 min) hasta
  que se corto y se corrio el script. Sin errores en el log ni datos afectados. Leccion: tras el push, esperar a que
  HEAD cambie y dar unos segundos fijos; nunca un bucle con pgrep cuyo patron aparece en el propio comando.
  Estado final de produccion: main en `v2026.11.24-e2.1` (15b0c9e), arbol limpio, cron corriendo, 3 respaldos en
  ~/respaldos-ibbla, `ibbla:verificar` con una sola falla (contrasena de semilla de super@admin.com).
  Retoque `v2026.11.24-e2.2`: el reporte mensual por correo leia REPORTE_MENSUAL_TO con env() dentro del comando y,
  con la configuracion en cache (como queda tras cada deploy), abortaba sin enviar. Ahora lee config/reportes.php.
  Importa desde hoy porque el cron ya existe: el primer envio automatico sera el 01/12 a las 08:00.
  Estado final real de produccion (20:05 CR): main en `v2026.11.24-e2.2` (b331c37), arbol limpio, configuracion en
  cache, cron con latido, reporte mensual probado en seco con la configuracion en cache (1 destinatario, 4 adjuntos,
  sin enviar), 4 respaldos en ~/respaldos-ibbla. Suite: 980 tests, 14 fallos = linea base de Breeze.
- 2026-11-24 (21:00-22:30 CR): PROMESAS FUTURAS (pedido nuevo de David con el Excel de promesas 2027 de La Aurora).
  Especificacion en 06-PROMESAS-FUTURAS.md. Corte de promesas = borron y cuenta nueva con fecha de inicio (dia 1 de
  un mes); apartado "Promesas futuras" solo para el administrador; altas a mano y por Excel (quien ya esta en el
  corte no se sobrescribe ni se duplica; reimportar no agrega nada); titulos del Excel = categorias y una nueva nace
  inactiva y se activa con el corte (desde ese dia sale en los sobres); plantilla descargable. La historia no se
  toca: al activarse el corte las promesas anteriores pasan a `promesas_historial` y el motor usa en cada mes las
  que regian ese mes (`PromesasPorPeriodo`). Activacion por planificador cada diez minutos y disparo perezoso.
  Dos equipos: M (motor y activacion, 991 pruebas) y P (pantallas, importador, plantilla, manual; 1000 pruebas).
  Ensayo del lider sobre la copia de produccion: sin cortes las cifras son las de produccion; con un corte
  programado de 323 promesas quedan identicas; tras simular la activacion, los meses anteriores quedan identicos
  persona por persona; el Excel real se lee sin errores (135 filas: 69 calzan, 44 con nombre parecido, 22 sin
  parecido; Construccion no existe y se propone crearla; Campamento calza sola); reimportar no agrega nada;
  22 pantallas y reportes en 200. Limitaciones anotadas: un periodo que cruce un corte a mitad de anio puede
  descuadrar la tabla de compromisos del estado de cuenta (el corte de 2027 es el 1 de enero y no cruza).
  DESPLEGADO 22:00 CR como `v2026.11.24-e3` (main 2e257b6): suite final 1012 pruebas / 14 de Breeze; un minuto de
  mantenimiento; migracion de las tres tablas; huella de cifras de produccion IDENTICA antes y despues del deploy
  (cortes 31/10 y 24/11, con promesa y todas); `ibbla:verificar` OK salvo la contrasena de semilla. En produccion no
  hay todavia ningun corte: falta cargar el Excel real junto con David o Roy (decidir Construccion y emparejar
  los nombres que no calzan solos).
- 2026-11-25 (09:20 CR): PROMESAS FUTURAS, ajustes pedidos por David y desplegados como `v2026.11.25-e3.1` (main
  b19c3fd): la plantilla y el importador llevan una tercera columna **Frecuencia** (Semanal, Quincenal o Mensual por
  persona; vacia toma la elegida al subir; un archivo sin la columna se lee como antes); al reimportar, quien fue
  emparejado a mano ya no pide decidir de nuevo. Suite 1015 pruebas / 14 de Breeze; un minuto de mantenimiento;
  huella de produccion identica antes y despues; ningun corte creado todavia en produccion.
  Decisiones de David para la carga real: la columna "Construccion" de su Excel va a la categoria existente
  **Yo Creo 2030** (no se crea categoria ni se renombra); los montos son semanales; los nombres dudosos los marca
  el en `Documents/IBBLA-respaldos/nombres-por-revisar-promesas-2027.xlsx` (69 calzan solos, 44 parecidos, 20
  posibles, 2 sin parecido) y con eso el lider crea el corte de enero 2027 y carga el archivo.
- 2026-11-25 (09:35 CR): CORTE «PROMESAS 2027» CARGADO EN PRODUCCION (id 2, inicia el 01/01/2027, programado).
  David devolvio el Excel de nombres con sus decisiones (66 filas: 45 eran una persona existente, 21 personas
  nuevas, ninguna omitida). Carga asistida por el lider con los mismos servicios del importador: 135 personas,
  531 promesas programadas semanales en colones, 21 personas creadas, ninguna categoria nueva (la columna
  Construccion fue a Yo Creo 2030). Suma de montos del Excel = suma cargada (899.265,40 semanales). Antes se
  ensayo sobre una copia fresca de produccion y en produccion primero en seco; la huella de cifras de hoy quedo
  identica y las 618 promesas vigentes no cambiaron. Queda en auditoria como importacion asistida por soporte.
  El 1 de enero el corte se activa solo: las promesas de 2026 pasan al historial y rigen las nuevas.
- 2026-11-26 (02:40 CR): TARJETAS CON QR DE ACCESO PERSONAL, pedido de David, DESPLEGADO como `v2026.11.26-e4`
  (main 798c419). Especificacion en `07-TARJETAS-QR.md`, informe en `INFORME-tarjetas-qr.md`. Un solo equipo.
  Que hace: cada persona tiene una tarjeta con su nombre, su clase y un QR (`/t/{token}`, secreto de 40
  caracteres en `personas.token_acceso`). Al escanear: sin cuenta, crea su acceso con un usuario o un correo y
  una contrasena y entra a Mi perfil; con cuenta, llega al inicio de sesion con su identificador ya escrito y
  tiene que escribir la contrasena (el QR nunca entra solo). El administrador ve la tarjeta en una ventana desde
  Personas (boton Tarjeta), la baja en PDF y baja todas juntas en `/personas/tarjetas` (8 por hoja carta, con
  marcas de corte; con promesa, todas las activas o una clase). Desde la ventana puede ver la contrasena de una
  cuenta de miembro (copia cifrada en `users.password_visible`, solo rol miembro, cada consulta en auditoria,
  interruptor `IBBLA_CONTRASENA_VISIBLE`), restablecer el acceso o generar un QR nuevo. El login acepta correo
  o nombre de usuario (`users.usuario`; `users.email` paso a opcional).
  Revision del lider antes de subir: el limite de intentos de las rutas publicas era por IP (30 y 10 por
  minuto) y habria dejado afuera a la gente el dia que toda la iglesia escanee desde el Wi-Fi del templo;
  ahora el limite corto es por tarjeta y el de la IP es un tope holgado, con pagina de espera en espanol.
  Suite final 1061 pruebas / 14 de Breeze. Ensayo sobre una copia de produccion del mismo dia: migracion
  limpia (los 21 usuarios conservan su correo), huella de cifras identica, PDF de 200 tarjetas en 4,7 s la
  primera vez y 1 s despues, 255 escaneos seguidos desde una sola IP sin bloqueo. Probado en navegador local
  de punta a punta (tarjeta, crear acceso, entrar, contrasena equivocada, restablecer, QR nuevo, espera).
  Deploy: un minuto y medio de mantenimiento (02:39 a 02:40 CR), 74 migraciones, huella de produccion
  IDENTICA antes y despues (cortes 31/10 y 26/11), `ibbla:verificar` sin fallas nuevas (sigue la contrasena
  de semilla de super@admin.com), PDF masivo de 255 tarjetas medido en el servidor con datos ficticios:
  3,1 s sin cache y 0,5 s despues. En produccion todavia no se genero ninguna tarjeta real.
  Pendiente con el cliente: (1) probar con un telefono una tarjeta impresa; (2) de las 200 personas que
  recibirian tarjeta, 126 no tienen clase en el sistema y su tarjeta sale sin ese renglon: el Excel de promesas
  2027 trae la clase de 71 de ellas y se puede cargar con su visto bueno (las mete en la lista de asistencia
  de esa clase); (3) confirmar si se queda la contrasena visible para el administrador o solo "Restablecer
  acceso"; (4) las cuentas que ya existian no tienen copia visible hasta que cambien su contrasena.
