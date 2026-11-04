# Decisiones vigentes (cliente + lider) — leer antes de retomar cualquier equipo

Fecha de corte de este documento: 04/11/2026 (reanudacion tras la pausa del 13/10). Estas decisiones
mandan sobre los briefs cuando se contradigan.

## 1. Decisiones del cliente (Roy / David, 13/10)
1. Roy (quien administra la iglesia en la app) usa el rol **Administrador**.
2. Un culto cerrado se puede borrar **solo si es admin**: confirmacion explicita, motivo obligatorio y
   auditoria completa (culto, fecha, total, cuantos sobres). El tesorero no ve el boton.
3. El tesorero **no ve el modulo Personas** (lista, fichas, compromisos) pero **si puede agregar
   personas rapido**: alta rapida (nombre, telefono, opcional PIN/correo) desde donde registra sobres y
   desde el menu, sin acceso a lista ni edicion.
4. Se **elimina "Entrar como invitado"**: queda solo el login con usuario y contrasena de las personas
   agregadas. (Quitar boton y ruta/rol; si quitarlo rompe algo, dejarlo deshabilitado por config.)
5. Las **firmas del recuento se pueden corregir despues de firmadas**, incluso con el culto cerrado, por
   admin y tesorero, con auditoria de quien cambio que firma y cuando.
6. WhatsApp = **CallMeBot "como en ARCA"** (ver seccion 4). Sin bot de Meta (el driver meta queda guardado).
7. "Hermanos servidores" para el reporte de cumplimiento: aun sin definir; el filtro ofrece
   todas / con promesa / por ministerio / seleccion.

## 2. Reglas de calculo definitivas (las fija el lider por delegacion de David)
1. **Dado** para el cumplimiento = solo lo entregado en las categorias con promesa de esa persona. El
   diezmo y las categorias sin promesa se MUESTRAN (estado de cuenta y Excel) pero no cuentan para "al dia".
2. **Esperado** = solo meses completos anteriores a la fecha de corte; el mes en curso NO se cobra, pero
   lo dado en el mes en curso SI cuenta. **AL DIA** si dado acumulado >= esperado acumulado (tolerancia
   1 colon). El estado nunca se decide mes a mes; al miembro se le muestra el acumulado (mes a mes solo
   como detalle informativo, sin "deudas" por mes).
3. **Moneda**: todo en colones. Sobre en USD -> tipo de cambio guardado en el sobre; si no tiene, tipo de
   cambio (venta) de la fecha del culto; si tampoco, el de hoy. Promesa en USD -> para cada mes esperado,
   tipo de cambio de venta vigente al ultimo dia de ese mes (el de hoy para el mes en curso).
   Frecuencias: semanal x domingos del mes, quincenal x 2, mensual x 1.
4. **Lecturas nunca escriben**: la sincronizacion de compromisos sale de las pantallas (QA-025) y pasa a
   un comando `compromisos:sincronizar` idempotente, acotado a meses con cultos, que corre al cerrar un
   culto y desde un boton de admin.
5. **Visibilidad de reportes**: financieros (Reporte General, estados de cuenta, cumplimiento, lote) para
   admin y tesorero; asistencia para admin, tesorero y asistente; el miembro solo lo suyo.
6. **Duplicados de sobres**: solo si misma persona (no anonimo) + mismo culto + mismo total + mismo
   metodo en los ultimos 10 s; los anonimos nunca se rechazan como duplicados (solo se protege el doble
   clic del mismo formulario con un token de envio).
7. Cerrar culto y cerrar asistencia siguen siendo acciones separadas; el encabezado del culto muestra
   ambos estados. Dashboard de asistencia: admin, tesorero y asistente. Cumpleaneros: admin, tesorero y
   asistente (miembro no).
8. Editar un sobre nunca cambia a quien pertenece salvo que el usuario lo elija; persona inactiva se
   muestra con etiqueta "inactiva" y se conserva.
9. USD en PDFs de recuento: convertidos con el tipo de cambio del sobre; totales en colones con el
   detalle en USD entre parentesis (QA-026: un solo total).
10. Si algo queda ambiguo: elegir lo mas simple, documentarlo (docs/ROLES.md, REGLAS-CALCULO.md o el
    informe final) y seguir. No se le preguntan mas cosas al cliente.

## 3. Reparto de los hallazgos del QA inicial (docs/plan-oct-2026/QA-INICIAL.md)
- Equipo 3a (motor): QA-024 (cifras distintas por persona), QA-025 (lecturas que escriben), QA-026
  (helper de conversion USD, publico para el equipo 2), QA-010 (permisos de descargas), PDFs lentos
  (promesas anual 12-18 s, Reporte PDF 8-12 s), VolumenDevSeeder sin fechas futuras.
