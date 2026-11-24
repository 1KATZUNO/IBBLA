# QA inicial de IBBLA — segunda ronda (Equipo 1)

Revisión completa de la app (visual y funcional) con los cuatro roles, en escritorio, tablet y móvil.
No se corrigió nada: este documento reporta. Fecha: 13/10/2026. Base revisada: `integracion/oct-2026`
en `799fef8` (A–E + hotfix de reportes; lo que entró después en integración son solo notas del plan).

- Rama: `qa/revision-inicial`. Servidor `php artisan serve` en `127.0.0.1:8111`, BD `ibbla_qa` con
  `migrate --seed` + `VolumenDevSeeder` (150 personas, 74 cultos, 2.596 sobres, 245 promesas).
- Resoluciones: 1366×768 (PC), 1024×768 y 768×1024 (tablet), 390×844 (móvil).
- Método: flujos completos a mano en el navegador integrado + Chrome headless por protocolo DevTools
  para (a) una matriz de 60 rutas × 4 roles con códigos y tiempos, (b) 24 descargas (PDF/Excel/CSV)
  por rol y (c) una auditoría de diseño de 57 pantallas × 4 resoluciones (225 mediciones: desborde,
  elementos cortados, controles chicos, botones fuera de vista, alto de página) con capturas.
  Los PDF se leyeron con `pdftotext`/PyMuPDF y los Excel con `openpyxl` para comparar cifras.
- Usuarios de prueba (solo local, mismo patrón del seeder): `admin@ibbsc.com / admin123`,
  `tesorero@ibbsc.com / tesorero123`, `asistente@ibbsc.com / asistente123` (creado en Gestionar
  Usuarios) y `miembro@ibbsc.com / miembro123` (creado desde Gestionar Personas > Manuel Segura Mora >
  correo + contraseña; queda vinculado a esa persona).
- Capturas en `docs/qa/` (16 JPG, 690 KB). Notas de entorno al final (anexo D): los tiempos son
  orientativos porque los otros cuatro equipos corrían a la vez en la misma máquina.

## Resumen

1. La app es navegable en las cuatro resoluciones: 0 px de desborde horizontal y 0 botones cortados en
   225 mediciones; las barras fijas de Guardar (sobre, persona, asistencia) funcionan.
2. Lo más grave está en los números: la misma persona tiene cifras distintas en estado de cuenta,
   Reporte General, Mi perfil, Compromisos y ficha (USD sin convertir, frecuencia ignorada, distinta
   regla de "dado"). Esto afecta el listado para el pastor del miércoles (QA-024).
3. Consultar la pantalla Compromisos escribe en la BD y le crea "deudas" al miembro, incluso de meses
   futuros (QA-025).
4. El PDF de recuento de un culto no cuadra consigo mismo: Total general ≠ fila TOTALES (QA-026).
5. En Recuento hay pérdida silenciosa de datos: editar un sobre de una persona inactiva lo deja
   Anónimo (QA-002) y los sobres anónimos seguidos se rechazan por "duplicado" (QA-001).
6. Se puede borrar un culto cerrado con todos sus sobres sin rastro; el tesorero ve el botón y recibe
   403 (QA-003). Hay más botones visibles que dan 403 (Excel del tesorero, QA-010).
7. La App de Clases da error 500 a la maestra que tiene una sola clase (QA-020, bloqueante).
8. Miembro y asistente con sesión abierta reciben 403 al entrar a la raíz del sitio, a /login o a la
   app instalada (QA-011); "Yo/Mi perfil" da error a tesorero y asistente (QA-014).
9. Seguridad: el admin de la iglesia puede editar al Super Admin (QA-012) y la API de la app de
   asistencia acepta escrituras de cualquier usuario con sesión, incluido el miembro (QA-013).
10. Textos: login y validaciones en inglés, muchas palabras sin tildes, "Total Profit" en un reporte.
    Rendimiento: PDF anual de promesas 12–18 s, Reporte PDF 8–12 s, Dashboard de promesas 3,5–15 s.

**Hallazgos por severidad: 43 en total — 1 bloqueante, 12 altas, 20 medias, 10 bajas.**

## Top 10 para arreglar primero

| # | Hallazgo | Por qué primero | Equipo |
|---|---|---|---|
| 1 | QA-024 Cifras distintas por persona según la pantalla o el PDF | Roy presenta el miércoles; el Reporte General subestima lo prometido en USD y cuenta como "dado" lo de categorías sin promesa | 3 (HU-R7 motor único, R13) |
| 2 | QA-025 Compromisos escribe "deudas" al consultar | Cambia lo que ve el miembro y el Dashboard de promesas con solo navegar | 3 (motor único) |
| 3 | QA-020 App de Clases 500 con una sola clase | Bloquea a cada maestra con una clase; arreglo de una línea | 2 |
| 4 | QA-002 Editar sobre de persona inactiva lo deja Anónimo | Pérdida silenciosa de atribución de dinero | 2 (REC-4) |
| 5 | QA-001 Sobres anónimos seguidos rechazados (30 s) | Pasa todos los domingos al meter sobres; se pierde lo digitado | 2 (REC-4) |
| 6 | QA-003 Eliminar culto cerrado con sus sobres, sin rastro | Pérdida irreversible de datos financieros | 2 (REC-2, REC-5) |
| 7 | QA-026 PDF de recuento: total general ≠ totales de la tabla | Es el documento que firman los tesoreros | 3 (R10, R14) con 2 |
| 8 | QA-011 + QA-014 Entrada de miembro/asistente: 403 en "/", /login y PWA; "Mi perfil" con error | Es la puerta del miembro, justo cuando se va a promover Mi perfil y WhatsApp | 2 (REC-5) |
| 9 | QA-010 Excel con permisos invertidos | El tesorero no puede bajar sus Excel; el asistente sí baja datos financieros | 2 (REC-5) con 3 (R8) |
| 10 | QA-012 + QA-013 Super Admin editable; API de asistencia sin rol | Seguridad | 2 |

Siguen de cerca: QA-032 Mi perfil contradictorio (3 y 5), QA-038 textos en inglés (4), QA-034
acciones masivas irreversibles en Personas (2), QA-030 descargas lentas (3).

## 1. Hallazgos

### Tabla resumen

