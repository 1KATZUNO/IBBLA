# Informe del Equipo G, fase 1: ambiente QA y guía de usuario final

Rama `feat/guia-ambiente-qa` (base `integracion/oct-2026`, e3ffae5). Fecha: 24/11/2026.
Este encargo corrige el brief en tres puntos: no se toca `VerificarIbbla.php` (F1), no se agregan tarjetas al centro de
reportes (R2) y el manual en PDF ya existía: se verificó y se completó.

## 1. Ambiente QA en Hostinger (QA-G01 a QA-G04)

Lo instalará David (hPanel + SSH); aquí queda todo listo y documentado. No se ejecutó nada en el servidor.

### `deploy/hostinger.sh --qa` (QA-G01, QA-G04)
- Con `--qa` despliega la rama `integracion/oct-2026` (o la de `--branch`, o un `--tag`) en
  `/home/u598753224/domains/qa.ibbla.eu/public_html`, con su propio `.env`, **respaldo previo de su BD** (el archivo lleva el nombre de la base,
  así no se confunde con los de producción) y chequeo final contra `https://qa.ibbla.eu` (`/login` 200 y reporte general 302).
- Defensas (el script se detiene sin tocar nada):
  1. `--qa` solo corre si la ruta termina en `domains/qa.ibbla.eu/public_html` (ni `--allow-path` lo salta).
  2. El `.env` debe decir `APP_ENV=qa`.
  3. `DB_DATABASE` no puede coincidir con la del `.env` de producción (se lee de `.../domains/ibbla.eu/public_html/.env`);
     si ese archivo no existe se exige que el nombre de la base contenga `qa`.
  4. Sin `--qa`, el script se niega a correr en la ruta de QA (también con `--allow-path`).
- Siguen funcionando `--dry-run`, `--tag`, `--branch`, `--maintenance`, `--sin-respaldo`, `--skip-check`, `--url`.
- **Producción sin `--qa` queda igual que hoy**: el diff contra la base son líneas agregadas (variables, opción, bloque `if QA`) y un único bloque
  movido (el `case` de la ruta, que ahora está dentro del `else` y por eso cambia de sangría; su contenido es idéntico), más una guarda nueva que solo
  dispara si la ruta es la de QA. Para revisarlo: `git diff -w integracion/oct-2026 -- deploy/hostinger.sh`.
- Extra (QA-G04), a decidir por el líder: opción nueva `--manual-pdf` que corre `php artisan manual:pdf` tras las cachés. **Con `--qa` se hace siempre;
  en producción solo si se pide** (así producción no cambia). Motivo: el PDF completo tarda ~30 s en dibujarse y Hostinger corta a los 10 s (ver 2.4).
  Recomendación: usar `--manual-pdf` en el próximo deploy de producción.

**Qué se probó** (carpetas temporales que imitan `/home/u.../domains/qa.ibbla.eu/public_html` y `.../ibbla.eu/public_html`, clones reales del repo, `--dry-run`):
`bash -n` limpio; `--qa` correcto (usa la rama `integracion/oct-2026`, URL `https://qa.ibbla.eu`); `--qa` con tag inexistente falla;
sin `--qa` en la ruta de QA (con y sin `--allow-path`) se niega; `--qa` en la ruta de producción y en otra ruta se niega; `APP_ENV` distinto de `qa` se niega;
misma BD que producción se niega; sin `.env` de producción y BD sin `qa` se niega, con `qa` pasa; producción sin `--qa` sigue mostrando `rama main` y `https://ibbla.eu`;
con `--manual-pdf` aparece el paso, sin él no.
**Lo que no se puede probar fuera del servidor:** `mysqldump` real (respaldo), `composer install`, `migrate`, el `curl` final contra `https://qa.ibbla.eu`
(en `--dry-run` solo se muestra), el cron y los permisos de hPanel. Conviene que David corra `bash deploy/hostinger.sh --qa --dry-run` la primera vez.

### Aviso de ambiente y noindex (QA-G02)
- `resources/views/partials/aviso-ambiente.blade.php`: franja amarilla «AMBIENTE DE PRUEBAS · los datos no son reales», solo con `APP_ENV=qa`.
  Una línea `@include('partials.aviso-ambiente')` en `layouts/admin`, `layouts/guest` y `layouts/super-admin` (único cambio en layouts).
  Usa clases `warning-*` ya existentes en el tema; al recompilar assets quedan incluidas.
