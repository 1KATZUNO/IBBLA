# IBBLA — Plan maestro de la segunda ronda (dictado por David el 12/10/2026)

Lider tecnico: Claude (Fable 5.1). Cinco equipos de agentes, un QA inicial que revisa toda la app
(visual y funcional) y trabajo dividido en historias de usuario (HU). Se corre de noche para tener
tokens al dia siguiente; cada equipo en su worktree y rama `feat/<slug>`, integrando en
`integracion/oct-2026`. Reglas del `00-PLAN.md` siguen vigentes (MySQL para tests, lint Blade, sin push
de los agentes, commits en espanol, tope 70 % del plan semanal).

## 0. Lo que dijo el cliente (Roy, tesoreria) y lo que ya se entrego

- 10/10 y 12/10: la app "se pega" al generar el Reporte General y los hermanos piden sus estados de
  cuenta. **Resuelto el 12/10 con el hotfix v2026.10.12-reportes** (Reporte General en segundos y
  boton "Estado de cuenta (PDF)" por persona). Roy ya lo tiene.
- 12/10 16:01: **para el miercoles 14/10 debe presentar al pastor un listado de los hermanos
  servidores, para determinar quienes estan al dia y quienes tienen pendiente, con corte a la fecha de
  hoy.** David le prometio el reporte bien hecho, en PDF y Excel, presentable. Ver seccion 7.
- David le adelanto: apartado solo para reportes, aviso de reportes por WhatsApp y mas funcionalidad.

## 1. Principios de diseno (aplican a todos los equipos)

1. Profesional, guiado por HubSpot y Salesforce: barra superior fija (upbar, como en ARCA) + menu
   lateral; tablas limpias con filtros arriba y acciones a la derecha; tarjetas de resumen; estados
   vacios con que hacer; formularios por secciones con Guardar siempre visible; 44 px de toque.
2. Facil de entender y no cansado: menos cuadros, mas lineas; un solo camino para cada tarea; nada
   de informacion de otros contextos en la pantalla (p. ej. cuadros de otros cultos al crear un sobre).
3. Logica de roles y accesos coherente: lo que un usuario no puede usar se ve deshabilitado (gris,
   con tooltip de por que) o no aparece, pero siempre con la misma regla en toda la app.
4. Reportes y descargas (PDF/Excel) faciles de leer: una idea por pagina, encabezados claros, totales
   al final, colores solo para estados (al dia / pendiente), nada decorativo.
5. Cada cambio con su prueba (phpunit en MySQL) y con captura antes/despues cuando es visual.

## 2. Los cinco equipos

### Equipo 1 — QA inicial de toda la app (opus) — rama `qa/revision-inicial`
Revisa IBBLA completa (integracion/oct-2026 ya con A-E + hotfix) en escritorio, tablet y movil, con
el usuario admin, tesorero, asistente y miembro. Entrega `docs/plan-oct-2026/QA-INICIAL.md`: hallazgos
numerados (pantalla, rol, que pasa, que deberia pasar, severidad, captura) + lista de "cosas que no
tienen sentido" (flujo, textos, botones muy abajo, campos sin logica). No corrige: reporta. Sus
hallazgos se reparten a los equipos 2-5 como HU nuevas. Es el primero en correr; los demas arrancan
con su informe en mano.

### Equipo 2 — Recuento por lineas, permisos de edicion y logica de sobres (opus) — `feat/recuento-lineas-permisos`
- HU-REC-1 Lista de cultos por lineas: en Recuento desaparecen los cuadros de abajo. Una tabla con
  fecha (dia), tipo de culto, resumen corto (sobres, total, estado abierto/cerrado), buscador por fecha
  y filtro por mes/tipo; se selecciona una linea para abrir el culto.
- HU-REC-2 Culto cerrado = solo lectura para tesoreros: ven todo, no editan ni agregan; el boton de
  editar sale deshabilitado con tooltip "cerrado el dd/mm por X". El admin si puede corregir.
- HU-REC-3 Modulo "Accesos" (David lo llamo logistica): el admin elige un usuario y le concede
  permiso de EDICION sobre: un documento (culto/recuento) concreto, varios, todos, o un rango de fechas;
  con vigencia opcional (hasta fecha). Los concesionarios siempre pueden crear cultos nuevos. Tabla
  `permisos_edicion` (user_id, tipo, referencia, desde, hasta, otorgado_por, vence), auditoria de
  concesion y de cada edicion hecha bajo permiso. Pantalla con lista de permisos vigentes y boton
  revocar.