| ID | Sev. | Equipo | Pantalla | Resumen |
|---|---|---|---|---|
| QA-001 | Alta | 2 | Recuento > Agregar sobre | Sobres anónimos seguidos se rechazan como duplicados y se pierde lo digitado |
| QA-002 | Alta | 2 | Recuento > Editar sobre | Sobre de persona inactiva queda Anónimo al guardar |
| QA-003 | Alta | 2 | Recuento (archivo), Gestionar Cultos | Eliminar culto cerrado borra sus sobres sin guardas ni rastro; tesorero ve el botón y recibe 403 |
| QA-004 | Media | 2 | Recuento (culto abierto) | Debajo del culto se cargan las 60 tarjetas de cultos cerrados: página de 11.700–23.700 px y 743 KB |
| QA-005 | Media | 2 | Recuento (culto cerrado) | El tesorero no ve los sobres del culto cerrado; selector no refleja el culto; no dice quién cerró |
| QA-006 | Media | 2 | Recuento | Admin edita sobres de culto cerrado sin aviso pero no puede eliminarlos; reglas distintas a Asistencia |
| QA-007 | Media | 2 | Recuento, Asistencia | Cultos abiertos ordenados del más lejano al más próximo: el de hoy queda de último |
| QA-008 | Media | 2 | Recuento > Agregar sobre | Flujo largo: 2 cargas por sobre (~2 s + ~5 s), sin "guardar y siguiente", persona en dos controles |
| QA-009 | Baja | 2 | Recuento | Cerrar culto sin resumen ni validaciones; firmas modificables por URL tras el cierre |
| QA-010 | Alta | 2/3 | Reportes, dashboards | Excel: tesorero 403 en 3 pantallas; asistente sí descarga ingresos y promesas |
| QA-011 | Alta | 2 | "/", /login, PWA | Miembro y asistente con sesión reciben 403 (redirige a /dashboard) |
| QA-012 | Alta | 2 | Gestionar Usuarios | Admin de iglesia abre y puede guardar al Super Admin; usuarios sin filtro por iglesia |
| QA-013 | Alta | 2 | API /asistencia-app | Solo pide sesión: el miembro lee clases con alumnos y pasa la autorización en escrituras; login sin límite |
| QA-014 | Media | 2 | Menú "Yo", Principal | "Mi perfil" visible a tesorero/asistente y da error; admin no tiene Mi perfil |
| QA-015 | Media | 2 | Dashboard asistencia, Usuarios | Tesorero ve el dashboard de asistencia y el asistente no; descripciones de roles falsas |
| QA-016 | Media | 2/3 | Personas, reportes | Tesorero sin Reporte General ni estados de cuenta; ruta de WhatsApp permitida sin pantalla |
| QA-017 | Media | 2/4 | Login | "Entrar como Invitado" no entra: "Tu iglesia no esta activa" |
| QA-018 | Media | 2 | Personas / Usuarios | Dos caminos para dar acceso al miembro; desde Usuarios queda sin persona |
| QA-019 | Baja | 2 | Varias | /register abierto, /profile de Breeze en inglés con "Delete Account", rutas viejas con 500 |
| QA-020 | Bloqueante | 2 | App de Clases | Error 500 (TypeError) cuando el usuario ve una sola clase |
| QA-021 | Media | 2 | Asistencia | Asistencia cerrada: Editar abre el formulario y rechaza al guardar; el admin tampoco puede |
| QA-022 | Media | 2 | Nueva asistencia | Selector de 75 cultos, futuros primero, sin preseleccionar; acepta cultos cerrados en Recuento |
| QA-023 | Baja | 4 | App de Clases | Muestra el slug técnico ("ninos_2_6") |
| QA-024 | Alta | 3 | Estado de cuenta, Reporte General, Mi perfil, Compromisos, ficha | Misma persona, cifras distintas |
| QA-025 | Alta | 3 | Compromisos | Consultar un mes escribe compromisos en BD (también futuros) que el miembro ve como deuda |
| QA-026 | Alta | 3 | PDF de recuento, "Ver" culto cerrado | Total general ≠ TOTALES de la tabla (USD sumados como colones) |
| QA-027 | Media | 3 | Dashboard de promesas vs Reporte de promesas | Mismo mes, cifras distintas; formato de miles distinto; "Profit" |
| QA-028 | Media | 3 | Estado de cuenta PDF | Indicadores de cabecera sin relación; cobra el mes en curso completo |
| QA-029 | Media | 3 | Reporte General | 141 páginas, una por persona; no resume quién está al día |
| QA-030 | Media | 3 | Descargas | PDF anual de promesas 12–18 s; Reporte PDF 8–12 s; Dashboard de promesas 3,5–15 s |
| QA-031 | Baja | 3 | Excel/PDF | Celdas vacías en vez de 0, fechas como texto, sin autofiltro; tabla del PDF pegada al borde |
| QA-032 | Alta | 3/5 | Mi perfil (miembro) | "¡Cumplido!" y "deuda pendiente" para la misma promesa; 28 tarjetas rojas |
| QA-033 | Media | 3 | Ficha de persona | "Total ofrendado" sin período, con USD como ₡ y sobres futuros |
| QA-034 | Alta | 2/4 | Personas > Mantenimiento | "Borrar todas las promesas" y "Eliminar personas inactivas" con un confirm() |
| QA-035 | Media | 4 | Personas (lista) | Sin orden alfabético (inactivos primero); no busca por teléfono |
| QA-036 | Baja | 2 | Personas > Editar | Guardar recrea las promesas; "Reiniciar compromisos" dice "October 2026" |
| QA-037 | Baja | 5 | Avisos de WhatsApp | Nadie es notificado con el proveedor link; el aviso llega al mismo tesorero que registra |
| QA-038 | Media | 4 | Login y formularios | Login completo y validaciones en inglés |
| QA-039 | Baja | 4 | General | Textos sin tildes; "IBBSC Admin" para el miembro; PWA "ANGEDA"; login "Sistema Admin" |
| QA-040 | Baja | 4 | Dashboards, Ministerios | Controles de menos de 36 px |
| QA-041 | Baja | 4/3 | Menú, Gestionar Cultos | "Reportes" abre "Ingresos y Asistencia"; Gestionar Cultos sin estado abierto/cerrado |
| QA-042 | Media | 2/4 | Auditoría | 72 % visitas GET, columnas técnicas, sin filtro por iglesia, borrados sin detalle |
| QA-043 | Baja | 3/4 | Dashboard general | "+0 % vs septiembre" sin datos; "pendientes este mes" a mitad de mes |

### Recuento y sobres

#### QA-001 · Sobres anónimos seguidos se rechazan como duplicados — Alta · Equipo 2 (REC-4)
- Pantalla / rol / dispositivo: Recuento > + Agregar Sobre; tesorero y admin; todos (probado en 1024).
- Pasos: culto abierto (14/10/2026); guardar un sobre Anónimo de ₡300; antes de 30 s, "+ Agregar
  Sobre", Anónimo ₡400, Guardar.
- Qué pasa: vuelve a la lista con "Atención: Ya se registró un sobre para esta persona en este culto
  hace unos segundos. Verifique que no sea duplicado." El segundo sobre no se guarda y el formulario no
  conserva lo digitado. El control compara `persona_id` nulo con nulo, así que todo anónimo dentro de
  30 s es "duplicado". El mensaje habla de "esta persona" aunque no hay persona.
- Qué debería pasar: el control aplica solo a la misma persona; si se quiere avisar, volver al
  formulario con los datos y un botón "Guardar de todos modos".
- Captura: `docs/qa/recuento-sobre-anonimo-rechazado-1024.jpg`. (Ya lo había anotado el agente B.)

