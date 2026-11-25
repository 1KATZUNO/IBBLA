#!/usr/bin/env bash
# =============================================================================
# IBBLA - deploy en Hostinger (se corre EN el servidor, por SSH)
#
#   cd /home/u598753224/domains/ibbla.eu/public_html
#   bash deploy/hostinger.sh --tag v2026.10.20            # desplegar un tag
#   bash deploy/hostinger.sh                              # pull --ff-only de main
#   bash deploy/hostinger.sh --dry-run --tag v2026.10.20  # solo muestra el plan
#
# Opciones:
#   --tag <tag>      Desplegar ese tag (checkout en HEAD desacoplado). Sin --tag: main.
#   --branch <rama>  Rama a actualizar con pull --ff-only (por defecto main).
#   --dry-run        No cambia nada: hace fetch, muestra lo que correria y lo que cambiaria.
#   --maintenance    Pone la app en mantenimiento (artisan down) mientras despliega.
#   --url <url>      URL base del chequeo final (por defecto APP_URL del .env, o https://ibbla.eu).
#   --skip-check     No hacer el chequeo final con curl.
#   --sin-respaldo   No respaldar la BD antes de migrar (solo con un respaldo hecho a mano).
#   --allow-path     Permitir correr fuera de .../domains/ibbla.eu/public_html (no recomendado).
#   --qa             Desplegar el AMBIENTE QA (qa.ibbla.eu) y no produccion. Ver docs/AMBIENTE-QA.md.
#                    Sin --branch ni --tag usa la rama integracion/oct-2026. Solo corre en
#                    .../domains/qa.ibbla.eu/public_html, con APP_ENV=qa y una BD distinta de la de
#                    produccion; chequea https://qa.ibbla.eu. Sin --qa el script no corre en la ruta de QA.
#   --manual-pdf     Deja listo el PDF del manual de usuario (php artisan manual:pdf, ~30 s) para que nadie lo
#                    espere al descargarlo. Con --qa se hace siempre; en produccion solo si se pide.
#   -h, --help       Esta ayuda.
#
# Variables de entorno opcionales: PHP_BIN (php), COMPOSER_BIN (composer).
#
# El script NO contiene credenciales: el .env vive solo en el servidor y no se toca.
# IMPORTANTE: ~/public_html (sin "domains/ibbla.eu") es OTRA app (musica.ibbla.eu).
# =============================================================================
set -uo pipefail

# Todo el trabajo va dentro de main(): bash lee la funcion completa antes de ejecutarla, asi que el
# git checkout/pull que reemplaza este mismo archivo a mitad de la corrida no la corrompe.
main() {

RUTA_ESPERADA_SUFIJO="domains/ibbla.eu/public_html"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

TAG=""
RAMA="main"
DRY_RUN=0
MANTENIMIENTO=0
URL_BASE=""
SALTAR_CHEQUEO=0
PERMITIR_RUTA=0
SIN_RESPALDO=0
MANUAL_PDF=0

QA=0
RAMA_EXPLICITA=0
RUTA_QA_SUFIJO="domains/qa.ibbla.eu/public_html"
RAMA_QA="integracion/oct-2026"
URL_QA="https://qa.ibbla.eu"

uso() { sed -n '2,29p' "$0" | sed 's/^# \{0,1\}//'; }

while [ $# -gt 0 ]; do
    case "$1" in
        --tag) TAG="${2:-}"; shift 2 ;;
        --branch) RAMA="${2:-}"; RAMA_EXPLICITA=1; shift 2 ;;
        --qa) QA=1; shift ;;
        --manual-pdf) MANUAL_PDF=1; shift ;;
        --dry-run) DRY_RUN=1; shift ;;
        --maintenance) MANTENIMIENTO=1; shift ;;
        --url) URL_BASE="${2:-}"; shift 2 ;;
        --skip-check) SALTAR_CHEQUEO=1; shift ;;
        --sin-respaldo) SIN_RESPALDO=1; shift ;;
        --allow-path) PERMITIR_RUTA=1; shift ;;
        -h|--help) uso; exit 0 ;;
        *) echo "Opcion desconocida: $1"; uso; exit 2 ;;
    esac
done

paso()  { echo; echo "==> $*"; }
info()  { echo "    $*"; }
falla() { echo; echo "FALLA: $*" >&2; }

# Ejecuta (o, en --dry-run, solo muestra) un comando que modifica algo.
ejecutar() {
    if [ "$DRY_RUN" -eq 1 ]; then
        echo "    [dry-run] $*"
        return 0
    fi
    echo "    \$ $*"
    "$@"
}

# --- Ubicacion: siempre la raiz del repo, y verificar que es la app correcta ---
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ" || exit 1

