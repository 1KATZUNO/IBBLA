# IBBLA · Sistema visual (UI)

Guia para quien construya o cambie una pantalla. Referencia: HubSpot y Salesforce (limpio, denso pero
ordenado, acciones a la derecha, filtros arriba). Lo que esta aqui ya esta en el codigo: los nombres de
clases y componentes son los reales.

Responsable del layout y de este sistema: equipo de UI (rama `feat/ui-upbar-manual`). **Nadie mas toca
`resources/views/layouts/`**; si necesitas algo del layout (un enlace de menu, una seccion nueva), pidelo
o agregalo en las listas que se indican mas abajo.

## 1. Principios

1. **Una pantalla, un camino.** Titulo y ayuda arriba, filtros debajo, datos al centro, la accion principal
   a la derecha del titulo. Nada de informacion de otros contextos.
2. **El color significa algo.** El color de la iglesia es para acciones y foco; verde / ambar / rojo / azul
   solo para estados. Nada decorativo en reportes.
3. **Tocar sin fallar.** Todo control mide al menos 44 px. Las acciones principales de un formulario largo
   (Guardar) siempre se ven (`.action-bar`).
4. **Lo que no puedes usar no estorba.** Un enlace que daria 403 no se muestra (el menu y el buscador usan la
   misma regla de permisos que la ruta). Un boton que existe pero no se puede usar ahora va deshabilitado con
   su porque (tooltip).
5. **Todo explicado.** Cada pantalla tiene su boton `?` que abre su seccion del manual (ver seccion 7).

## 2. Tokens

### 2.1 Color

| Token | Tailwind | Uso |
|---|---|---|
| Marca (tema de la iglesia) | `brand-50 ... brand-900` (`bg-brand-600`, `text-brand-700`, `ring-brand-500`) | Boton primario, enlaces, foco, estado activo del menu, barra superior (`brand-900`) y menu (`brand-800`). Cambia con el tema de cada iglesia (`Tenant::colors`). |
| Exito | `success-*` / `green-*` | Al dia, abierto, activo, enviado, guardado |
| Aviso | `warning-*` / `amber-*` | Pendiente, por revisar |
| Peligro | `danger-*` / `red-*` | Atrasado, inactivo, eliminar |
| Informacion | `info-*` / `blue-*` | Mensajes informativos |
| Neutros | `gray-50 ... gray-900` | Fondo de pagina `gray-50`, tarjetas blancas, bordes `gray-200`, texto `gray-900`, secundario `gray-500` |

Como funciona el tema: el layout escribe `--t-50 ... --t-900` y `--t-50-rgb ... --t-900-rgb` en `:root` a
partir de `Tenant::colors` (`App\Support\Tema`). `tailwind.config.js` define `brand` con esas variables, asi
que `bg-brand-600/50` (con opacidad) tambien funciona. Las paginas sin layout (login, errores) usan el azul
por defecto que trae `resources/css/app.css`.

Nunca escribas `blue-600` para una accion principal: usa `brand-600` (o `btn-primary`).

### 2.2 Tipografia

Fuente **Inter**. Escala (Tailwind):

| Uso | Clases |
|---|---|
| Titulo de pagina | `.page-title` (`text-xl sm:text-2xl font-semibold tracking-tight`) |
| Titulo de seccion / tarjeta | `.section-title`, `.card-title` (`text-base font-semibold`) |
| Texto de cuerpo y formularios | `text-base` (16 px) |
| Tablas y texto denso | `text-sm` (14 px) |
| Encabezados de tabla, ayudas, notas | `text-xs` (12 px) |
| Cifras | `tabular-nums` (digitos de ancho fijo); en tablas `.col-num` las alinea a la derecha |

### 2.3 Espaciado, radios y sombras

- Base de 4 px. Margen de pagina: `p-4 sm:p-6 xl:p-8` (ya lo pone el layout). Separacion entre bloques:
  `space-y-6`. Padding de tarjeta: `p-4 sm:p-6`. Entre botones: `gap-2` / `gap-3`.
