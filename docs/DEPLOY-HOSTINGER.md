# IBBLA · Despliegue en Hostinger (ibbla.eu)

Reemplaza al `DEPLOYMENT_CHECKLIST.md`, `deploy.sh`, `deploy.ps1` y `.cpanel.yml` heredados de IBBSC
(cPanel / Verpex), que no aplican a este servidor y se borraron.

| Dato | Valor |
|---|---|
| App | `ibbla.eu` |
| Ruta en el servidor | `/home/u598753224/domains/ibbla.eu/public_html` (clon de git de `https://github.com/1KATZUNO/IBBLA`) |
| Acceso | SSH como `u598753224` |
| PHP / Composer | PHP 8.2+ (`composer.json` pide `^8.2`). Sin colas, sin workers y sin node |
| Assets | `public/build` esta **versionado**: se hace `npm run build` en local y se commitea antes del tag |
| Script de deploy | `deploy/hostinger.sh` (corre en el servidor) |
| Verificacion | `php artisan ibbla:verificar` |

> **Cuidado con la ruta.** `~/public_html` (sin `domains/ibbla.eu/`) del mismo usuario es **otra app**
> (IBBSCMusica, `musica.ibbla.eu`). El 12/10 el primer comando del hotfix cayo ahi y le recacheo
> config, rutas y vistas por error. `deploy/hostinger.sh` se niega a correr si la ruta no termina en
> `domains/ibbla.eu/public_html`.

## 1. Antes de desplegar (en tu maquina)

1. Todo lo que va a salir esta mergeado en `main` y la suite pasa en MySQL.
2. Opcional pero recomendado: `powershell -File deploy\empaquetar.ps1` (suite en MySQL, lint de Blade,
   `npm run build` sin cambios pendientes). Si pasa, sigue. Mientras sigan los 15 fallos de Breeze de la linea base,
   usar `-FallosBase 15`. Si dice que `public/build` cambio, commitea el build nuevo (el servidor no tiene node).
3. Etiqueta la version y sube `main` y el tag (convencion: `vAAAA.MM.DD` o `vAAAA.MM.DD-motivo`):

   ```bash
   git checkout main && git pull
   git tag v2026.10.20
   git push origin main v2026.10.20
   ```

## 2. Desplegar (en el servidor)

```bash
ssh u598753224@<host-de-hostinger>
cd /home/u598753224/domains/ibbla.eu/public_html
pwd                                           # confirmar que termina en domains/ibbla.eu/public_html

bash deploy/hostinger.sh --dry-run --tag v2026.10.20   # 1) ver el plan, no cambia nada
bash deploy/hostinger.sh --tag v2026.10.20             # 2) desplegar
php artisan ibbla:verificar                            # 3) revisar el servidor
```

Sin `--tag` hace `git pull --ff-only` de `main`. Opciones: `--maintenance` (pone la app en
mantenimiento mientras despliega), `--url`, `--skip-check`, `--branch`. `bash deploy/hostinger.sh -h`.

El script, en orden:

1. Se niega a seguir si la ruta no es la de IBBLA, si falta `.env` o si hay cambios locales en archivos versionados.
2. `git fetch --tags`, muestra los commits que entran y hace el checkout del tag (o `pull --ff-only`).
3. `composer install --no-dev --optimize-autoloader` **solo si cambio** `composer.lock`/`composer.json` (o si no hay `vendor/`).
4. `php artisan migrate --force`.
5. `optimize:clear`, `config:cache`, `route:cache`, `view:cache`, y `storage:link` si falta.
6. Chequeo con `curl`: `/login` debe dar 200 y `/personas/reporte-general?accion=ver` sin sesion debe dar 302. Termina con `DEPLOY OK`
   (codigo 0) o con `FALLA` (codigo 1) y la linea para volver atras.

Notas:

- El `.env` del servidor **no se toca** nunca. Si agregas una variable nueva, editala a mano y luego `php artisan config:cache`
  (en produccion la app solo lee `config()`; un `.env` cambiado sin recachear no tiene efecto).
- La primera vez que se despliega una version que ya trae `deploy/hostinger.sh`, hay que traerlo antes:
  `git fetch --tags && git checkout tags/v2026.10.20` y despues correr el script (o hacer ese primer deploy a mano, ver mas abajo).