- HU-REC-4 Crear sobre sin ruido: al crear/editar un sobre solo se ve ese culto; nada de cuadros de
  otros cultos al bajar. Revisar el flujo de "meter sobres" y proponer el mas corto (foco en campo
  monto, teclado numerico, siguiente sobre sin salir).
- HU-REC-5 Roles y accesos con logica: tabla unica de que ve y que puede cada rol (admin, tesorero,
  asistente, invitado, miembro) escrita en `docs/ROLES.md`, y la app la cumple: campos y botones sin
  permiso deshabilitados (blur/gris + tooltip) u ocultos, segun la regla del punto 1.3.

### Equipo 3 — Centro de Reportes profesional (opus) — `feat/centro-reportes`
Base: `HU-reportes.md` (R1-R9: centro, selector tipo WHERE IN con chips, periodo, vista previa, PDF
individual/lote/general, Excel, motor unico, permisos, calidad visual). Se amplia con:
- HU-R10 Reportes de cultos: por rango o por cultos elegidos; sobres, totales por categoria, metodo
  de pago, firmas; PDF y Excel.
- HU-R11 Reportes de ingresos: rango de fechas, por categoria y por metodo, comparativo con periodo
  anterior; PDF y Excel.
- HU-R12 Reportes de asistencia: integra el "Asistencia por clase" (C) al centro; rango y clases.
- HU-R13 Reporte "Cumplimiento de promesas con corte a fecha" (el que Roy necesita): lista de
  personas (filtro: todas / con promesa / servidores / ministerio) con prometido a la fecha, dado,
  diferencia y estado AL DIA / PENDIENTE; resumen arriba (cuantos al dia, cuantos pendientes, montos);
  PDF presentable y Excel con filtros. Corte = fecha elegida (por defecto hoy).
- HU-R14 Descargas faciles de leer: plantilla unica de PDF (portada corta, encabezado con iglesia,
  periodo y filtros aplicados, tabla, totales, pie con fecha de generacion) y de Excel (hoja Resumen +
  hoja Detalle, encabezados fijos, formato moneda, autofiltro).

### Equipo 4 — UI profesional, upbar, buscador y manual (sonnet) — `feat/ui-upbar-manual`
- HU-UI-1 Sistema visual: tokens de color, tipografia, espaciado y componentes (boton, tabla, tarjeta,
  filtro, estado vacio) documentados en `docs/UI.md` y aplicados a todas las pantallas (sobre el trabajo
  de B).
- HU-UI-2 Upbar fija como en ARCA: logo, buscador global, acceso al manual, usuario/rol, salir. El
  menu lateral se mantiene.
- HU-UI-3 Buscador global por palabras: personas, cultos (por fecha), reportes, pantallas y temas del
  manual; resultados agrupados; funciona con teclado.
- HU-UI-4 Manual de usuario dentro de la app: una pagina por modulo, cada boton explicado (que hace,
  quien lo puede usar, que pasa despues), con capturas; indice y busqueda; boton "?" contextual en cada
  pantalla que abre la seccion correspondiente. Se genera tambien en PDF descargable.

### Equipo 5 — WhatsApp para la persona y analisis de ingresos (sonnet + apoyo opus para el analisis) — `feat/whatsapp-persona-analisis`
- HU-WA-1 Opt-in de la persona: en Mi perfil, el miembro activa "Recibir mis reportes por WhatsApp",
  confirma su numero y sigue pasos ilustrados (imagenes) para probarlo; queda registrado el
  consentimiento (fecha) y puede desactivarlo.
- HU-WA-2 Bot por Meta, solo si es gratis: la persona escribe al numero de la iglesia "Reporte Enero",
  "Reporte Anual", "Estado de cuenta" y el bot responde con el PDF. Nota tecnica: en la API de Meta las
  conversaciones que INICIA la persona (service) son gratis y sin limite desde nov-2024; lo que cuesta
  es iniciar mensajes desde la iglesia. Requiere cuenta Meta Business funcional + numero (sirve el fijo
  de la iglesia). Se implementa como driver `meta` + webhook de entrada con comandos; queda detras de
  config hasta que exista la cuenta.