- Equipo 2 (recuento/permisos): QA-020, QA-002, QA-001 (ya hechos en su rama), QA-003, QA-011, QA-014,
  QA-012, QA-013, QA-010 (matriz), QA-026 (usar el helper), "cosas sin logica" de recuento.
- Equipo 4 (UI): responsive OK; lang/es (login y validaciones en ingles); matriz de roles para el manual;
  textos confusos en vistas que ya toque; login sin invitado (diseno).
- Equipo 5 (WhatsApp): QA-014 solo en lo suyo (Mi perfil robusto sin persona).

## 4. WhatsApp con CallMeBot — datos reales
- Flujo (igual que ARCA, repo C:\Users\rgonh\Documents\gestion-app-arca, buscar "CallMeBot"): el receptor
  agrega el contacto del bot, le manda "I allow callmebot to send me messages", el bot responde con la
  clave y un enlace completo (phone= y apikey=), y pega ese enlace (o solo la clave) en su perfil;
  boton "Enviar mensaje de prueba". Envio: GET https://api.callmebot.com/whatsapp.php?phone=+506...&text=...&apikey=...
  (timeout 10 s). Gratuito, de terceros, sin garantia: documentarlo asi.
- Numero del bot que respondio el 13/10: +34 644 91 07 79 (ARCA usa +34 644 97 54 14; ambos son del
  bot; los mensajes llegan por el contacto que el usuario ya tenga guardado). Valor por defecto en
  config/whatsapp.php, cambiable por .env.
- Respuestas del bot: primera vez "API Activated for your phone number. Your APIKEY is NNNNNNN";
  si ya existia "You already have an apikey. Your apikey is NNNNNNN You can send a test messages using
  the url https://api.callmebot.com/whatsapp.php?phone=506XXXXXXXX&text=This+is+a+Test&apikey=NNNNNNN".
  La validacion acepta enlace completo o clave sola y comprueba que phone coincida con el numero del
  receptor normalizado.
- Respuesta de la API en un envio correcto: HTTP 200 text/html con el texto "Message queued. You will
  receive it in a few seconds." (devuelve 200 tambien en errores: hay que leer el texto).
- Pruebas reales: SOLO al numero de David (+506 8676 3570), maximo 3 mensajes, solo con el comando
  `whatsapp:probar` terminado, nunca desde tests ni en bucle. La clave esta en el .env (no versionado)
  del worktree IBBLA-wt/whatsapp-persona-analisis como CALLMEBOT_TEST_PHONE / CALLMEBOT_TEST_APIKEY;
  no commitearla, no imprimirla (enmascarar ****336). El 13/10 el lider ya envio 1 mensaje real y llego.

## 5. Estado de cada rama al pausar (13/10) y que falta
Ver la nota "2026-10-13 (20:35)" en 00-PLAN.md: lista commit a commit y pendientes por equipo. Cada
equipo, al retomar: `git merge integracion/oct-2026` en su worktree (trae el QA y estos documentos),
leer su brief en 03-BRIEFS-EQUIPOS.md, su lista de pendientes y este archivo, y continuar.

## 6. Equipo 6 — Entrega y despliegue (nuevo, sonnet) — worktree IBBLA-wt/entrega-deploy
- `deploy/hostinger.sh` para correr EN el servidor (ruta real: /home/u598753224/domains/ibbla.eu/public_html,
  clon de git de 1KATZUNO/IBBLA): git fetch + checkout del tag o pull de main, `composer install --no-dev`
  solo si cambio composer.lock, `php artisan migrate --force`, optimize:clear + config/route/view:cache,
  storage:link si falta, y un chequeo final con curl a /login. Sin credenciales dentro.
- `docs/DEPLOY-HOSTINGER.md` que reemplaza al checklist heredado de IBBSC (cPanel/Verpex no aplican).
- Comando `php artisan ibbla:verificar`: entorno (APP_ENV, APP_DEBUG, caches), conexion a BD, migraciones
  pendientes, permisos de storage, tiempos de los reportes pesados con los datos actuales, y un resumen OK/FALLA.
- Limpiar `.env.example` (quitar las credenciales reales de IBBSC y la APP_KEY; dejar placeholders;
  documentar WHATSAPP_* y CALLMEBOT_*), agregar `vendor/` a .gitignore si falta, y revisar que ningun
  archivo versionado tenga secretos (grep de password/APP_KEY/apikey).
- No toca vistas ni logica de negocio.