- Si el servidor tiene otro PHP por defecto: `PHP_BIN=/ruta/a/php82 bash deploy/hostinger.sh ...` (igual `COMPOSER_BIN`).

### Deploy manual (si el script no sirve)

```bash
cd /home/u598753224/domains/ibbla.eu/public_html
git fetch --tags && git checkout tags/v2026.10.20
composer install --no-dev --optimize-autoloader      # solo si cambio composer.lock
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 3. Cuando hay migraciones nuevas

Las migraciones **no se deshacen solas** al volver de version: volver el codigo no revierte la base.

1. **Respaldo antes**: hPanel > Bases de datos > Copias de seguridad, o `mysqldump` (usuario y base estan en el `.env` del servidor; no los pegues en comandos que queden en el historial compartido).
2. Mira que entra: `bash deploy/hostinger.sh --dry-run --tag ...` lista los commits; `git diff --stat <actual> <tag> -- database/migrations` muestra las migraciones nuevas.
3. Si alguna es destructiva o lenta (cambiar tipo de columna, borrar columna, tablas grandes), desplegar con `--maintenance`.
4. Despues de desplegar, `php artisan ibbla:verificar` debe decir `Migraciones: al dia`.
5. Si `migrate` falla a medias, no sigas desplegando: lee el error, corrige y repite `php artisan migrate --force`. Si hace falta volver, restaura el respaldo del paso 1 **y** vuelve el codigo (seccion siguiente).

Pendiente conocido de la fase 1: la migracion que agrega `audit_logs.tenant_id` (historial de ingresos) y, cuando Roy
mande la lista de los 30 ministerios, cargarla en `config/ministerios.php` y correr
`php artisan db:seed --class=MinisterioSeeder --force` (es idempotente).

## 4. Rollback

```bash
cd /home/u598753224/domains/ibbla.eu/public_html
git checkout v2026.10.12-reportes           # el tag anterior (el script lo imprime al terminar)
composer install --no-dev --optimize-autoloader   # solo si el composer.lock de aquel tag es distinto
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan ibbla:verificar --sin-reportes
```

Si el tag viejo es anterior a `deploy/hostinger.sh`, el script ya no esta en el arbol: por eso el rollback se da como comandos sueltos.
Si la version mala traia migraciones, el codigo viejo normalmente sigue funcionando con columnas de mas (las migraciones son aditivas);
si no, restaurar el respaldo de la BD.

Para quedar otra vez en `main` despues de un rollback: `git checkout main && git pull --ff-only`.

## 5. Que revisa `ibbla:verificar`

| Chequeo | En produccion | Fuera de produccion |
|---|---|---|
| `APP_ENV` = production (el ambiente sale tambien en la primera linea) | AVISO si no | AVISO |
| `APP_DEBUG` = false | **FALLA** si es true | AVISO |
| `APP_KEY` definida | FALLA | FALLA |
| Caches de config, rutas y vistas activas | **FALLA** si falta alguna | AVISO |
| Conexion a la BD | FALLA | FALLA |
| Migraciones pendientes | FALLA | FALLA |
| Cuentas de `AdminUserSeeder` (`admin@ibbsc.com`, `super@admin.com`, `tesorero@ibbsc.com`) con la contrasena publica del repo | **FALLA** | AVISO |
| Escritura en `storage/*` y `bootstrap/cache` | FALLA | FALLA |
| `public/storage` (enlace) | AVISO | AVISO |
| Cron del planificador: latido `ibbla:ultimo_schedule` en la cache (lo escribe una tarea cada minuto) | **FALLA** si falta o tiene mas de 10 min | FALLA (solo con `APP_ENV=local` es AVISO) |
| Avisos de WhatsApp automaticos esperando mas de 10 min | AVISO (nadie los procesa o el proveedor los rechaza) | igual |
| `QUEUE_CONNECTION` distinta de `sync` | AVISO (no hay worker de colas en Hostinger; IBBLA no la usa) | igual |
| Reporte General y estado de cuenta (la persona con mas sobres) | FALLA si tarda 10 s o mas, AVISO desde 5 s | igual |

Opciones: `--tenant=ID`, `--sin-reportes`, `--umbral=SEGUNDOS` (por defecto 10, el corte de Hostinger). Codigo de salida 1 si hay alguna FALLA.
El 504 del Reporte General se producia a los 60 s; con el fix tarda unos 3 s con 140 personas y 2.600 sobres.
No escribe en la base: los PDF se generan en memoria.

## 6. Cron

Los avisos de WhatsApp, el reporte mensual, el tipo de cambio y la purga de auditoria dependen del scheduler de Laravel.
En hPanel > Avanzado > Cron Jobs debe existir (cada minuto):

```
cd /home/u598753224/domains/ibbla.eu/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Verificar que apunte a `domains/ibbla.eu/public_html` y no a `~/public_html`.

Tareas programadas (`routes/console.php`; `php artisan schedule:list` las muestra):

| Tarea | Cuando | Para que |
|---|---|---|
| `ibbla:latido` | cada minuto | Guarda la hora en la cache (`ibbla:ultimo_schedule`). `ibbla:verificar` FALLA si tiene mas de 10 min: el cron no corre |
| `whatsapp:enviar-pendientes --max-segundos=50` | cada minuto, sin encimarse (candado de 5 min) | Envia los avisos de WhatsApp automaticos (QE-009) |
| `exchange:fetch` | 18:00 CR | Tipo de cambio del BCCR |
| `reporte:enviar-mensual` | dia 1, 08:00 CR | Correo con los reportes del mes anterior |
| `audit:purge` | mensual | Purga la auditoria vieja |

### Avisos de WhatsApp: fuera de la peticion (QE-009)

Crear un culto o guardar el 10.o sobre **no** llama a ningun proveedor: solo deja los avisos en la tabla
`avisos_whatsapp`. Los que el sistema puede mandar solo (driver `meta` con credenciales, o `callmebot` a quien
activo su WhatsApp) quedan con `envio_automatico = 1` y los envia `whatsapp:enviar-pendientes` en el minuto
siguiente. Reintentos: 3 como maximo (esperas de 1 y 5 min); si se agotan, o si el aviso tiene mas de 12 horas
(cron caido), deja de ser automatico y queda pendiente en el panel /avisos con su ultimo error, para mandarlo
con un toque. No se usan las colas de Laravel: `QUEUE_CONNECTION=sync` sigue siendo lo correcto en Hostinger.

Para probarlo a mano sin esperar al cron: `php artisan whatsapp:enviar-pendientes`.

## 7. Problemas frecuentes

| Sintoma | Causa probable | Que hacer |
|---|---|---|
| 500 despues del deploy | cache vieja o permisos | `php artisan optimize:clear`, revisar `storage/logs/laravel.log`, `ibbla:verificar` |
| 500 y el log no se escribe | `storage/` sin permiso | `chmod -R ug+rwX storage bootstrap/cache` |
| Cambie el `.env` y no pasa nada | `config:cache` activo | `php artisan config:cache` |
| Logos o imagenes subidas no se ven | falta `public/storage` | `php artisan storage:link` |
| El script dice "ruta no termina en ..." | estas en `~/public_html` (otra app) | `cd ~/domains/ibbla.eu/public_html` |
| `git pull` rechazado | alguien edito archivos en el servidor | `git status`; no se despliega encima de cambios locales |
| Reporte General vuelve a dar 504 | datos mucho mayores o limite de PHP/nginx | `ibbla:verificar` (mide el tiempo real) |

## Respaldo automático de la base de datos (desde v2026.11.24-e1)

Antes de correr las migraciones, `deploy/hostinger.sh` hace un `mysqldump` comprimido de la base del
`.env` en `~/respaldos-ibbla/<bd>_<fecha>_antes_de_<tag>.sql.gz` (conserva los 10 más recientes; la
contraseña viaja por `MYSQL_PWD`, nunca en la línea de comandos). Si el respaldo falla, el deploy se
detiene sin migrar. Para restaurar: `gunzip -c <archivo> | mysql -h <host> -u <usuario> -p <bd>`.
`--sin-respaldo` lo omite y solo tiene sentido con un respaldo hecho a mano en hPanel.