- `App\Http\Middleware\NoIndexEnQa` (registrado como middleware **global** en `bootstrap/app.php`, no en el grupo web: el middleware `auth` corre antes
  que los del grupo y sus redirecciones se quedaban sin cabecera): `X-Robots-Tag: noindex, nofollow` solo en QA.
- Tests `tests/Feature/AmbienteQaTest.php` (4): en `qa` aparecen el aviso y la cabecera (login, las cuatro pantallas de cada rol y una redirección);
  en `production`, `local` y `testing` no.

### `docs/AMBIENTE-QA.md` (QA-G03)
Pasos de hPanel para David (subdominio, BD y usuario propios, clonar por SSH, `.env` de QA con `APP_ENV=qa`, `APP_DEBUG=false`, `MAIL_MAILER=log`,
sin claves de CallMeBot, cron del planificador, primer `--qa`), cómo refrescar QA con una copia de producción (`mysqldump` + importación y luego
vaciar `activaciones_whatsapp` y `avisos_whatsapp`, poner teléfonos en NULL, cambiar contraseñas y sesiones) y el flujo «primero QA, luego producción».
Sin credenciales. En `docs/DEPLOY-HOSTINGER.md` solo se agregó una sección corta al final.

## 2. Manual de usuario (QA-G05 a QA-G09)

### 2.1 Repaso de las 14 páginas contra la app del worktree
Método: comparar cada página con las vistas y las etiquetas reales (listado de textos de botones por vista, contraste de cada **negrita** del manual con
el código) y con las capturas. El manual ya traía lo de la E1 (centro de reportes, cumplimiento con corte, estados de cuenta en lote, Accesos,
sobres anónimos, WhatsApp con CallMeBot en Mi perfil) y **no menciona** «Entrar como invitado» ni el registro público.
Lo que estaba **desfasado o faltaba** y se corrigió:
- **Recuento y sobres (reescrita):** describía una lista desplegable «Seleccionar Culto» y los botones «Guardar Sobre» / «Descargar PDF» / «PDF Transferencias»,
  que ya no existen. Ahora explica la lista de cultos por líneas (filtros, Todos · Abiertos · Cerrados), la pantalla del culto (**+ Agregar sobre**, **+ Dinero suelto**,
  **− Egreso**, menú **PDF** con **Recuento completo** y **Solo transferencias**, **Cerrar culto**), el formulario del sobre (**Guardar y siguiente** / **Guardar y terminar**,
  nombre en grande, **Sobre anónimo (sin persona)**, **+ Nueva persona**, **Último guardado**), corregir sobres, firmas (**Firmar**, **Guardar firma**, **Guardar firmas**),
  el cierre con resumen y las tres variantes del aviso de culto cerrado.
- **Personas:** sección nueva «Agregar una persona rápido (alta rápida)» (menú **Agregar persona** del Tesorero, **+ Nueva persona** desde el sobre); el buscador también busca por teléfono.
- **Principal:** tarjeta **Agregar persona** del Tesorero.
- **Cultos:** el botón es **Eliminar culto** y pide **Motivo (obligatorio)** si tiene sobres o está cerrado.
- **Reportes:** la pantalla se titula «Ingresos y Asistencia»; anclas por sección (2.2).
- **Página nueva «Novedades de noviembre 2026»** (primera del índice): qué cambió y dónde está, por área, con quién lo usa y a qué página ir.
- Tabla de botones (qué hace, quién puede usarlo, qué pasa después) sigue en todas las páginas con captura.
Tests de contenido (`ManualContenidoTest`, 5): no queda texto de funciones eliminadas; el manual cubre los temas de la E1; Novedades es la primera página; cada botón
citado en Recuento existe con ese texto en las vistas (guarda contra futuros desfases); toda captura citada existe y está en `tools/manual/capturas.json`.

