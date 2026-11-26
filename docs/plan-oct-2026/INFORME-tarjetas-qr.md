# Informe · Tarjetas con QR de acceso personal

Rama `feat/tarjetas-qr-trabajo` (sale de `feat/tarjetas-qr`). Especificación: `07-TARJETAS-QR.md`.
Base de datos de desarrollo `ibbla_rn` (150 personas de volumen) y `ibbla_rn_test` para phpunit.

## 1. Qué se hizo

| Pedido | Dónde quedó |
|---|---|
| QR por persona, secreto de 40 caracteres, `/t/{token}` | `personas.token_acceso` (único, oculto en JSON), `Persona::asegurarTokenAcceso()` / `renovarTokenAcceso()`; `Str::random(40)` (usa `random_bytes`). Nace la primera vez que se pide la tarjeta (ventana, PDF de una o PDF masivo). |
| Escanear sin cuenta: «Crea tu acceso» | `AccesoTarjetaController` + `resources/views/acceso/crear.blade.php`. Usuario (sin @: letras sin tilde, números, `.`, `-`, `_`, 3 a 30) o correo, y la contraseña dos veces (mínimo 8). Crea la cuenta de rol **miembro** de la iglesia de la persona, la enlaza (`personas.user_id`), si eligió correo y la ficha no tenía lo deja también como correo de la ficha, inicia sesión y va a **Mi perfil**. Todo dentro de una transacción con la persona bloqueada (dos envíos a la vez no crean dos cuentas). |
| Escanear con cuenta | `acceso/entrar.blade.php`: el inicio de sesión normal (`POST /login`) con su usuario o correo ya escrito; tiene que escribir la contraseña. El QR nunca inicia sesión. Si falla, vuelve a la misma tarjeta con el error. |
| Token inválido | `acceso/no-valido.blade.php` (404): «Este código ya no es válido. Pídele uno nuevo al administrador de tu iglesia.» También para una iglesia inactiva. |
| Con otra sesión abierta | Si es la suya, va directo a su inicio; si es otra cuenta, `acceso/otra-sesion.blade.php` pide cerrar sesión (no mezcla cuentas). |
| Límite de intentos | Por **tarjeta**: 20 por minuto al abrir y 10 al guardar; por **IP** un tope holgado (300 y 150 por minuto). Limitadores `tarjeta-qr` y `tarjeta-qr-guardar` de `AppServiceProvider`; quien se pasa ve «Espera un momento» (`acceso/espera.blade.php`). Ver contraseña: `throttle:30,1` por administrador. Ver sección 8. |
| Usuario o correo en el login normal | Ver sección 2. |
| Contraseña visible para el administrador | `users.password_visible` con cast `encrypted`; solo cuentas miembro (`User::esCuentaMiembro()`); interruptor `IBBLA_CONTRASENA_VISIBLE` en `config/ibbla.php`, encendido por defecto; botón **Ver contraseña** solo al administrador y auditado (quién vio la de quién y si había copia, nunca el valor); aviso al miembro en «Crea tu acceso», «Crea tu contraseña nueva» y Mi cuenta → Cambiar contraseña. |
| Restablecer acceso | Contraseña imposible de adivinar, borra la copia, cambia el `remember_token`, borra sus sesiones (driver `database`) y marca `users.debe_crear_contrasena`: el próximo escaneo pide «Crea tu contraseña nueva». Solo cuentas miembro y nunca la propia. Auditado. |
| Tarjeta flotante en Personas | Botón **Tarjeta** en cada renglón → ventana con la tarjeta (mismo diseño que el PDF, QR en SVG), estado de la cuenta, **Descargar tarjeta (PDF)**, **Ver contraseña**, **Restablecer acceso** y **Generar QR nuevo** (estas dos con confirmación dentro de la ventana). Funciona con `fetch` y también sin JavaScript (los formularios vuelven a Personas con el aviso). |
| PDF masivo | `/personas/tarjetas` (pantalla con las tres opciones y su conteo) y `/personas/tarjetas/pdf?filtro=promesa|activas|clase&clase=ID&accion=ver|descargar`. Carta vertical, 8 tarjetas de 9,5 × 6,35 cm (2 × 4), marcas de corte en el margen y guía gris fina; orden por clase (sin clase al final) y nombre. |
| Manual, ROLES, buscador | Personas: «Tarjetas con QR de acceso» (rutas `personas.tarjeta*`); Mi perfil: «Entrar con tu tarjeta QR» (rutas `acceso.*`); `docs/ROLES.md`; buscador global: «Tarjetas con QR de acceso» (solo admin) con el icono nuevo `qr`. |

