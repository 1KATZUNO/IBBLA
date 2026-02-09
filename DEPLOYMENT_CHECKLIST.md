# ✅ Checklist de Producción - IBBSC

Usa este checklist antes de desplegar a producción.

## 📋 Configuración Básica

- [ ] Clonar repositorio en servidor de producción
- [ ] Instalar dependencias: `composer install --no-dev --optimize-autoloader`
- [ ] Instalar node modules: `npm ci`
- [ ] Copiar `.env.example` a `.env`
- [ ] Generar APP_KEY: `php artisan key:generate`
- [ ] Configurar variables de entorno en `.env`

## 🔐 Seguridad

- [ ] `APP_ENV=production` en `.env`
- [ ] `APP_DEBUG=false` en `.env`
- [ ] Cambiar password del usuario admin por defecto
- [ ] Verificar que `.env` está en `.gitignore`
- [ ] Configurar permisos de archivos (775 storage/, bootstrap/cache/)
- [ ] Habilitar SSL/HTTPS (certificado válido)
- [ ] Configurar firewall del servidor
- [ ] Cambiar credenciales de base de datos por defecto
- [ ] Configurar backup automático de base de datos

## 🗄️ Base de Datos

- [ ] Crear base de datos en MySQL
- [ ] Configurar credenciales en `.env`
- [ ] Ejecutar migraciones: `php artisan migrate --force`
- [ ] Ejecutar seeders: `php artisan db:seed --force`
- [ ] Verificar que el usuario admin se creó correctamente
- [ ] Hacer backup inicial de la base de datos

## ⚡ Optimización

- [ ] Compilar assets: `npm run build`
- [ ] Cachear configuración: `php artisan config:cache`
- [ ] Cachear rutas: `php artisan route:cache`
- [ ] Cachear vistas: `php artisan view:cache`
- [ ] Optimizar autoload: `php artisan optimize`
- [ ] Verificar que OPcache está habilitado en PHP

## 🌐 Servidor Web

### Apache
- [ ] Configurar VirtualHost
- [ ] Apuntar DocumentRoot a `/public`
- [ ] Habilitar mod_rewrite
- [ ] Configurar AllowOverride All
- [ ] Reiniciar Apache

### Nginx (alternativa)
- [ ] Configurar server block
- [ ] Configurar try_files correctamente
- [ ] Reiniciar Nginx

## 📧 Correo (Si se usa)

- [ ] Configurar MAIL_MAILER en `.env`
- [ ] Configurar credenciales SMTP
- [ ] Probar envío de emails
- [ ] Verificar que los emails no van a spam

## 📊 Logging y Monitoreo

- [ ] Configurar LOG_LEVEL=error en `.env`
- [ ] Configurar rotación de logs
- [ ] Configurar monitoreo de errores (opcional: Sentry, Bugsnag)
- [ ] Configurar alertas de errores críticos
- [ ] Verificar permisos de escritura en storage/logs/

## 🔄 Tareas Programadas (Cron)

- [ ] Configurar cron job para Laravel scheduler:
  ```
  * * * * * cd /ruta/a/ibbsc && php artisan schedule:run >> /dev/null 2>&1
  ```

## 🧪 Pruebas Finales

- [ ] Probar login con usuario admin
- [ ] Probar creación de culto
- [ ] Probar registro de sobre
- [ ] Probar registro de asistencia
- [ ] Probar cierre de culto
- [ ] Probar generación de PDFs
- [ ] Probar acceso con cada rol:
  - [ ] Admin
  - [ ] Tesorero
  - [ ] Asistente
  - [ ] Invitado
  - [ ] Miembro
- [ ] Probar responsive en móvil
- [ ] Verificar que todos los enlaces funcionan
- [ ] Verificar páginas de error (403, 404, 500)

## 🎨 UI/UX

- [ ] Verificar que el símbolo ₡ aparece correctamente
- [ ] Verificar animaciones de login/logout
- [ ] Probar sidebar en desktop y móvil
- [ ] Verificar colores y estilos
- [ ] Probar todos los modales de confirmación

## 📱 Funcionalidades Específicas

### Recuento
- [ ] Crear sobre
- [ ] Editar sobre
- [ ] Agregar ofrenda suelta
- [ ] Cerrar culto
- [ ] Ver culto cerrado
- [ ] Generar PDF de recuento

### Asistencia
- [ ] Registrar asistencia
- [ ] Editar asistencia
- [ ] Cerrar asistencia
- [ ] Generar PDF de asistencia
- [ ] Ver reporte mensual

### Personas
- [ ] Crear persona
- [ ] Editar persona
- [ ] Crear persona rápida (AJAX)
- [ ] Asignar acceso de miembro (email/password)
- [ ] Probar login como miembro

### Dashboard
- [ ] Verificar totales mensuales
- [ ] Cambiar mes/año
- [ ] Verificar que los 9 stat cards funcionan
- [ ] Verificar información de cultos recientes

## 🔄 Backup

- [ ] Configurar backup automático diario de DB
- [ ] Configurar backup de archivos (storage/)
- [ ] Probar restauración de backup
- [ ] Documentar proceso de backup/restore
- [ ] Guardar backups en ubicación externa

## 📖 Documentación

- [ ] README.md actualizado
- [ ] CHANGELOG.md con versión actual
- [ ] Documentar credenciales admin inicial
- [ ] Documentar URLs importantes
- [ ] Compartir información de acceso con equipo

## ⚠️ Post-Deployment

- [ ] Verificar que el sitio está online
- [ ] Monitorear logs por 24-48 horas
- [ ] Cambiar password admin inmediatamente
- [ ] Crear usuarios adicionales (tesorero, asistente)
- [ ] Capacitar usuarios sobre el sistema
- [ ] Configurar contacto de soporte técnico

## 🚨 Plan de Contingencia

- [ ] Documentar proceso de rollback
- [ ] Guardar backup pre-deployment
- [ ] Tener número de contacto de soporte técnico
- [ ] Documentar comandos críticos de emergencia

---

## 📝 Notas Adicionales

### Comandos Útiles de Emergencia

```bash
# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Limpiar todo el caché
php artisan optimize:clear

# Poner sitio en mantenimiento
php artisan down

# Sacar sitio de mantenimiento
php artisan up

# Restaurar backup de DB
mysql -u username -p database_name < backup.sql
```

### Información de Contacto

- **Desarrollador**: [Tu nombre]
- **Email**: [tu@email.com]
- **Teléfono**: [tu teléfono]
- **Repositorio**: [URL del repo]

---

**Fecha de deployment**: _______________  
**Versión desplegada**: v1.0.0  
**Desplegado por**: _______________  
**Verificado por**: _______________
