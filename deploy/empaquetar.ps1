<#
.SYNOPSIS
  Verificacion local antes de poner un tag de IBBLA (no despliega ni sube nada).

.DESCRIPTION
  1. Arbol de git sin cambios en archivos versionados.
  2. Suite completa en MySQL (la suite no corre en SQLite).
  3. Lint: php -l de todo el PHP versionado, Blade sin directivas pegadas a una letra,
     y compilacion de todas las vistas (view:cache) para cazar errores de sintaxis Blade.
  4. npm run build y comprobar que public/build no cambia (si cambia, hay assets sin commitear).
  Termina con un resumen y, si todo pasa, los comandos para etiquetar y subir.

.PARAMETER Database
  Base MySQL SOLO para tests (se vacia con RefreshDatabase). Por defecto ibbla_dep_test.

.PARAMETER FallosBase
  Cantidad de fallos de la linea base que se toleran. La suite de octubre-2026 trae 15 de Breeze
  que ya fallaban antes; por defecto 0 (estricto). Pasar -FallosBase 15 mientras sigan.

.PARAMETER SinBuild
  Omitir el paso de npm run build.

.EXAMPLE
  powershell -File deploy\empaquetar.ps1 -Tag v2026.10.20 -FallosBase 15
#>
param(
    [string]$Tag = "",
    [string]$Database = "ibbla_dep_test",
    [int]$FallosBase = 0,
    [switch]$SinBuild
)

$ErrorActionPreference = "Continue"
$raiz = Split-Path -Parent $PSScriptRoot
Set-Location $raiz

$resultados = [ordered]@{}
function Registrar([string]$nombre, [bool]$ok, [string]$detalle = "") {
    $script:resultados[$nombre] = @{ ok = $ok; detalle = $detalle }
    $marca = if ($ok) { "[OK]   " } else { "[FALLA]" }
    $color = if ($ok) { "Green" } else { "Red" }
    Write-Host ("  {0} {1} {2}" -f $marca, $nombre, $detalle) -ForegroundColor $color
}
function Paso([string]$t) { Write-Host ""; Write-Host "==> $t" -ForegroundColor Cyan }

# --- 0. Preflight ---
Paso "0. Herramientas y rama"
foreach ($cmd in @("git", "php")) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) { Write-Host "Falta '$cmd' en el PATH." -ForegroundColor Red; exit 1 }
}
$rama = (git rev-parse --abbrev-ref HEAD).Trim()
Write-Host "  Rama: $rama   Commit: $((git rev-parse --short HEAD).Trim())"
if ($Database -notmatch "test") {
    Write-Host "  La base '$Database' no parece de tests (RefreshDatabase la vacia). Cancelado." -ForegroundColor Red
    exit 1
}

# --- 1. Git limpio ---
Paso "1. Arbol de git"
$sucios = git status --porcelain --untracked-files=no
Registrar "Sin cambios sin commitear en archivos versionados" (-not $sucios) ($(if ($sucios) { "(" + (@($sucios).Count) + " archivo(s))" } else { "" }))
if ($Tag) {
    $existe = git tag --list $Tag
    Registrar "El tag $Tag aun no existe" (-not $existe)
}

# --- 2. Suite en MySQL ---
Paso "2. Suite completa en MySQL ($Database)"
$env:DB_CONNECTION = "mysql"
$env:DB_DATABASE = $Database
$salida = & php vendor/bin/phpunit --colors=never 2>&1 | Out-String
$ultimas = ($salida -split "`r?`n" | Where-Object { $_.Trim() } | Select-Object -Last 3) -join " | "
$fallos = 0
if ($salida -match "OK \((\d+) tests") {
    Registrar "Suite" $true "($($Matches[1]) tests)"
} elseif ($salida -match "Tests: (\d+),.*?(Failures: (\d+))?.*?(Errors: (\d+))?") {
    foreach ($m in [regex]::Matches($salida, "(Failures|Errors): (\d+)")) { $fallos += [int]$m.Groups[2].Value }
    Registrar "Suite (tolerados: $FallosBase)" ($fallos -le $FallosBase) "$fallos fallo(s)/error(es). $ultimas"
} else {
    Registrar "Suite" $false "no se pudo interpretar la salida: $ultimas"
}
Remove-Item Env:\DB_CONNECTION, Env:\DB_DATABASE -ErrorAction SilentlyContinue

# --- 3. Lint ---
Paso "3. Lint"
$malos = @()
foreach ($f in (git ls-files "*.php")) {
    $r = & php -l $f 2>&1
    if ($LASTEXITCODE -ne 0) { $malos += $f }
}
Registrar "php -l de todo el PHP versionado" ($malos.Count -eq 0) $(if ($malos.Count) { "(" + ($malos -join ", ") + ")" } else { "" })

$pegadas = git grep -nE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' -- "*.blade.php"
Registrar "Blade sin directivas pegadas a una letra" (-not $pegadas) $(if ($pegadas) { "`n" + ($pegadas -join "`n") } else { "" })

$vistas = & php artisan view:cache 2>&1 | Out-String
$vistasOk = ($LASTEXITCODE -eq 0)
& php artisan view:clear 2>&1 | Out-Null
Registrar "Todas las vistas compilan (view:cache)" $vistasOk $(if (-not $vistasOk) { "`n" + $vistas.Trim() } else { "" })

# --- 4. Build de assets ---
Paso "4. Assets (npm run build)"
if ($SinBuild) {
    Write-Host "  Omitido (-SinBuild)."
} elseif (-not (Get-Command npm -ErrorAction SilentlyContinue) -or -not (Test-Path "node_modules")) {
    Registrar "npm run build" $false "(falta npm o node_modules: npm ci)"
} else {
    & npm run build 2>&1 | Out-Null
    $buildOk = ($LASTEXITCODE -eq 0)
    Registrar "npm run build" $buildOk
    if ($buildOk) {
        $cambios = git status --porcelain -- public/build
        Registrar "public/build sin cambios pendientes" (-not $cambios) $(if ($cambios) { "(commitear el build nuevo antes del tag)`n" + ($cambios -join "`n") } else { "" })
    }
}

# --- Resumen ---
Paso "Resumen"
$fallidos = @($resultados.GetEnumerator() | Where-Object { -not $_.Value.ok })
if ($fallidos.Count -eq 0) {
    Write-Host "  TODO OK: se puede etiquetar." -ForegroundColor Green
    $t = if ($Tag) { $Tag } else { "vAAAA.MM.DD" }
    Write-Host ""
    Write-Host "  git checkout main; git pull"
    Write-Host "  git tag $t"
    Write-Host "  git push origin main $t"
    Write-Host "  (luego, en el servidor: bash deploy/hostinger.sh --tag $t)"
    exit 0
}
Write-Host "  $($fallidos.Count) chequeo(s) fallaron: no etiquetar todavia." -ForegroundColor Red
foreach ($f in $fallidos) { Write-Host "   - $($f.Key)" -ForegroundColor Red }
exit 1