**Con promesa** = promesa vigente (`promesas`) **o** programada en un corte de Promesas futuras todavía sin
activar (`promesas_programadas` de `cortes_promesas.activado_at IS NULL`), estén activas o no (manda la
promesa). **Activas** = `activo = 1`. **Una clase** = las activas de esa clase; la tarjeta muestra esa clase.
**Clase principal** de la tarjeta: la clase donde la persona es miembro antes que visita, luego por el orden de
las clases (activas primero); sin clase, la tarjeta no la muestra.

## 2. Usuario o correo: cómo se decidió

Lo que había: `users.email` obligatorio y único; Breeze con `LoginRequest` (regla `email`, `Auth::attempt` por
correo, límite de 5 intentos por correo + IP); `AuthenticatedSessionController` que, si el usuario no tiene
iglesia, la busca por el dominio del correo (`tenant_email_domains`); el historial de ingresos guarda el
correo de los intentos fallidos; Usuarios y Mi cuenta exigen correo.

Opciones evaluadas:

1. **Guardar el usuario en la columna `email`** (sin @). Cero cambios de esquema, pero cualquier código que
   trate `email` como correo (recuperar contraseña, notificaciones, dominios de iglesia, validaciones `email`
   de Usuarios y Mi cuenta) recibiría algo que no es un correo; es un error escondido para el futuro.
2. **Correo sintético** (`usuario@usuarios.local`): igual de engañoso y se vería en pantallas y auditoría.
3. **Columna `usuario` aparte y correo opcional** (elegida). Es explícita y el camino de quien ya entra con
   correo no cambia: el formulario sigue enviando el campo `email`; si trae `@` se hace exactamente el
   `Auth::attempt(['email' => …])` de siempre; si no trae `@`, `Auth::attempt(['usuario' => minúsculas])`.
   Un usuario no puede contener `@`, así que no hay ambigüedad. La clave del límite de intentos es la misma.

Ajustes que exige un correo nulo (todos revisados y probados): la búsqueda de iglesia por dominio solo corre
si hay correo; el historial de intentos fallidos anota el usuario; Mi cuenta muestra el usuario y deja el
correo opcional para esas cuentas; Usuarios (lista y editar) muestra «Usuario: …» y no exige correo a esas
cuentas (a las que tienen correo se lo sigue exigiendo); la barra superior muestra el usuario si no hay
correo. Recuperar contraseña por correo no aplica a quien no tiene correo: para eso está «Restablecer
acceso». El texto del login pasó a «Correo electrónico o usuario» (sigue conteniendo «Correo electrónico»,
que exige `LoginEspanolTest`).

La copia visible vive en `User`: `establecerContrasena()` pone hash y copia en un solo lugar (Mi cuenta,
Usuarios, Personas y la tarjeta la usan) y un `saving` del modelo garantiza que **nunca** quede copia en una
cuenta que no sea miembro (también si el administrador le cambia el rol) ni con el interruptor apagado, y que
se vacíe si la contraseña cambia por otra vía (recuperar por correo): el administrador nunca ve una vieja. Si
la copia no se puede descifrar (APP_KEY cambiada), se trata como «sin copia» en vez de romper la tarjeta.

## 3. Cómo probarlo a mano

Servidor: `php artisan serve --host=127.0.0.1 --port=8124` en el worktree (BD `ibbla_rn`). Administrador de
las semillas: `admin@ibbsc.com` (clave en `AdminUserSeeder`).

