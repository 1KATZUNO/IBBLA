# QA final de la tanda E2 · 24/11/2026

Equipo Q. Rama `qa/revision-final` = `integracion/oct-2026` @ cb5f7c1. BD `ibbla_qa` (VolumenDevSeeder: 150 personas,
un año de cultos, ~2.350 sobres), servidor `php artisan serve` en :8111 (y :8112 con `APP_ENV=qa` para el
ambiente QA). MySQL 8.4 local. Pruebas por HTTP real (curl con sesión de los cuatro roles), cruces contra la BD
con SQL directo y lectura de PDF/Excel (pdftotext / PhpSpreadsheet). Scripts y evidencias fuera del repo, en
`C:\Users\rgonh\Documents\IBBLA-wt\qa-qf-evidencias\` (sin commitear).

Usuarios: admin@ibbsc.com, tesorero@ibbsc.com (semilla) y, creados para esta prueba, `qf.asistente@ibbsc.com`
(rol asistente) y `qf.miembro@ibbsc.com` (rol miembro, vinculado a la persona 6). A admin y tesorero se les puso
un teléfono ficticio para disparar avisos (las claves de WhatsApp están vacías: nada salió).

## Veredicto: **GO con condiciones**

No hay hallazgos bloqueantes. Lo nuevo funciona con los cuatro roles, las cifras cuadran al céntimo (pantalla =
Excel = PDF = BD) y todo responde en menos de 5 s. La suite queda en verde, salvo los 14 tests de Breeze de
siempre.

Condiciones:
1. ~~QF-009~~: ya verificado en MariaDB 11.8.9 de producción, sin diferencia. No queda condición por esto.
2. Desplegar producción con `--manual-pdf` (QF-007). Así el primer «Descargar manual» no genera el PDF dentro
   de la petición.
3. Antes de dar por buenos los avisos automáticos de WhatsApp, confirmar el cron de `schedule:run` en Hostinger
   y `QUEUE_CONNECTION=sync`. `php artisan ibbla:verificar` lo avisa: hoy, en local, dice «nunca corrió».

Las tres de severidad Media (QF-001 a QF-003) pueden ir en la siguiente tanda: ninguna da cifras incorrectas,
dos son preexistentes y la otra es de rotulación.

## Hallazgos

| Id | Sev. | Resumen |
|---|---|---|
| QF-001 | Media | Plantilla de importación de asistencia da 500 (preexistente) |
| QF-002 | Media | Reporte de cultos: columna «Efectivo» ya neta junto a «Suelto» y «Egresos» |
| QF-003 | Media | «Ingresos» del Dashboard es neto de egresos; en el Reporte de ingresos es bruto |
| QF-004 | Baja | Ruta vieja `pdf-ingresos?accion=descargar` redirige con `accion=ver` |
| QF-005 | Baja | Categorías por defecto sin tilde (`Construccion`, `Prestamo`) para iglesias o semillas nuevas |
| QF-006 | Baja | Manual sin sección del Reporte de cultos; «?» de cultos e ingresos sin ancla |
| QF-007 | Baja | En producción el PDF del manual se genera en la primera descarga si no se usa `--manual-pdf` |
| QF-008 | Baja | App de Clases en voseo («No tenés…», «Pedile…») frente al tuteo del resto |
| QF-009 | Cerrado (era Alta) | `JSON_SEARCH` con ruta comodín: verificado en MariaDB 11.8.9 (producción), sin diferencia |

### QF-001 · Plantilla de importación de asistencia: error 500 (Media, preexistente)
- Pasos: admin → `GET /admin/asistencia/importar/plantilla`.
- Esperado: descarga el .xlsx. Observado: 500 a los 23 s, con `Call to undefined method
  PhpOffice\PhpSpreadsheet\Style\Style::getComment()`.
- Archivo: `app/Exports/PlantillaAsistenciaExport.php:130`. Es así desde bba3cd9 (Fase 3); no es regresión de
  E2. Propuesta: `$sheet->getComment('A2')->getText()->createTextRun(...)`.

### QF-002 · Reporte de cultos: «Efectivo» ya incluye suelto y egresos (Media)
- Pasos: tesorero → `/reportes/cultos?desde=2026-11-01&hasta=2026-11-30` (o el Excel). Culto 69 de prueba:
  sobres en efectivo ₡35.300 (incluye USD 20 × 515), transferencia ₡7.000, suelto ₡2.500, egreso ₡1.500.
- Observado: Efectivo 36.300 | Transf. 7.000 | Suelto 2.500 | Egresos 1.500 | Total 43.300. El total es correcto
  y sigue la nota «efectivo (sobres + suelto − egresos)», pero quien suma las columnas a mano obtiene 44.300.
  El TOTAL de «Efectivo» del rango (₡5.084.709,76) no coincide con «Sobres en efectivo» del Reporte de ingresos
  del mismo mes (₡5.082.709,76): cada culto con suelto y egreso desvía la cifra.
- Esperado: rotular la columna «Efectivo neto (sobres + suelto − egresos)» o mostrar solo los sobres en
  efectivo.
- Archivo: `app/Services/Reportes/Cultos/GeneradorReporteCultos.php` (usa `ResumenCulto::efectivo()`).

### QF-003 · «Ingresos» no significa lo mismo en el Dashboard y en el Reporte de ingresos (Media, preexistente)
- Noviembre 2026: Dashboard «Ingresos del período ₡6,109,082» y «Total Mensual ₡6,109,081.59» (neto de
  egresos, de `totales_culto.total_general`). Reporte de ingresos: «Ingresos ₡6,112,081.59 … Egresos 3.000 …
  Neto 6,109,081.59».
- Las dos cifras son correctas, pero el rótulo «Ingresos» del Dashboard debería decir «Neto (ingresos −
  egresos)».

### QF-004 · Redirección vieja de PDF de ingresos pierde «descargar» (Baja)
- `/ingresos-asistencia/pdf-ingresos?fecha_inicio=2026-01-01&fecha_fin=2026-03-31&accion=descargar` redirige a
  `/reportes/ingresos/pdf?…&accion=ver`. Las fechas sí se conservan, igual que en las otras rutas viejas.

### QF-005 · Categorías por defecto sin tilde (Baja)
- La migración `2026_11_24_100000_corregir_tildes…` corrige los datos existentes, y en producción basta. Pero
  `Tenant::defaultCategories()` sigue con `'Construccion'` y `'Prestamo'` (`app/Models/Tenant.php:175,177`):
  una iglesia nueva o una semilla (como `ibbla_qa`) sale sin tilde. Se ve en Dashboard, reportes y PDF de
  recuento en QA. Los tipos de culto sí quedaron con tilde. El enlace por `slug` con `compromisos` y los
  reportes no se rompe: `compromisos.categoria` sigue en slugs y las cifras cuadran.

### QF-006 · Manual: falta el Reporte de cultos (Baja)
- `/manual/reportes` menciona el Reporte de ingresos, pero no hay sección del Reporte de cultos. El «?» de
  `/reportes/cultos` y `/reportes/ingresos` abre `/manual/reportes` sin ancla. Los de Cumplimiento y Estados de
  cuenta sí tienen la suya.

### QF-007 · PDF del manual en producción (Baja)
- Sin `--manual-pdf`, el primer `/manual/pdf` genera el archivo dentro de la petición: el script estima ~30 s.
  Aquí, ya generado, tardó 0,4–5,2 s. Desplegar con `--manual-pdf`.

### QF-008 · Voseo en App de Clases (Baja)
- Miembro sin clase en `/app/clase`: «No tenés una clase asignada. Pedile al administrador que te asigne una.»
  El resto de la app usa tuteo («Escribe al menos un monto…»).

### QF-009 · MariaDB: `JSON_SEARCH` con comodín en la ruta (cerrado: verificado en MariaDB 11.8.9, producción, sin diferencia)
- El líder técnico corrió la expresión en el MariaDB 11.8.9 de producción. Da lo mismo que MySQL 8.4:
  `"$[0].imagen"` con imagen; NULL con imagen vacía, sin la clave o con NULL. No requiere acción.
- Dato adicional del líder: E1 ya está en producción y el motor de cumplimiento da la misma huella de cifras por
  persona en MariaDB (servidor) y en MySQL (copia local).

Detalle original:
- `app/Services/Reportes/Cultos/GeneradorReporteCultos.php:97`:
  `JSON_SEARCH(firmas_tesoreros_imagenes, 'one', '_%', NULL, '$[*].imagen')`.
- Hasta donde sé, MariaDB admite comodines en la ruta de `JSON_SEARCH`; solo `JSON_SET/INSERT/REPLACE/REMOVE`
  los rechazan. Aun así no se probó en 11.8.
- Verificación en MariaDB: `SELECT JSON_SEARCH('[{"imagen":"x"}]','one','_%',NULL,'$[*].imagen');` debe dar
  `"$[0].imagen"`.

## Compatibilidad MariaDB (punto 4)
Revisé `git diff 94a719f..HEAD` en `app`, `database` y `routes` (todo `DB::raw`, `selectRaw`, `whereRaw`,
`orderByRaw`, `groupBy`, `DB::select` y migraciones):
- GROUP BY: todos explícitos con las columnas seleccionadas (`CultosSinCerrar`, `CuadreSobresDetalles`,
  `TotalesCulto`, `VerificarIbbla`) o con la misma expresión (`LOWER(d.categoria)`). Sirven con
  ONLY_FULL_GROUP_BY de MariaDB, que no reconoce dependencias funcionales.
- No aparecen `ANY_VALUE`, `->>`, `JSON_TABLE`, `REGEXP_LIKE`, colaciones `_0900_`, `RENAME COLUMN`,
  `MODIFY` ni índices funcionales.
- Migraciones nuevas: solo `addColumn ... after()`, índices compuestos, `json()` y `foreignId()->unique()`;
  todo compatible.
- `orderByRaw('persona_ministerio.hasta IS NOT NULL')`, `YEAR(fecha)`, `CASE`, `COALESCE` y
  `COUNT(DISTINCT)`: compatibles.
- Único caso dudoso: QF-009, ya verificado en MariaDB 11.8.9 de producción, sin diferencia.

## Lo nuevo, por equipo (punto 1)

**R1**
- Selector y vista previa:
  - Funcionan los filtros combinables (`grupo=filtros&estado=todos&promesa=si&aporto=si&orden=dado&dir=desc`).
  - Siguen funcionando las URL viejas: `grupo=con_promesa`, `grupo=ministerio&ministerio=1` y
    `grupo=seleccion&personas=6,13`. Todas dan 200 en Cumplimiento y Estados de cuenta.
  - Estados de cuenta pagina con `pagina=2`.
  - Los totales del conjunto completo coinciden:
    - Cumplimiento (con promesa, corte 24/11): pantalla «Total con promesa (86 personas, todas las páginas)»
      ₡70,454,880 / ₡6,415,227 = Excel 70454880 / 6415227.1 = PDF 70,454,880 / 6,415,227.
    - Estados de cuenta: pantalla ₡43,300,679.96 / ₡70,454,880.00 / ₡6,415,227.10 = fila TOTAL del Excel.
- QE-008: Dashboard de promesas «Prometido (meses completos) ₡70,454,880» = «Le correspondía» de Cumplimiento.
  Aparece la tarjeta «Noviembre (en curso, todavía no se cobra)».
- QE-011: la columna dice «Último aporte a sus compromisos».

**R2**
- Reporte de ingresos de noviembre contra SQL directo:
  - Categorías: diezmo 3,607,051.17; ofrenda especial 199,333.55; misiones 292,000; seminario 425,410.61;
    construcción 468,090; CAMPA 320,000; préstamo 351,055; micro 444,141.26.
  - Suelto 5,000. Total 6,112,081.59 = Excel al céntimo, con 15 sobres en USD (todos con tipo de cambio).
- Comparativo con octubre presente (−17,4 %).
- `totales_culto` del culto de prueba = recuento (USD 20 → ₡10.300 con TC 515).
- Las rutas viejas de ingresos redirigen a las nuevas con fechas (ver QF-004).
- Centro de reportes:
  - Admin y tesorero ven las tarjetas de Cultos, Ingresos, Cumplimiento, Estados de cuenta y Asistencia.
  - Asistente y miembro reciben 403 en el centro. El asistente tiene sus enlaces de asistencia en el menú.
- Buscador: devuelve los reportes según el rol. A asistente y miembro solo les muestra páginas del manual.

**F1**
- Crear culto y los sobres 1–11 responden en 0,6–2,0 s. Crean `avisos_whatsapp` (2 de `culto_creado` y 2 de
  `sobres_alcanzados`, una sola vez por culto) en estado `pendiente`, sin llamadas HTTP.
- `whatsapp:enviar-pendientes`: «Avisos enviados: 0 · fallidos: 0 · vencidos: 0» (driver link, sin claves).
- `ibbla:verificar`: RESUMEN OK con 8 avisos, entre ellos el del cron y `QUEUE_CONNECTION`.
- Cierre de culto: 1 sola entrada «Cerrar culto» en la auditoría; un segundo cierre no la duplica.
- `/promesas`, `/promesas/5/edit` y `/promesas/create` llevan a `/personas` (admin) o dan 403 (tesorero).
  `/usuarios/2` lleva a `/usuarios`.
- Mantenimiento masivo: 200 solo para admin y 403 para los demás.
- Guardar la ficha y vincular usuario con persona: cubiertos por `PromesasEnSitioTest` y
  `UsuarioPersonaVinculoTest` (en verde). No se probaron a mano.

**F2**
- `/app/clase`: tesorero 403; miembro sin clase ve el mensaje (QF-008).
- Dashboard: «83 de 86 personas con promesa están pendientes al 31/10/2026 (último mes completo)» y la
  comparación «vs 01/10/2026 – 24/10/2026 mes en curso (24 días): mismos días».
- Principal: sin «…» en los subtítulos.
- El tesorero obtiene 200 en los reportes y Excel de asistencia (`ingresos-asistencia/asistencia*`,
  `pdf-asistencia*`, `excel-asistencia*`).
- Tipos de culto con tilde. Categorías: ver QF-005.
- Asistencia cerrada, probada por HTTP sobre el culto 69:
  - El asistente la crea y la cierra.
  - Al editarla ve «Cerrada el 24/11/2026 a las 19:03 por QF asistente · solo lectura», sin botón de guardar.
    Si intenta el PUT, recibe 403.
  - El admin la corrige (10 → 11) y queda la auditoría «Corregir asistencia cerrada del culto del 23/11/2026»
    con el antes y el después.

**G**
- Con `APP_ENV=qa`: aparece el banner «AMBIENTE DE PRUEBAS» (login y pantallas con sesión) y la cabecera
  `X-Robots-Tag: noindex, nofollow`.
- Con `APP_ENV=local`: ni banner ni cabecera.
- Botón «?» presente en todas las pantallas revisadas.
- `/manual/pdf`: 200 para los cuatro roles (3 MB).
- `deploy/hostinger.sh`:
  - `bash -n` sin errores.
  - Sin `--qa`, el camino de producción queda igual. El único agregado es que se niega a correr en la ruta de
    QA, y el manual PDF solo se genera con `--manual-pdf`.

## Flujo crítico (punto 2)
Pasos, como admin:
1. Crear el culto 69 (especial, 23/11).
2. Registrar 11 sobres: colones con persona, USD 20 con TC 515, anónimo, transferencia con comprobante y 7 de
   diezmo.
3. Registrar suelto ₡2.500 y egreso ₡1.500.
4. Cerrar el culto.
5. PDF de recuento (200, 3,5 s; «TOTALES 27,300 / 3,000 / 5,000 / 7,000 … 43,300»).

Resultados:
- `compromisos` sincronizados: persona 15, construcción, noviembre: `monto_dado` 14.000 (7.000 + 7.000 de dos
  cultos).
- Cumplimiento con corte y Estados de cuenta individual y en lote, incluido el ZIP de selección: 200, 1,7 s.
- ZIP con grupo vacío: redirige, sin 500.
- Mi perfil del miembro y su PDF: 200.
- `/register`: 404.

## Matriz de permisos (punto 3, GET; código y tiempo en segundos)
Completa en `qa-qf-evidencias\matriz.txt` (80 rutas × 5 perfiles). Rutas nuevas o tocadas:

| Ruta | admin | tesorero | asistente | miembro | sin sesión |
|---|---|---|---|---|---|
| reportes/cultos (+pdf, excel) | 200 | 200 | 403 | 403 | 302 |
| reportes/ingresos (+pdf, excel) | 200 | 200 | 403 | 403 | 302 |
| reportes/cumplimiento (+pdf, excel) | 200 | 200 | 403 | 403 | 302 |
| reportes/estados-de-cuenta (+pdf, excel, zip) | 200 | 200 | 403 | 403 | 302 |
| ingresos-asistencia (centro) | 200 | 200 | 403 | 403 | 302 |
| ingresos-asistencia/asistencia, -clases, pdf/excel asistencia | 200 | 200 | 200 | 403 | 302 |
| ingresos-asistencia/ingresos, pdf-ingresos, excel-ingresos, excel-promesas | 302→nuevo | 302→nuevo | 403 | 403 | 302 |
| ingresos-asistencia/promesas, pdf-promesas(-anual) | 200 | 200 | 403 | 403 | 302 |
| dashboard, dashboard/promesas | 200 | 200 | 403 | 403 | 302 |
| dashboard/asistencia | 200 | 200 | 200 | 403 | 302 |
| buscar | 200 | 200 | 200 | 200 | 302 |
| manual, manual/pdf | 200 | 200 | 200 | 200 | 302 |
| app/clase | 200 | 403 | 403 | 200 (mensaje) | 302 |
| personas/mantenimiento/{inactivas,promesas} | 200 | 403 | 403 | 403 | 302 |
| promesas/* | 302→personas | 403 | 403 | 403 | 302 |
| usuarios, usuarios/create, usuarios/buscar-personas | 200 | 403 | 403 | 403 | 302 |
| usuarios/{id} | 302→lista | 403 | 403 | 403 | 302 |
| avisos | 200 | 200 | 403 | 403 | 302 |
| mi-perfil/informe | 302 | 302 | 302 | 200 | 302 |
| admin/asistencia/importar/plantilla | **500** (QF-001) | 403 | 403 | 403 | 302 |

## Tiempos (punto 3; tesorero, volumen, máquina con la suite corriendo a la vez en parte)

| Petición | s |
|---|---|
| cumplimiento `grupo=todas` / `con_promesa` / filtros + orden | 0,9 / 1,0 / 1,1 |
| cumplimiento PDF / Excel (todas) | 1,6 / 1,7 |
| estados-de-cuenta pantalla (todas, año) / pág. 2 | 1,2 / 0,9 |
| estados-de-cuenta Excel con_promesa / todas | 2,7 / 4,5 |
| estados-de-cuenta PDF con_promesa / todas | 1,9 / 2,5 |
| estados-de-cuenta ZIP (selección) | 1,7 |
| reportes/cultos pantalla / PDF / Excel (12 meses) | 0,8 / 1,0 / 1,0 |
| reportes/ingresos pantalla / PDF / Excel (12 meses) | 1,2 / 1,1 / 1,3 |
| dashboard / dashboard/promesas | 1,0 / 1,1 |
| ingresos-asistencia/promesas?mes=todos / pdf-promesas-anual | 0,8 / 0,8 |
| pdf-ingresos-transferencias | 4,3 |
| manual/pdf (primera descarga en esta sesión) | 5,2 |
| crear culto / guardar sobre (incluido el 10.º) | 1,0–4,0 / 0,6–2,0 |
| ibbla:verificar: Reporte General PDF / estado de cuenta PDF | 1,4 / 1,3 |

Todo queda por debajo de 10 s. El Excel de estados de cuenta que R1 midió en 12,6–17,9 s tardó aquí 2,7–4,5 s.
El único caso lento es QF-001 (23 s, y termina en error).

## Estado de los hallazgos anteriores (punto 5)

| Id | Estado | Evidencia |
|---|---|---|
| QE-001 | Corregido (E1) | PDF de recuento del culto 69 con la columna Total completa en el texto; no se revisó visualmente |
| QE-002 | Corregido | `excel-promesas` redirige a `reportes/cumplimiento/excel?grupo=con_promesa` (motor) |
| QE-003 | Corregido | Centro de reportes con Cumplimiento y Estados de cuenta para el tesorero; buscador los encuentra |
| QE-004 | Corregido | `/register` → 404 |
| QE-005 | Corregido | ZIP con ministerio sin personas → 302 a la pantalla, sin 500 |
| QE-006 | Corregido (E1) | No se reprobó el borrado; `compromisos` se recalculó al guardar sobres en el culto cerrado/abierto |
| QE-007 | Parcial | «?» en todas las pantallas y anclas en Cumplimiento y Estados de cuenta; falta el Reporte de cultos (QF-006) |
| QE-008 | Corregido | Prometido del Dashboard = Le correspondía de Cumplimiento (₡70,454,880); mes en curso aparte |
| QE-009 | Corregido | Avisos creados en `pendiente`, sin HTTP; los envía el comando del planificador |
| QE-010 | Corregido (E1) | manifest «IBBLA · Administración de la iglesia», icono `logo-ibbla-256.png` |
| QE-011 | Corregido | Columna «Último aporte a sus compromisos»; `UltimoAporteCompromisosTest` en verde |
| QE-012 | Corregido | Tesorero 200 en pantallas, PDF y Excel de asistencia |
| QE-013 | Corregido | Un solo «Cerrar culto» en la auditoría, también tras cerrar dos veces |
| QE-014 | Corregido (por suite) | `BarraYBuscadorSobreTest` / `SobreSinPersonaPorDescuidoTest` en verde; sin prueba visual |
| QE-015 | Corregido | Asistente: «solo lectura» sin guardar y PUT 403; admin corrige con auditoría |
| QE-016 | Corregido | Tesorero 403; miembro sin clase con mensaje (voseo, QF-008) |
| QE-017 | Corregido | `ibbla:verificar` informa el ambiente, el cron y `QUEUE_CONNECTION` |
| QE-018 | Parcial | Tipos de culto con tilde; categorías por defecto aún sin tilde (QF-005) |
| QE-019 | Corregido | Principal sin «…» en los subtítulos |
| QE-020 | Corregido | `/promesas*` → Personas; `/usuarios/{id}` → lista |
| QA-018 | Corregido (por suite) | `UsuarioPersonaVinculoTest` en verde; `usuarios/buscar-personas` solo admin |
| QA-021 | Corregido | Igual que QE-015 (probado por HTTP) |
| QA-034 | Corregido | Pantalla de confirmación propia solo para admin (200 admin, 403 resto) |
| QA-036 | Corregido (por suite) | `PromesasEnSitioTest` en verde |
| QA-043 | Corregido | «+12.6% vs 01/10/2026 – 24/10/2026 mes en curso (24 días): mismos días»; pendientes al 31/10 |

## Suite (punto 6)
`DB_CONNECTION=mysql DB_DATABASE=ibbla_qa_test php -d memory_limit=1G -d max_execution_time=0 vendor/bin/phpunit`:
**971 tests, 5.567 aserciones, 14 fallos** (5 min 05 s). Los 14 son los de Breeze: `Auth/AuthenticationTest`
(2), `EmailVerificationTest` (2), `PasswordConfirmationTest` (2), `PasswordUpdateTest` (2), `ExampleTest` (1)
y `ProfileTest` (5). No falla ningún otro.

## No probado
- Revisión visual de los PDF (maquetación) y prueba en móvil.
- JS del selector en el navegador: «Seleccionar todos los filtrados», aviso de más de 50 y casilla de cabecera.
  Solo se probaron las URL que genera.
- Barra y buscador del sobre en el navegador.
- POST de borrados masivos, vinculación de usuario y guardado de ficha (cubiertos por la suite).
- MariaDB real, salvo la expresión de QF-009 (la verificó el líder en producción).