### 2.2 Botón «?» por pantalla
- `Manual::paginaParaRuta` ahora admite `patrón#ancla` en el encabezado `rutas:` de una página. En `07-reportes.md`:
  `reportes.cumplimiento*` abre `/manual/reportes#cumplimiento-de-promesas-con-corte`, `reportes.estados-cuenta*` abre `#estados-de-cuenta-en-lote`;
  además Ingresos (`#reporte-de-ingresos`), Asistencia (`#reporte-de-asistencia`) y Asistencia por clase (`#asistencia-por-clase-con-promedio`).
- **Cobertura:** de las 114 rutas GET con nombre, todas las pantallas de la intranet tienen «?». Quedan sin él, a propósito: `login`, `password.*`, `verification.*`
  (pantallas de ingreso, layout `guest`), `informe.persona` (enlace firmado público), `api.*`, `storage.*`, `manual.*` y `super-admin.*` (otra consola, otro layout).
- Tests `ManualAyudaPantallasTest` (5): **toda** ruta GET con nombre (menos la lista anterior) tiene ayuda; Cumplimiento y Estados de cuenta abren su sección;
  el botón se pinta con el ancla para admin y tesorero; toda ancla declarada existe como título de sección.
  Ojo en la integración: si R1/R2/F2 agregan una pantalla con ruta nueva (p. ej. `reportes.cultos`), este test falla hasta que su ruta se declare en el encabezado de la página que corresponda.

### 2.3 Capturas regeneradas (`tools/manual/capturar.mjs`)
| Captura | Motivo |
|---|---|
| `recuento-lista.jpg` (nueva) | la lista de cultos por líneas no tenía captura |
| `recuento-culto.jpg` | marcas desfasadas (el manual hablaba de un selector que ya no existe); ahora 6 marcas |
| `recuento-nuevo-sobre.jpg` | formulario nuevo (anónimo, Guardar y siguiente, etc.); 4 marcas |
| `reportes-indice.jpg` | el índice ahora son tarjetas (centro de reportes) |

Las otras 30 no cambiaron: desde su generación (5/11, commit 5bccd6c) no cambió ninguna otra vista (`git diff 5bccd6c HEAD -- resources/views`: solo el índice de reportes,
el layout guest y PDFs). Cambios en la herramienta: `capturar.mjs` acepta `{culto_abierto}` / `{culto_cerrado}` en la ruta (lee el id real de la lista de Recuento en lugar del id
fijo 74 de otra base); `capturas.json` usa esos marcadores. Requisito: tener un culto abierto con sobres (en la base de pruebas se reabrió uno). `tools/manual/node_modules` no
viaja con git: en el worktree es un junction al del repo principal (sin modificarlo).

### 2.4 PDF del manual completo
- Verificado: se genera entero (portada, índice «Contenido», las 15 páginas del manual y sus capturas, unas 60 hojas A4, ~2,8 MB), se descarga desde **Manual → Descargar el manual (PDF)**
  para todos los roles y cada página tiene su **Descargar esta página (PDF)**.
- **Tiempo:** la generación en frío tarda **~25-30 s** en esta máquina (cargada con cinco equipos; el benchmark de CPU da 3 veces el de una máquina libre, así que en
  Hostinger podrían ser 10-15 s): **no cabe en los 10 s**. Por eso el archivo se sirve **ya generado**: `php artisan manual:pdf` (ya existía) lo deja en `storage/app/manual`,
  el deploy lo corre solo en QA (y en producción con `--manual-pdf`), y la descarga posterior sale en < 1 s (la prueba exige < 10 s con el archivo generado).
  Si alguien lo pide antes de generarlo, `ManualPdf` ahora usa `ignore_user_abort(true)` para que, aunque Hostinger corte la petición, el PDF se termine y quede para el siguiente intento.
  El PDF por página (en frío) tarda 5 a 20 s según la página (la de Recuento es la más pesada); no se pre-genera.
- Refactor mínimo: `ManualPdf::vista()` (la vista que se convierte a PDF) para poder probarla.
- Tests `ManualPdfTest` (5): contenido (portada, índice, una sección por página, capturas incrustadas y existentes), `manual:pdf` deja un solo PDF con ≥ 30 páginas,
  la descarga sirve ese mismo archivo en < 10 s, los cinco roles lo descargan, el índice del manual ofrece el enlace, y un cambio en una página invalida el archivo.