- HU-AN-1 Analisis de ingresos: con un volcado de la BD de produccion (David lo baja de Hostinger),
  cuadrar sobres vs detalles vs totales de culto vs lo que ve cada persona en su estado de cuenta;
  detectar duplicados, sobres sin persona, montos en USD sin tipo de cambio, cultos sin cierre,
  compromisos desincronizados. Entrega `docs/plan-oct-2026/ANALISIS-INGRESOS.md` con hallazgos y
  correcciones propuestas (no se corrige nada en produccion sin OK).

## 3. Entregas propuestas

| Entrega | Contenido | Que ve el cliente |
|---|---|---|
| E1 (primera noche + dia) | QA inicial; Recuento por lineas + cerrado solo lectura (REC-1, REC-2); Centro de Reportes v1 con el reporte de cumplimiento con corte (R13) + personas (R2-R6) en PDF/Excel; deploy | Roy presenta su listado y ya navega los cultos por lineas |
| E2 | Modulo Accesos (REC-3), crear sobre sin ruido (REC-4), roles con logica (REC-5); reportes de cultos, ingresos y asistencia (R10-R12); plantilla unica de PDF/Excel (R14); UI profesional + upbar (UI-1, UI-2); analisis de ingresos (AN-1) | La app se ve y se siente profesional; reportes completos |
| E3 | Buscador global (UI-3), manual dentro de la app (UI-4), opt-in de WhatsApp (WA-1), QA integral + ambiente QA | Manual y busqueda; la gente activa WhatsApp |
| E4 (final) | Bot de Meta si es gratis (WA-2), pulido de hallazgos del QA integral, guia final, cierre a main | Cierre de la ronda |

Cada entrega termina con: suite verde (salvo los 15 de Breeze), `npm run build`, QA visual de lo nuevo,
merge a main con tag, deploy a Hostinger (git pull + optimize:clear + config/route/view:cache +
migrate --force cuando haya migraciones) y nota a Roy con lo nuevo.

## 4. Orden de arranque (noche 1, tras el reinicio semanal)

1. Equipo 1 (QA) y Equipo 3 (reportes) arrancan juntos: el QA no bloquea a reportes.
2. Equipo 2 arranca cuando el QA entregue (sus HU de recuento dependen de lo que el QA vea).
3. Equipos 4 y 5 arrancan en paralelo con el 2 (distintos archivos; el 4 toca layouts: nadie mas
   los toca).
4. El lider integra en orden 3 -> 2 -> 4 -> 5, corre suite y build, lanza QA de entrega, cierra.

## 5. Preguntas abiertas para David / Roy

1. "Hermanos servidores": son las personas asignadas a ministerios (tabla de servidores de la app) o
   todos los que tienen promesa? (Para el filtro del reporte R13 y para el listado del miercoles.)
2. Volcado de la BD de produccion para el analisis de ingresos (AN-1) y para probar los reportes con
   datos reales: `mysqldump` desde Hostinger (hPanel > Databases) a Downloads.
3. Lista de 30 ministerios (pendiente desde la ronda 1).
4. Cuenta Meta Business: con que cuenta de Facebook se intenta (Roy / iglesia / nueva) y el numero
   (fijo de la iglesia).
5. Respuestas de C y D (dias en 0, ninos/ninas de la app movil, capilla; dashboards en anio).

## 6. Riesgos

- Tokens: 5 equipos + QA en una noche puede pasar del 70 %; se lanzan en dos tandas (1+3, luego 2+4+5)
  y se mide con get_usage antes de cada tanda.
- Hostinger compartido: sin colas ni cron garantizado; todo lo pesado debe correr en < 10 s por
  peticion (regla del Reporte General).
- Conflictos: el equipo 4 reestructura layouts; los demas no tocan `layouts/`.

## 7. Listado para el pastor (miercoles 14/10/2026)

Formato: PDF (1-3 paginas, vertical) + Excel. Encabezado: iglesia, "Cumplimiento de promesas",
corte a la fecha, total de personas, cuantas al dia y cuantas pendientes. Tabla ordenada por estado y
nombre: Nombre, Promesa (resumen), Le correspondia a la fecha, Ha dado, Diferencia, Estado (AL DIA /
PENDIENTE), Observacion (p. ej. "sin aportes desde agosto"). Totales al pie. Regla de estado: acumulado
a la fecha (lo mismo que el estado de cuenta), no mes por mes. Se genera con el reporte R13 en cuanto
exista; si no llega a tiempo, el lider lo arma con un script sobre el volcado de la BD (o sobre el
texto del Reporte General de produccion) y lo entrega como archivos en Downloads/07 - Iglesia.
