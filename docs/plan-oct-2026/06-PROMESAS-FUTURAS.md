# Promesas futuras (corte de promesas) — especificación

Pedido de David el 24/11/2026, con el Excel de las promesas 2027 de La Aurora en la mano. Lo construyen dos
equipos en paralelo sobre la rama base `feat/promesas-futuras` (sale de `integracion/oct-2026`):
**Equipo M** (motor y activación) y **Equipo P** (pantallas e importador).

## 1. Lo que pidió el cliente (reglas de negocio)

1. **Borrón y cuenta nueva.** Las promesas nuevas son un corte: al entrar en vigor, TODAS las promesas
   anteriores de la iglesia dejan de regir. Quien no viene en el corte queda sin promesa; quien es nuevo, entra.
2. **Entran en vigor en una fecha** (las de 2027, el 1 de enero). Se cargan desde ahora en un apartado
   **«Promesas futuras»**, donde uno crea el corte y le pone la fecha de inicio. Hasta ese día nada cambia.
3. **Se agregan personas a mano y por Excel.** En un lote de Excel, si la persona **ya está** en ese corte,
   **no se sobrescribe ni se duplica: se deja lo que ya había**.
4. **Se pueden editar** (y quitar) las promesas programadas.
5. **Si traen promesas nuevas (categorías nuevas), son nuevas y deben aparecer en los sobres.** Los títulos de
   las columnas del Excel son las categorías; un título que no existe en la iglesia es una categoría nueva.
6. **Plantilla** descargable de cómo debe venir el Excel: la primera fila es para los títulos; las dos primeras
   columnas son **Miembro** (o **Nombre**) y **Clase**; de la tercera en adelante, una columna por categoría.

Regla que agrega el líder (innegociable): **la historia no se toca.** Un reporte de meses anteriores al corte
debe dar exactamente lo mismo antes y después de que el corte entre en vigor.

Decisiones del líder:
- La fecha de inicio es siempre el **día 1 de un mes futuro** (el motor trabaja por meses completos).
- Solo el **Administrador** usa el apartado (igual que Gestionar Personas en `docs/ROLES.md`). Todo queda en auditoría.
- Una categoría nueva nace **inactiva** (`tenant_categories.activa = false`) y queda anotada en el corte; se
  activa cuando el corte entra en vigor. Así no aparece en sobres ni reportes antes de tiempo.
- Las categorías viejas que ya no tengan promesas en el corte nuevo NO se desactivan solas.
- Un corte **sin promesas nunca se activa solo** (evita dejar a toda la iglesia sin promesas por error).

## 2. Modelo de datos (ya creado en la rama base; no lo cambien sin avisar al líder)

Migración `2026_11_25_000001_create_cortes_promesas_tables.php` y modelos:

- `cortes_promesas` → `App\Models\CortePromesas` (usa `BelongsToTenant`): `tenant_id`, `nombre`, `inicia_el`
  (date, día 1), `activado_at` (null = programado), `creado_por`, `activado_por`, `categorias_nuevas` (json: ids de
  `tenant_categories` creadas inactivas para este corte). Único por (`tenant_id`, `inicia_el`). Ya trae
  `programadas()`, `scopeProgramados()`, `scopeActivados()`, `estaProgramado()`, `yaDebeRegir()`, `diasParaIniciar()`.
- `promesas_programadas` → `App\Models\PromesaProgramada`: `corte_id`, `persona_id`, `categoria` (slug), `monto`,
  `moneda`, `frecuencia`. Mismos campos que `promesas`. **Ningún reporte ni pantalla existente lee esta tabla.**
- `promesas_historial` → `App\Models\PromesaHistorial` (usa `BelongsToTenant`): `tenant_id`, `corte_id` (corte al
  que pertenecían; null = las de antes del primer corte), `persona_id`, `categoria`, `monto`, `moneda`,
  `frecuencia`, `vigente_desde` (null = desde siempre), `vigente_hasta` (víspera del corte que las reemplazó).
- La tabla `promesas` **sigue siendo «lo que rige hoy»**: no cambia de forma ni de significado. Todo el código
  que hoy la lee (ficha de la persona, Servidores, informes) sigue viendo solo las vigentes.

## 3. Equipo M — activación y motor