[ -f artisan ] || { falla "No hay 'artisan' en $RAIZ. No parece la app Laravel."; exit 1; }
[ -e .git ] || { falla "$RAIZ no es un clon de git."; exit 1; }
[ -f .env ] || { falla "Falta el .env en $RAIZ (se crea una sola vez a mano en el servidor)."; exit 1; }

if [ "$QA" -eq 1 ]; then
    # --- Ambiente QA: tres defensas para no tocar nunca produccion (docs/AMBIENTE-QA.md) ---
    case "$RAIZ" in
        *"$RUTA_QA_SUFIJO") ;;
        *)
            falla "--qa solo corre en una ruta que termine en $RUTA_QA_SUFIJO (esta es $RAIZ). --allow-path no aplica a QA."
            exit 1
            ;;
    esac

    env_de() { grep -E "^$2=" "$1" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r'; }

    APP_ENV_QA="$(env_de .env APP_ENV)"
    if [ "$APP_ENV_QA" != "qa" ]; then
        falla "El .env de $RAIZ dice APP_ENV='$APP_ENV_QA' y para --qa debe decir APP_ENV=qa."
        exit 1
    fi

    DB_QA="$(env_de .env DB_DATABASE)"
    if [ -z "$DB_QA" ]; then
        falla "No se pudo leer DB_DATABASE del .env de QA."
        exit 1
    fi
    # La ruta de produccion es la misma con el subdominio cambiado: si su .env existe, la BD no puede coincidir.
    RUTA_PROD="${RAIZ%"$RUTA_QA_SUFIJO"}$RUTA_ESPERADA_SUFIJO"
    if [ -f "$RUTA_PROD/.env" ]; then
        DB_PROD="$(env_de "$RUTA_PROD/.env" DB_DATABASE)"
        if [ -n "$DB_PROD" ] && [ "$DB_QA" = "$DB_PROD" ]; then
            falla "DB_DATABASE de QA ($DB_QA) es la misma que la de produccion ($RUTA_PROD/.env). QA necesita su propia base de datos."
            exit 1
        fi
    else
        case "$DB_QA" in
            *qa*|*QA*) ;;
            *)
                falla "No se encontro el .env de produccion ($RUTA_PROD/.env) para comparar y DB_DATABASE ('$DB_QA') no contiene 'qa'. Use una base cuyo nombre incluya 'qa'."
                exit 1
                ;;
        esac
    fi

    # Valores por defecto propios de QA (un --branch / --url explicito manda)
    [ "$RAMA_EXPLICITA" -eq 0 ] && RAMA="$RAMA_QA"
    [ -z "$URL_BASE" ] && URL_BASE="$URL_QA"
else
    case "$RAIZ" in
        *"$RUTA_QA_SUFIJO")
            falla "Esta ruta ($RAIZ) es la del ambiente QA: ahi se despliega con --qa (ni siquiera --allow-path lo permite sin --qa)."
            exit 1
            ;;
    esac
    case "$RAIZ" in
        *"$RUTA_ESPERADA_SUFIJO") ;;
        *)
            if [ "$PERMITIR_RUTA" -ne 1 ]; then
                falla "Esta ruta ($RAIZ) no termina en $RUTA_ESPERADA_SUFIJO."
                echo "     ~/public_html del mismo usuario es OTRA app (musica.ibbla.eu). Si de verdad es la correcta, use --allow-path." >&2
                exit 1
            fi
            ;;
    esac
fi

# --- Resumen del plan ---
paso "Deploy IBBLA"
[ "$QA" -eq 1 ] && info "AMBIENTE QA (APP_ENV=qa, BD $DB_QA)"
[ "$DRY_RUN" -eq 1 ] && info "MODO DRY-RUN: no se cambia nada (solo git fetch)."
info "Ruta:   $RAIZ"
if [ -n "$TAG" ]; then info "Objetivo: tag $TAG"; else info "Objetivo: rama $RAMA (pull --ff-only)"; fi

ANTES="$(git rev-parse HEAD)"
ANTES_DESC="$(git describe --tags --always 2>/dev/null || echo "$ANTES")"
info "Version actual: $ANTES_DESC ($(git rev-parse --short HEAD))"

# Cambios locales en archivos versionados bloquearian el checkout/pull.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    falla "Hay cambios locales en archivos versionados del servidor:"
    git status --short --untracked-files=no >&2
    echo "     Revise con 'git diff'. No se despliega encima de cambios sin commitear." >&2
    exit 1
fi

# --- 1. Traer codigo ---
paso "1/6 Traer el codigo"
# fetch solo lee del remoto: corre tambien en dry-run para que el plan sea real.
echo "    \$ git fetch --tags --prune origin"
git fetch --tags --prune origin || { falla "git fetch fallo."; exit 1; }

if [ -n "$TAG" ]; then
    git rev-parse -q --verify "refs/tags/$TAG" >/dev/null || { falla "El tag '$TAG' no existe en el remoto."; exit 1; }
    DESTINO="$(git rev-list -n1 "$TAG")"