#### QA-002 · Editar un sobre de persona inactiva lo deja Anónimo — Alta · Equipo 2 (REC-4)
- Pantalla / rol / dispositivo: Recuento > Editar sobre; tesorero y admin; todos (probado en 1024).
- Pasos: `/recuento/2201/edit` (sobre #33 de ₡51.000 de Miguel Ulate Herrera, persona inactiva, culto
  abierto 01/11/2026); sin tocar nada, "Actualizar Sobre".
- Qué pasa: el selector solo lista personas activas, así que muestra "-- Anónimo --"; al guardar,
  `persona_id` queda NULL. El sobre desaparece del estado de cuenta de esa persona sin ningún aviso.
- Qué debería pasar: mostrar siempre la persona del sobre (marcada "inactiva") y no cambiarla salvo que
  el usuario elija otra. El editar tampoco tiene buscador (solo un select de 140 nombres).
- Captura: `docs/qa/recuento-editar-sobre-persona-inactiva-1024.jpg`.

#### QA-003 · Eliminar un culto cerrado borra sus sobres sin guardas ni rastro — Alta · Equipo 2 (REC-2, REC-5)
- Pantalla / rol: Recuento > "Cultos Cerrados (Archivo)" (icono de basurero en cada tarjeta) y
  Gestionar Cultos (Eliminar en cada fila); admin y tesorero.
- Pasos (admin): se creó el culto 13/10/2026 Especial, se registró un sobre de ₡25.000, se cerró y se
  eliminó desde el archivo.
- Qué pasa: el culto y su sobre se borran en cascada (verificado en BD: 0 sobres). La auditoría solo
  guarda `cultos.destroy {"_method":"DELETE"}` sin id, fecha ni monto. No hay ninguna regla como en
  Ministerios o Clases ("no se puede eliminar si tiene datos"). El tesorero ve el mismo basurero, el
  modal "¿Estás seguro…?" y al confirmar recibe "403 Acceso Denegado".
- Qué debería pasar: un culto cerrado o con sobres no se elimina (o solo admin, con motivo escrito y
  auditoría de lo borrado); quien no puede, no ve el botón.
- Capturas: `docs/qa/recuento-eliminar-culto-tesorero-1024.jpg`, `docs/qa/recuento-archivo-otros-cultos-1024.jpg`.

#### QA-004 · La pantalla del culto arrastra todos los cultos cerrados — Media · Equipo 2 (REC-1, REC-4)
- Pantalla / rol / dispositivo: Recuento con un culto abierto; tesorero y admin; todas.
- Qué pasa: debajo de los sobres, el resumen por categorías y las firmas aparecen las 60 tarjetas de
  "Cultos Cerrados (Archivo)" con sus totales y botones. Alto de la página con el culto del 18/10
  (40 sobres): 11.671 px en 1366, 14.179 en 1024, 14.223 en 768 y 23.753 en 390; 743 KB de HTML.
  "Guardar Firmas" queda a ~6.000 px (7.200 en móvil). Después de cada sobre se vuelve a esta página.
  Es lo que el cliente describió como "cuadros de otros cultos al crear un sobre".
- Qué debería pasar: la pantalla del culto muestra solo ese culto; el archivo va en su propia vista por
  líneas, paginada.
- Captura: `docs/qa/recuento-archivo-otros-cultos-1024.jpg`.

#### QA-005 · Culto cerrado: el tesorero no ve sus sobres — Media · Equipo 2 (REC-2)
- Pasos: tesorero abre `/recuento?culto_id=75` (culto 14/10 cerrado).
- Qué pasa: el selector dice "-- Seleccione un culto --" (los cerrados no están en la lista), aparece
  "Culto Cerrado (13/10/2026 18:55)" y los PDF, y debajo el archivo. No se ven los sobres ni los totales
  de ese culto ni quién lo cerró. Además se pudo cerrar el culto del 14/10 el día 13/10 (antes de que
  ocurra).
- Qué debería pasar: vista de solo lectura del culto (sobres, totales, firmas) con "Cerrado el dd/mm
  por X"; no permitir cerrar un culto futuro.
- Captura: `docs/qa/recuento-culto-cerrado-tesorero-1024.jpg`.

