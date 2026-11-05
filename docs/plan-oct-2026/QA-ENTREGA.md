# QA de entrega E1: segunda ronda de IBBLA

QA de entrega (Equipo 1). No se corrigió nada: este documento verifica y reporta. Fecha: 05/11/2026.
Base revisada: `integracion/oct-2026` en `237e091` (incluye `5bccd6c` más el ajuste del test de la barra
superior y notas del plan), traída por fast-forward a la rama `qa/revision-inicial`.

- Entorno: worktree `IBBLA-wt/qa-revision-inicial`, `php artisan serve --port=8111`, MySQL local `ibbla_qa`
  con `migrate:fresh --seed` + `VolumenDevSeeder` (150 personas, 63 cultos cerrados, 2.215 sobres,
  208 promesas) + `UsuariosDemoSeeder` + `compromisos:sincronizar`. `.env` sin `WHATSAPP_DRIVER`
  (= `link`): **no se mandó ningún mensaje real** ni se usó ninguna clave.
- Roles: admin, tesorero, asistente, miembro (Ana Hernandez Valverde, persona 11) y servidor.
- Resoluciones: 1366×768, 1024×768, 768×1024 y 390×844.
- Método: flujos a mano en el navegador integrado; matriz de 61 rutas × 5 roles por HTTP (códigos,
  redirecciones y tiempos); 26 descargas × 2 roles con tiempos; lectura de los PDF (PyMuPDF) y los Excel
  (openpyxl) para comparar cifras; auditoría visual automática con Chrome headless (puppeteer-core):
  62 pantallas distintas, 248 mediciones entre los 5 roles y las 4 resoluciones (desborde horizontal,
  elementos fuera de pantalla, botones cortados, controles chicos, textos en inglés, marca vieja,
  errores de consola); pruebas puntuales en `tinker` dentro de transacciones revertidas (validación del
  enlace de WhatsApp).
- Tests: `phpunit` contra `ibbla_qa_test`: **829 tests, 16 fallos** sobre `5bccd6c`: los 15 de Breeze
  conocidos más `UpbarTest::test_el_menu_del_usuario_lleva_a_mi_cuenta_y_el_miembro_ve_mi_perfil`. Con
  `237e091` `UpbarTest` pasa (9/9), así que la línea base queda en **los 15 de Breeze**.
- Datos que quedaron en `ibbla_qa` por las pruebas: persona 151 "Zacarias Prueba QA" (alta rápida),
  usuario 7 `intruso@ibbsc.com` (creado por `/register`, ver QE-004), clase "Niños 2-6", asistencia 1
  (cerrada), un acceso de edición del tesorero sobre un culto ya borrado, y los cultos 64 (05/11) y 65
  (08/11), creados y borrados con motivo.

## Resumen ejecutivo

La E1 resuelve casi todo lo que el QA inicial marcó como grave:

- El recuento ya es por líneas.
- Los sobres seguidos se registran en unos 3 s, con el nombre en grande junto a Guardar.
- Los anónimos no se pierden ni se rechazan.
- El culto cerrado es de solo lectura, con motivo, para el tesorero.
- El módulo Accesos funciona de punta a punta, con auditoría.
- Borrar un culto cerrado exige admin y motivo, y deja el detalle de lo borrado.
- La seguridad está bien: Super Admin protegido, API de asistencia con rol, redirecciones por rol.

**Las cifras por persona cuadran al céntimo** entre Compromisos, ficha, Mi perfil, estado de cuenta PDF,
cumplimiento (pantalla, PDF y Excel), estados de cuenta en lote y Reporte General. Todas las descargas
tardan menos de 10 s. La auditoría automática no encontró desbordes ni textos en inglés en ninguna
resolución.

Lo que impide un GO limpio son tres cosas que el usuario va a ver en la primera semana:

1. **El PDF de recuento** (el que firman los tesoreros) sale con la columna TOTAL cortada en el borde
   derecho (QE-001).
2. **El Excel de promesas** sigue con el cálculo viejo: promesas en dólares sin convertir y el mes en
   curso cobrado. Una persona sale "1.901 % cumplido" donde el estado de cuenta dice que debe ₡484.200
   (QE-002). Es justo el tipo de contradicción que la ronda vino a eliminar, y ese Excel también se
   adjunta en el correo mensual.
3. **Las pantallas nuevas de reportes no tienen puerta.** El menú "Reportes" abre el hub viejo con 2
   tarjetas. Cumplimiento solo se alcanza por un enlace de texto dentro del Dashboard de promesas.
   Estados de cuenta en lote no tiene ningún enlace, y el tesorero no tiene camino al Reporte General.
   El buscador tampoco los encuentra y el manual no los menciona (QE-003, QE-007).

### Veredicto: **NO-GO condicionado** (pasa a GO con 4 arreglos acotados)

Se puede subir a producción en cuanto se corrijan QE-001, QE-002, QE-003 y QE-004. Son cambios chicos,
sin migraciones. Lo demás puede ir después del deploy.

Condiciones del deploy:

- Respaldo de la BD antes de `migrate` (QE-017).
- `QUEUE_CONNECTION=sync` y el cron del scheduler en Hostinger.
- Cambiar las contraseñas de semilla que reporta `ibbla:verificar`.
- Correr `compromisos:sincronizar` después de migrar.

### Hallazgos nuevos por severidad

| Severidad | Cantidad | IDs |
|---|---|---|
| Bloqueante | 0 | — |
| Alta | 3 | QE-001, QE-002, QE-003 |
| Media | 7 | QE-004 a QE-010 |
| Baja | 10 | QE-011 a QE-020 |
| **Total** | **20** | |

