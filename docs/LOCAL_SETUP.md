# IBBLA · Entorno local (Windows + Laragon)

Receta verificada el 2026-10-11 (agente A, worktree `feat/reporte-general-504`).
Sirve para el repo principal y para cualquier worktree en `C:\Users\rgonh\Documents\IBBLA-wt\<slug>`.

| Pieza | Valor |
|---|---|
| PHP | 8.3 del PATH (`php -v`; WinGet, ZTS x64). Trae pdo_mysql, pdo_sqlite, gd, intl, zip, mbstring |
| MySQL | Laragon 8.4.3: `C:\laragon\bin\mysql\mysql-8.4.3-winx64` |
| my.ini | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini` (puerto 3306) |
| datadir | `C:\laragon\data\mysql-8.4` (ya inicializado; NO es `C:\laragon\data\mysql`) |
| Usuario BD | `root` **sin password** (root@localhost; conectar por `127.0.0.1`) |
| Log de MySQL | `C:\laragon\data\mysql-8.4\<HOSTNAME>.err` (hoy `DavidGonza.err`) |
| Assets | `public/build` esta commiteado: no hace falta `npm` salvo que cambies CSS/JS |

## 1. Arrancar MySQL (una sola vez para todos)

Primero mira si ya esta arriba. **Un solo mysqld por datadir**: si arrancas un segundo sobre el
mismo datadir pelean por `ibdata1`.

```powershell
netstat -ano | Select-String "LISTENING" | Select-String ":3306 "
```

Si no sale nada, arrancalo en background (PowerShell):

```powershell
$bin = "C:\laragon\bin\mysql\mysql-8.4.3-winx64"
Start-Process -FilePath "$bin\bin\mysqld.exe" -ArgumentList "--defaults-file=`"$bin\my.ini`"" -WindowStyle Hidden
Start-Sleep -Seconds 8
netstat -ano | Select-String "LISTENING" | Select-String ":3306 "   # debe aparecer 0.0.0.0:3306
```

Se ven dos procesos `mysqld.exe` (monitor + servidor): es normal en MySQL 8 para Windows.

Comprobar acceso y parar (solo si de verdad hay que pararlo; los demas agentes lo usan):

```bash
/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -uroot -h127.0.0.1 -e "SELECT VERSION(); SHOW DATABASES;"
/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqladmin.exe -uroot -h127.0.0.1 shutdown
```

## 2. Crear tu base (una por agente/worktree)

Convencion: `ibbla_<letra o slug>` para la app y `ibbla_<x>_test` para los tests (ver 7).
Ejemplo del agente A: `ibbla_a` y `ibbla_a_test`.

```bash
MYSQL=/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe
$MYSQL -uroot -h127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS ibbla_x CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS ibbla_x_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 3. vendor (copia real, NUNCA junction)

Composer resuelve las clases `App\` desde la ruta real: con un junction el worktree cargaria el codigo
del repo principal sin que te des cuenta.

```powershell
robocopy "C:\Users\rgonh\Documents\IBBLA\vendor" "C:\Users\rgonh\Documents\IBBLA-wt\<slug>\vendor" /E /NFL /NDL /NJH /NJS /MT:16
```

robocopy devuelve 1 cuando copio bien (no es error). Si alguien cambia `composer.json` hay que
correr `composer install` en el worktree en vez de copiar.

**Cuidado al commitear:** `vendor/` NO esta en `.gitignore` (sale como `?? vendor/` en
`git status`). Nunca uses `git add -A` ni `git add .`: agrega los archivos por nombre.

## 4. .env (desde el del repo principal)

El `.env` del repo principal es una copia del de produccion de IBBSC (APP_ENV=production, BD remota,
lineas `DB_*` con espacio delante). Se copia y se sobreescriben las claves locales (Git Bash):

```bash
cd /c/Users/rgonh/Documents/IBBLA-wt/<slug>
cp /c/Users/rgonh/Documents/IBBLA/.env .env
sed -i -E \
  -e 's/^[[:space:]]+(DB_[A-Z_]+=)/\1/' \
  -e 's#^APP_ENV=.*#APP_ENV=local#' \
  -e 's#^APP_DEBUG=.*#APP_DEBUG=true#' \
  -e 's#^APP_URL=.*#APP_URL=http://localhost:<puerto>#' \
  -e 's#^APP_KEY=.*#APP_KEY=#' \
  -e 's#^DB_HOST=.*#DB_HOST=127.0.0.1#' \
  -e 's#^DB_PORT=.*#DB_PORT=3306#' \
  -e 's#^DB_DATABASE=.*#DB_DATABASE=ibbla_x#' \
  -e 's#^DB_USERNAME=.*#DB_USERNAME=root#' \
  -e 's#^DB_PASSWORD=.*#DB_PASSWORD=#' \
  .env