## Cómo probarlo a mano
- QA: en un `.env` local poner `APP_ENV=qa` y abrir cualquier pantalla: franja amarilla y, con `curl -I`, la cabecera `X-Robots-Tag`.
- «?»: en `/reportes/cumplimiento` y `/reportes/estados-de-cuenta` (admin o tesorero) el «?» abre la sección propia en otra pestaña.
- Manual: `/manual` (primera página «Novedades de noviembre 2026»), `/manual/recuento`, y **Descargar el manual (PDF)** después de `php artisan manual:pdf`.

## Decisiones
- `NoIndexEnQa` como middleware global (explicado arriba).
- La guarda de ruta en el script de QA no admite `--allow-path`: preferimos que se niegue a que pueda tocar otra carpeta.
- El PDF se pre-genera en el deploy y no se intenta acelerar DomPDF (el tiempo es de maquetación, no de las imágenes).

## Pendientes y riesgos
- **Fase 2** (cuando el líder avise): documentar lo que construyen R1/R2/F1/F2 (reportes de cultos e ingresos, selector completo, avisos en cola, asistencia cerrada,
  confirmaciones) y regenerar las capturas afectadas. Dejado listo: `capturar.mjs` con marcadores de culto, anclas por ruta, páginas `07-reportes` y `02-recuento` con su estructura,
  tests de contenido y de «?» que avisan de lo que falte. Actualizar «Novedades» con esos cambios.
- Producción: considerar `--manual-pdf` por defecto (cambia el comportamiento del script; por eso no se hizo).
- Primera descarga del PDF por página en frío puede cortarse en Hostinger (se termina igual y la segunda vez sale).
- El aviso de ambiente usa clases de Tailwind que hay que recompilar con `npm run build` al integrar (no se commitea `public/build`).

## Notas para el manual de usuario (para quien lo mantenga)
- Textos exactos de la UI de Recuento: **+ Agregar sobre**, **+ Dinero suelto**, **− Egreso**, **PDF** (Recuento completo / Solo transferencias), **Cerrar culto** (confirma con **Sí, cerrar**),
  **Guardar y siguiente**, **Guardar y terminar**, **Guardar cambios**, **+ Nueva persona**, **Agregar y elegir**, **Sobre anónimo (sin persona)**, **Solo anónimos**, **Asignar persona**,
  **Firmar**, **Guardar firma**, **Guardar firmas**, **Agregar tesorero**.
- Cambios propuestos a archivos de otros equipos: ninguno.

## Archivos tocados
`deploy/hostinger.sh`, `docs/AMBIENTE-QA.md`, `docs/DEPLOY-HOSTINGER.md` (sección final), `bootstrap/app.php`, `app/Http/Middleware/NoIndexEnQa.php`,
`resources/views/partials/aviso-ambiente.blade.php`, tres layouts (una línea), `app/Support/Manual/Manual.php`, `app/Support/Manual/ManualPdf.php`,
`resources/views/manual/paginas/{00-novedades-noviembre-2026,01-principal,02-recuento,03-cultos,04-personas,07-reportes}.md`, `public/images/manual/{recuento-lista,recuento-culto,recuento-nuevo-sobre,reportes-indice}.jpg`,
`tools/manual/{capturar.mjs,capturas.json}`, tests `AmbienteQaTest`, `ManualAyudaPantallasTest`, `ManualContenidoTest`, `ManualPdfTest`.

---

# Fase 2: documentar lo que integraron R1, R2, F1 y F2 (25/11/2026)

Base: `integracion/oct-2026` (cb5f7c1), migraciones aplicadas en `ibbla_gqa`. Fuentes: `INFORME-reportes-3b.md` (R1), `INFORME-reportes-nuevos.md` (R2) y los commits de F1/F2 (QE-009, QE-014, QE-015/QA-021, QE-016, QA-018, QA-034, QA-036, QA-043, QE-013, QE-020) contrastados con las vistas.

