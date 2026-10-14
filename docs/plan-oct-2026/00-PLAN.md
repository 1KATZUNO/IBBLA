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