1. **Tarjeta flotante**: `http://127.0.0.1:8124/personas` como administrador → botón **Tarjeta** de cualquier
   persona. Ves nombre, clase (a `ibbla_rn` le agregué 5 clases de ejemplo y repartí 129 personas), QR y
   «Cuenta: Sin cuenta». **Descargar tarjeta (PDF)** abre la tarjeta sola.
2. **Crear acceso**: copia la dirección del QR (o mira `personas.token_acceso`), cierra sesión y abre
   `http://127.0.0.1:8124/t/<token>` → «Crea tu acceso». Escribe un usuario sin @ (p. ej. `pruebaqr`) y una
   contraseña dos veces → entras a **Mi perfil** con el aviso «Tu cuenta quedó creada…».
3. **Login normal con usuario**: cierra sesión, `/login`, escribe `pruebaqr` y la contraseña → Mi perfil.
4. **Con cuenta**: vuelve a abrir `/t/<token>` sin sesión → «Entra a tu cuenta» con `pruebaqr` ya escrito; una
   contraseña equivocada no entra y vuelve a la tarjeta con el error.
5. **Ver contraseña**: como administrador, Personas → Tarjeta de esa persona → **Ver contraseña** → aparece;
   en Auditoría queda «Ver contraseña de un miembro» (sin el valor).
6. **Restablecer acceso** → confirmación → la ventana dice «Debe crear contraseña nueva»; abre `/t/<token>` sin
   sesión → «Crea tu contraseña nueva» → entras con la nueva; la vieja ya no sirve.
7. **Generar QR nuevo** → confirmación → `/t/<token viejo>` dice «Este código ya no es válido».
8. **Masivo**: Personas → **Tarjetas con QR** (o buscador: «tarjetas») → elige opción → **Ver PDF**.
9. **Interruptor**: `IBBLA_CONTRASENA_VISIBLE=false` en `.env` y `php artisan config:clear` → no aparece Ver
   contraseña ni el aviso, y no se guardan copias.
10. Con tesorero, asistente o miembro: `/personas/tarjetas` y `/personas/1/tarjeta` → 403.

## 4. Tiempos medidos (equipo de desarrollo)

| Qué | Tiempo |
|---|---|
| PDF masivo, **255 tarjetas, ningún QR en caché** (peor caso; script directo) | **3,9 s** |
| PDF masivo, 140 activas, con los QR en caché (script directo) | 0,4 – 0,7 s |
| PDF masivo por HTTP (`artisan serve`), 140 activas: primera vez / siguientes | 4,3 s / 1,5 s |
| PDF masivo por HTTP, 87 con promesa (en caché) | 1,2 s |
| Pantalla `/personas/tarjetas` | 0,7 s |
| Tarjeta flotante (`/personas/{id}/tarjeta`) | 0,5 s |
| PDF de una tarjeta | 0,7 s |
| Test `TarjetasMasivoTest::test_el_pdf_de_255_personas…` (HTTP, sin caché) | < 10 s exigido; la segunda < 3 s |

Casi todo el tiempo de la primera vez es calcular cada QR (~15 ms: la librería prueba las 8 máscaras); dibujar
las 255 tarjetas sobre el lienzo cuesta menos de 1 s. Por eso cada matriz queda en la caché de la app
(`CACHE_STORE=database`, una lectura y una escritura por PDF) y desde la segunda descarga sale en ~1 s.
El PDF de 140 tarjetas pesa ~520 KB (el logo va una sola vez).

**QR verificado decodificándolo**: (a) `tests/Unit/MatrizQrTest` lo decodifica con un lector propio (formato,
máscara, zigzag y bloques) y obtiene la dirección exacta, nivel M; (b) a mano, rasterizando la página 1 del PDF
de ejemplo a 200 ppp y decodificando las 8 tarjetas con un lector independiente en Python
(`tq_decodificar.py` del scratchpad): las 8 dan su `https://ibbla.eu/t/<token>` y coinciden con la base. Nivel M,
versión 4 (33 × 33 módulos), margen blanco de 4 módulos, QR de 3,5 cm (4,3 cm con el margen; ~1,05 mm por
módulo). Falta la prueba
con un teléfono sobre papel impreso (ver pendientes).