## Qué se documentó, por página
| Página | Cambios |
|---|---|
| `07-reportes` (reescrita) | Tabla de tarjetas del centro de reportes (incluye **Reporte de cultos**, **Reporte de ingresos**, asistencia para el Tesorero); filtros de fechas y atajos; secciones **Reporte de cultos** y **Reporte de ingresos** (comparativo, PDF y Excel, máximo un año); enlaces cruzados de asistencia; **selector de personas** completo (filtros combinables, Seleccionar todos los filtrados, contador, buscar por nombre/PIN/teléfono); **vista previa** ordenable y paginada; columna **Último aporte a sus compromisos**; ya no dice que el Excel de asistencia es solo del Administrador |
| `05-promesas` | Dashboard y Reporte de promesas con **meses completos** y el **mes en curso aparte**; Excel igual al de Cumplimiento; guardar la ficha conserva el historial; `/promesas` vieja lleva a Personas |
| `02-recuento` | Barra del sobre **Sin persona** (gris) hasta marcar **Sobre anónimo**; buscador con PIN y teléfono; flechas que no llegan solas a «Crear persona nueva» |
| `06-asistencia` | Asistencia cerrada en **solo lectura**; corrección por el Administrador con auditoría («Corrección de culto cerrado»); cerrar asistencia no cierra el culto; mensajes de la **App de Clases** (acceso denegado / «No tenés una clase asignada…») |
| `04-personas` | **Mantenimiento: borrados masivos** con pantalla de confirmación (escribir **CONFIRMAR**) y auditoría; usuario creado desde la ficha queda ligado y con rol Miembro; guardar no pierde historial |
| `10-usuarios` | **Persona vinculada** (buscador por nombre, PIN o teléfono; **Quitar vínculo**) |
| `12-whatsapp` | Los avisos **ya no salen al guardar**: los envía el planificador en cerca de un minuto; reintentos, **Enviar ahora**, textos de «Tiene / No tiene WhatsApp automático» |
| `08-dashboards` | Mes en curso comparado con los mismos días del anterior; promesas al día y pendientes al último mes completo |
| `11-auditoria` | Abre en «solo acciones»; **Ver todo (visitas y datos técnicos)**; el cierre de culto queda registrado |
| `00-novedades-noviembre-2026` | Todo lo anterior, por área, pensado para Roy |

## «?» de las pantallas nuevas
`Manual::paginaParaRuta` ya admitía `patrón#ancla`; en esta fase: `reportes.cultos*` → `/manual/reportes#reporte-de-cultos`; `reportes.ingresos*` (y la ruta vieja `ingresos-asistencia.ingresos`, que ahora redirige) → `#reporte-de-ingresos`; `personas.mantenimiento` (pantalla de confirmación de borrados masivos) → `/manual/personas#mantenimiento-borrados-masivos`; `avisos.*` → `/manual/whatsapp#avisos-de-whatsapp`. El recorrido de **todas** las rutas GET con nombre (menos ingreso, enlaces públicos, API, manual y plataforma) sigue en `ManualAyudaPantallasTest` y pasa con las rutas nuevas, y se agregaron las aserciones de estas anclas. `ManualContenidoTest` exige además que el manual hable de lo nuevo (reporte de cultos/ingresos, selector, vista previa, último aporte, mes en curso, CONFIRMAR, persona vinculada, planificador…).

## Capturas
Regeneradas (con `capturar.mjs`, 25/11): `reportes-indice` (tarjetas nuevas), `reportes-ingresos` (ahora la pantalla nueva), `recuento-nuevo-sobre` (barra «Sin persona»), `usuarios-formulario` (marca 6: Persona vinculada), `whatsapp-avisos` (aviso de envío automático), `promesas-reporte` (meses completos), `dashboards-general`.
Nuevas: `reportes-cultos`, `reportes-cumplimiento` (selector de personas), `personas-mantenimiento` (confirmación CONFIRMAR).
Sin captura nueva (solo texto): asistencia cerrada en solo lectura, vista previa del selector (queda bajo el pliegue de la pantalla), pantalla de acceso denegado de la App de Clases, Reporte de ingresos con el comparativo completo.

## Defectos de la app vistos al documentar (no se tocaron)
- Ninguno bloqueante. Observaciones: el texto de la App de Clases usa voseo («No tenés… Pedile…») mientras el resto de la app usa «tú/usted»; la tarjeta **Sin firmas completas** del Reporte de cultos no explica en pantalla qué cuenta como «firmas completas».

## Pendientes
- Capturas de asistencia cerrada y de la vista previa paginada (requieren datos/estado específicos).
- Si el líder añade pantallas con ruta nueva, `ManualAyudaPantallasTest` avisa qué ruta falta declarar.