Hallazgos del QA inicial (43): **25 corregidos, 13 parciales, 5 siguen igual** (tabla en la sección 3).

## 1. Top 10

| # | Hallazgo | Por qué importa |
|---|---|---|
| 1 | QE-001 PDF de recuento con la columna TOTAL cortada | Es el documento que se imprime y se firma cada domingo |
| 2 | QE-002 Excel de promesas: USD sin convertir y mes en curso cobrado | Misma persona, cifras distintas; va adjunto en el correo mensual |
| 3 | QE-003 Cumplimiento, estados de cuenta en lote y Reporte General sin camino en el menú (el tesorero no los ve) | El entregable principal de reportes no se encuentra |
| 4 | QE-004 `/register` abierto: cualquiera con un correo @ibbsc.com se crea un usuario | Cuentas sin control; consume el cupo de 10 usuarios; contradice el login |
| 5 | QE-007 Manual desactualizado en Recuento y Reportes; pantallas nuevas sin "?" | Enseña el comportamiento viejo ("vacío = anónimo", anónimos rechazados) |
| 6 | QE-006 Borrar o corregir un culto cerrado no recalcula la tabla compromisos | El Dashboard y el Excel de promesas cuentan dinero borrado |
| 7 | QE-005 ZIP de estados de cuenta con un grupo vacío da error 500 | Error del servidor con un filtro válido |
| 8 | QE-009 Avisos de WhatsApp enviados dentro de la petición (cola sync) | Con CallMeBot lento, guardar el 10.º sobre puede pasar de 10 s (504) |
| 9 | QE-008 Dashboard de promesas y Reporte de promesas cobran el mes en curso | Tercer "prometido del año" distinto (₡72,5 M frente a ₡65,6 M) |
| 10 | QE-010 Marca: icono de la app instalada es el de ANGEDA, login sin el nombre de IBBLA, correo "IBBSC" | Primera impresión del miembro al instalar la app |

## 2. Hallazgos nuevos

### QE-001 · PDF de recuento: la columna TOTAL queda cortada en el borde (Alta)
- Pantalla / rol / dispositivo: Recuento > PDF del culto (`/ingresos-asistencia/pdf-recuento/{culto}`);
  admin y tesorero; cualquier dispositivo (es el PDF).
- Pasos: culto del 11/10/2026 (id 59) > PDF.
- Qué pasa: las cifras ya cuadran (total general 1.733.317,11 = efectivo 1.302.437,03 + transferencias
  430.880,08 = fila TOTALES; ver QA-026), pero la tabla es más ancha que la hoja A4 horizontal:
  - el encabezado "TOTAL" se lee "TOTA";
  - los totales de cada sobre quedan pegados al borde derecho o fuera de él (texto hasta x=844 en una
    hoja de 842 pt);
  - la primera página gasta media hoja en 5 tarjetas apiladas;
  - la tabla trae 11 sobres por página (7 páginas para 47 sobres).
- Qué debería pasar: la tabla entra completa en la hoja (anchos fijos o fuente menor, sin la columna
  "Notas" vacía), con el margen derecho igual al izquierdo.
- Captura: `docs/qa/entrega-pdf-recuento-total-cortado.jpg`.

### QE-002 · Excel de promesas con el cálculo viejo (USD sin convertir, mes en curso) (Alta)
- Pantalla / rol: Dashboard de promesas > "Descargar Excel" y `/ingresos-asistencia/excel-promesas`
  (admin y tesorero).
  - El mismo `PromesasExport` va adjunto en `reporte:enviar-mensual`.
  - El reporte de Servidores (`ServidorController`, líneas 158 y 237) usa las mismas columnas.
- Qué pasa: `PromesasExport` suma `compromisos.monto_prometido` y `monto_dado` en lugar de las columnas
  `_crc`.
  - Luis Ulate Rodriguez (persona 71), Misiones $49 cada quincena: el Excel dice "Prometido 2026 1.078"
    (dólares) contra "Dado 20.500" (colones), con **saldo +19.422 y 1.901,7 %**.
  - Su estado de cuenta, Compromisos, cumplimiento y Reporte General dicen: le corresponde ₡504.700, dado
    ₡20.500, −₡484.200.
  - Mismo problema con Andrés Salazar (CAMPA $43: 792,8 %) y Ricardo Murillo (Construcción $33).
- Además:
  - la columna "Promesa Mensual" muestra el monto por periodo (₡14.000 semanal aparece como mensual);
  - "Prometido 2026" incluye noviembre, que está en curso (Luis, Préstamo: 672.000 frente a 602.000 del
    estado de cuenta);
  - las celdas sin dato salen vacías;
  - aparece la promesa de diezmo, que no es compromiso.
- Qué debería pasar: el Excel sale del mismo motor, o al menos de `monto_prometido_crc` y
  `monto_dado_crc` con meses completos. La promesa y su frecuencia van como texto, igual que en el Excel
  del cumplimiento.

### QE-003 · Los reportes nuevos no tienen puerta en el menú (Alta)
- Pantalla / rol: menú "Reportes" (`/ingresos-asistencia`), Principal, buscador; admin y tesorero.
- Qué pasa:
  - El menú "Reportes" abre "Ingresos y Asistencia" con solo 2 tarjetas (Ingresos, Promesas).
  - `/reportes/cumplimiento` solo se alcanza por un enlace de texto dentro del Dashboard de promesas.
  - `/reportes/estados-de-cuenta` no tiene ningún enlace en la app.
  - El Reporte General y el Reporte PDF solo están en Gestionar Personas, que el tesorero no ve. La ruta
    le responde 200, pero no tiene cómo llegar.
  - El buscador global con "cumplimiento", "estados de cuenta" o "reporte general" devuelve el Dashboard
    de promesas, el Reporte de promesas y el manual, nunca estas pantallas.
  - Esas dos pantallas y Accesos tampoco tienen el botón "?" de ayuda.