#### QA-006 · Reglas de edición en culto cerrado incoherentes — Media · Equipo 2 (REC-2, REC-3)
- Qué pasa: el admin abre y guarda `/recuento/2597/edit` de un culto cerrado sin ningún aviso de que el
  culto está cerrado; en cambio no puede eliminar ese sobre ("No se puede eliminar un sobre de un culto
  cerrado"). En el "Ver" del archivo el admin tiene dos enlaces "Editar" por fila. En Asistencia, el
  admin no puede corregir una asistencia cerrada (QA-021).
- Qué debería pasar: una sola regla (el plan dice: admin corrige, tesorero solo lee) con aviso visible
  y auditoría del cambio.

#### QA-007 · El culto de hoy queda de último — Media · Equipo 2 (REC-1)
- Qué pasa: Recuento sin culto elegido ofrece como accesos rápidos 30/12, 27/12, 20/12 y 13/12
  (los más lejanos) y el selector ordena de diciembre hacia hoy; el culto del 14/10 queda al final.
  En Nueva asistencia pasa lo mismo con 75 opciones (QA-022). Con los datos de prueba hay cultos
  futuros; en producción pasa en cuanto se creen cultos por adelantado.
- Qué debería pasar: primero el culto de hoy o el próximo, luego los anteriores.

#### QA-008 · Registrar varios sobres seguidos cuesta demasiado — Media · Equipo 2 (REC-4)
- Medido (tesorero, 1024): abrir el formulario 1,9–2,8 s; guardar y volver a la lista 4,9–5,7 s (la
  lista es la página de QA-004). Son 2 cargas por sobre; tras guardar hay que volver a tocar
  "+ Agregar Sobre". No hay foco automático. La persona aparece dos veces (buscador + select de 140
  nombres con el mismo valor); el buscador muestra solo el nombre (no PIN ni teléfono, hay homónimos).
- Qué debería pasar: "Guardar y siguiente" sin salir del formulario, foco en el primer monto, un solo
  control de persona con búsqueda.

#### QA-009 · Cierre de culto y firmas sin controles — Baja · Equipo 2
- "Cerrar Culto" se puede usar con 0 sobres y sin firmas; el modal no muestra el resumen (sobres,
  total) antes de cerrar. La ruta de firmas (`POST /recuento/firmas/{culto}`) acepta cambios en un culto
  cerrado y no deja auditoría.

### Roles, permisos y seguridad

#### QA-010 · Excel con permisos invertidos — Alta · Equipo 2 (REC-5) con Equipo 3 (R8)
- Qué pasa: el tesorero ve "Excel" en Reporte de ingresos y "Descargar Excel" en Dashboard de promesas y
  Dashboard de asistencia; los tres dan 403. Las rutas `excel-ingresos`, `excel-promesas`,
  `excel-asistencia` están en el grupo `role:admin,asistente`, así que el asistente (sin acceso a
  finanzas) descarga por URL `/ingresos-asistencia/excel-ingresos` y `/excel-promesas` (200).
- Qué debería pasar: Excel financiero para admin y tesorero; Excel de asistencia para admin y asistente;
  botón solo para quien puede.
- Captura: `docs/qa/dashboard-promesas-tesorero-excel-1366.jpg` (botón visible para el tesorero).

#### QA-011 · 403 al entrar al sitio con sesión abierta — Alta · Equipo 2
- Pantalla / rol / dispositivo: `/`, `/login`, `/register` y la app instalada (manifest con
  `start_url: "/"`); asistente y miembro; todos (probado en 390).
- Qué pasa: `/` redirige a `/login`, el middleware de invitados manda a `/dashboard` (destino por
  defecto de Laravel) y para estos roles es "403 Acceso Denegado". Un miembro que vuelve a abrir
  ibbla.eu o el acceso directo del teléfono ve 403.
- Qué debería pasar: llevar a cada rol a su inicio (Principal o Mi perfil).
- Captura: `docs/qa/miembro-raiz-403-390.jpg`.

#### QA-012 · El admin de la iglesia puede editar al Super Admin — Alta · Equipo 2
- Pasos: admin abre `/usuarios/1/edit`.
- Qué pasa: formulario con nombre, correo, rol y nueva contraseña del Super Admin (respuesta 200).
  `UserController` (edit, update, destroy) no filtra por iglesia, así que guardar o eliminar también
  procede (no se ejecutó para no alterar ese usuario). Además `/usuarios/{id}` da 500 (falta la vista).
- Qué debería pasar: solo usuarios de la propia iglesia; nunca super admins.

#### QA-013 · API de la app de asistencia sin control de rol — Alta · Equipo 2
- Pasos: con sesión de miembro, desde cualquier página: `GET /asistencia-app/clases`,
  `POST /asistencia-app/personas/quick-add` y `POST /asistencia-app/asistencia/clase` (con el token CSRF
  de la página).
- Qué pasa: GET responde 200 con clases, maestros y alumnos; los POST responden 422 (faltan datos), es
  decir, la autorización pasó: con datos válidos el miembro crea personas y escribe asistencia. El
  tesorero también (en la web no tiene Asistencia). `POST /asistencia-app/login` no tiene límite de
  intentos (el login web sí: 5).
- Qué debería pasar: mismas reglas que la web (admin, asistente y maestras de su clase) y `throttle`.

#### QA-014 · "Yo / Mi perfil" con error para tesorero y asistente — Media · Equipo 2 (REC-5)
- Qué pasa: el menú "Yo" y el acceso "Mi perfil" de Principal se muestran al tesorero y al asistente;
  al abrirlos vuelven a Principal con "Error: No se encontró información de persona asociada." El admin
  no tiene "Mi perfil" ni estado de cuenta propio aunque también es hermano de la iglesia.
- Qué debería pasar: mostrarlo solo si el usuario tiene persona vinculada (o permitir vincularla).

#### QA-015 · Dashboard de asistencia y descripciones de roles — Media · Equipo 2 (REC-5)
- Qué pasa: el tesorero ve "Dashboard de asistencia" y el asistente, que es quien registra asistencia,
  recibe 403. En Crear/Editar usuario los roles dicen "Asistente - Dashboard + Asistencia" (no tiene
  dashboard) e "Invitado - Solo Dashboard" (no tiene nada). El rol "Servidor" existe en la app pero no
  en la lista de roles del plan.

#### QA-016 · El tesorero no llega a los reportes por persona — Media · Equipos 2 y 3 (R8)
- Qué pasa: Reporte General, Reporte PDF, estados de cuenta, ficha y Compromisos son solo admin. La ruta
  "Enviar por WhatsApp" sí permite al tesorero, pero no tiene pantalla desde donde usarla. El plan dice
  "Permisos: admin y tesorero" para reportes. Ver duda 5.

#### QA-017 · "Entrar como Invitado" no funciona — Media · Equipos 2 y 4
- Pasos: en el login, "Entrar como Invitado".
- Qué pasa: vuelve al login con "Tu iglesia no esta activa. Contacta al administrador." (el usuario
  invitado se crea sin iglesia). El mensaje además es falso: la iglesia está activa.
- Qué debería pasar: quitar el botón o definir qué ve un invitado. Ver duda 7.
- Captura: `docs/qa/login-invitado-iglesia-no-activa.jpg`.

#### QA-018 · Dos caminos para dar acceso al miembro — Media · Equipo 2
- Qué pasa: desde Personas > Editar (correo + contraseña) se crea un usuario "miembro" vinculado pero
  sin rol dinámico (`tenant_role_id` vacío, a diferencia de los creados en Usuarios). Desde Usuarios se
  puede crear un "Miembro" pero no vincularlo a una persona: entra y Mi perfil responde "No se encontró
  información de persona asociada".
- Qué debería pasar: un solo flujo "dar acceso a esta persona" y la misma estructura de rol.

#### QA-019 · Puertas sobrantes — Baja · Equipo 2
- `/register` (Breeze) está abierto: cualquiera con un correo del dominio de la iglesia crea un miembro
  sin verificar el correo; la página está en inglés y su título es "Iniciar Sesion".
- `/profile` (Breeze, layout viejo, en inglés) es accesible a todos los roles y ofrece "Delete Account".
- Rutas viejas con 500: `/promesas` ("Call to a member function sum() on null", sin enlace en el menú)
  y `/usuarios/{id}` (vista inexistente).

### Asistencia

#### QA-020 · App de Clases: error 500 con una sola clase — Bloqueante · Equipo 2
- Pantalla / rol: App de Clases; maestra con una clase asignada (y el admin si solo hay una clase activa).
- Pasos: con una sola clase activa ("Ninos 2-6"), admin abre "App de Clases".
- Qué pasa: 500 `TypeError: ClaseAppController::index(): Return value must be of type View,
  RedirectResponse returned` (línea 50: el método declara `: View` y devuelve un redirect cuando hay una
  sola clase). Con dos clases funciona. Toda maestra con una clase cae en este caso.
- Qué debería pasar: entrar directo a la app de su clase.
- Captura: `docs/qa/app-clases-500-una-clase-1366.jpg`.

#### QA-021 · Asistencia cerrada: se edita y se pierde — Media · Equipo 2
- Pasos (asistente, 768): registrar asistencia, Cerrar, luego "Editar" (la URL sigue disponible).
- Qué pasa: el formulario se abre normal, sin aviso; al guardar: "No se puede editar una asistencia
  cerrada" y se pierde lo digitado. El admin tampoco puede corregirla (en Recuento sí puede).
- Qué debería pasar: vista de solo lectura con aviso; regla de corrección igual a Recuento.

#### QA-022 · Nueva asistencia: selector difícil — Media · Equipo 2
- Qué pasa: el selector de culto tiene 75 opciones ordenadas del más lejano (30/12/2026) al más antiguo,
  sin preseleccionar el culto de hoy y sin buscador. Acepta cultos ya cerrados en Recuento (se registró
  asistencia en el culto del 14/10 cerrado). Hay dos cierres distintos (culto y asistencia). Ver duda 9.

#### QA-023 · App de Clases muestra el slug — Baja · Equipo 4
- La lista de clases muestra "ninos_2_6" y "jovenes_13_17" debajo del nombre.

### Reportes y números

#### QA-024 · Misma persona, cifras distintas según dónde se mire — Alta · Equipo 3 (R7, R13)
Persona de prueba: Manuel Segura Mora, id 25 (promesas: Misiones $22 mensual, Construcción ₡4.000 semanal,
Micro ₡13.500 quincenal). Corte 13/10/2026, después de registrarle un sobre de ₡15.000 (diezmo
₡10.000 + misiones ₡5.000) en el culto del 14/10.

| Salida | Esperado | Dado | Diferencia / estado |
|---|---|---|---|
| Estado de cuenta PDF (ficha, Compromisos, lista y Mi perfil dan el mismo PDF) | ₡555.300 | ₡79.500 en categorías prometidas (total contribuido ₡812.500) | −₡475.800, todo "Pendiente" |
| Reporte General PDF | ₡442.220 | ₡175.000 | −₡267.220 |
| Mi perfil (pantalla, octubre) | Misiones "de ₡22,00"; Construcción "de ₡4.000"; Micro "de ₡13.500" | Misiones ₡5.000 | Misiones "¡Cumplido! 100 %" |
| Compromisos (pantalla, octubre) | Misiones "₡22,00", total ₡54.330 | ₡0 | Misiones "−₡11.330 Déficit" |
| Ficha (pantalla) | promesa Misiones "₡22,00" | "Total ofrendado ₡940.085" | — |

Causas observadas:
- Reporte General no convierte USD: la promesa de $22 cuenta como ₡22 por mes (₡220 en el año) y los
  sobres en USD se suman como colones; además cuenta como "dado" lo entregado en categorías sin promesa
  (seminario, campa, préstamo).
- El estado de cuenta convierte USD con el tipo de cambio de hoy (₡11.330/mes) y solo cuenta lo dado en
  categorías prometidas.
- Mi perfil compara lo del mes contra el monto por periodo (semanal sin ×domingos, quincenal sin ×2) y
  sin convertir USD.
- Compromisos muestra el esperado en la moneda de la promesa con símbolo ₡, pero calcula la diferencia
  convertida.
- La ficha suma `total_declarado` de todos los años: un sobre de $85 cuenta como ₡85 e incluye sobres
  de noviembre (datos de prueba futuros).
- Otras personas con la misma diferencia (cálculo directo de los servicios): Lucia Hernandez Calderon (id 35)
  (dado: informe ₡18.300 vs Reporte General ₡31.125,01), Ana Chaves Quesada, id 52 (₡140.445 vs ₡216.671,83),
  Rafael Ramirez Brenes, id 45 (₡24.500 vs ₡286.602,14).
- Qué debería pasar: un solo cálculo (HU-R7) y la misma cifra en todas las salidas; definir con el
  cliente la regla de "dado" y del corte (dudas 1–3).
- Capturas: `docs/qa/compromisos-usd-mezclado-1366.jpg`, `docs/qa/mi-perfil-cumplido-vs-deuda-1366.jpg`,
  `docs/qa/estado-cuenta-pdf-pagina1.jpg`.

#### QA-025 · Consultar Compromisos escribe "deudas" en la BD — Alta · Equipo 3
- Pasos: Mi perfil del miembro muestra "Compromisos Pendientes 28". El admin abre Compromisos de esa
  persona y elige Año 2027, Mes Enero (solo mirar).
- Qué pasa: `CompromisoController@show` hace `firstOrCreate` y guarda los compromisos del mes
  consultado; Mi perfil pasa a 31 con tres "Deuda pendiente" de enero 2027. Lo mismo con cualquier año
  (se probó 2025 y 2030). Esas filas también alimentan el Dashboard de promesas.
- Qué debería pasar: una consulta no escribe; los compromisos se calculan al vuelo o con un proceso
  explícito ("Recalcular").

#### QA-026 · PDF de recuento: el total general no cuadra con la tabla — Alta · Equipo 3 (R10, R14) con Equipo 2
- Pantalla: PDF de recuento del culto 11/10/2026 (`/ingresos-asistencia/pdf-recuento/59`) y "Ver" del
  mismo culto en el archivo.
- Qué pasa: cabecera "TOTAL GENERAL 1.733.317,11" y "DIEZMO 1.111.405,60"; fila TOTALES de la tabla del
  mismo PDF "1.568.323,11" y diezmo "1.046.127,60"; Total efectivo 1.236.131,03 + Total transferencias
  332.192,08 = 1.568.323,11. La tabla suma los sobres en USD como colones (sobre #4: 32,00 / 73,00 /
  87,00 = USD $192) y la cabecera sí los convierte. La tabla llega al borde derecho de la página.
- Qué debería pasar: columnas en colones convertidos (USD aparte, informativo) y todos los totales
  iguales.
- Captura: `docs/qa/recuento-pdf-totales-no-cuadran.jpg`.

#### QA-027 · Dos versiones de "promesas de octubre" — Media · Equipo 3
- Dashboard de promesas (octubre 2026): prometido ₡7.639.799,00, dado ₡644.739,50, cumplimiento 8,4 %.
- Reporte de promesas (octubre 2026): prometido ₡7.607.960, dado ₡2.867.114, faltante ₡4.740.846.
- El dashboard lee la tabla `compromisos` (sin convertir moneda y solo lo que alguien calculó, ver
  QA-025); el reporte calcula desde los sobres. Formato de miles distinto (₡7,639,799.00 frente a
  ₡7.607.960) y una tarjeta y columna "Total Profit" / "Profit" en el reporte.

#### QA-028 · Estado de cuenta: cabecera confusa y corte — Media · Equipo 3 (R13, R14)
- Qué pasa: arriba dice "Total contribuido en 2026 ₡812.500 / Compromisos a la fecha ₡555.300 /
  Diferencia en compromisos −₡475.800": la diferencia usa lo dado solo en categorías prometidas
  (₡79.500), que no aparece. "Esperado a la fecha" cobra el mes en curso completo, así que a mitad de
  mes todos quedan "Pendiente"; el Dashboard usa meses completos.
- Qué debería pasar: mostrar el "dado en compromisos" que se compara y aplicar la regla de corte que
  se defina (el plan propone meses completos).

#### QA-029 · Reporte General de 141 páginas — Media · Equipo 3 (R13)
- Una página por persona con 10 meses × 6 categorías × dado/esperado. No resume quién está al día ni
  cuánto falta; no sirve como listado para el pastor.

#### QA-030 · Descargas y pantallas lentas — Media · Equipo 3
Mediciones en local (varias corridas, máquina compartida con los otros equipos; ver anexo A):
PDF "Reporte anual" de promesas 12–18 s (4 de 4 corridas sobre 12 s); "Reporte PDF" de contribuciones
8–12 s; Dashboard de promesas 3,5–15 s; Reporte General 3–16 s; PDF de recuento de un culto 3–10 s;
Excel de ingresos 1,5–9 s; estado de cuenta 2–6 s. Con la regla de Hostinger (< 10 s) los dos primeros
son candidatos al próximo 504.

#### QA-031 · Detalles de Excel y PDF — Baja · Equipo 3 (R14)
- Excel de ingresos: el culto sin sobres (15/10) sale con celdas vacías en vez de 0.
- Excel de asistencia por clase: fecha como texto, sin autofiltro.
- PDF de recuento: tabla pegada al borde derecho (ver QA-026).

### Personas y Mi perfil

#### QA-032 · Mi perfil del miembro contradice sus propios números — Alta · Equipos 3 y 5
- Pantalla / rol / dispositivo: Mi perfil; miembro; 1366 y 390.
- Qué pasa: "Misiones ₡5.000,00 de ₡22,00 ¡Cumplido! 100 %" (la promesa es $22) y más abajo, en la misma
  pantalla, "Misiones octubre 2026 ₡11.330,00 Deuda pendiente". "Promesas cumplidas 1" y "Compromisos
  pendientes 28": 28 tarjetas rojas, una por categoría y mes, incluido el mes en curso, cada una con
  "Creado: 13/10/2026" (fecha técnica). En móvil son ~5.000 px de deudas.
- Qué debería pasar: un resumen acumulado al corte (lo mismo que el PDF), en la moneda correcta, y el
  detalle mes a mes plegado. Es la pantalla que se va a promover con el opt-in de WhatsApp.
- Capturas: `docs/qa/mi-perfil-cumplido-vs-deuda-1366.jpg`, `docs/qa/mi-perfil-28-deudas-390.jpg`.

#### QA-033 · Ficha de persona con totales engañosos — Media · Equipo 3
- "Total ofrendado ₡940.085" no dice período, suma USD como colones (sobre de $85 mostrado "₡85,00") e
  incluye sobres futuros. La promesa en USD se muestra "₡22,00 Mensual".

#### QA-034 · Acciones masivas irreversibles al alcance — Alta · Equipos 2 y 4
- Pantalla: Gestionar Personas > "Mantenimiento (acciones que no se pueden deshacer)".
- Qué pasa: "Borrar todas las promesas" elimina promesas y compromisos de toda la iglesia y "Eliminar
  personas inactivas (10)" borra personas y sus usuarios; ambas con un `confirm()` del navegador y sin
  auditoría propia. Las rutas dicen "temporal".
- Qué debería pasar: quitarlas o moverlas a un área de administración con confirmación escrita
  (escribir el nombre de la iglesia), respaldo previo y registro.

#### QA-035 · Lista de personas sin orden — Media · Equipo 4 (con R2)
- La lista no ordena por nombre: aparecen primero las 10 personas inactivas (orden de alta). El buscador
  solo busca nombre y PIN: "8152" (parte de un teléfono) no encuentra a nadie. Seis iconos por fila
  (ver, compromisos, WhatsApp, PDF, editar, eliminar).

#### QA-036 · Detalles de Personas — Baja · Equipo 2
- Guardar una persona (aunque solo se cambie el correo) borra y recrea todas sus promesas (nuevos id y
  fechas).
- "Reiniciar compromisos" responde "Compromisos reiniciados correctamente desde October 2026".

### WhatsApp

#### QA-037 · Avisos: nadie se entera — Baja · Equipo 5
- Funciona: aviso "Culto creado" y "41 sobres en Domingo AM del 18/10/2026" para el tesorero con
  teléfono, con "Abrir WhatsApp / Marcar enviado / Descartar"; el botón "Enviar por WhatsApp" de una
  persona abre wa.me con el mensaje y un enlace firmado de 7 días (sin sesión: 200; con otro id: 403).
- Pero con el proveedor "link" nadie recibe nada si no entra al panel; el aviso de "sobres alcanzados"
  le llega al mismo tesorero que está registrando; sin teléfono en los usuarios el panel queda vacío.
  El botón deshabilitado explica el motivo solo con tooltip, que no se ve en tablet ni móvil. La
  decisión de CallMeBot (plan, 13/10 18:40) cubre la mayor parte.

### UI y textos

#### QA-038 · Login y validaciones en inglés — Media · Equipo 4 (configuración)
- Login: "Email, Password, Remember me, Forgot your password?, Log in"; error de credenciales "These
  credentials do not match our records." (con `APP_LOCALE=es` y sin carpeta `lang` sale la clave cruda
  "auth.failed", como reportó D).