### 3.1 Activación: `App\Services\Promesas\ActivadorCortes`
- `activar(CortePromesas $corte, ?User $por = null): void`, en una transacción e idempotente:
  1. Copia a `promesas_historial` las `promesas` actuales de las personas de esa iglesia, con
     `vigente_hasta = inicia_el - 1 día`, `vigente_desde = inicia_el` del corte activado anterior (o null) y
     `corte_id` = ese corte anterior (o null).
  2. Borra esas `promesas` y crea las del corte a partir de `promesas_programadas` (que se conservan como registro).
  3. Activa las categorías de `categorias_nuevas`.
  4. Marca `activado_at` / `activado_por`.
  5. Tras el commit: sincroniza `compromisos` desde el mes de `inicia_el` hasta el mes actual
     (`SincronizadorCompromisos`) y deja una entrada de auditoría.
- `activarPendientes(?int $tenantId = null): int`: activa, del más antiguo al más nuevo, los cortes programados
  cuya fecha ya llegó **y que tienen al menos una promesa programada**. Devuelve cuántos activó.
- Disparadores: comando `promesas:activar-cortes` en el planificador cada diez minutos, y un disparo perezoso
  en las peticiones web autenticadas (como mucho una vez por minuto, con candado en caché) para no depender solo
  del cron. En consola no hay iglesia enlazada: usar `withoutGlobalScopes()` donde haga falta.

### 3.2 Motor: qué promesas rigen cada mes
- Una sola clase (por ejemplo `App\Services\Reportes\PromesasPorPeriodo`) decide qué promesas rigen cada mes:
  el mes M usa las filas de `promesas_historial` cuyo tramo [`vigente_desde`, `vigente_hasta`] lo cubre; si
  ninguna lo cubre (meses desde el último corte activado, o siempre si no hay historial), usa `promesas`.
- La usan `MotorReportes::leerPromesas`, `CuentaPersona` (lo esperado de cada mes sale de las promesas que regían
  ESE mes), `CumplimientoPromesasService` y `CandidatasSelector` («con promesa» = tuvo alguna promesa que rigió en
  algún mes del periodo) y `SincronizadorCompromisos`.
- Lo que se muestra como «la promesa» de una persona en un reporte (texto y columnas) es el juego que rige el
  **último mes del periodo** del reporte.
- Sin historial, todo debe dar exactamente lo mismo que hoy (ni una consulta distinta en el camino feliz si se puede).

### 3.3 Pruebas de aceptación del Equipo M
1. Con un corte **programado** (con promesas y una categoría nueva inactiva), todos los números de los reportes
   existentes son idénticos a no tenerlo.
2. Al **activarlo** (fijar «hoy» en la fecha de inicio):
   - un periodo totalmente anterior al corte da, persona por persona y mes por mes, lo mismo que antes de activar;
   - un periodo posterior usa las promesas nuevas: quien no venía en el corte no tiene esperado ni figura «con
     promesa»; quien es nuevo sí;
   - un periodo que cruza el corte suma cada mes con las promesas que regían ese mes;
   - `promesas` tiene el juego nuevo, `promesas_historial` el viejo con su vigencia, y la categoría nueva queda
     activa y sale en `tenant_categories()`.
3. Activar dos veces no cambia nada; un corte vacío no se activa; dos cortes seguidos (2027 y 2028) encadenan
   bien los tramos.
4. `compromisos` queda coherente antes y después del corte. Comando, entrada del planificador y disparo perezoso probados.

## 4. Equipo P — apartado «Promesas futuras», importador y plantilla

### 4.1 Pantallas (solo Administrador; diseño según `docs/UI.md`)
- **Lista de cortes**: nombre, fecha de inicio, estado (**Programado**: «entra en vigor el dd/mm/aaaa, faltan N
  días»; **Vigente** desde…; **Anterior**), personas, promesas y totales por categoría. Botón **Nuevo corte**.
- **Nuevo / editar corte**: nombre y mes de inicio (día 1 de un mes futuro; único por iglesia). Se edita y se
  elimina solo mientras está programado; eliminar pide escribir CONFIRMAR y borra sus promesas programadas y las
  categorías inactivas que se crearon para él.
- **Detalle del corte**: una fila por persona con su clase y lo prometido por categoría, totales, buscador, y las
  acciones **Agregar persona**, **Importar Excel**, **Descargar plantilla**, editar y quitar por fila. Si el corte
  no tiene promesas, un aviso claro de que no entrará en vigor. Ya activado se muestra en solo lectura.
- **Agregar / editar persona**: buscar una persona existente (nombre, PIN o teléfono) o dar de alta una nueva
  (reusar la lógica del alta rápida de `PersonaController`), y un monto por categoría con su frecuencia (por
  defecto semanal) y moneda (por defecto colones). Si la persona ya está en el corte, se va a editar.
