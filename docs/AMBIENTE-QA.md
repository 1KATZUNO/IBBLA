# Ambiente QA de IBBLA (qa.ibbla.eu)

QA es una **copia de IBBLA para probar antes de tocar producción**: otro subdominio, otra base de datos y
otro `.env`, con el mismo código. Ahí se prueba cada versión; cuando Roy o el equipo la dan por buena, se
despliega a producción (`ibbla.eu`). Los datos de QA no son reales y **QA nunca debe escribirle a una
persona de verdad**.

| | Producción | QA |
|---|---|---|
| Dirección | `https://ibbla.eu` | `https://qa.ibbla.eu` |
| Carpeta en el servidor | `/home/u598753224/domains/ibbla.eu/public_html` | `/home/u598753224/domains/qa.ibbla.eu/public_html` |
| Base de datos | la de producción | **propia** (su nombre debe incluir `qa`) |
| `APP_ENV` | `production` | `qa` |
| Rama / versión que corre | `main` por tag (`--tag v2026.xx`) | `integracion/oct-2026` (o la que se indique con `--branch`) |
| Comando de deploy | `bash deploy/hostinger.sh --tag <tag>` | `bash deploy/hostinger.sh --qa` |

Dentro de la app, QA se reconoce porque **todas las pantallas llevan una franja amarilla arriba: «AMBIENTE DE
PRUEBAS · los datos no son reales»**, y porque responde con la cabecera `X-Robots-Tag: noindex, nofollow`
(los buscadores no lo indexan). En producción no aparece ninguna de las dos cosas.

> Aviso: lo de este documento lo hace David en el hPanel de Hostinger y por SSH. Nada de aquí se ejecuta
> desde el repositorio. **No pegues contraseñas en este archivo ni en chats**: van solo en el `.env` de QA.

---

## 1. Instalación (una sola vez)

### 1.1 Subdominio

1. hPanel > **Dominios > Subdominios** > crear `qa` sobre `ibbla.eu`.
2. Cuando pregunte la carpeta, debe quedar **`/home/u598753224/domains/qa.ibbla.eu/public_html`**
   (si hPanel propone otra, cámbiala: el script de deploy solo corre en esa ruta).
3. Activa el **SSL gratis** del subdominio (hPanel > Seguridad > SSL) y fuerza HTTPS.
4. Versión de PHP: la misma que producción (8.2 o superior).

### 1.2 Base de datos y usuario propios

1. hPanel > **Bases de datos > Administración de bases de datos MySQL** > crear una base y un usuario **nuevos**,
   por ejemplo `u598753224_ibbla_qa` y un usuario `u598753224_qa`, con contraseña propia (distinta de la de producción).
2. El nombre de la base **debe contener `qa`**: el script de deploy se niega a correr si la base de QA tiene el
   mismo nombre que la de producción o, si no puede compararlas, si el nombre no incluye `qa`.
3. Ese usuario solo debe tener permiso sobre la base de QA, nunca sobre la de producción.

### 1.3 Clonar el repositorio por SSH

```bash
ssh -p 65002 u598753224@<ip-del-servidor>        # los datos están en hPanel > Avanzado > Acceso SSH
cd /home/u598753224/domains/qa.ibbla.eu
rm -rf public_html                                  # solo si hPanel dejó la carpeta vacía con su index por defecto
git clone <url-del-repositorio> public_html          # el mismo remoto que usa producción
cd public_html
git checkout integracion/oct-2026
composer install --no-dev --optimize-autoloader
```

Si el repositorio es privado, usa la misma llave de despliegue que ya tiene producción (no la copies aquí).

### 1.4 El `.env` de QA

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Valores que **deben** quedar así (el resto, como en producción: idioma, zona horaria, etc.):

```ini
APP_NAME=IBBLA
APP_ENV=qa
APP_DEBUG=false
APP_URL=https://qa.ibbla.eu

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<la base de QA, con "qa" en el nombre>
DB_USERNAME=<el usuario de QA>
DB_PASSWORD=<la contraseña de QA>

# QA no manda correos ni WhatsApp reales
MAIL_MAILER=log
WHATSAPP_DRIVER=callmebot
# (sin ninguna clave de CallMeBot en el .env: las claves son de cada persona y se guardan en la base)
```

Reglas:

- `APP_ENV=qa` es lo que activa la franja amarilla y el noindex, y lo que exige el script de deploy.
- `APP_DEBUG=false`, igual que producción: así QA se comporta como producción.
- `MAIL_MAILER=log`: los correos (restablecer contraseña, etc.) se escriben en `storage/logs/laravel.log` y no salen.
- Deja vacías las variables de CallMeBot / Meta. QA **no debe** tener la `APP_KEY` de producción: genera una nueva
  (así las claves de WhatsApp cifradas de producción, si llegan en una copia, no se pueden leer).
- Protege el archivo: `chmod 600 .env`.

### 1.5 Primer despliegue

```bash
bash deploy/hostinger.sh --qa --dry-run     # primero en seco: muestra el plan y no cambia nada
bash deploy/hostinger.sh --qa               # el de verdad
```

El primer `--qa` crea las tablas con `php artisan migrate --force` (la base está vacía). Para tener datos de prueba
ver la sección 3. Al terminar, el script chequea `https://qa.ibbla.eu/login` (200) y debe decir `DEPLOY OK`.
Luego: `php artisan ibbla:verificar`.

### 1.6 Cron del planificador

hPanel > **Avanzado > Cron Jobs**, cada minuto, igual que producción pero apuntando a la carpeta de QA:

```
cd /home/u598753224/domains/qa.ibbla.eu/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Con `MAIL_MAILER=log` y sin claves de WhatsApp, los avisos y el reporte mensual se "envían" a la cola o al log,
sin salir de QA.

---

## 2. Desplegar en QA (cada vez)

```bash
ssh ... && cd /home/u598753224/domains/qa.ibbla.eu/public_html
bash deploy/hostinger.sh --qa --dry-run      # opcional: ver qué cambia
bash deploy/hostinger.sh --qa                # rama integracion/oct-2026
bash deploy/hostinger.sh --qa --branch otra-rama
bash deploy/hostinger.sh --qa --tag v2026.11.30-rc1
```

Opciones que siguen valiendo con `--qa`: `--dry-run`, `--tag`, `--branch`, `--maintenance`, `--sin-respaldo`,
`--skip-check`, `--url`. Antes de migrar hace el **respaldo de la base de QA** en `~/respaldos-ibbla/` (el nombre del
archivo lleva el de la base, así no se confunde con los de producción).

Defensas del script (si alguna falla, se detiene sin tocar nada):

1. **`--qa` solo corre en `.../domains/qa.ibbla.eu/public_html`.** Ni `--allow-path` lo salta.
2. **El `.env` debe decir `APP_ENV=qa`.**
3. **`DB_DATABASE` no puede ser la de producción**: se compara con la del `.env` de
   `/home/u598753224/domains/ibbla.eu/public_html` si existe; si no se puede comparar, el nombre debe contener `qa`.
4. **Sin `--qa`, el script se niega a correr en la carpeta de QA** (para que un deploy de producción nunca
   caiga en QA por error de carpeta).

---

## 3. Refrescar QA con una copia de producción

Sirve para probar con datos parecidos a los reales (volumen, casos raros). Se hace cuando QA quedó muy desfasado
o antes de probar una versión grande. **Nunca al revés** (de QA a producción).

> Los datos de producción son de personas reales (nombres, teléfonos, promesas). La copia vive solo en el
> servidor, se borra al terminar y QA debe quedar **sin teléfonos ni claves de WhatsApp** (paso 3.3).

### 3.1 Sacar la copia de producción

```bash
cd /home/u598753224/domains/ibbla.eu/public_html
# Usuario y base están en el .env de producción. La contraseña se pide al ejecutar (-p), no se escribe en el comando.
mysqldump --single-transaction --quick --no-tablespaces --routines --triggers \
  -u <usuario-de-produccion> -p <base-de-produccion> | gzip > ~/copia-prod-para-qa.sql.gz
