# 📊 IBBSC - Sistema de Administración

Sistema completo de gestión administrativa para iglesias, desarrollado con Laravel 12 y TailwindCSS 4.0.

## 🚀 Características Principales

### 📈 Gestión de Ingresos
- **Recuento de Sobres**: Registro detallado de diezmos, misiones, construcción, seminario, campamento, préstamos y microfinanzas
- **Ofertas Sueltas**: Control de donaciones sin sobre asignado
- **Métodos de Pago**: Soporte para efectivo y transferencias bancarias
- **Cierre de Cultos**: Bloqueo de ediciones después del cierre
- **Reportes PDF**: Generación automática de recuentos individuales con símbolo ₡

### 👥 Gestión de Personas
- **Registro de Miembros**: Base de datos completa con información de contacto
- **Roles de Usuario**: Admin, Tesorero, Asistente, Invitado, Miembro
- **Acceso de Miembros**: Los miembros pueden ver su propio progreso (sin editar)
- **Promesas**: Seguimiento de compromisos financieros
- **Compromisos Mensuales**: Cálculo automático de saldos y deudas

### 📊 Asistencia
- **Registro Detallado**: Chapel, clases por edades (0-1, 2-6, 7-8, 9-11 años)
- **Categorías**: Adultos, adultos mayores, jóvenes, niños (hombres y mujeres)
- **Maestros**: Control de asistencia de maestros
- **Cierre de Asistencia**: Bloqueo después del cierre
- **Reportes PDF**: Asistencia por culto y mensual con totales demográficos

### 📅 Gestión de Cultos
- **Tipos de Culto**: Domingo AM, Domingo PM, Miércoles
- **Estados**: Abierto/Cerrado
- **Totales Automáticos**: Cálculo de ingresos y asistencia

### 📊 Dashboard Inteligente
- **Totales Mensuales**: Vista dinámica con selector de mes/año
- **9 Categorías**: Total Mensual, Diezmos, Misiones, Construcción, Suelto, Seminario, Campamento, Préstamo, Micro
- **Gráficos**: Distribución de ingresos por categoría (cuando se implemente)
- **Información Rápida**: Cultos recientes, asistencias, promesas

### 🔒 Seguridad
- **Autenticación Laravel Breeze**: Sistema robusto de login/logout
- **Control de Roles**: Middleware para restricción por rol
- **Rate Limiting**: Protección contra ataques de fuerza bruta (5 intentos máximo)
- **CSRF Protection**: Tokens en todos los formularios
- **Password Hashing**: Bcrypt con 12 rounds
- **Páginas de Error Personalizadas**: 403, 404, 500

## 💻 Requisitos del Sistema

- PHP >= 8.2
- Composer >= 2.0
- Node.js >= 18.0
- NPM >= 9.0
- MySQL >= 8.0 o MariaDB >= 10.3
- Extensiones PHP: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, GD

## 📦 Instalación

### 1. Clonar el Repositorio
```bash
git clone <repository-url>
cd IBBSCation
```

### 2. Instalar Dependencias
```bash
composer install
npm install
```

### 3. Configurar Variables de Entorno
```bash
cp .env.example .env
php artisan key:generate
```

Edita el archivo `.env` con tus configuraciones:
```env
APP_NAME="IBBSC Administración"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
APP_TIMEZONE=America/Costa_Rica

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ibbsc
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña_segura
```

### 4. Configurar Base de Datos
```bash
php artisan migrate --seed
```

### 5. Compilar Assets
```bash
# Para desarrollo
npm run dev

# Para producción
npm run build
```

### 6. Optimizar para Producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 7. Configurar Permisos
```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Windows (PowerShell como Admin)
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)F" /T
icacls "bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)F" /T
```

### 8. Usuario Administrador por Defecto
Después de correr las migraciones, se creará un usuario administrador:
- **Email**: admin@ibbsc.com
- **Password**: password 
- **⚠️ IMPORTANTE**: Cambia este password inmediatamente después del primer login

## 🔐 Roles y Permisos

### Admin
- ✅ Acceso total al sistema
- ✅ Gestión de usuarios, personas, promesas
- ✅ Gestión de cultos y clases de asistencia
- ✅ Reportes completos
- ✅ Recálculo de compromisos

### Tesorero
- ✅ Gestión de recuento (sobres y ofertas sueltas)
- ✅ Cierre de cultos
- ✅ Reportes de ingresos
- ✅ Acceso a dashboard

### Asistente
- ✅ Gestión de asistencia
- ✅ Cierre de asistencias
- ✅ Reportes de asistencia
- ✅ Acceso a dashboard