- Radios: controles `rounded-lg` (8 px), tarjetas `rounded-xl` (12 px), badges `rounded-full`.
- Sombras: `shadow-card` (tarjeta, casi imperceptible), `shadow-card-hover` (tarjeta clicable),
  `shadow-popover` (menus y buscador), `shadow-upbar`.

### 2.4 Tamanos y alturas

- Control tactil minimo: **44 px** (`min-h-[44px]`; `.btn`, `.btn-icon`, `.btn-row`, `.input-primary`, `.tab`).
- Barra superior: `--upbar-h` = 3.5 rem (56 px), `h-upbar`.
- Pantalla completa: `h-screen h-dvh` (100dvh con 100vh de respaldo). Nunca solo `100vh`.
- `z-index`: contenido < `z-20` (`.action-bar`) < barra superior `z-40` < cajon del menu y popovers `z-50`
  < overlay de cierre de sesion `z-[80]`.

## 3. Estructura de la pagina (`layouts/admin.blade.php`)

```
+--------------------------------------------------------------------+
| ☰ [logo] IBBSC     [ Buscar personas, cultos...   / ]   Manual  A ▾  Salir |  <- barra superior fija (56 px)
+-----------+--------------------------------------------------------+
| Menu      |  Titulo de la pagina                      [accion] [? Ayuda] |
| lateral   |  descripcion o fecha                                         |
| (por rol) |  [ avisos del sistema ]                                      |
|           |  contenido (scroll solo aqui)                                |
+-----------+--------------------------------------------------------+
```

- **Barra superior** (`layouts/partials/upbar`): boton de menu, logo y siglas, **buscador global**, **Manual**,
  usuario y rol (menu con *Mi perfil*, *Mi cuenta*, *Manual*) y **Salir**. En 768 px la marca se acorta y los
  botones quedan solo con icono; en 390 px el buscador se abre desde la lupa a pantalla completa.
- **Menu lateral** (`layouts/partials/sidebar`): fijo desde 1024 px (se oculta con el boton de menu y se
  recuerda); cajon encima del contenido en tablet vertical y celular (Esc lo cierra).
- **Encabezado de pagina** (`layouts/partials/encabezado`): lo llena cada pantalla con secciones de Blade.
- **Avisos** (`layouts/partials/avisos`): `success`, `error`, `warning`, `info` de la sesion y errores de
  validacion, siempre bajo el encabezado.
- El scroll es solo de `<main>`: la barra superior no se mueve, y `scrollbar-gutter: stable` evita que el
  contenido salte al pasar de una pantalla corta a una larga.
- El modo App movil (`?mobile=1`, WebView) conserva su cabecera de 48 px y la barra inferior.

### Secciones que usa cada pantalla

```blade
@extends('layouts.admin')

@section('title', tenant_title('Cultos'))          {{-- opcional --}}
@section('page-title', 'Gestion de Cultos')         {{-- obligatorio: titulo (h1) --}}
@section('page-subtitle', 'Crea el culto antes de registrar sus sobres.')   {{-- opcional (por omision: la fecha) --}}
@section('page-actions')                            {{-- opcional: acciones a la derecha del titulo --}}
    <a href="{{ route('cultos.create') }}" class="btn-primary">+ Nuevo culto</a>
@endsection
@section('sin-encabezado', '1')                     {{-- opcional: la pantalla trae su propio encabezado --}}

@section('content')
    ...
@endsection
```

Pantallas con `<x-app-layout>` (perfil de usuario de Breeze) usan el mismo marco: `layouts/app.blade.php`
solo reenvia al layout principal.

### Menu lateral y buscador: una sola lista

`App\Support\Navegacion\Menu` define las pantallas del menu, con su ruta, icono, regla de visibilidad y
palabras clave. La usan el menu lateral y el buscador global (grupos *Pantallas* y *Reportes*), asi que
una pantalla aparece en los dos con la misma regla de permisos. Una entrada cuya ruta no existe no aparece
(no hace falta `Route::has`). Para agregar una pantalla:

1. En `Menu::definicion()`, suma una fila con `ruta`, etiqueta, icono (`App\Support\Iconos`), patron de
   rutas activas (`'recuento.*|asistencia.index'`), regla de visibilidad y palabras clave.