- Qué debería pasar: el hub de Reportes tiene tarjetas para Cumplimiento, Estados de cuenta (lote),
  Reporte General y Contribuciones (admin y tesorero). Las pantallas están en el buscador y tienen su
  "?".

### QE-004 · `/register` sigue abierto (Media; recomendado antes del deploy)
- Pantalla / rol: `/register` sin sesión.
- Pasos: abrir `/register`, poner un nombre, `intruso@ibbsc.com` y una contraseña, y tocar
  "Registrarse".
- Qué pasa: se crea el usuario 7 con rol miembro en IBBLA, sin verificar el correo, y entra a Principal.
  - Cualquiera que sepa el dominio de la iglesia puede hacerlo.
  - Cada alta consume el cupo `max_usuarios` (10).
  - El login dice lo contrario: "solo entran las personas que él agregó".
  - Es el resto de QA-019.
- Qué debería pasar: quitar las rutas de registro; las altas las hace el admin.

### QE-005 · ZIP de estados de cuenta con un grupo vacío da error 500 (Media)
- Pantalla / rol: Reportes > Estados de cuenta > Personas "Por ministerio" (o cualquier filtro sin
  personas) > ZIP; admin y tesorero.
- Qué pasa: con 0 personas, `ZipArchive::close()` no crea el archivo y `response()->download()` falla
  ("The file … estB77E.tmp does not exist"): error 500.
  - Reproducido con `grupo=ministerio&ministerio=3` (los datos de prueba no tienen servidores en
    ministerios).
  - El PDF y el Excel del mismo filtro sí responden.
- Detalle menor: cuando el filtro pasa de 40 personas, el aviso "El ZIP admite hasta 40 personas…" se
  muestra dos veces.
- Qué debería pasar: mostrar "No hay personas en ese filtro" y volver a la pantalla.

### QE-006 · Borrar o corregir un culto cerrado no recalcula la tabla compromisos (Media)
- Pasos: cerrar el culto del 05/11 con sobres de Ricardo Murillo (₡3.000 en Construcción) y Luis Ulate
  ($49 en Misiones), y borrarlo como admin.
- Qué pasa: el Dashboard de promesas siguió mostrando "Dado ₡6.023.281,73" contra ₡5.995.047 del
  cumplimiento; la diferencia son los ₡28.235 del culto borrado.
  - Después, `compromisos:sincronizar --mes=11` actualizó 2 filas.
  - Solo el cierre de un culto (observer) y el botón "Recalcular" escriben la tabla. Borrar un culto
    cerrado o corregir sobres con acceso de edición no la actualizan.
- Qué debería pasar: recalcular el mes del culto al borrarlo y al corregir un sobre de un culto cerrado.

### QE-007 · Manual desactualizado en Recuento y Reportes (Media)
- `manual/paginas/02-recuento.md` todavía dice:
  - "Si el sobre no trae nombre, déjalo en Anónimo" y "Vacía = sobre anónimo";
  - "+ Agregar Persona Rápida";
  - que dos anónimos seguidos se rechazan y que "el formulario no conserva lo que escribiste";
  - que el culto cerrado pasa a "Cultos Cerrados (Archivo)", al final de la pantalla;
  - que "solo el Administrador puede corregirlo" (no menciona Accesos).
- `07-reportes.md` dice "Reportes de personas (solo Administrador)" y no menciona Cumplimiento ni
  Estados de cuenta en lote.
- Ninguna página cubre el módulo Accesos.
- Qué debería pasar: el manual está al día con la E1; es lo que se va a enseñar a los tesoreros.

### QE-008 · Dashboard de promesas y Reporte de promesas cobran el mes en curso (Media)
- El Dashboard de promesas abre en "2026 · 365 días": prometido ₡72.468.445 (incluye noviembre
  completo), cumplimiento 8,3 %.
- El cumplimiento al corte dice: le correspondía ₡65.585.135 (enero a octubre). La diferencia,
  ₡6.883.310, es lo prometido de noviembre.
- El Reporte de promesas abre en noviembre, que está en curso, y muestra "Total faltante ₡6.285.210" de
  un mes que todavía no se cobra.
- Los formatos de miles son distintos entre ambos (₡72,468,445.00 frente a ₡6.883.310).
- Es el resto de QA-027.
- Qué debería pasar: la misma regla del motor (meses completos), o rotular "incluye el mes en curso".

### QE-009 · Los avisos de WhatsApp se envían dentro de la petición (Media; riesgo no reproducido)
- `EnviarAvisoWhatsApp` es `ShouldQueue`, pero en Hostinger se usa `QUEUE_CONNECTION=sync` (lo dice el
  `.env.example`).
- Así, el envío a CallMeBot corre dentro de "crear culto" o del guardado del 10.º sobre, con
  `connect_timeout` de 3 s y `timeout` de 10 s por destinatario.
- Con CallMeBot lento y 2 líderes activados, ese guardado puede pasar de los 10 s de Hostinger.
- No se probó con envíos reales (prohibido en este QA).
- Qué debería pasar: enviar con `dispatch()->afterResponse()` o desde el scheduler, nunca durante el
  guardado.