- Validaciones: "The nombre field is required." (persona), "The password field must be at least 8
  characters." y "The password field confirmation does not match." (usuario), "The comprobante numero
  field is required." (sobre por transferencia), "The culto id field is required." (asistencia).
- Captura: `docs/qa/asistencia-validacion-ingles-768.jpg`.

#### QA-039 · Textos y marca — Baja · Equipo 4
- Sin tildes o eñes: "Administracion", "Auditoria", "Cargar Historico", "Cerrando sesion", "Siguenos",
  "Atencion", "Ninos, ninas", "Construccion", "No se encontro".
- El encabezado del menú dice "IBBSC Admin" también para el miembro; el manifiesto de la app instalada
  se llama "ANGEDA - Sistema de Administracion"; el login dice "Sistema Admin / Administracion de
  Iglesia" en vez del nombre de la iglesia.

#### QA-040 · Controles chicos — Baja · Equipo 4
- Menos de 36 px de alto: pestañas "General / Asistencia / Promesas" de los tres dashboards; "Editar /
  Desactivar / Eliminar" de Ministerios (texto); "Ver auditoría completa" en Historial de ingresos.

#### QA-041 · Nombres y ubicación de pantallas — Baja · Equipos 4 y 3 (R1)
- El menú dice "Reportes" y la pantalla se titula "Ingresos y Asistencia"; para el tesorero tiene 2
  tarjetas. El Reporte General y el estado de cuenta están dentro de Gestión de Personas.
