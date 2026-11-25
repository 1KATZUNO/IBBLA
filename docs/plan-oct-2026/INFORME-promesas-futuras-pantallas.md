# Informe · Equipo P · Promesas futuras: pantallas, importador de Excel y plantilla

Rama `feat/promesas-futuras-pantallas` (desde `feat/promesas-futuras`). Especificación:
`06-PROMESAS-FUTURAS.md`, secciones 4 y 5. Hecho todo el encargo (puntos 1 a 6).

## Qué se hizo

| # | Historia | Estado |
|---|---|---|
| 1 | Cortes: lista, nuevo, editar, eliminar (escribir CONFIRMAR) y detalle; solo Administrador; auditoría; menú lateral y buscador | Hecho y probado |
| 2 | Importar Excel con revisión antes de aplicar (categorías y personas), aplicar en una transacción | Hecho y probado |
| 3 | Plantilla .xlsx (fila de títulos + hoja «Instrucciones») | Hecho y probado |
| 4 | Agregar / editar / quitar una persona a mano (existente o alta nueva) | Hecho y probado |
| 5 | Manual (sección «Promesas futuras» en *Promesas*, con `rutas:` para el «?»), fila en Novedades y en `docs/ROLES.md` | Hecho y probado |
| 6 | Pruebas de aceptación 4.3 | 20 pruebas, 198 aserciones |

### Archivos
- `app/Http/Controllers/PromesasFuturasController.php` (sin FormRequests: las reglas son pocas y van en el controlador).
- `app/Services/Promesas/Importacion/`: `LectorExcelPromesas` (lee y valida el archivo, sin BD),
  `AnalizadorImportacion` (revisión: categorías y estado de cada fila, sin escribir), `AplicadorImportacion`
  (transacción), `CategoriasCorte` (categorías de promesa activas + las inactivas del corte; crea una inactiva),
  `PlantillaPromesas`, `Normalizador` (nombres sin tildes/mayúsculas, montos de texto).
- `resources/views/promesas-futuras/`: `index`, `form`, `eliminar`, `show`, `agregar`, `persona`, `importar`, `revisar`.
- `routes/web.php` (grupo solo admin + `audit`), `app/Support/Navegacion/Menu.php` (menú + 2 extras del buscador),
  `app/Models/PromesaProgramada.php` (dos ayudantes de formato).
- Manual: `05-promesas.md` (sección + `rutas: … promesas-futuras.*#promesas-futuras`), `00-novedades-noviembre-2026.md`.
  `docs/ROLES.md`: una fila.
- Pruebas: `tests/Feature/PromesasFuturas/CortesPromesasFuturasTest.php`, `ImportacionPromesasFuturasTest.php`.

No se tocó ningún archivo del Equipo M ni `public/build`.

## Rutas (todas `role:admin` + `audit`)

`/promesas-futuras` (lista) · `/nuevo` · `/plantilla` · `/{corte}` (detalle) · `/{corte}/editar` · `/{corte}/eliminar` ·
`/{corte}/importar` (subir) → `/{corte}/importar/revisar` → `POST /{corte}/importar/aplicar` (o `/descartar`) ·
`/{corte}/personas/agregar` · `POST /{corte}/personas/nueva` · `/{corte}/personas/{persona}` (GET/PUT/DELETE).
Va en `/promesas-futuras` y no en `/promesas/...` porque `promesas/{cualquiera}` redirige a Personas (QE-020).

## Cómo probarlo a mano
1. Entrar como Administrador → menú **Administración** → **Promesas futuras** (o buscar «promesas futuras»).
2. **+ Nuevo corte**: «Promesas 2027», enero de 2027 → abre el detalle vacío, con el aviso «no entrará en vigor».
3. **Descargar plantilla**: fila 1 = Miembro, Clase y las categorías de promesa activas; hoja «Instrucciones».
4. **Importar Excel** → elegir el archivo, frecuencia (semanal) y moneda (colones) → **Subir y revisar**:
   tarjetas con Calzan / Ya están / Nombre parecido / No existen / Se ignoran; categorías con su selector; las
   filas con nombre parecido piden decisión (Aplicar queda gris con «Falta decidir N filas»).