else
    git rev-parse -q --verify "refs/remotes/origin/$RAMA" >/dev/null || { falla "La rama origin/$RAMA no existe."; exit 1; }
    DESTINO="$(git rev-parse "origin/$RAMA")"
fi

if [ "$ANTES" = "$DESTINO" ]; then
    info "Ya esta en esa version; se reejecutan migraciones y caches por si acaso."
else
    info "Commits que entran:"
    git --no-pager log --oneline "$ANTES..$DESTINO" 2>/dev/null | head -30 | sed 's/^/      /'
fi

if [ -n "$TAG" ]; then
    ejecutar git checkout --quiet "tags/$TAG" || { falla "git checkout del tag fallo."; exit 1; }
else
    ejecutar git checkout --quiet "$RAMA" || { falla "git checkout de $RAMA fallo."; exit 1; }
    ejecutar git pull --ff-only origin "$RAMA" || { falla "git pull --ff-only fallo (historia divergente?)."; exit 1; }
fi

# Mantenimiento: solo despues de tener el codigo, y se levanta siempre al salir.
if [ "$MANTENIMIENTO" -eq 1 ]; then
    paso "Modo mantenimiento"
    ejecutar "$PHP_BIN" artisan down --retry=60
    if [ "$DRY_RUN" -eq 0 ]; then trap '"$PHP_BIN" artisan up >/dev/null 2>&1' EXIT; fi
fi

# --- 2. Composer (solo si cambio composer.lock o no hay vendor) ---
paso "2/6 Dependencias (composer)"
if [ ! -d vendor ]; then
    info "No hay vendor/: se instala."
    CAMBIO_LOCK=1
elif ! git diff --quiet "$ANTES" "$DESTINO" -- composer.lock composer.json 2>/dev/null; then
    info "composer.lock/composer.json cambiaron."
    CAMBIO_LOCK=1
else
    info "composer.lock sin cambios: se omite composer install."
    CAMBIO_LOCK=0
fi
if [ "$CAMBIO_LOCK" -eq 1 ]; then
    ejecutar "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction || { falla "composer install fallo."; exit 1; }
fi

# --- Respaldo de la BD antes de migrar (QE-017): mysqldump comprimido en ~/respaldos-ibbla ---
paso "Respaldo de la base de datos"
if [ "$SIN_RESPALDO" -eq 1 ]; then
    info "Omitido (--sin-respaldo)."
else
    leer_env() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r'; }
    DB_HOST_ENV="$(leer_env DB_HOST)"; DB_PORT_ENV="$(leer_env DB_PORT)"; DB_NAME_ENV="$(leer_env DB_DATABASE)"
    DB_USER_ENV="$(leer_env DB_USERNAME)"; DB_PASS_ENV="$(leer_env DB_PASSWORD)"
    DIR_RESPALDOS="$HOME/respaldos-ibbla"
    ARCHIVO_RESPALDO="$DIR_RESPALDOS/${DB_NAME_ENV:-ibbla}_$(date +%Y%m%d_%H%M%S)_antes_de_${TAG:-$RAMA}.sql.gz"
    if [ -z "$DB_NAME_ENV" ] || [ -z "$DB_USER_ENV" ]; then
        falla "No se pudo leer DB_DATABASE / DB_USERNAME del .env, asi que no se puede respaldar. Use --sin-respaldo solo si ya tiene un respaldo a mano."
        exit 1
    fi
    OPCIONES_DUMP="--single-transaction --quick --no-tablespaces --routines --triggers"
    if mysqldump --help 2>/dev/null | grep -q -- '--column-statistics'; then OPCIONES_DUMP="$OPCIONES_DUMP --column-statistics=0"; fi
    if [ "$DRY_RUN" -eq 1 ]; then
        info "[dry-run] mysqldump $DB_NAME_ENV -> $ARCHIVO_RESPALDO"
    else
        mkdir -p "$DIR_RESPALDOS" && chmod 700 "$DIR_RESPALDOS"
        info "mysqldump $DB_NAME_ENV -> $ARCHIVO_RESPALDO"
        # La contrasena viaja por MYSQL_PWD (no queda en la linea de comandos ni en el historial).
        # shellcheck disable=SC2086
        if MYSQL_PWD="$DB_PASS_ENV" mysqldump $OPCIONES_DUMP -h "${DB_HOST_ENV:-127.0.0.1}" -P "${DB_PORT_ENV:-3306}" -u "$DB_USER_ENV" "$DB_NAME_ENV" | gzip > "$ARCHIVO_RESPALDO" && [ -s "$ARCHIVO_RESPALDO" ]; then
            info "Respaldo listo: $ARCHIVO_RESPALDO ($(du -h "$ARCHIVO_RESPALDO" | cut -f1))."
            info "Para restaurar: gunzip -c $ARCHIVO_RESPALDO | mysql -h ${DB_HOST_ENV:-127.0.0.1} -u $DB_USER_ENV -p $DB_NAME_ENV"
            # Se conservan los 10 respaldos mas recientes.
            ls -1t "$DIR_RESPALDOS"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
        else
            rm -f "$ARCHIVO_RESPALDO"
            falla "El respaldo fallo, asi que NO se migra. Revise mysqldump (permisos, host) o use --sin-respaldo solo con un respaldo hecho a mano."
            exit 1
        fi
    fi
