# Tarjetas con QR de acceso personal — especificación

Pedido de David el 25/11/2026. Rama `feat/tarjetas-qr` (sale de `integracion/oct-2026`). Un solo equipo.

## 1. Lo que pidió el cliente

1. **Cada persona con promesa tiene un QR.** El QR sirve para llevarla a **su cuenta**.
2. **Si la persona todavía no tiene cuenta** (no tiene correo guardado): al escanear, de una vez le pide **un
   usuario o correo y una contraseña**, y queda con su cuenta creada.
3. **Si ya tiene cuenta**: el QR solo la lleva a su cuenta, pero **debe escribir su contraseña**. El QR nunca
   inicia sesión por sí solo.
4. **El administrador puede ver esa contraseña**, por si a la persona se le olvida.
5. **Cómo se muestran**, dos formas:
   - En **Personas**, el administrador toca un apartado **«Tarjeta»** y sale, bien bonita, una tarjeta flotante
     con el **nombre**, la **clase** y el **QR**.
   - **Descarga masiva**: un PDF con esas mismas tarjetas, bien bonitas, **unas 8 por página tamaño carta**.

## 2. Decisiones del líder

- **Todas las personas pueden tener QR**; se genera la primera vez que se pide su tarjeta. La descarga masiva
  trae por defecto a las **personas con promesa** (vigente hoy o programada en un corte de Promesas futuras) y
  permite elegir: con promesa, todas las activas, o una clase. Ordenadas por clase y nombre.
- **El QR es una dirección corta y secreta**: `https://<sitio>/t/<token>`, con un token aleatorio de 40
  caracteres guardado en la persona (`personas.token_acceso`, único). No se deriva del id ni del PIN. El
  administrador puede **generar un QR nuevo** para una persona (el anterior deja de servir).
- **Al escanear** (ruta pública, con límite de intentos):
  - persona sin cuenta → formulario «Crea tu acceso»: usuario o correo, contraseña y repetir contraseña. Al
    guardar queda creada su cuenta con rol **miembro**, enlazada a su persona, y entra a **Mi perfil**.
  - persona con cuenta → pantalla de inicio de sesión con su usuario o correo ya escrito; escribe su contraseña.
  - token que no existe → página amable «Este código ya no es válido, pídele uno nuevo al administrador».
- **Usuario o correo**: la cuenta puede identificarse con un correo o con un nombre de usuario (sin @). El
  inicio de sesión normal de la app debe aceptar cualquiera de los dos. Estudia el login actual (Breeze,
  `LoginRequest`, dominios de correo por iglesia si aplican) y elige la forma menos riesgosa de permitir cuentas
  sin correo; nada de lo que hoy funciona para los usuarios con correo puede cambiar.
- **Contraseña visible para el administrador** (pedido expreso del cliente). Se guarda cifrada con la llave de
  la app (cast `encrypted`), en una columna aparte del hash normal, **solo para cuentas de rol miembro**; nunca
  para administrador, tesorero, asistente ni súper admin. Se muestra en la tarjeta de la persona con un botón
  **«Ver contraseña»**, solo al administrador, y **cada consulta queda en auditoría**. Si el miembro cambia su
  contraseña desde su perfil, la copia visible se actualiza. El formulario donde el miembro escribe su
  contraseña avisa claro: «El administrador de la iglesia puede ver esta contraseña para ayudarte si la
  olvidas. No uses la misma de tu correo ni la de tu banco.»
  - Va detrás de un interruptor de configuración (`config/ibbla.php` o similar, leído de `.env`, **encendido por
    defecto**) para poder apagarlo sin tocar código: apagado, no se guarda la copia ni aparece el botón.
  - Además, el administrador tiene **«Restablecer acceso»**: borra la contraseña de esa cuenta y el próximo
    escaneo del QR le pide a la persona una contraseña nueva. Es la vía recomendada; también queda en auditoría.
- **Tarjeta**: diseño cuidado (logo y siglas de la iglesia, nombre grande, clase, QR nítido, una línea
  «Escanea para entrar a tu cuenta»). En Personas abre como tarjeta flotante sobre la pantalla, con: estado de
  la cuenta (sin cuenta / usuario o correo), descargar la tarjeta sola en PDF, Ver contraseña, Restablecer
  acceso y Generar QR nuevo (estas dos con confirmación).
- **PDF masivo**: hoja carta, **8 tarjetas por página** (2 columnas × 4 filas) con guías de corte, mismo diseño.
  Hostinger corta a los 10 s: con 255 personas debe generarse muy por debajo de eso. DomPDF con muchos SVG es
  lento; mide y, si hace falta, dibuja el QR sobre el lienzo de DomPDF (como hace `ReporteGeneralPdf`) a partir
  de la matriz del QR, o parte la descarga por clase. La librería de QR ya está en el proyecto (la usa
  `ServidorController::qrCulto`).
- **Solo el Administrador** ve tarjetas, contraseñas y descargas. Tesorero, asistente y miembro: 403.
- La clase de la persona sale de donde hoy la toma la app (pivote `clase_persona` / `clases_asistencia`); si
  tiene varias, la principal o la primera; si no tiene, la tarjeta no muestra clase.

## 3. Criterios de aceptación

- Pruebas por rol de todas las rutas nuevas; las públicas con límite de intentos.
- Flujo completo: persona sin cuenta escanea, crea acceso con correo → entra a Mi perfil; otra lo crea con
  usuario sin @ → puede iniciar sesión luego desde el login normal con ese usuario; persona con cuenta escanea
  → login con su identificador escrito → entra con su contraseña; contraseña equivocada no entra.
- El token viejo deja de servir al generar uno nuevo; Restablecer acceso obliga a crear contraseña de nuevo.
- Ver contraseña: solo admin, solo cuentas miembro, deja auditoría; con el interruptor apagado no existe.
- El login de los usuarios que ya existen (con correo) sigue funcionando igual; la suite existente en verde
  (solo los 14 de Breeze pueden fallar; si tocas el login, esas pruebas de Breeze no deben empeorar).
- PDF masivo de todas las personas en menos de 10 s con los datos de volumen; 8 por página; el QR se lee con un
  teléfono (verifica decodificando al menos uno en una prueba o a mano).
- Manual: sección nueva en la página de Personas (y Mi perfil si cambia) con su «?»; hay un test que exige que
  toda ruta con pantalla esté declarada en el manual. `docs/ROLES.md` al día. Sin `env()` fuera de `config/`.
- Producción corre MariaDB 11.8: nada exclusivo de MySQL 8.
