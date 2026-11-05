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