fi

# --- 3. Migraciones ---
paso "3/6 Migraciones"
if [ "$DRY_RUN" -eq 1 ]; then
    info "Migraciones pendientes segun el codigo actual (las del codigo nuevo se ven al desplegar):"
    "$PHP_BIN" artisan migrate:status --pending --no-ansi 2>/dev/null | head -20 | sed 's/^/      /'
fi
ejecutar "$PHP_BIN" artisan migrate --force || { falla "migrate fallo. Revise la salida; la BD pudo quedar a medias."; exit 1; }

# --- 4. Caches y storage ---
paso "4/6 Caches y enlace de storage"
ejecutar "$PHP_BIN" artisan optimize:clear || { falla "optimize:clear fallo."; exit 1; }
ejecutar "$PHP_BIN" artisan config:cache   || { falla "config:cache fallo."; exit 1; }
ejecutar "$PHP_BIN" artisan route:cache    || { falla "route:cache fallo."; exit 1; }
ejecutar "$PHP_BIN" artisan view:cache     || { falla "view:cache fallo."; exit 1; }
if [ -e public/storage ]; then
    info "public/storage ya existe."
else
    ejecutar "$PHP_BIN" artisan storage:link || info "AVISO: storage:link fallo (revise permisos o crearlo a mano)."
fi

# --- Manual de usuario en PDF ya generado (siempre en QA; en produccion solo con --manual-pdf) ---
if [ "$QA" -eq 1 ] || [ "$MANUAL_PDF" -eq 1 ]; then
    paso "Manual de usuario en PDF"
    ejecutar "$PHP_BIN" artisan manual:pdf || info "AVISO: manual:pdf fallo; el PDF se generara en la primera descarga (puede tardar)."
fi

# --- 5. Chequeo final ---
paso "5/6 Chequeo final"
CHEQUEO_OK=1
if [ "$SALTAR_CHEQUEO" -eq 1 ]; then
    info "Omitido (--skip-check)."
else
    if [ -z "$URL_BASE" ]; then
        URL_BASE="$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r')"
        [ -z "$URL_BASE" ] && URL_BASE="https://ibbla.eu"
    fi
    URL_BASE="${URL_BASE%/}"
    info "URL base: $URL_BASE"

    comprobar() { # url esperado descripcion
        local obtenido
        if [ "$DRY_RUN" -eq 1 ]; then info "[dry-run] curl $1 (esperado $2)"; return 0; fi
        obtenido="$(curl -s -o /dev/null -m 30 -w '%{http_code}' "$1" 2>/dev/null)" || obtenido="000"
        if [ "$obtenido" = "$2" ]; then info "OK    $3 -> $obtenido"; else info "FALLA $3 -> $obtenido (esperado $2)"; CHEQUEO_OK=0; fi
    }
    comprobar "$URL_BASE/login" 200 "/login"
    # Sin sesion, el reporte general debe mandar al login (302): prueba que la ruta existe y la app arranca.
    comprobar "$URL_BASE/personas/reporte-general?accion=ver" 302 "/personas/reporte-general (sin sesion)"
fi

# --- 6. Resumen ---
paso "6/6 Resumen"
if [ "$DRY_RUN" -eq 1 ]; then
    info "Dry-run terminado: no se cambio nada."
    exit 0
fi
info "Version anterior: $ANTES_DESC ($(git rev-parse --short "$ANTES"))"
info "Version actual:   $(git describe --tags --always 2>/dev/null) ($(git rev-parse --short HEAD))"
info "Para volver atras: git checkout $ANTES_DESC && $PHP_BIN artisan optimize:clear && $PHP_BIN artisan config:cache && $PHP_BIN artisan route:cache && $PHP_BIN artisan view:cache"
info "(si composer.lock era distinto, correr tambien composer install --no-dev --optimize-autoloader; migraciones nuevas: ver docs/DEPLOY-HOSTINGER.md)"
info "Verificacion completa: $PHP_BIN artisan ibbla:verificar"

if [ "$CHEQUEO_OK" -eq 1 ]; then
    echo; echo "DEPLOY OK"
    exit 0
fi
falla "El chequeo final no paso. Revise storage/logs/laravel.log y considere el rollback de arriba."
exit 1
}

main "$@"
exit $?