- Entradas en el menú lateral (grupo Administración) y en el buscador global, solo para el Administrador.

### 4.2 Excel
- **Formato**: primera hoja; fila 1 = títulos; columna A **Miembro** o **Nombre**; columna B **Clase**; de la C en
  adelante, una columna por categoría con su título. Una persona por fila. Monto vacío o 0 = sin promesa en esa
  categoría. Al subirlo se elige la **frecuencia** de los montos (por defecto semanal) y la **moneda** (por
  defecto colones). Los montos se guardan con dos decimales. Máximo 2 MB y 2.000 filas.
- **Plantilla descargable** (.xlsx): fila 1 con Miembro, Clase y las categorías de promesa activas de la iglesia
  en su orden, más una hoja «Instrucciones» que explique lo anterior y que una columna con título nuevo crea una
  categoría nueva.
- **Revisión antes de aplicar** (nada se guarda hasta confirmar):
  - *Categorías*: cada título se empareja con la categoría de igual nombre (sin distinguir tildes ni
    mayúsculas). Para un título que no existe se elige: **crear la categoría nueva** (por defecto), usar una
    categoría existente, o no importar esa columna.
  - *Personas*: cada fila queda en uno de estos estados: **Calza** (nombre igual, sin tildes ni mayúsculas, a una
    sola persona de la iglesia); **Ya está en este corte: se deja como está**; **Nombre parecido** (hay que
    elegir entre las candidatas, crear persona nueva u omitir; sin opción por defecto); **No existe** (por
    defecto, crear persona nueva; se puede omitir o buscar a alguien); **Sin montos** (se ignora); **Repetida en
    el archivo** (se toma la primera). **Aplicar** solo se habilita con todas las filas resueltas.
- **Aplicar**, en una transacción: crea las categorías nuevas **inactivas** (tipo `ambos`,
  `excluir_de_promesas = false`, slug único, al final del orden) y las anota en `categorias_nuevas` del corte;
  crea las personas nuevas (con su clase si existe una clase con ese nombre; nunca cambia la clase de una persona
  existente); inserta las promesas programadas **solo de quienes no estaban en el corte**. Resultado: cuántas
  personas se agregaron, cuántas ya estaban y se dejaron igual, cuántas se omitieron y qué categorías se crearon.
  Volver a subir el mismo archivo no agrega nada.
- Todo queda en auditoría (crear, editar y eliminar corte; importar; agregar, editar y quitar persona).

### 4.3 Pruebas de aceptación del Equipo P
Permisos por rol (admin entra; tesorero, asistente y miembro 403); validaciones del corte; alta, edición y baja
manual; plantilla con el encabezado correcto; importación: emparejar categorías, crear categoría nueva inactiva,
emparejar personas (exacto, parecido, inexistente), **no sobrescribir ni duplicar a quien ya está**, reimportar
sin cambios, decimales, errores de formato (encabezado malo, monto no numérico, nombres repetidos); auditoría;
los reportes existentes dan lo mismo antes y después de importar; página del manual con su «?» (hay un test que
exige que toda ruta con pantalla esté declarada en una página del manual); menú y buscador solo para admin.

## 5. Contrato entre los dos equipos
- P escribe `cortes_promesas` (programados) y `promesas_programadas`; M los lee al activar. P no llama al
  activador ni toca el motor; M no toca pantallas.
- Archivos de M: `app/Services/Promesas/ActivadorCortes.php`, `app/Services/Reportes/**` (motor),
  `app/Console/Commands/**`, `routes/console.php`, el middleware del disparo perezoso y `bootstrap/app.php`,
  `app/Models/CortePromesas.php` y `PromesaHistorial.php`, sus tests.
- Archivos de P: `app/Http/Controllers/PromesasFuturasController.php` (y sus requests),
  `app/Services/Promesas/Importacion/**`, `resources/views/promesas-futuras/**`, `routes/web.php`,
  `app/Support/Navegacion/Menu.php`, `app/Models/PromesaProgramada.php`, el manual
  (`resources/views/manual/**`), `docs/ROLES.md`, sus tests.
- Nadie commitea `public/build/**` (lo recompila el líder).

## 6. Después de integrar (líder)
Suite completa, build, ensayo sobre la copia de producción (huella de cifras por persona idéntica con el corte
programado y, simulando la activación, idéntica para los meses anteriores), deploy con el orden de
`docs/DEPLOY-HOSTINGER.md` y carga del Excel real de 2027 junto con David (emparejar los nombres que no calzan).