5. **Aplicar** → mensaje con agregadas / ya estaban / omitidas / personas creadas / categorías nuevas.
6. Volver a subir el mismo archivo y aplicar: «0 personas agregadas, N ya estaban y se dejaron igual».
7. En el detalle: buscar, **Editar** y **Quitar** por fila, **+ Agregar persona** (buscar por nombre, PIN o
   teléfono, o dar de alta). Comprobar en Personas / reportes que nada cambió.
8. Tesorero, asistente o miembro: `/promesas-futuras` responde 403 y no aparece en menú ni buscador.

Con el Excel real del cliente (solo se leyó, sin copiarlo ni imprimir nombres): 135 filas, 5 categorías
(Misiones, Construcción, Campamento, Seminario, Alcance Local), todas con clase y montos; sin errores; lectura
0,1 s; los montos con muchos decimales quedan en dos.

## Tiempos medidos (datos de volumen `ibbla_rn`, `php artisan serve` local)
| Petición | Tiempo |
|---|---|
| Revisar un Excel de 134 filas (130 personas que calzan) | 1,3 s |
| Aplicar + redirección al detalle (132 personas) | 1,6 s |
| Detalle del corte con 132 personas | ~1,0 s |
| Lista de cortes | 0,8 s |
| Plantilla | 1,9 s |
| Prueba automática: subir + revisar + aplicar 150 filas | < 8 s exigido en la prueba (pasa holgada) |

## Decisiones
- **Archivo subido**: se guarda *lo leído* (no el archivo) en la caché, con clave atada al usuario y al corte
  (`promesas-futuras.revision.{user}.{corte}`) y caducidad de 2 horas. La revisión se recalcula contra la BD
  cada vez y otra vez al aplicar (si alguien cambió algo entretanto, manda lo que hay en ese momento).
  Las decisiones del formulario son solo las de las filas que piden decisión y los selectores de columna.
- **Emparejar personas**: nombre sin tildes, mayúsculas ni signos. Igual a una sola persona = Calza (o «Ya está
  en este corte» si tiene promesas programadas en él). Igual a dos o más = Nombre parecido (hay que elegir).
  Parecido = `similar_text` ≥ 80 % o todas las palabras del nombre corto (≥ 2) dentro del largo
  («Ana Mora» ↔ «Ana Mora Solís»); hasta 5 candidatas. Se comparan TODAS las personas de la iglesia, también las
  inactivas (se marcan «inactiva»), para no duplicar nunca.
- **Categorías**: se emparejan por nombre o por slug, sin tildes ni mayúsculas, contra las de promesa activas y
  las inactivas creadas para ESTE corte (así reimportar no crea otra «Alcance Local»). Una categoría nueva:
  `activa = false`, `tipo = ambos`, `excluir_de_promesas = false`, slug único, `orden` al final, gris `#6b7280`.
- **Montos**: celdas numéricas o texto «₡ 1,500.50», «1.500,50», «1500,5»; negativo o texto = error del archivo
  (se lista la celda). Vacío o 0 = sin promesa. Redondeo a 2 decimales.
- **Fila sin montos** y **repetida** se ignoran (cuentan como omitidas). Una fila cuyos montos estaban solo en
  columnas «No importar» también cuenta como omitida.
- **Persona nueva** del Excel: nombre limpio, activa, y su clase solo si existe una clase con ese nombre (nunca
  se cambia la clase de una persona existente).
- **Alta a mano**: replica exactamente `PersonaController::quickStore` (mismos campos y la misma acción de
  auditoría «Alta rápida de persona», con `desde: Promesas futuras`). No se llamó al controlador ajeno. Si ya hay
  alguien con el mismo nombre normalizado, avisa y pide marcar «Es otra persona».
- **Editar a mano** reemplaza las promesas programadas de esa persona en el corte (una fila por promesa; si
  tenía dos en la misma categoría se ven las dos). Guardar sin montos no borra: para eso está «Quitar del corte».