2. Si solo debe poder encontrarse con el buscador (no va en el menu), sumala en `Menu::extras()`.
3. Agrega o ajusta su pagina del manual (seccion 7).

## 4. Componentes

Todas las clases estan en `resources/css/app.css`. Un componente Blade equivalente existe donde la
estructura lo justifica (`resources/views/components/ui`).

### Botones

| Clase | Uso | Ejemplo |
|---|---|---|
| `btn-primary` | La accion principal de la pantalla (una sola) | `+ Nuevo culto`, `Guardar` |
| `btn-secondary` | Cancelar, volver, filtros, descargas | `Cancelar`, `PDF` |
| `btn-success` | Registrar dinero o confirmar algo positivo | `Guardar sobre` |
| `btn-warning` | Cerrar o bloquear un culto o una asistencia | `Cerrar culto` |
| `btn-danger` / `btn-danger-soft` | Eliminar o acciones destructivas | `Eliminar` |
| `btn-ghost` | Accion terciaria sin caja | `Limpiar filtros` |
| `btn-icon` | Solo icono (acciones de fila, cerrar) | con `aria-label` |
| `btn-row` | Accion de fila con texto | `Editar`, `Ver` |

Todos de 44 px. Un boton deshabilitado usa el atributo `disabled` (opacidad y cursor automaticos) y un
`title` que explica por que.

### Tarjeta y encabezado de pagina

```blade
<div class="card">
    <div class="card-header"><h2 class="card-title">Resumen</h2> <a class="btn-secondary">PDF</a></div>
    <div class="card-body">...</div>
    <div class="card-footer">...</div>
</div>
```

`glass-card` es un alias antiguo de `card`. `page-header`, `page-title`, `page-subtitle` y `page-actions`
los pone el layout.

### Tarjeta de resumen (KPI)

```blade
<div class="summary-grid">
    <x-ui.resumen etiqueta="Ingresos del mes" valor="₡1.250.000" nota="vs. septiembre: +10 %" />
</div>
```

`stat-card` (dashboards) lleva borde lateral con el color de la iglesia.

### Barra de filtros

Arriba de la tabla; los controles de 44 px; los botones de accion a la derecha (`.filter-actions`):

```blade
<form method="GET" class="filter-bar">
    <div class="filter-field"><label class="filter-label" for="q">Buscar</label><input id="q" name="q" class="input-primary"></div>
    <div class="filter-actions"><button class="btn-primary">Filtrar</button><a class="btn-ghost">Limpiar</a></div>
</form>
```

### Tabla de datos

Dentro de una tarjeta y de `.table-scroll` (el scroll horizontal queda dentro de la tarjeta, nunca en la
pagina). Cifras a la derecha, acciones a la derecha:

```blade
<div class="card overflow-hidden"><div class="table-scroll">
<table class="data-table">
    <thead><tr><th>Fecha</th><th class="col-num">Total</th><th class="col-actions">Acciones</th></tr></thead>
    <tbody>
        <tr><td>12/10/2026</td><td class="col-num">₡1.250.000</td><td class="col-actions"><a class="btn-row">Ver</a></td></tr>
    </tbody>
</table>
</div></div>
```

Columnas secundarias se ocultan en pantallas angostas (`hidden xl:table-cell`) para que las acciones
queden siempre a la vista en 768 y 1024 px.

### Badge de estado

`<x-ui.badge tipo="success" punto>Abierto</x-ui.badge>` o directamente `badge-success`, `badge-warning`,
`badge-danger`, `badge-info`, `badge-neutral`, `badge-brand` (+ `badge-dot`). Significados: success = al
dia, abierto, activo, enviado; warning = pendiente; danger = atrasado, inactivo; info = informativo;
neutral = sin estado; brand = categoria o rol.

### Estado vacio

```blade
<x-ui.vacio titulo="No hay cultos registrados" texto="Crea el primero para empezar a registrar sobres.">
    <x-slot name="acciones"><a href="{{ route('cultos.create') }}" class="btn-primary">Crear culto</a></x-slot>
</x-ui.vacio>
```