Ejemplo para ver el diseño (datos ficticios, 140 activas, 18 páginas): `tarjetas-ejemplo.pdf` y
`tarjetas-ejemplo-p1.png` en el scratchpad de la sesión.

## 5. Archivos

Nuevos: `config/ibbla.php`, migración `2026_11_26_000001_tarjetas_qr_acceso.php`,
`app/Http/Controllers/{AccesoTarjetaController,TarjetaPersonaController}.php`,
`app/Services/Tarjetas/{TarjetasAcceso,TarjetasPdf}.php`, `app/Support/Qr/MatrizQr.php`,
`resources/views/acceso/*`, `resources/views/personas/tarjetas/{_tarjeta,masivo}.blade.php`,
`tests/Concerns/CreaEscenarioTarjetas.php`, `tests/Feature/TarjetasQr/*` (5 archivos), `tests/Unit/MatrizQrTest.php`.

Tocados: `User`, `Persona`, `LoginRequest`, `AuthenticatedSessionController`, `PasswordController`,
`ProfileUpdateRequest`, `RegistrarAccesoSubscriber`, `UserController`, `PersonaController` (crear la cuenta con
`establecerContrasena`), `LienzoPdf` (`rectangulos()`, `imagenBase64Repetida()`), `Iconos` (`qr`), `Menu`
(entrada del buscador), `routes/web.php`, vistas de login, Mi cuenta, Usuarios (lista y editar), Personas
(lista) y `layouts/partials/upbar.blade.php` (una línea: el usuario si no hay correo), manual (04 y 13) y
`docs/ROLES.md`.

## 6. Notas para el manual de usuario

Ya escritas en `04-personas.md` («Tarjetas con QR de acceso», «La tarjeta de una persona», «Imprimir todas
las tarjetas» y dos preguntas frecuentes) y `13-mi-perfil.md` («Entrar con tu tarjeta QR»). Textos exactos
nuevos: botón **Tarjeta**; botón **Tarjetas con QR**; ventana «Tarjeta con QR de acceso»; **Descargar tarjeta
(PDF)**, **Ver contraseña**, **Ocultar**, **Restablecer acceso** / **Sí, restablecer acceso**, **Generar QR
nuevo** / **Sí, generar QR nuevo**; estados «Sin cuenta», «Con cuenta», «Debe crear contraseña nueva»;
pantalla «Tarjetas con QR de acceso» con **Personas con promesa**, **Todas las personas activas**, **Una
clase**, **Ver PDF**, **Descargar PDF**; páginas públicas «Crea tu acceso» (**Crear mi acceso y entrar**),
«Crea tu contraseña nueva» (**Guardar y entrar**), «Entra a tu cuenta» (**Entrar**), «Este código ya no es
válido», «Tienes otra sesión abierta». Login: «Correo electrónico o usuario». Faltan capturas
(`tools/manual/capturas.json`): la ventana de la tarjeta y la pantalla masiva.

## 7. Pendientes y riesgos

- **Suite completa**: 1059 pruebas, 6482 aserciones, 14 fallos (los mismos 14 de Breeze de la línea base; ninguno nuevo ni distinto). Lint de Blade vacío.
- **Probar con un teléfono** el QR impreso en papel (verificado decodificando la imagen del PDF, no con cámara).
- **Primera descarga masiva en Hostinger**: sin ningún QR en caché, 255 tarjetas tardan ~3,9 s aquí; si el
  servidor fuera 2 veces más lento serían ~8 s. Desde la segunda vez ~1 s. Si llegara a cortarse, basta
  descargar por clase una vez (deja los QR en caché) o reintentar. Generar QR nuevo deja la matriz vieja en
  la caché (unos 1,2 KB, inofensivo).
- **El QR apunta al dominio por el que entró el administrador** (`route()` de la petición): las tarjetas de
  producción hay que generarlas desde `ibbla.eu`, no desde QA ni desde una IP.
- **Quien tenga en la mano la tarjeta de una persona sin cuenta puede crearle el acceso** (es el diseño
  pedido); el manual dice que se entreguen en mano. Restablecer acceso + Generar QR nuevo lo corrigen.