### QE-010 · Marca vieja en la app instalada, el login y el correo (Media)
- `public/manifest.json`: icono `angeda_deep_ocean.png` (la "A" turquesa de ANGEDA), `theme_color`
  turquesa, nombre "Sistema de Administración de Iglesia" / "Iglesia". Quien instale la app en el
  teléfono ve el logo de ANGEDA.
- Login: muestra el logo de IBBLA, pero el título dice "Administración de la iglesia" y la pestaña,
  "Iniciar sesión - Sistema de Administración", sin el nombre de IBBLA (QA-039).
- `App\Mail\ReporteMensualMail`: asunto fijo "IBBSC — Reportes de …". `tenant_title()` y el shell de la
  App de Clases usan "IBBSC" como respaldo.
- Pie de varios PDF: "Sistema de Administracion" (sin tilde).

### QE-011 · Cumplimiento: "Último aporte" no usa la misma base que "Ha dado" (Baja)
- Ana Hernandez Valverde: "Ha dado ₡0 · Último aporte 19/07/2026 · sin aportes en 10 meses (Enero a
  Octubre)". El 19/07 dio para Construcción, que no prometió; "Ha dado" solo cuenta Préstamo.
- Luis Ulate: último aporte el 09/08, aunque dio diezmo el 11/10.
- Para el pastor se lee como una contradicción.
- Qué debería pasar: decir "Último aporte a sus compromisos", con la misma base que "Ha dado", o
  explicarlo en pantalla.

### QE-012 · El tesorero ve y descarga reportes de asistencia (Baja)
- `docs/ROLES.md` (fila "Reportes y Excel de asistencia") dice que el tesorero no los ve.
- En la app, el tesorero ve "Descargar Excel" en el Dashboard de asistencia, y
  `/ingresos-asistencia/excel-asistencia` y `/ingresos-asistencia/asistencia` le responden 200.
- La propia matriz lo lista como pendiente.

### QE-013 · Auditoría: cierre sin registro y entradas duplicadas (Baja)
- Cerrar el culto del 05/11 no dejó ningún registro: la ruta `recuento.cerrar-culto` no queda en
  `audit_logs`.
- Cada acción con auditoría propia sale dos veces: la genérica del middleware (con la ruta) y la
  semántica.
- Siguen las visitas GET y las columnas técnicas ("auth.login", "auth.failed", "cultos.index", agente del
  navegador). Es el resto de QA-042.