Siempre dice que paso y que hacer; si hay un filtro activo, ofrece quitarlo.

### Formularios

- Etiqueta arriba (`.field-label`), ayuda (`.field-hint`), error (`.field-error`), campo `.input-primary`
  (44 px, foco con el color de la iglesia).
- Formularios largos: secciones (`.form-section` + `.form-section-title`) y **Guardar siempre visible**
  con `.action-bar` como ultimo hijo del `<form>`.
- Teclado numerico en movil para montos (`inputmode="decimal"`).

### Avisos, pestanas, teclas e iconos

- `alert-success|error|warning|info` (los pinta el layout con la sesion).
- `.tabs` + `.tab` / `.tab-active`: pestanas tipo segmento (de 44 px).
- `.kbd`: tecla en pantalla (`<kbd class="kbd">/</kbd>`).
- Iconos: `<x-icono nombre="lupa" class="h-5 w-5" />`; el catalogo esta en `App\Support\Iconos`.

## 5. Como se ve cada tamano

| Ancho | Barra superior | Menu | Contenido |
|---|---|---|---|
| >= 1280 | Marca con nombre completo, buscador, *Manual*, usuario con rol, *Salir* | Fijo, 288 px | Tablas con todas las columnas |
| 1024 - 1279 | Marca con siglas, buscador, *Manual*, usuario | Fijo, 272 px (ocultable) | Columnas secundarias ocultas |
| 768 - 1023 | Siglas, buscador, iconos | Cajon | Una columna, tablas con scroll interno |
| < 768 | Menu, logo, lupa (buscador a pantalla completa), manual, usuario | Cajon | Una columna |

## 6. Buscador global (`GET /buscar`)

Escribe en la barra superior o presiona `/` (o `Ctrl+K`) desde cualquier pantalla. Busca por palabras
(todas deben aparecer, sin importar tildes ni mayusculas) en:

| Grupo | Que busca | Quien lo ve |
|---|---|---|
| Personas | Nombre, PIN o telefono | Administracion |
| Cultos | Por fecha (`12/10`, `12/10/2026`, `2026-10-12`, `octubre`, `hoy`) o tipo (`domingo`) | Recuento (abre el recuento) y asistencia (abre la asistencia) |
| Pantallas y Reportes | Las del menu y otras (`Menu::extras()`), por nombre y palabras clave | Solo las que el rol puede abrir |
| Manual de usuario | Temas y secciones del manual | Todos |

Teclado: `↑` `↓` mueven, `Enter` abre, `Esc` cierra (otra vez, borra). El ultimo elemento es *Ver todos los
resultados* (`/buscar?q=...`, pagina completa que tambien funciona sin JavaScript). La barra pide
`/buscar?q=...` con `Accept: application/json`; la respuesta trae `grupos[]` con `items[]` (`titulo`,
`detalle`, `url`, `icono`, `trazo`). `?solo=manual` limita a temas del manual. Minimo 2 letras.
Implementacion: `App\Services\Buscador\BuscadorGlobal`.

## 7. Manual de usuario (`/manual`)

Una pagina por modulo en `resources/views/manual/paginas/NN-modulo.md` (Markdown). El indice (`/manual`),
el buscador del manual, el boton `?` de cada pantalla y el PDF salen de esos archivos: **para agregar una
seccion basta con crear o editar un `.md`**.

### Formato

```markdown
---
titulo: Personas y compromisos
orden: 40                      # (opcional: sale del prefijo del archivo)
icono: personas                # nombre de App\Support\Iconos
resumen: Una linea para el indice y el buscador.
roles: admin                   # todos, admin, tesorero, asistente, servidor, miembro
rutas: personas.*, compromisos.*   # pantallas cuyo boton "?" abre esta pagina (admite *)
ancla: seccion-inicial         # opcional: el "?" salta a esa seccion
palabras: miembros, hermanos   # sinonimos para el buscador
---

## Para que sirve
...
## La pantalla
![Lista de personas](img:personas-lista.jpg "Filtros (1) y acciones (2)")

| Boton o control | Que hace | Quien puede usarlo | Que pasa despues |
|---|---|---|---|
| **1. Nueva persona** | Abre el formulario. | Administrador | Al guardar vuelves a la lista con un mensaje. |

## Paso a paso: agregar una persona
1. ...
```