php artisan key:generate        # APP_KEY propia del worktree (no reusar la de produccion)
php artisan config:clear
```

`SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` quedan en `database`: las tablas las crean las
migraciones. `MAIL_MAILER=log` (los correos van a `storage/logs/laravel.log`).

## 5. Migrar y sembrar

```bash
php artisan migrate --seed --force --no-ansi
```

`DatabaseSeeder` crea el tenant IBBSC con categorias, tipos de culto y roles, mas los usuarios de
prueba, el tipo de cambio y los tipos de registro extra. Para empezar de cero:
`php artisan migrate:fresh --seed --force`.

### Datos de volumen (opcional, para probar reportes pesados)

```bash
php artisan db:seed --class=VolumenDevSeeder --force
```

Agrega al tenant IBBSC un anio completo (el actual): 150 personas (140 activas), 74 cultos (70 del
anio + 4 de diciembre anterior), ~2.600 sobres con ~5.000 detalles (5% anonimos, 5% USD), 245
promesas, compromisos mensuales sincronizados de enero al mes actual y `totales_culto`. Tarda ~6 s,
es determinista y re-ejecutable (borra lo que creo antes; todo lleva la marca `[volumen-dev]` en
`notas`). Se niega a correr con `APP_ENV=production`. No toca asistencia ni ministerios.

## 6. Levantar la app

Cada agente usa su puerto (A=8101; los demas, el que les asigno el lider).

```powershell
Start-Process -FilePath "php" -ArgumentList "artisan","serve","--host=127.0.0.1","--port=8101" -WorkingDirectory "C:\Users\rgonh\Documents\IBBLA-wt\<slug>" -WindowStyle Hidden
netstat -ano | Select-String "LISTENING" | Select-String ":8101 "
```

(o en primer plano: `php artisan serve --port=8101`). Abrir `http://localhost:8101/login`.

### Usuarios de prueba (de `database/seeders/AdminUserSeeder.php`)

| Rol | Email | Password |
|---|---|---|
| Admin del tenant IBBSC (el que hay que usar) | `admin@ibbsc.com` | `admin123` |
| Tesorero | `tesorero@ibbsc.com` | `tesorero123` |
| Super admin (sin tenant, panel de plataforma) | `super@admin.com` | `admin123` |

Ojo: CLAUDE.md dice `password`; es incorrecto, vale lo del seeder. Tras el login redirige a
`/principal`; `/dashboard` y `/personas` responden 200 (verificado con curl).

Prueba de login por curl (Git Bash), util para agentes sin navegador:

```bash
cd "$TMP" && rm -f cj.txt
T=$(curl -s -c cj.txt -b cj.txt http://127.0.0.1:8101/login | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)"/\1/')
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" -c cj.txt -b cj.txt -X POST http://127.0.0.1:8101/login \
  --data-urlencode "_token=$T" --data-urlencode "email=admin@ibbsc.com" --data-urlencode "password=admin123"
curl -s -o /dev/null -w "%{http_code}\n" -c cj.txt -b cj.txt http://127.0.0.1:8101/personas   # 200
```

## 7. Tests (IMPORTANTE: `php artisan test` no sirve tal cual)

`phpunit.xml` usa SQLite en memoria, pero varias migraciones hacen `ALTER TABLE ... MODIFY COLUMN`
(solo MySQL), asi que con SQLite fallan TODOS los tests que migran (linea base 2026-10-11:
24 de 25 fallan). Ademas `php artisan test` borra del entorno las variables que estan en `.env`
antes de lanzar PHPUnit, asi que no se le puede cambiar la conexion desde fuera.

Correr PHPUnit directo contra tu base `_test` de MySQL (PHPUnit no pisa variables ya definidas):

```bash
# Git Bash
DB_CONNECTION=mysql DB_DATABASE=ibbla_x_test php -d memory_limit=1G -d max_execution_time=0 vendor/bin/phpunit --filter NombreDelTest
```

```powershell
# PowerShell (las variables quedan solo en esa sesion)
$env:DB_CONNECTION = "mysql"; $env:DB_DATABASE = "ibbla_x_test"; php vendor/bin/phpunit --filter NombreDelTest
```

- Usa SIEMPRE la base `_test`: `RefreshDatabase` hace `migrate:fresh` y la vacia.
- Si olvidas las dos variables cae a SQLite (falla, pero no rompe nada); si pones solo
  `DB_CONNECTION=mysql`, PHPUnit fija `DB_DATABASE=:memory:` y falla al conectar. Con este comando
  no se puede vaciar la base de desarrollo por accidente.
- Linea base en MySQL (2026-10-11): 25 tests, 15 fallan. Son los tests de Breeze (`Auth/*`,
  `ProfileTest`): crean usuarios sin tenant y el login responde "Tu iglesia no esta activa". No es
  regresion de nadie; filtra por tus tests.
- Los usuarios de tus tests necesitan tenant activo y `tenant_role_id` (copiar lo que hace
  `AdminUserSeeder`).

## 8. Verificacion antes de cada commit (reglas del plan)

```bash
php -l ruta/al/archivo.php                                   # cada PHP tocado
grep -rnE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' resources/views   # debe salir vacio
DB_CONNECTION=mysql DB_DATABASE=ibbla_x_test php vendor/bin/phpunit --filter TuTest
```

## Problemas tipicos

| Sintoma | Causa / arreglo |
|---|---|
| `SQLSTATE[HY000] [2002] No connection could be made` | MySQL no esta arriba: paso 1 |
| `Unknown database 'ibbla_x'` | Falta el paso 2 |
| `No application encryption key has been specified` | `php artisan key:generate` |
| `Vite manifest not found` | Falta `public/build` (deberia venir en git); `npm ci && npm run build` |
| `near "MODIFY": syntax error` en tests | Estas en SQLite: usa el comando del paso 7 |
| Cambios de `.env` no se notan | `php artisan config:clear` |
