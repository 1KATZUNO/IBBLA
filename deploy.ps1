# ============================================
# Script de Deployment para IBBSC (Windows)
# ============================================

Write-Host "🚀 Iniciando deployment de IBBSC..." -ForegroundColor Green

# Verificar si estamos en el directorio correcto
if (-Not (Test-Path "artisan")) {
    Write-Host "❌ Error: No se encontró el archivo artisan. ¿Estás en el directorio correcto?" -ForegroundColor Red
    exit 1
}

# Activar modo de mantenimiento
Write-Host "📦 Activando modo de mantenimiento..." -ForegroundColor Yellow
php artisan down --render="errors::503"

# Pull latest changes from git (opcional, comentado por defecto)
# Write-Host "📥 Descargando últimos cambios..." -ForegroundColor Yellow
# git pull origin main

# Instalar dependencias de Composer
Write-Host "📦 Instalando dependencias de Composer..." -ForegroundColor Yellow
composer install --no-dev --optimize-autoloader

# Instalar dependencias de NPM
Write-Host "📦 Instalando dependencias de NPM..." -ForegroundColor Yellow
npm ci

# Compilar assets de producción
Write-Host "🎨 Compilando assets..." -ForegroundColor Yellow
npm run build

# Ejecutar migraciones
Write-Host "🗄️  Ejecutando migraciones..." -ForegroundColor Yellow
php artisan migrate --force

# Limpiar y optimizar
Write-Host "🧹 Limpiando caché..." -ForegroundColor Yellow
php artisan optimize:clear

Write-Host "⚡ Optimizando aplicación..." -ForegroundColor Yellow
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Configurar permisos (Windows con IIS)
Write-Host "🔐 Configurando permisos..." -ForegroundColor Yellow
icacls "storage" /grant "IIS_IUSRS:(OI)(CI)F" /T
icacls "bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)F" /T

# Desactivar modo de mantenimiento
Write-Host "✅ Desactivando modo de mantenimiento..." -ForegroundColor Yellow
php artisan up

Write-Host "🎉 Deployment completado exitosamente!" -ForegroundColor Green
Write-Host ""
Write-Host "📋 No olvides:" -ForegroundColor Cyan
Write-Host "   - Verificar que el sitio funciona correctamente" -ForegroundColor White
Write-Host "   - Revisar los logs: Get-Content storage\logs\laravel.log -Tail 50" -ForegroundColor White
Write-Host "   - Hacer backup de la base de datos regularmente" -ForegroundColor White