- Gestionar Cultos no muestra si cada culto está abierto o cerrado y ofrece "Eliminar" en todos.

#### QA-042 · Auditoría poco útil — Media · Equipos 2 y 4
- 187 de 258 registros (72 %) son visitas GET; columnas técnicas ("admin.servidores.reporte",
  "Mozilla/5.0 (Windows NT 10.0…"); no filtra por iglesia (205 registros sin `tenant_id`); las acciones
  críticas no dicen sobre qué registro (QA-003).
- Captura: `docs/qa/auditoria-ruido-1366.jpg`.

#### QA-043 · Dashboard general: comparaciones engañosas — Baja · Equipos 3 y 4
- "Asistencia promedio 114, +0 % vs septiembre" cuando septiembre no tiene asistencia; "226 de 243
  promesas aún pendientes este mes" y "Promesas cumplidas 17/243" calculados a mitad de mes.

## 2. Cosas sin lógica

1. Al registrar sobres se ve el archivo completo de cultos cerrados debajo del culto (QA-004) y
   después de cada sobre se vuelve a esa página larga.
2. Los selectores de culto (Recuento y Asistencia) empiezan por diciembre; el culto de hoy queda al
   final (QA-007, QA-022).
3. La persona del sobre está en dos controles a la vez (buscador y select) y al editar desaparece el
   buscador; si la persona está inactiva el sobre pasa a Anónimo (QA-002, QA-008).