- **Copia visible**: depende de `APP_KEY`; si se cambia la llave sin `APP_PREVIOUS_KEYS`, las copias dejan de
  leerse (la tarjeta dice «sin copia» y queda Restablecer acceso). Las cuentas creadas antes de esta versión
  no tienen copia hasta que cambien su contraseña o se restablezcan.
- La migración vuelve `users.email` opcional (`ALTER … MODIFY … NULL`, compatible con MariaDB 11.8); el
  `down()` solo puede volver a exigirlo si ninguna cuenta quedó sin correo.
- El título de la pestaña de las páginas públicas es el del layout de invitado («Iniciar sesión · IBBLA»):
  si se quiere otro, hay que darle una sección de título a `layouts/guest.blade.php`.
- `ibbla_rn` quedó con 5 clases de ejemplo y 129 asignaciones (para que el PDF muestre clases); la cuenta de
  prueba que creé en la verificación con navegador la borré.

## 8. Revisión de integración (líder técnico, 26/11)

Integrado en `feat/tarjetas-qr`. Suite completa: 1061 pruebas, 14 fallos (los 14 de Breeze de la línea base).

**Corregido antes de subir**

- **Límite de intentos por red.** Las rutas públicas tenían 30 aperturas y 10 guardados por minuto **por IP**.
  El día que se repartan las tarjetas escanea toda la iglesia desde el Wi-Fi del templo, que sale a internet
  con una sola IP: la persona número 11 que creara su acceso en el mismo minuto recibía el «429 Too Many
  Requests» del framework, en inglés. Ahora el límite corto es por tarjeta y el de la IP es un tope holgado;
  adivinar un token de 40 caracteres no es viable, así que no se pierde protección. Pruebas:
  `test_la_misma_tarjeta_tiene_limite_de_intentos_y_lo_avisa_en_espanol` y
  `test_toda_la_iglesia_puede_escanear_a_la_vez_desde_la_misma_red` (40 personas crean su acceso seguidas
  desde la misma IP).
- **Mensaje de contraseña equivocada.** Decía «El correo o la contraseña no son correctos» también a quien
  entra con usuario. Ahora: «El correo, el usuario o la contraseña no son correctos».

**Ensayo con una copia de producción del 26/11** (255 personas, 21 usuarios)

| Qué | Resultado |
|---|---|
| Migración | Corre limpia; los 21 usuarios conservan su correo, ninguno queda sin identificador, ninguna copia de contraseña |
| Cifras de cumplimiento | Huella idéntica a la de producción antes del cambio |
| PDF masivo «con promesa» (200 tarjetas) | 4,7 s la primera vez, 1,0 s las siguientes |
| PDF masivo «activas» (255 tarjetas) | 2,1 s (55 QR nuevos), 1,0 s las siguientes |
| Tarjeta flotante / PDF de una | 0,05 s / 0,23 s |
| 255 tarjetas escaneadas seguidas desde una sola IP | Las 255 responden bien |

Probado además en un navegador contra el servidor local: tarjeta flotante, «Crea tu acceso» con usuario,
entrada a Mi perfil, contraseña equivocada (vuelve a la tarjeta con el error), contraseña correcta, otra
sesión abierta y la página de espera.

**Dato de los datos reales que conviene decidir con el cliente**

De las 200 personas que hoy recibirían tarjeta (promesa vigente o programada para 2027), **126 no tienen
clase** en el sistema, así que su tarjeta sale sin el renglón «Clase». El Excel de promesas 2027 sí trae la
clase de sus 135 personas: 27 ya tienen esa misma clase, 20 tienen otra (no se tocan) y **71 no tienen
ninguna**; de esas, 52 calzan por nombre con una clase del sistema y 19 calzan con una equivalencia evidente
(«11-12 años» → «Jóvenes 11-12», «13-14 años» → «Jóvenes 13-14», «15-16 años» → «Jóvenes 15-16», «17-19 años»
→ «Jóvenes 17-19», «Logos» → «Logos Mayores 20-24»). Cargarlas es un cambio de datos que además las mete en
la lista de asistencia de esa clase: se hace solo con el visto bueno del cliente.