### Invitado
- ✅ Solo lectura del dashboard
- ❌ Sin permisos de edición

### Miembro
- ✅ Vista personal "Yo"
- ✅ Visualización de sus propias promesas
- ✅ Visualización de sus compromisos
- ❌ Sin permisos de edición

## 📂 Estructura del Proyecto

```
IBBSCation/
├── app/
│   ├── Exceptions/
│   │   └── Handler.php          # Manejo de errores 403, 404, 500
│   ├── Http/
│   │   ├── Controllers/         # Controladores principales
│   │   ├── Middleware/          # CheckRole middleware
│   │   └── Requests/            # LoginRequest con rate limiting
│   ├── Models/                  # Modelos Eloquent
│   └── Providers/               # Service providers
├── database/
│   ├── migrations/              # Migraciones de base de datos
│   └── seeders/                 # Seeders (usuario admin)
├── resources/
│   ├── css/                     # TailwindCSS
│   ├── js/                      # JavaScript
│   └── views/                   # Plantillas Blade
│       ├── auth/                # Login/Registro
│       ├── errors/              # 403, 404, 500
│       ├── layouts/             # Layout principal con sidebar
│       ├── pdfs/                # Templates para PDFs
│       └── [modulos]/           # Vistas por módulo
└── routes/
    └── web.php                  # Rutas con middleware de roles
```

## 🎨 Características de UI/UX

- ✨ **Diseño Responsivo**: Funciona en desktop, tablet y móvil
- 🎯 **Sidebar Dinámico**: Navegación adaptada por rol de usuario
- 💫 **Modales Elegantes**: Confirmaciones sin alert() nativo del navegador
- 🔄 **Animaciones**: Transiciones suaves en login/logout
- 📣 **Feedback Visual**: Estados de carga, éxito y error claros
- 🎨 **Tema Moderno**: Gradientes, sombras y colores coherentes
- ₡ **Símbolo de Colones**: Moneda costarricense en toda la aplicación

## 📱 Módulos del Sistema

### Recuento (Admin, Tesorero)
- `/recuento` - Lista de cultos abiertos
- `/recuento/create` - Agregar sobre
- `/recuento/{sobre}/edit` - Editar sobre
- `/recuento/suelto` - Agregar ofrenda suelta
- `/recuento/{culto}/cerrar` - Cerrar culto
- `/recuento/culto-cerrado/{culto}` - Ver culto cerrado

### Asistencia (Admin, Asistente)
- `/asistencia` - Lista de cultos
- `/asistencia/create` - Registrar asistencia
- `/asistencia/{asistencia}/edit` - Editar asistencia
- `/asistencia/{asistencia}/cerrar` - Cerrar asistencia

### Personas (Admin)
- `/personas` - Lista de personas
- `/personas/create` - Agregar persona
- `/personas/{persona}/edit` - Editar persona
- `/personas/quick-store` - Creación rápida (AJAX)
- `/personas/{persona}/compromisos` - Ver compromisos

### Promesas (Admin)
- `/promesas` - Lista de promesas
- `/promesas/create` - Crear promesa
- `/promesas/{promesa}/edit` - Editar promesa

### Reportes
- `/ingresos-asistencia/ingresos` - Reporte de ingresos
- `/ingresos-asistencia/asistencia` - Reporte de asistencia
- `/ingresos-asistencia/pdf-*` - Generación de PDFs

### Mi Perfil (Miembros)
- `/mi-perfil` - Vista personal con promesas y compromisos (solo lectura)

## 🛠️ Comandos Útiles

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Recrear base de datos (¡CUIDADO EN PRODUCCIÓN!)
php artisan migrate:fresh --seed

# Optimizar para producción
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Compilar assets
npm run build

# Verificar logs
tail -f storage/logs/laravel.log

# Generar nueva APP_KEY
php artisan key:generate
```

## 🐛 Troubleshooting

### Error: "No application encryption key has been specified"
```bash
php artisan key:generate
```

### Error de permisos en storage/
```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache

# Windows
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)F" /T
```

### Cambios en .env no se reflejan
```bash
php artisan config:clear
```

### Assets no se cargan correctamente
```bash
npm run build
php artisan optimize:clear
```

### Errores de base de datos
- ✅ Verifica credenciales en `.env`
- ✅ Asegúrate de que la base de datos existe
- ✅ Ejecuta `php artisan migrate`
- ✅ Verifica que el usuario MySQL tiene permisos

### Números se redondean automáticamente
- ✅ Ya solucionado: Los inputs tienen `step="1"` para evitar redondeo del navegador

## 📊 Base de Datos

### Tablas Principales
- `users` - Usuarios del sistema con roles
- `personas` - Miembros de la iglesia (pueden tener user_id para login)
- `cultos` - Registro de cultos con fecha y tipo
- `sobres` - Sobres de ofrendas por persona
- `detalles_sobre` - Desglose de categorías por sobre
- `ofrenda_suelta` - Ofertas sin sobre asignado
- `asistencias` - Registro de asistencia por culto
- `promesas` - Promesas financieras de los miembros
- `compromisos` - Estado mensual de compromisos con saldo_actual
- `culto_totales` - Totales calculados al cerrar culto

## 🔄 Flujo de Trabajo

1. **Admin** crea cultos en `/cultos` (Ej: Domingo AM 2025-11-24)
2. **Tesorero** registra sobres en `/recuento` durante/después del culto
3. **Asistente** registra asistencia en `/asistencia` durante el culto
4. Después del culto, se cierran ambos:
   - Recuento: bloquea edición de sobres y ofertas
   - Asistencia: bloquea edición de números
5. Los datos quedan bloqueados permanentemente
6. Se generan reportes PDF para respaldo físico
7. Dashboard muestra estadísticas actualizadas en tiempo real
8. **Miembros** pueden ver su progreso personal en `/mi-perfil`

## 🚀 Despliegue en Producción

### Servidor Web (Apache)
```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    DocumentRoot /var/www/ibbsc/public
    
    <Directory /var/www/ibbsc/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/ibbsc_error.log
    CustomLog ${APACHE_LOG_DIR}/ibbsc_access.log combined
</VirtualHost>
```

### SSL con Let's Encrypt
```bash
sudo certbot --apache -d tu-dominio.com
```

### Cron Job para Tareas Programadas
```cron
* * * * * cd /var/www/ibbsc && php artisan schedule:run >> /dev/null 2>&1
```

### Checklist de Producción
- [ ] `APP_DEBUG=false` en `.env`
- [ ] `APP_ENV=production` en `.env`
- [ ] Cambiar password del admin
- [ ] Configurar backups automáticos de DB
- [ ] Habilitar SSL/HTTPS
- [ ] Configurar firewall
- [ ] Ejecutar `php artisan optimize`
- [ ] Compilar assets con `npm run build`
- [ ] Configurar logs (rotation, monitoring)
- [ ] Probar todas las funcionalidades críticas

## 📝 Notas Importantes

- ⚠️ **Backups**: Configura backups automáticos diarios de la base de datos
- 🔒 **SSL**: SIEMPRE usa HTTPS en producción
- 🔑 **Passwords**: Cambia el password del admin inmediatamente
- 🐛 **Debug**: `APP_DEBUG=false` en producción
- 📋 **Logs**: Monitorea `storage/logs/` regularmente
- ₡ **Moneda**: Sistema usa colones costarricenses (₡)
- 📱 **Mobile**: Interfaz completamente responsive
- 🔄 **Actualizaciones**: Haz backup antes de cualquier actualización

## 🔧 Mantenimiento

### Backup de Base de Datos
```bash
# Exportar
mysqldump -u username -p ibbsc > backup_$(date +%Y%m%d).sql

# Importar
mysql -u username -p ibbsc < backup_20251122.sql
```

### Monitoreo de Logs
```bash
# Ver últimas líneas
tail -n 100 storage/logs/laravel.log

# Seguir en tiempo real
tail -f storage/logs/laravel.log

# Buscar errores
grep "ERROR" storage/logs/laravel.log
```

### Limpiar Archivos Temporales
```bash
# Limpiar views compiladas
php artisan view:clear

# Limpiar caché de aplicación
php artisan cache:clear

# Limpiar todo
php artisan optimize:clear
```

## 🤝 Soporte

Para soporte técnico o reportar problemas:

1. **Revisar logs**: `storage/logs/laravel.log`
2. **Verificar permisos**: storage/ y bootstrap/cache/
3. **Comprobar .env**: Credenciales y configuraciones
4. **Consultar documentación**: Este README
5. **Verificar versiones**: PHP, MySQL, extensiones

## 📖 Recursos Adicionales

- [Laravel Documentation](https://laravel.com/docs)
- [TailwindCSS Documentation](https://tailwindcss.com/docs)
- [DomPDF Documentation](https://github.com/barryvdh/laravel-dompdf)

## 📄 Licencia

Este proyecto es propietario. Todos los derechos reservados.

---

**Desarrollado con ❤️ para IBBSC**  
*Sistema de Administración v1.0*  
*Noviembre 2025*