```

(`deploy/hostinger.sh` ya deja respaldos de producción en `~/respaldos-ibbla/`: puedes usar el más reciente.)

### 3.2 Importar en la base de QA

```bash
cd /home/u598753224/domains/qa.ibbla.eu/public_html
# Poner QA en mantenimiento mientras se importa
php artisan down --retry=60
# Vaciar la base de QA (borra las tablas de QA; es solo QA) y cargar la copia
mysql -u <usuario-de-qa> -p <base-de-qa> -e "DROP DATABASE IF EXISTS \`<base-de-qa>\`; CREATE DATABASE \`<base-de-qa>\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c ~/copia-prod-para-qa.sql.gz | mysql -u <usuario-de-qa> -p <base-de-qa>
```

Antes de pulsar Enter, **lee dos veces el nombre de la base** que va en cada comando: debe ser la de QA.
(Si el usuario de QA no puede borrar/crear bases en hPanel, en vez del `DROP DATABASE` borra las tablas desde
phpMyAdmin de QA: Seleccionar todo > Eliminar.)

### 3.3 Dejar QA inofensivo (obligatorio, antes de levantar el mantenimiento)

Ejecuta esto en la base de **QA** (phpMyAdmin de QA o `mysql ... <base-de-qa>`):

```sql
-- 1. WhatsApp: fuera claves, activaciones y mensajes en cola
DELETE FROM activaciones_whatsapp;
DELETE FROM avisos_whatsapp;
-- 2. Sin teléfonos: nadie recibe nada aunque alguien active el envío
UPDATE personas SET telefono = NULL;
UPDATE users    SET telefono = NULL;
-- 3. Contraseñas de QA propias (hash bcrypt de una clave de pruebas que el equipo conoce;
--    genéralo con:  php artisan tinker --execute="echo Hash::make('<clave-de-pruebas>');"
--    y pégalo aquí). Nadie entra a QA con su clave de producción.
UPDATE users SET password = '<hash-bcrypt-de-la-clave-de-pruebas>', remember_token = NULL;
-- 4. Fuera sesiones abiertas (si SESSION_DRIVER=database) y tokens de reinicio de clave
DELETE FROM sessions;
DELETE FROM password_reset_tokens;
```

Si alguna tabla no existe en esa versión, el `DELETE` avisa: se ignora. Después:

```bash
php artisan migrate --force          # por si QA corre código más nuevo que la copia
php artisan compromisos:sincronizar  # recalcula los compromisos con el código de QA
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
php artisan ibbla:verificar
rm ~/copia-prod-para-qa.sql.gz       # la copia con datos reales no se queda en el servidor
```

Comprobaciones rápidas: entrar a `https://qa.ibbla.eu` y ver la franja amarilla; en Mi perfil de cualquier usuario
no debe haber WhatsApp activado; en `/avisos` no debe haber mensajes pendientes.

---

## 4. Flujo «primero QA, luego producción»

1. **Cada cambio llega a la rama `integracion/oct-2026`** (la que arma el líder técnico).
2. David corre en QA: `bash deploy/hostinger.sh --qa`.
3. Roy y el equipo **prueban en QA** (con la lista de `docs/plan-oct-2026/QA-ENTREGA.md` / `QA-FINAL.md`). Lo que falle
   se reporta y se corrige en la rama; se vuelve al paso 2.
4. Con QA aprobado, el líder técnico integra a `main` y crea el **tag** de versión (`v2026.xx.yy`).
5. David despliega **ese mismo tag** en producción: `bash deploy/hostinger.sh --tag v2026.xx.yy`
   (siguiendo `docs/DEPLOY-HOSTINGER.md`), y después `php artisan compromisos:sincronizar` y `php artisan ibbla:verificar`.
6. Si producción necesita un arreglo urgente, se prueba igual en QA antes (aunque sea rápido).

Regla de oro: **lo que no pasó por QA no se despliega en producción**, y **QA nunca recibe datos de WhatsApp reales**.

---

## 5. Problemas frecuentes

| Síntoma | Causa | Qué hacer |
|---|---|---|
| El script dice «--qa solo corre en una ruta que termine en domains/qa.ibbla.eu/public_html» | estás en otra carpeta | `cd /home/u598753224/domains/qa.ibbla.eu/public_html` |
| «El .env ... dice APP_ENV=...» | el `.env` de QA tiene otro `APP_ENV` | poner `APP_ENV=qa` y `php artisan config:cache` |
| «DB_DATABASE de QA es la misma que la de producción» | QA apunta a la base de producción | corregir `DB_DATABASE`/usuario del `.env` de QA **antes** de seguir |
| El script dice «es la del ambiente QA: ahí se despliega con --qa» | corriste el deploy de producción en la carpeta de QA | usar `--qa` |
| No se ve la franja amarilla | `config:cache` viejo o `APP_ENV` distinto | `php artisan config:cache`; revisar `APP_ENV=qa` |
| QA mandó un WhatsApp o correo real | quedaron teléfonos/claves o `MAIL_MAILER` distinto de `log` | detener (`php artisan down`), repetir la sección 3.3, revisar el `.env` |