- Accesos: el acceso concedido sobre un culto que después se borró sigue "vigente" ("Un culto que ya no
  existe").

### QE-014 · Barra del sobre: "ANÓNIMO" en rojo antes de elegir (Baja)
- Al abrir "Registrar sobres", y mientras se escribe un nombre que todavía no se eligió, la barra junto
  a Guardar dice "SOBRE DE ANÓNIMO" en rojo.
  - El guardado sí lo frena: pide confirmación o abre el alta rápida.
  - Pero el rótulo hace pensar que el sobre ya quedó anónimo.
  - Debería decir "Sin persona" hasta que se marque «Sobre anónimo».
- Teclado: con un solo resultado, el primero ya viene marcado, y Flecha abajo + Enter elige "Crear
  «…» como persona nueva" en vez de la persona (riesgo de duplicados).
- En el buscador del sobre solo se ve el nombre, sin PIN ni teléfono, y hay homónimos (resto de QA-008).

### QE-015 · Asistencia cerrada sigue editable en pantalla (Baja; QA-021 sigue igual)
- El asistente y el admin abren `/asistencia/1/edit` de una asistencia cerrada sin ningún aviso. Al
  guardar sale "No se puede editar una asistencia cerrada", y el admin tampoco puede corregirla.
- Se registra aquí porque Recuento ya tiene la regla buena (Accesos) y Asistencia no.

### QE-016 · App de Clases: tesorero redirigido al login, miembro sin clase con pantalla vacía (Baja)
- El tesorero en `/app/clase` recibe un 302 a `/login` (y de ahí va a Principal), en lugar de un 403 o de
  no ver la opción.
- El miembro que no es maestra entra a "App de Clases — Selecciona una clase / No hay clases
  activas…". No está en su menú, pero la URL responde 200.

### QE-017 · Deploy sin respaldo previo; verificación con límite de 15 s (Baja)
- `deploy/hostinger.sh` corre `migrate --force` sin respaldo previo de la BD, y su "para volver atrás" no
  revierte migraciones. Recomendado: `mysqldump` antes del paso 3, o documentarlo como paso manual.
- `ibbla:verificar` mide los reportes contra un límite de 15 s (Hostinger corta a los 10 s) y no revisa
  `QUEUE_CONNECTION=sync` ni que exista el cron del scheduler.
- El CSS compilado del repo (`public/build/assets/app-BGrUNYG1.css`) no es el que genera hoy `vite build`:
  difieren 9 clases de vendor que no se usan. No tiene efecto visible, pero el build no es reproducible;
  conviene recompilar antes del tag.

### QE-018 · Textos sin tilde y gramática (Baja)
- Tipos de culto: "Miercoles", "Sabado".
- Categorías: "Construccion", "Prestamo" (son datos de la iglesia).
- Asistencia: "Nino", "Nina", "Asignalos".
- Personas: "Cada una pide confirmacion".
- Gramática: "Culto del 08/11/2026 eliminado con sus 1 sobres".
- Es el resto de QA-039.

### QE-019 · Principal: subtítulos de los accesos cortados con "…" (Baja)
- En 1366 los accesos rápidos muestran "Recuento del culto a…", "Capilla, clases, salv…" y "Buscar,
  agregar y e…".
- Se entienden, pero se ven inacabados; con dos líneas alcanza.

### QE-020 · Rutas viejas que siguen respondiendo (Baja; resto de QA-019)
- `/promesas` (admin) da 500: "Call to a member function sum() on null".
- `/usuarios/{id}` da 405 (antes 500); en producción se ve la página de error.
- `/profile` ya está en español y sin "Delete Account".

## 3. Hallazgos del QA inicial (43)

| ID | Estado | Evidencia (05/11) |
|---|---|---|
| QA-001 | Corregido | Sobres #3 y #4 anónimos guardados en menos de 30 s, sin aviso de duplicado |
| QA-002 | Corregido | `/recuento/2201/edit` (Miguel Ulate, inactivo): "Sobre de Miguel Ulate Herrera · persona inactiva"; al guardar sigue `persona_id=6` |
| QA-003 | Corregido | Solo admin; motivo obligatorio (mín. 10 caracteres) + casilla; auditoría "Eliminar culto" con fecha, total, sobres y motivo; el tesorero no tiene botón y `DELETE /cultos/64` le da 403 |
| QA-004 | Corregido | La pantalla del culto muestra solo ese culto; lista de cultos por líneas, 25 por página. Alto con 47 sobres: 6.606 px en 1366 y 7.487 px en 390 (antes 11.671 y 23.753) |
| QA-005 | Corregido | El tesorero ve sobres, totales y firmas del cerrado: "Cerrado el 05/11/2026 a las 14:31 por Administrador · solo lectura". Culto futuro: "Todavía no se puede cerrar: el culto es el 08/11/2026" (403 por URL) |
| QA-006 | Corregido | El admin corrige con el aviso "lo estás corrigiendo como administrador" y queda en Accesos > Últimos cambios; el tesorero ve en gris con motivo. Asistencia sigue distinta (QE-015) |
| QA-007 | Corregido | Lista: "05/11/2026 Hoy" primero, luego los anteriores; Nueva asistencia ordena del más reciente al más antiguo |
| QA-008 | Parcial | "Guardar y siguiente" en la misma pantalla (unos 3 s por sobre), foco en Persona, un solo control con búsqueda, nombre en grande. El buscador del sobre no muestra PIN ni teléfono (QE-014) |
| QA-009 | Corregido | Modal con resumen ("5 sobres, total ₡45.935. Ojo: 1 sobre está como Anónimo…"); Cerrar en gris si el culto está vacío o es futuro; firmas en culto cerrado auditadas con antes y después |
| QA-010 | Corregido | Tesorero: 200 en `excel-ingresos` y `excel-promesas`; asistente: 403; botones según el rol (resto en QE-012) |
| QA-011 | Corregido | `/` y `/login` con sesión: el miembro va a `/mi-perfil`, los demás a `/principal`, sin 403 |
| QA-012 | Corregido | `/usuarios/1/edit`, `PUT` y `DELETE /usuarios/1` dan 404; la lista no muestra al Super Admin |
| QA-013 | Corregido | Miembro y tesorero: `GET /asistencia-app/clases` 403 y `POST quick-add` 403 "No tienes permiso…"; `POST /asistencia-app/login` da 429 desde el 5.º intento |
| QA-014 | Corregido | "Yo" solo para el miembro con persona; admin, tesorero, asistente y servidor sin persona ven una pantalla que lo explica |
| QA-015 | Corregido | El asistente tiene Dashboard de asistencia; roles "Asistente - Asistencia y sus reportes" y "Servidor - Marcar su asistencia y Mi perfil"; sin "Invitado" |
| QA-016 | Parcial | El tesorero tiene permiso (200) en Reporte General, cumplimiento y estados de cuenta, pero no tiene camino en la interfaz (QE-003) |
| QA-017 | Corregido | Sin botón de invitado; `/login/guest` da 404 |
| QA-018 | Sigue igual | Usuarios no vincula persona; Personas > Editar crea el usuario sin `tenant_role_id` (`PersonaController`, l. 268). Mitigado: Mi perfil sin persona explica qué hacer |
| QA-019 | Parcial | `/profile` en español y sin "Delete Account"; `/register` sigue abierto (QE-004) y `/promesas` da 500 (QE-020) |
| QA-020 | Corregido | Con una sola clase activa, `/app/clase` redirige (302) a `/app/clase/ninos_2_6`, que responde 200 |
| QA-021 | Sigue igual | Ver QE-015 |
| QA-022 | Parcial | Ordenado del más reciente; sigue sin buscador ni preselección (63 opciones) y acepta un culto cerrado en Recuento (se registró asistencia en el del 01/11, cerrado) |
| QA-023 | Corregido | La lista de clases ya no muestra el slug |
| QA-024 | Parcial | Las salidas del motor cuadran al céntimo (sección 4); el Excel de promesas no (QE-002) |
| QA-025 | Corregido | 2.156 filas en `compromisos` antes y después de consultar 2025, 2027 y 2030; la pantalla dice "solo consulta, no modifica nada" |
| QA-026 | Parcial | Totales iguales: 1.733.317,11 en la cabecera, en la fila TOTALES y en efectivo + transferencias. La tabla sigue cortada (QE-001) |
| QA-027 | Parcial | "Profit" pasó a "excedente"; el dashboard y el reporte de promesas siguen con otra base y otro formato (QE-008) |
| QA-028 | Corregido | Cabecera: total contribuido, le corresponde (meses completos), ha dado en sus compromisos, diferencia y estado |
| QA-029 | Parcial | El Reporte General sigue con una página por persona (77 págs.); el resumen existe en Cumplimiento (4 págs.), pero no está enlazado (QE-003) |
| QA-030 | Corregido | Todas las descargas tardan menos de 10 s en local (sección 5) |
| QA-031 | Parcial | Los Excel nuevos traen filtro, fechas reales, ceros y panel fijo. Excel de ingresos: fechas como texto y sin filtro. Excel de promesas: celdas vacías. PDF de recuento: cortado |
| QA-032 | Corregido | Mi perfil: acumulado al corte (₡65.000 / ₡0 / −₡65.000), mes a mes plegado ("di / del mes"), sin "deudas" ni un "¡Cumplido!" contradictorio |
| QA-033 | Corregido | Ficha: "Dado en 2026 (al 05/11/2026) ₡218.500 · Compromisos: PENDIENTE (−₡1.198.700)"; promesa "$49.00 Quincenal" |
| QA-034 | Sigue igual | "Eliminar personas inactivas (10)" y "Borrar todas las promesas" con `confirm()` y sin auditoría propia; rutas comentadas como "temporal" |
| QA-035 | Corregido | Lista alfabética; `?buscar=8539` encuentra a Luis Ulate por teléfono |
| QA-036 | Sigue igual | `update` borra y recrea las promesas (l. 346); "Compromisos reiniciados correctamente desde " + `format('F Y')` (l. 504, en inglés) |
| QA-037 | Parcial | CallMeBot completo (tarjeta, validación, panel); el aviso de sobres sigue llegando también a quien registra; con `link` nadie se entera si no entra al panel |
| QA-038 | Corregido | Login, credenciales, límite de intentos y validaciones en español ("Para una transferencia hay que anotar el número de comprobante.") |
| QA-039 | Parcial | IBBLA en menú, títulos y PDF, sin "IBBSC" visible en pantallas; faltan el nombre en el login, el icono de la PWA (ANGEDA) y tildes (QE-010, QE-018) |
| QA-040 | Corregido | Auditoría automática: ningún control de menos de 36 px dentro del contenido en las 4 resoluciones |
| QA-041 | Parcial | Gestionar Cultos muestra "Cerrado"; el menú "Reportes" sigue abriendo "Ingresos y Asistencia" y el Reporte General sigue en Personas |
| QA-042 | Parcial | Las acciones críticas tienen detalle (culto borrado, firmas, accesos, alta rápida); siguen las visitas GET, las columnas técnicas y los duplicados (QE-013) |
| QA-043 | Sigue igual | El día 5: "Los ingresos bajaron 74.8% respecto a octubre 2026" y "202 de 206 promesas aún pendientes este mes" |

Totales: 25 corregidos, 13 parciales, 5 siguen igual.

## 4. Consistencia de cifras (corte al 05/11/2026)

- Persona A: **Luis Ulate Rodriguez** (71). Promesas: Misiones **$49 cada quincena**, Préstamo ₡14.000
  semanal, Micro ₡17.500 mensual.
- Persona B: **Ana Hernandez Valverde** (11, la del usuario miembro). Promesa: Préstamo ₡6.500 mensual.

| Salida | A: le corresponde | A: ha dado | A: diferencia | A: total contribuido | B: le corresponde | B: ha dado | B: diferencia | B: total contribuido |
|---|---|---|---|---|---|---|---|---|
| Compromisos (pantalla admin) | 1.281.700 | 83.000 | −1.198.700 PENDIENTE | 218.500 | 65.000 | 0 | −65.000 PENDIENTE | 47.500 |
| Ficha de la persona | — | — | −1.198.700 PENDIENTE | 218.500 | — | — | −65.000 PENDIENTE | 47.500 |
| Estado de cuenta PDF (ficha) | 1.281.700 | 83.000 | −1.198.700 | 218.500 | 65.000 | 0 | −65.000 | 47.500 |
| Mi perfil (pantalla y PDF del miembro) | n/a (sin usuario) | | | | 65.000 | 0 | −65.000 | 47.500 |
| Cumplimiento (pantalla y Excel) | 1.281.700 | 83.000 | −1.198.700 | — | 65.000 | 0 | −65.000 | — |
| Estados de cuenta en lote (Excel) | 1.281.700 | 83.000 | −1.198.700 | 218.500 | 65.000 | 0 | −65.000 | 47.500 |
| Reporte General (PDF, fila TOTAL) | — | 83.000 | −1.198.700 | — | — | 0 | −65.000 | — |
| **Excel de promesas** | **1.078 + 192.500 + 672.000** | 20.500 + 2.000 + 60.500 | **+19.422 (1.901,7 %) en Misiones** | — | **71.500** | vacío | **−71.500** | — |

Por categoría (A), le corresponde / ha dado / diferencia:

| Categoría | Le corresponde | Ha dado | Diferencia |
|---|---|---|---|
| Misiones | ₡504.700 | ₡20.500 | −₡484.200 |
| Préstamo | ₡602.000 | ₡60.500 | −₡541.500 |
| Micro | ₡175.000 | ₡2.000 | −₡173.000 |

Son iguales en Compromisos, en el PDF, en el Excel de lote (hoja Compromisos) y en el Reporte General.
En el mes a mes, Misiones da ₡50.470 por mes (49 × 2 × 515).

Totales del grupo: el cumplimiento ("le correspondía ₡65.585.135, ha dado ₡5.995.047") coincide con el
Reporte General ("GRAN TOTAL 5.995.047 / −59.590.088").

- La única salida que no cuadra es el Excel de promesas (QE-002).
- El Dashboard de promesas usa otra base (QE-008) y quedó desfasado tras borrar un culto (QE-006).
- Nota: el único tipo de cambio cargado es el de hoy (₡515), así que todas las conversiones usan ese
  valor. Con historia de tipos de cambio conviene repetir la comparación.

## 5. Tiempos medidos (local, segunda corrida, con otra auditoría corriendo en paralelo)

| Descarga / pantalla | Admin | Tesorero | Tamaño |
|---|---|---|---|
| Cumplimiento (pantalla) | 0,9 s | 1,2 s | 308 KB HTML |
| Cumplimiento PDF / Excel | 1,3 s / 1,9 s | 2,3 s / 2,7 s | 4 págs. / 30 KB |
| Estados de cuenta en lote, PDF (141 personas) | 2,6 s | 4,2 s | 145 págs., 403 KB |
| Estados de cuenta en lote, Excel (141) | 4,7 s (repetido: 6,4 / 6,2 / 4,7) | 5,7 s | 187 KB |
| Estados de cuenta, ZIP (3 personas) | — | 1,5 s | 3 PDF |
| Reporte General PDF | 3,0 s | 5,4 s | 77 págs. |
| Reporte PDF de contribuciones | 2,5 s | 3,4 s | 5 págs. |
| Estado de cuenta de una persona | 4,2 s | 4,9 s | 1 pág. |
| PDF de recuento (47 sobres) | 5,9 s (repetido: 5,3 / 7,5 / 4,3) | 7,3 s | 7 págs. |
| PDF de ingresos | 9,8 s (repetido: 5,7 / 3,4 / 4,6) | 5,6 s | 4 págs. |
| PDF de promesas mensual / anual | 4,0 s / 4,0 s | 3,6 s / 4,1 s | 1 / 2 págs. |
| Excel de ingresos / promesas | 3,4 s / 3,7 s | 2,6 s / 2,9 s | |
| Manual PDF | 1,7 s | 0,8 s | 58 págs., 2,8 MB |
| Dashboard / Dashboard de promesas | 1,4 s / 3,3 s | 1,0 s / 1,8 s | |
| Registrar un sobre ("Guardar y siguiente", en el navegador) | 3,2 a 3,9 s | | |
| `ibbla:verificar`: Reporte General / estado de cuenta | 1,1 s / 1,1 s | | |

Nada pasa de 10 s. El primer acceso tras levantar el servidor sí fue lento por las cachés frías:
Gestionar Personas 10,4 s, ficha 9,6 s, Gestionar Cultos 8,2 s. En Hostinger hay que medir de nuevo el
PDF de ingresos y el de recuento, que son los más cercanos al límite.

## 6. Roles y accesos (matriz de `docs/ROLES.md`)

La matriz de 61 rutas × 5 roles coincide con `docs/ROLES.md`, salvo lo anotado al final de esta sección.

- **Entradas:** `/` y `/login` con sesión llevan al miembro a `/mi-perfil` y a los demás a `/principal`;
  ningún 403.
- **Menús por rol:**

  | Rol | Entradas del menú |
  |---|---|
  | Admin | 21 entradas; sin "Yo" porque no tiene persona |
  | Tesorero | Principal, Cumpleañeros, **Agregar persona**, Recuento, Dashboard, Dashboard de promesas, Dashboard de asistencia, Reportes, Avisos (sin Personas) |
  | Asistente | Principal, Cumpleañeros, Asistencia, Dashboard de asistencia, Reportes (asistencia), Asistencia por clase |
  | Miembro | Principal y Yo |
  | Servidor | Principal y Marcar asistencia |

  Cumpleañeros está oculto (y da 403) para el miembro y el servidor.
- **"Sin permiso = oculto o gris":**
  - Recuento, culto cerrado para el tesorero: Agregar sobre, Dinero suelto, Egreso y Editar en gris con
    "Cerrado el 05/11 por Administrador. Solo lectura…", y 403 con el mismo motivo por URL.
  - Cerrar culto: en gris con motivo si el culto está vacío o es futuro.
  - Eliminar culto: oculto para el tesorero.
  - Personas: oculto para el tesorero, 403 por URL.
- **Seguridad:**
  - El admin no ve ni edita al Super Admin (404).
  - La API de asistencia controla el rol.
  - El login web y el de la app tienen límite de intentos.
  - `/login/guest` da 404.
  - Pendiente: `/register` (QE-004).
- **Diferencias con la matriz:** QE-012 (tesorero y asistencia), QE-016 (App de Clases), QE-003
  (tesorero sin camino a sus reportes).

## 7. UI

- **Barra superior:** fija en todas las pantallas, con el logo y el nombre de IBBLA, el buscador, Manual
  y el menú de usuario.
- **Buscador global:**
  - "/" lo abre; las flechas y Enter funcionan.
  - Busca por palabras: "ulate", "ana herna", "8539", "11/10", "octubre", "domingo".
  - Respeta el rol: el tesorero no ve personas y el asistente no ve Recuento.
  - No encuentra las pantallas nuevas de reportes (QE-003).
- **Manual:**
  - Índice de 14 secciones y búsqueda (`/manual?q=…`).
  - "?" en todas las pantallas revisadas, salvo Accesos, Cumplimiento y Estados de cuenta.
  - PDF completo de 58 páginas en 1,7 s.
  - El contenido de Recuento y Reportes está desactualizado (QE-007).
- **Login:** en español y sin invitado; los mensajes de error y de límite de intentos están en español.
  "auth.failed" no aparece en ninguna pantalla de usuario; solo en la columna Acción de Auditoría.
- **Marca:** logo de IBBLA en la barra, el login, Principal y los PDF; ningún "IBBSC" ni "ANGEDA" visible
  en pantallas. Sí aparecen en el icono de la app instalada y en el asunto del correo (QE-010).

### 7.1 Auditoría automática de pantallas

Chrome headless con sesión real de cada rol, 248 mediciones:

| Rol | Pantallas | Mediciones (× 4 resoluciones) |
|---|---|---|
| Admin | 39 | 156 |
| Tesorero | 10 | 40 |
| Asistente | 6 | 24 |
| Miembro | 4 | 16 |
| Servidor | 3 | 12 |

Las pantallas del admin incluyen Recuento, sobre, edición de sobre, Cultos, Accesos, Personas, ficha,
Compromisos, alta rápida, Cumplimiento, Estados de cuenta, dashboards, reportes, Asistencia, Avisos,
Usuarios, Auditoría, Ministerios, Clases, Servidores, Manual, Buscar, Mi perfil, Mi cuenta y
Cumpleañeros.

Resultado:

- Las 248 respondieron 200.
- **0 desbordes horizontales** y **0 elementos fuera de pantalla** en 1366, 1024, 768 y 390.
- Los únicos "textos cortados" detectados son etiquetas solo para lector de pantalla ("Saltar al
  contenido", "Buscar en el sistema"); no son visibles.
- **0 controles de menos de 36 px** dentro del contenido.
- **0 textos en inglés**; las claves técnicas "auth.login" y "auth.failed" aparecen solo en la tabla de
  Auditoría.
- **0 apariciones de "IBBSC" o "ANGEDA"**.
- **0 errores de JavaScript** en consola.

## 8. WhatsApp

- **Driver `link`:** el `.env` no define `WHATSAPP_DRIVER` (queda en `link`). Con ese driver las rutas de
  activación responden 404 y la tarjeta no se dibuja, como está diseñado.
- **Tarjeta de activación:** para revisarla se renderizaron Mi perfil y Mi cuenta en `tinker` con
  `whatsapp.driver=callmebot` solo en memoria, sin ningún envío.
  - Tarjeta "Avisos por WhatsApp · Sin activar".
  - 3 pasos con capturas (`images/manual/whatsapp/paso-1..4`).
  - Botón "Abrir WhatsApp con el mensaje listo".
  - Consentimiento con el nombre de la iglesia y el número registrado.
  - Para el tesorero sin teléfono, un aviso claro de que falta el número en Gestionar Usuarios.
- **Validación del enlace** (en el servicio, dentro de una transacción revertida):

  | Lo que se pega | Respuesta |
  |---|---|
  | Enlace de otro número | "Ese enlace es del número +506 8888-9999, pero el número que la iglesia tiene registrado para usted es +506 8517-7546…" |
  | Texto sin enlace | "No encontramos el enlace del bot en lo que pegó…" |
  | Enlace correcto, sin marcar la casilla | "Marque la casilla para aceptar…" |
  | Enlace del número correcto | Se acepta |

- **Panel de avisos** (`/avisos`): visible para admin y tesorero. Dice "No hay avisos pendientes" porque
  ningún líder tiene teléfono en los datos de prueba.
- **Riesgo de tiempo de respuesta:** QE-009.

## 9. Deploy

- **`php artisan ibbla:verificar`:** RESUMEN OK, con 6 avisos esperables en local:
  - APP_ENV local y APP_DEBUG true;
  - sin config cache ni route cache;
  - sin `public/storage`;
  - contraseñas de semilla en admin, super admin y tesorero.

  Reporte General: 1,1 s; estado de cuenta: 1,1 s.
- **`bash deploy/hostinger.sh --dry-run`:** se corrió en un clon local en
  `…/domains/ibbla.eu/public_html`, con su `.env` y `vendor` copiados y el repo local como origen.
  - Recorre los 6 pasos sin cambiar nada: fetch, composer omitido, "No pending migrations", cachés,
    storage:link y chequeo con curl en dry-run.
  - En el mismo clon funcionan `config:cache`, `route:cache` y `view:cache` (no hay closures que rompan
    el caché de rutas).
  - No se tocó producción.
- **Recomendaciones:** ver QE-017 (respaldo antes de migrar, límite de 10 s, revisar la cola y el cron).

## 10. Qué arreglar antes y después del deploy

**Antes del deploy (condición del GO):**
1. QE-001, PDF de recuento: que la tabla entre en la hoja.
2. QE-002, Excel de promesas (y reporte de Servidores): columnas `_crc`, meses completos y frecuencia
   como texto, o generarlo desde el motor.
3. QE-003: tarjetas en Reportes para Cumplimiento, Estados de cuenta, Reporte General y Contribuciones
   (admin y tesorero), con las pantallas en el buscador y con su "?".
4. QE-004: quitar `/register`.
5. Operativo: respaldo de la BD, `QUEUE_CONNECTION=sync`, cron del scheduler, cambiar las contraseñas de
   semilla y correr `compromisos:sincronizar` después de migrar.

**Muy recomendable antes (son chicos):**
- QE-005: ZIP con grupo vacío da 500.
- QE-006: recalcular al borrar o corregir un culto cerrado.
- QE-010: icono de la PWA y asunto del correo.

**Después del deploy (siguiente entrega):**
- QE-007: manual de Recuento y Reportes.
- QE-008: regla única en el dashboard y el reporte de promesas.
- QE-009: avisos fuera de la petición, antes de activar CallMeBot.
- QE-011 a QE-020.
- Los que siguen del QA inicial:
  - QA-018: dar acceso a una persona.
  - QA-021 / QE-015: asistencia cerrada.
  - QA-034: acciones masivas.
  - QA-036: promesas recreadas y mes en inglés.
  - QA-043: dashboard a mitad de mes.
