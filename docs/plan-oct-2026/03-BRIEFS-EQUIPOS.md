# Briefs de los cinco equipos (segunda ronda) — listos para lanzar

Cada equipo trabaja en su worktree (ya creado, con vendor copiado, node_modules enlazado, `.env` propio
y bases `ibbla_<x>` / `ibbla_<x>_test` creadas). Antes de empezar, TODOS leen:
`docs/LOCAL_SETUP.md`, `docs/plan-oct-2026/00-PLAN.md` (reglas), `02-PLAN-MAESTRO.md` (principios y
HU) y, los de reportes, `HU-reportes.md`. Pasos comunes: `php artisan migrate --seed` y
`php artisan db:seed --class=VolumenDevSeeder` (150 personas con sobres y promesas), servidor en su
puerto, tests SOLO con `DB_CONNECTION=mysql DB_DATABASE=ibbla_<x>_test php vendor/bin/phpunit --filter ...`
(linea base: 15 tests de Breeze fallan, no son de nadie). Commits pequenos en espanol, sin push, sin
Co-Authored-By, `php -l` y lint Blade (`grep -rnE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' resources/views`
debe salir vacio) antes de cada commit. Nadie toca `resources/views/layouts/` salvo el equipo 4.
Usuarios del seeder: admin@ibbsc.com / admin123, tesorero@ibbsc.com / tesorero123 (crear asistente y
miembro con `php artisan tinker` o desde Admin > Usuarios; el miembro necesita `personas.user_id`).

| Equipo | Worktree | Rama | BD | Puerto | Modelo |
|---|---|---|---|---|---|
| 1 QA inicial | IBBLA-wt/qa-revision-inicial | qa/revision-inicial | ibbla_qa | 8111 | opus |
| 3a Motor de reportes | IBBLA-wt/reportes-motor | feat/reportes-motor | ibbla_rep | 8112 | opus |
| 2 Recuento y permisos | IBBLA-wt/recuento-lineas-permisos | feat/recuento-lineas-permisos | ibbla_rec | 8113 | opus |
| 4 UI, upbar y manual | IBBLA-wt/ui-upbar-manual | feat/ui-upbar-manual | ibbla_ui | 8114 | sonnet |
| 5 WhatsApp persona y analisis | IBBLA-wt/whatsapp-persona-analisis | feat/whatsapp-persona-analisis | ibbla_wa | 8115 | sonnet |

## Equipo 1 — QA inicial (no corrige, reporta)
Recorrer TODA la app con admin, tesorero, asistente y miembro, en escritorio (1366), tablet (768 y 1024)
y movil (390), con los datos de VolumenDevSeeder. Entregar `docs/plan-oct-2026/QA-INICIAL.md`:
1. Hallazgos numerados QA-001...: pantalla, rol, dispositivo, que pasa, que deberia pasar, severidad
   (bloqueante / alta / media / baja), captura en `docs/qa/` (jpg, solo cuando aporte).
2. "Cosas sin logica": flujos confusos, textos, botones muy abajo, campos que no deberian verse,
   informacion de otros contextos (ej. cuadros de otros cultos al crear un sobre).
3. Matriz observada de roles: por pantalla, que ve y que puede cada rol (base de `docs/ROLES.md`).
4. Top 10 para arreglar primero y a que equipo le toca (2, 3, 4 o 5) segun el plan maestro.
Usar read_page/get_page_text para verificar y captura solo en lo visual. Probar flujos completos:
crear culto, registrar sobres (varios seguidos), cerrar culto, asistencia, reportes (Reporte General,
estado de cuenta, asistencia por clase), WhatsApp (boton y avisos), dashboards, ministerios, historial de
ingresos, Mi perfil. Commit del informe y capturas en su rama. Informe final al lider: resumen + top 10.

## Equipo 3a — Motor unico de reportes, periodo, cumplimiento con corte y exportaciones
Base: `HU-reportes.md` (diagnostico D1-D7 y HU-R3, R5, R7, R13, R14 del plan maestro).
1. `App\Services\Reportes\MotorReportes` (o nombre equivalente): UNA sola forma de calcular, por persona y
   periodo, lo dado por categoria (USD convertido con el tipo de cambio del sobre, o el del dia si no
   hay) y lo esperado por promesa (semanal x domingos del mes, quincenal x2, mensual x1), con corte a
   fecha: lo esperado se cobra por meses completos anteriores al corte (el mes en curso no), lo dado
   cuenta todo hasta el corte; estado AL DIA si dado >= esperado acumulado. Reutilizar y absorber
   `ReporteGeneralService`, `InformePersonaService` y la logica de `CalculoPromesasService`/`reportePdf`
   para que TODOS den el mismo numero. Test de contrato: 3 personas x 2 periodos x 5 salidas identicas.
2. `PeriodoReporte` (anio + mes desde/hasta o fechas; corte) validado (inicio <= fin, max 24 meses).
3. Reporte "Cumplimiento de promesas con corte" (HU-R13): filtro de personas (todas / con promesa /
   ministerio / ids), corte (hoy por defecto); resumen (con promesa, al dia, pendientes, montos) y tabla
   (nombre, le correspondia, ha dado, diferencia, estado, ultimo aporte, observacion). Salidas: pantalla
   limpia en `/reportes/cumplimiento`, PDF presentable (portada corta, resumen, Pendientes, Al dia,
   sin promesa aparte; dibujado como `ReporteGeneralPdf` o con DomPDF si cabe en < 10 s para 220
   personas) y Excel (hojas Resumen, Mes a mes, Sin promesa; formato moneda, autofiltro, encabezados
   fijos). Modelo de referencia: `Downloads/07 - Iglesia/IBBLA/Cumplimiento_promesas_IBBLA_corte_2026-10-12.*`
   (lo hizo el lider a mano a partir del Reporte General; el reporte de la app debe dar lo mismo).