4. Dos cierres distintos para el mismo culto (recuento y asistencia), con reglas distintas para el
   admin; se puede cerrar un culto antes de su fecha y con 0 sobres; se puede registrar asistencia de
   un culto cerrado (QA-005, QA-006, QA-021, QA-022).
5. Botones que se ven y dan 403: basurero de cultos cerrados para el tesorero, Excel del tesorero en
   tres pantallas, "Mi perfil" de tesorero y asistente con error (QA-003, QA-010, QA-014).
6. El asistente, que registra asistencia, no tiene Dashboard de asistencia; el tesorero sí (QA-015).
7. Descripciones de roles que no corresponden a lo que el rol puede (QA-015).
8. El miembro ve "¡Cumplido!" y "Deuda pendiente" para la misma promesa en la misma pantalla, y 28
   tarjetas rojas con "Creado: 13/10/2026" (QA-032).
9. Compromisos: "Esperado ₡22,00" con "Diferencia −₡11.330" en la misma fila (QA-024).
10. Estado de cuenta: "Total contribuido ₡812.500", "Compromisos ₡555.300" y "Diferencia −₡475.800",
    tres números que no se explican entre sí (QA-028).
11. Promesas en dólares mostradas con ₡ en ficha, Mi perfil y Compromisos (QA-024, QA-033).
12. Dos pantallas de "promesas de octubre" con cifras distintas, formatos de miles distintos y
    "Profit" en un reporte de promesas (QA-027).
13. Mirar un mes en Compromisos crea deudas al miembro (QA-025).
14. El PDF que firman los tesoreros tiene dos totales distintos (QA-026).
15. El menú "Reportes" abre "Ingresos y Asistencia"; el Reporte General vive en Personas (QA-041).
16. Acciones que borran datos de toda la iglesia a dos clics de la lista de personas (QA-034).
17. Login con botón de invitado que no entra y con mensaje "tu iglesia no está activa" (QA-017).
18. Un miembro que abre ibbla.eu con sesión abierta ve "Acceso denegado" (QA-011).
19. La auditoría registra cada página vista y no dice qué culto se borró (QA-042).
20. El aviso de "sobres alcanzados" le llega a quien está metiendo los sobres (QA-037).
21. El admin no tiene "Mi perfil" ni su propio estado de cuenta (QA-014).

## 3. Matriz observada de roles

Observado en la app (no lo que debería ser). "Menú" = aparece en el menú lateral. "URL" = solo
escribiendo la dirección. Base para `docs/ROLES.md` (REC-5).

| Pantalla / acción | Admin | Tesorero | Asistente | Miembro |
|---|---|---|---|---|
| Al iniciar sesión llega a | Principal | Principal | Principal | Mi perfil |
| "/", /login, /register con sesión (y app instalada) | Dashboard | Dashboard | 403 | 403 |
| Principal: accesos rápidos | Sobres, Asistencia, Dashboard, Reportes, Personas, Cultos | Sobres, Dashboard, Reportes, Mi perfil (error) | Asistencia, Reportes, Mi perfil (error) | Mi perfil |
| Cumpleañeros | Menú | Menú | Menú | Menú |
| Yo / Mi perfil | Sin menú; URL → error "sin persona" | Menú → error "sin persona" | Menú → error "sin persona" | Menú, sí |
| Estado de cuenta propio (PDF) | No | No | No | Sí |
| /profile (Breeze, inglés, borrar cuenta) | URL | URL | URL | URL |
| App de Clases | Menú (500 con 1 clase) | URL, lista vacía | URL, lista vacía | URL, lista vacía |
| Recuento: ver culto, agregar/editar sobres, dinero suelto, egresos, cerrar | Sí | Sí | 403 | 403 |
| Editar sobre de culto cerrado | Sí, sin aviso | No (mensaje) | 403 | 403 |
| Eliminar sobre de culto cerrado | No | No | — | — |
| Ver culto cerrado (sobres y totales) | Solo en "Ver" del archivo | Solo en "Ver" del archivo | 403 | 403 |
| Eliminar culto cerrado (archivo de Recuento) | Sí, en cascada | Botón visible → 403 | 403 | 403 |
| Gestionar Cultos (crear, editar, eliminar) | Menú | 403 | 403 | 403 |
| Asistencia: registrar, editar, cerrar | Menú (no edita cerrada) | 403, sin menú | Menú (no edita cerrada) | 403 |
| Dashboard general y de promesas | Menú | Menú (Excel promesas → 403) | 403, sin menú | 403 |
| Dashboard de asistencia | Menú | Menú (Excel → 403) | 403, sin menú | 403 |
| "Reportes" | Hub con 4 tarjetas | Hub con 2 tarjetas (Ingresos, Promesas) | Va directo a Reporte de asistencia | 403 |
| Reporte de ingresos (pantalla y PDF) | Sí | Sí | 403 | 403 |
| Excel de ingresos y de promesas | Sí | Botón visible → 403 | URL → 200 (sin pantalla) | 403 |
| Reporte de promesas (pantalla y PDF) | Sí | Sí | 403 | 403 |
| Reportes de asistencia y Asistencia por clase (pantalla, PDF, CSV, Excel) | Menú | 403 | Menú | 403 |
| Avisos de WhatsApp | Menú | Menú | 403 | 403 |
| Gestionar Personas, ficha, Compromisos, editar persona | Menú | 403 | 403 | 403 |
| Reporte PDF, Reporte General, estado de cuenta de otra persona | Sí | 403 | 403 | 403 |
| Enviar por WhatsApp (persona) | Sí | Ruta permitida, sin pantalla | 403 | 403 |
| Ministerios, Clases, Usuarios, Servidores, Cargar histórico | Menú | 403 | 403 | 403 |
| Auditoría e Historial de ingresos (+ CSV) | Menú | 403 | 403 | 403 |
| Editar usuario Super Admin (`/usuarios/1/edit`) | Sí (no debería) | 403 | 403 | 403 |
| API app de asistencia (`/asistencia-app/*`) | Sí | Sí (no debería) | Sí | Sí (no debería) |
| Panel Super Admin | 403 | 403 | 403 | 403 |
| Botón "Entrar como Invitado" (sin sesión) | — | — | — | — no entra (QA-017) |

