#!/bin/bash

# ============================================
# Script de Deployment para IBBSC
# ============================================

echo "🚀 Iniciando deployment de IBBSC..."

# Verificar si estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo "❌ Error: No se encontró el archivo artisan. ¿Estás en el directorio correcto?"
    exit 1
fi

# Activar modo de mantenimiento
echo "📦 Activando modo de mantenimiento..."
php artisan down --render="errors::503"

# Pull latest changes from git (opcional, comentado por defecto)
# echo "📥 Descargando últimos cambios..."
# git pull origin main

# Instalar dependencias de Composer
echo "📦 Instalando dependencias de Composer..."
composer install --no-dev --optimize-autoloader

# Instalar dependencias de NPM
echo "📦 Instalando dependencias de NPM..."
npm ci

# Compilar assets de producción
echo "🎨 Compilando assets..."
npm run build

# Ejecutar migraciones
echo "🗄️  Ejecutando migraciones..."
php artisan migrate --force

# Limpiar y optimizar
echo "🧹 Limpiando caché..."
php artisan optimize:clear

echo "⚡ Optimizando aplicación..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Configurar permisos
echo "🔐 Configurando permisos..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Desactivar modo de mantenimiento
echo "✅ Desactivando modo de mantenimiento..."
php artisan up

echo "🎉 Deployment completado exitosamente!"
echo "📋 No olvides:"
echo "   - Verificar que el sitio funciona correctamente"
echo "   - Revisar los logs: tail -f storage/logs/laravel.log"
echo "   - Hacer backup de la base de datos regularmente"