4. Estado de cuenta individual en lote (HU-R5): un PDF con portada, indice y una seccion por persona
   (salto de pagina), y Excel; rendimiento <= 10 s para 50 personas.
5. Plantilla unica de PDF y Excel (HU-R14) como clases base reutilizables por el equipo 3b.
6. Reporte General y estado de cuenta existentes pasan a usar el motor sin cambiar sus numeros (tests
   de A y E siguen verdes). `reportePdf` deja de sumar USD sin convertir.
Permisos: admin y tesorero. Auditoria de cada exportacion. Informe final: que hay, rutas, como probar,
tiempos medidos, dudas.

## Equipo 2 — Recuento por lineas, cerrado solo lectura, Accesos, sobres sin ruido, roles con logica
HU-REC-1 a REC-5 del plan maestro, en ese orden. Detalles:
- REC-1: `recuento/index`: tabla por lineas (fecha, dia, tipo, sobres, total, estado), buscador por
  fecha y filtros mes/tipo; una linea abre el culto. Se eliminan los cuadros. Pantalla de un culto:
  encabezado con resumen y acciones a la derecha.
- REC-2: tesorero en culto cerrado = solo lectura (botones deshabilitados con tooltip "Cerrado el dd/mm
  por X"); backend tambien lo rechaza (403 con mensaje). Admin si puede.
- REC-3: modulo Accesos (`/admin/accesos`): otorgar a un usuario edicion sobre un culto, varios, todos o
  un rango de fechas, con vencimiento opcional; lista de permisos vigentes, revocar; auditoria.
  Policy/Gate unico `puedeEditarCulto(user, culto)` usado por recuento, sobres y cierre. Migracion
  `permisos_edicion`. Los usuarios con permiso siempre pueden crear cultos.
- REC-4: crear/editar sobre muestra solo su culto; flujo corto (foco en monto, siguiente sobre sin salir,
  teclado numerico en movil). Documentar el flujo propuesto antes de implementarlo.
- REC-5: `docs/ROLES.md` con la matriz definitiva (admin, tesorero, asistente, invitado, miembro) y la
  app la cumple: componente/regla unica para "sin permiso = deshabilitado con tooltip u oculto".
Tests de cada HU. Coordinacion: no tocar layouts; si el QA (equipo 1) ya entrego, leer su informe.

## Equipo 4 — UI profesional, upbar fija, buscador global y manual en la app
HU-UI-1 a UI-4. Guia: HubSpot/Salesforce (limpio, denso pero ordenado, acciones a la derecha, filtros
arriba, tarjetas de resumen, estados vacios utiles). Mantener el menu lateral de B; agregar la barra
superior fija (logo, buscador global, boton Manual, usuario/rol, salir) como en ARCA. Buscador global
por palabras: personas, cultos (fecha), pantallas y temas del manual, resultados agrupados, con teclado.
Manual dentro de la app (`/manual`): una pagina por modulo, cada boton explicado (que hace, quien puede,
que pasa despues), capturas, indice y busqueda; boton "?" en cada pantalla que abre su seccion; export a
PDF. `docs/UI.md` con tokens y componentes. Aplicar el sistema a todas las pantallas sin romper lo de B.
Capturas antes/despues en `docs/ux/`. Es el UNICO equipo que toca `resources/views/layouts/`.

## Equipo 5 — WhatsApp opt-in de la persona, bot Meta (config) y analisis de ingresos
- WA-1: en Mi perfil, "Recibir mis reportes por WhatsApp": confirmar numero, pasos ilustrados (imagenes
  en `public/images/manual/whatsapp/*.png`, generadas o capturas), prueba de envio (abre wa.me con un
  mensaje de prueba), consentimiento guardado (fecha) y desactivar. Tabla/columnas necesarias con
  migracion. Tests.
- WA-2: driver `meta` ya existe en `config/whatsapp.php`; agregar webhook de entrada
  (`/webhooks/whatsapp`) con verificacion de token y comandos: "reporte <mes>", "reporte anual",
  "estado de cuenta" -> responde con el enlace firmado al PDF (y el PDF como documento cuando el driver
  lo permita). Todo detras de config; sin cuenta real se prueba con tests y un comando artisan que simula
  el mensaje entrante. Nota: las conversaciones iniciadas por la persona son gratis en Meta.
- AN-1: `app/Console/Commands/AnalizarIngresos.php` (o script) que cuadra sobres vs detalles vs totales
  de culto vs estado de cuenta por persona, detecta duplicados, sobres sin persona, USD sin tipo de
  cambio, cultos sin cerrar y compromisos desincronizados; salida en Markdown. Se corre primero sobre
  los datos locales y, cuando David traiga el volcado de produccion, sobre ese (`docs/plan-oct-2026/ANALISIS-INGRESOS.md`).

## Orden de lanzamiento y presupuesto
Tanda 1: equipos 1 y 3a. Tanda 2 (tras medir uso con get_usage): 2, 4 y 5. Tope 70 % del plan semanal;
si se llega, el lider detiene, commitea lo pendiente y reporta.