## 4. Dudas para el cliente

1. Regla de "dado" para saber si alguien está al día: ¿solo lo dado en las categorías que prometió
   (estado de cuenta) o todo lo dado en categorías con promesa (Reporte General)? ¿El diezmo cuenta?
2. Corte: ¿el mes en curso se cobra completo (estado de cuenta de hoy) o solo meses completos (plan R13,
   dashboard)? ¿Desde qué fecha se le cobra a una persona nueva?
3. Promesas y sobres en dólares: ¿tipo de cambio del día del sobre, del cierre del culto o de hoy?
4. ¿Se puede eliminar un culto cerrado? Si sí, ¿quién y con qué motivo?
5. ¿Roy usa el rol Administrador o Tesorero? ¿El tesorero debe ver Personas, Reporte General y los
   estados de cuenta, y mandar informes por WhatsApp?
6. ¿Quién debe ver el Dashboard de asistencia: el asistente, el tesorero o ambos?
7. ¿Para qué es "Entrar como Invitado"? ¿Se quita? ¿Qué debería ver un invitado?
8. Al miembro, ¿se le muestra el detalle de deuda mes a mes o solo el acumulado al corte?
9. ¿Cerrar el culto (recuento) y cerrar la asistencia deben ser un solo cierre? ¿Se puede registrar
   asistencia de un culto ya cerrado?
10. Firmas del recuento: ¿se completan antes de cerrar? ¿Se pueden corregir después?
11. Sobres anónimos seguidos: ¿qué control de duplicados quieren (si alguno)?
12. ¿Cumpleañeros visible para todos los miembros (nombre y día)?
13. "Hermanos servidores" (listado del miércoles): ¿rol Servidor de la app (QR) o personas en
    ministerios? (ya está abierta en el plan).

## Anexo A. Tiempos medidos (local, orientativos)

| Pantalla o descarga | Medido | Comentario |
|---|---|---|
| PDF Reporte anual de promesas | 12,0 / 13,8 / 18,4 / 18,4 s | Siempre > 10 s |
| Reporte PDF (contribuciones) | 7,7 / 8,5 / 9,6 / 12,1 s | DomPDF con vista |
| Dashboard de promesas | 3,5 / 4,2 / 14,9 / 15,6 s | Siempre > 3 s |
| Reporte General PDF (141 páginas, 400 KB) | 2,9 / 4,3 / 5,5 / 16,2 s | Variable |
| PDF de recuento de un culto (47 sobres) | 3,2 / 6,0 / 9,6 s | |
| Excel de ingresos | 1,5 / 3,0 / 7,9 / 9,0 s | |
| Estado de cuenta PDF | 2,0 / 2,3 / 3,1 / 6,0 s | |
| Guardar sobre (POST + volver a la lista) | 4,9 / 5,0 / 5,7 s | La lista pesa 743 KB |
| Recuento con culto (HTML) | 0,8 s; 743 KB | 355 KB sin culto elegido |
| Listas y formularios (personas, cultos, dashboard general) | 0,7–2,5 s | |

Hubo dos "Maximum execution time of 30 seconds" en vistas de Breeze (/profile, /register) en momentos de
máxima carga de la máquina; repetidas después respondieron en ~1 s, así que no se cuentan como hallazgo.

## Anexo B. Lo que funciona (verificado)

- Las cuatro entradas al estado de cuenta (ficha, Compromisos, icono en la lista, Mi perfil) entregan el
  mismo PDF; el enlace firmado de WhatsApp abre sin sesión y con otro id da la página "informe no
  válido" (403).
- Reporte de ingresos de octubre: pantalla = PDF = Excel = Dashboard (₡7.421.236,67).
- Asistencia por clase: pantalla = PDF = CSV = Excel (8 niños, 6 niñas, promedio 14).
- Dashboards: rango invertido muestra "La fecha de fin no puede ser anterior a la fecha de inicio";
  rangos que cruzan años funcionan; atajos de período.
- Ministerios: crea, detecta duplicados sin distinguir tildes ni mayúsculas, bloquea eliminar con
  personas asignadas.
- Historial de ingresos: inicios, cierres e intentos fallidos con navegador y sistema; CSV con BOM y
  ";" (abre bien en Excel).
- El tesorero no puede editar sobres de un culto cerrado (el backend lo rechaza con mensaje).
- Cultos de Miércoles y Sábado se crean bien (la columna acepta el valor sin tilde).
- Diseño: 0 px de desborde horizontal y 0 elementos cortados en 225 mediciones (57 pantallas × 4
  resoluciones); las tablas anchas (ingresos, auditoría) se desplazan dentro de su tarjeta; barras fijas
  de Guardar en sobre, persona y asistencia; páginas 403/404 en español.

## Anexo C. Pantallas y capturas

Capturas del informe (`docs/qa/`): login-invitado-iglesia-no-activa, recuento-sobre-anonimo-rechazado-1024,
recuento-editar-sobre-persona-inactiva-1024, recuento-eliminar-culto-tesorero-1024,
recuento-archivo-otros-cultos-1024, recuento-culto-cerrado-tesorero-1024, app-clases-500-una-clase-1366,
compromisos-usd-mezclado-1366, mi-perfil-cumplido-vs-deuda-1366, mi-perfil-28-deudas-390,
miembro-raiz-403-390, recuento-pdf-totales-no-cuadran, estado-cuenta-pdf-pagina1,
asistencia-validacion-ingles-768, dashboard-promesas-tesorero-excel-1366, auditoria-ruido-1366.

## Anexo D. Entorno y datos de prueba

- Datos creados durante el QA en `ibbla_qa` (para reproducir): culto 75 (14/10/2026 Miércoles, cerrado,
  5 sobres, asistencia cerrada de 114), culto 77 (15/10/2026 Especial), culto 76 creado y borrado (prueba
  de QA-003), clases "Ninos 2-6" y "Jovenes 13-17", ministerio "Jóvenes", teléfono 8888-7777 en el
  usuario tesorero (avisos), sobre 2201 desvinculado (QA-002), compromisos de 2025/2027/2030 creados al
  consultar (QA-025), compromisos de la persona 35 reiniciados.
- `VolumenDevSeeder` crea cultos y sobres hasta el 30/12 con `created_at` futuro: inflan "octubre" en
  reportes y dashboards, y el control de duplicados de 30 s (QA-001) rechaza cualquier sobre anónimo en
  esos cultos. Conviene que el seeder llegue solo hasta hoy (útil para los equipos 3 y 5).
- Los tiempos se midieron con `php artisan serve` (un solo hilo) mientras los otros cuatro equipos
  corrían servidores y pruebas en la misma máquina: se reportan rangos y solo lo que fue lento en todas
  las corridas.
- Herramientas: navegador integrado y Chrome headless por DevTools Protocol (scripts en el scratchpad
  del agente, no se commitean).