- **Eliminar corte** borra sus programadas y solo las categorías de `categorias_nuevas` que siguen inactivas.
- **Corte activado**: todo lo que escribe redirige al detalle con aviso; el detalle se ve en solo lectura.
- **Estado en la lista**: Programado / Vigente (el activado más reciente) / Anterior.
- El detalle muestra como columnas solo las categorías con alguna promesa en el corte.
- Auditoría: «Corte de promesas creado/editado/eliminado», «Persona agregada a un corte de promesas»,
  «Promesas programadas editadas», «Persona quitada de un corte de promesas», «Promesas futuras importadas de
  Excel» (con archivo, frecuencia, moneda y el resultado) y «Alta rápida de persona».

## Pruebas (20, todas verdes)
Permisos por rol (admin 200; tesorero, asistente y miembro 403); lista vacía; validaciones (mes pasado o actual,
nombre vacío, mes repetido); editar y bloqueo de un corte activado; eliminar con CONFIRMAR (borra programadas y la
categoría inactiva, no toca `promesas`); alta / edición / baja manual con decimales y categoría no válida (diezmo);
alta nueva y aviso de nombre repetido; corte de otra iglesia 404; menú y buscador solo admin; «?» de cada
pantalla abre `/manual/promesas#promesas-futuras`; plantilla (títulos exactos + Instrucciones + el lector la
entiende); revisión (calza sin tildes, parecido, no existe, ya está, sin montos, repetida) sin guardar nada;
aplicar sin decidir no guarda; aplicar crea categoría inactiva anotada en el corte y fuera de `tenant_categories()`,
persona nueva con su clase, deja igual a quien ya estaba, no toca `promesas` ni `compromisos`; reimportar no
agrega nada; columna a categoría existente / no importar y «buscar a alguien»; errores de formato (encabezado,
monto no numérico, categoría repetida, sin categorías); corte activado no importa; 150 filas rápido; **los
reportes existentes (Reporte de promesas, Cumplimiento, Dashboard de promesas, Reporte General PDF, Personas)
dan exactamente lo mismo, byte a byte, antes y después de importar**.

## Suite completa
1.000 pruebas, 5.835 aserciones: 14 fallos, todos de Breeze (tests/Feature/Auth/*, ProfileTest, ExampleTest), la línea base. Lint de Blade vacío.

## Pendientes y riesgos
- **Reimportar con filas de nombre parecido** (resuelto en el ajuste posterior): si una candidata ya está en el
  corte, queda elegida por defecto (la más parecida) con el aviso «Ya está en este corte: se deja como está», y
  Aplicar la cuenta como «ya estaba» sin escribir nada. Las parecidas sin candidata en el corte siguen sin opción
  por defecto.
- **«Campamento» vs «CAMPA»**: con el Excel real, la columna Campamento NO calza sola con la categoría CAMPA y
  por defecto se propondría crear una categoría nueva. Al cargarlo con David hay que elegir «Usar «CAMPA»» en esa
  columna. (No se agregó un emparejamiento por prefijo para no adivinar.)
- La revisión vive en la caché de Laravel (`CACHE_STORE=database` por defecto y en `.env.example`: sirve; la
  columna `value` de `cache` aguanta de sobra 2.000 filas). Si en Hostinger estuviera en `array`, no funcionaría.
- Las acciones del encabezado del detalle (4 botones) se desplazan de lado en 390 px dentro de su barra; la
  página no tiene scroll horizontal.
- No hay capturas del manual para estas pantallas (`tools/manual/capturas.json` es de G).

## Notas para el manual
Ya escritas en `05-promesas.md` → «Promesas futuras». Botones con su texto exacto: **+ Nuevo corte**,
**Descargar plantilla**, **Ver**, **Editar**, **Crear corte**, **Guardar cambios**, **Eliminar corte…**,
**Eliminar corte**, **+ Agregar persona**, **Importar Excel**, **Editar corte**, **← Todos los cortes**,
**Buscar**, **Quitar**, **Elegir** / **Ya está: editar**, **Dar de alta y seguir**, **Guardar**,
**Quitar del corte**, **Subir y revisar**, **Aplicar**, **Descartar archivo**. Estados de la revisión: **Calza**,
**Ya está en este corte: se deja como está**, **Nombre parecido**, **No existe**, **Sin montos: se ignora**,
**Repetida en el archivo: se toma la primera**.

## Cambios propuestos a archivos de otros equipos
Ninguno.