- Las secciones son los titulos `##` y `###` (el id se genera solo, sin tildes).
- `![texto](img:archivo.jpg "pie")` toma `public/images/manual/archivo.jpg`; si el archivo no existe, la
  imagen se omite (el texto sigue leyendose).
- `[texto](route:personas.index)` enlaza una pantalla por el nombre de su ruta.
- Avisos: `> **Consejo:** ...` (verde), `> **Importante:** ...` / `> **Cuidado:** ...` (ambar), cualquier otro
  `> ...` (azul).
- Plantilla recomendada por modulo: *Para que sirve · Quien puede usarlo · Como llegar · La pantalla
  (captura con numeros + tabla boton / que hace / quien / que pasa despues) · Paso a paso · Preguntas
  frecuentes*.
- Archivos que empiezan con `_` no se publican.

### Capturas con numeros

Las capturas estan en `public/images/manual/`. Se generan con `tools/manual/capturar.mjs` (Chrome headless
+ `puppeteer-core`): lee `tools/manual/capturas.json` (ruta, rol, tamano y los numeros rojos a dibujar sobre
cada boton) y vuelve a sacar todas cuando cambia la interfaz (ver el encabezado del script). Hay que repetirlas
tras integrar ramas que cambian pantallas (por ejemplo Recuento): `cd tools/manual && node capturar.mjs`.
Usuarios de las capturas: los de `UsuariosDemoSeeder` y `AdminUserSeeder` (ver `capturas.json`).

### PDF

`/manual/pdf` (manual completo) y `/manual/{pagina}/pdf`. DomPDF con DejaVu Sans (trae el simbolo ₡). El PDF
se guarda en `storage/app/manual` y se reutiliza mientras no cambien las paginas, las capturas ni la
plantilla; el primero en pedirlo espera unos segundos. Para dejarlo listo al desplegar:
`php artisan manual:pdf`.

## 8. Idioma (español) y cambio del .env en produccion

Toda la app esta en español. Los textos de Laravel (validaciones, login, paginacion, recuperar contrasena)
viven en `lang/es/{validation,auth,pagination,passwords}.php` y los de Breeze en `lang/es.json`.

`config/app.php` deja `'locale' => 'es'` **fijo**: ya no depende de `APP_LOCALE`. Asi, aunque un `.env` traiga
`APP_LOCALE=en` (el de IBBSC en produccion y el `.env.example` heredado lo traen), el login y las validaciones
salen en español. Aun asi, lo correcto en cada servidor es dejar el `.env` coherente:

```
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_ES
```

Pasos en produccion (Hostinger): editar el `.env` del servidor (no se versiona), correr
`php artisan config:clear && php artisan config:cache` y comprobar `/login` (debe decir «Iniciar sesión» y
«Correo electrónico»). `.env.example` lo mantiene el equipo de entrega; este cambio solo hay que reflejarlo
alli. Prueba automatica: `tests/Feature/LoginEspanolTest.php`.

## 9. Checklist para una pantalla nueva

- [ ] `@section('page-title')` y, si hay accion principal, `@section('page-actions')`.
- [ ] Tabla dentro de `.card` + `.table-scroll`, con `.data-table`; acciones a la derecha de 44 px.
- [ ] Filtros arriba (`.filter-bar`), estado vacio con `<x-ui.vacio>`.
- [ ] Estados con `badge-*` (nunca colores decorativos).
- [ ] Formulario largo: secciones y `.action-bar` con Guardar.
- [ ] Probada en 1366, 1024, 768 y 390 px sin scroll horizontal de la pagina.
- [ ] Entrada en `Menu::definicion()` / `extras()` y pagina o seccion del manual con `rutas:` para el `?`.
- [ ] Lint de Blade: `grep -rnE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' resources/views` vacio.
