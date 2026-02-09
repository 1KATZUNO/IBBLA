# Sistema de Administración de Iglesia - IBBSC

Sistema completo para la gestión administrativa de una iglesia, incluyendo registro de sobres, asistencia, personas, promesas y reportes.

## 🚀 Características

- ✅ **Autenticación con roles**: Admin, Tesorero, General
- ✅ **Dashboard moderno** con gráficos interactivos (Chart.js)
- ✅ **Recuento digital** de sobres con categorías (diezmo, misiones, construcción, etc.)
- ✅ **Gestión de asistencia** por culto con clases y capilla
- ✅ **Administración de personas** y sus promesas
- ✅ **Cálculo automático** de totales por culto
- ✅ **Vista pública** limitada para usuarios generales
- ✅ **Sidebar responsive** plegable
- ✅ **Generación de PDFs** (pendiente implementación final)

## 📋 Requisitos

- PHP 8.2 o superior
- MySQL 8.0 o superior
- Composer
- Node.js y NPM

## 🔧 Instalación

### 1. Clonar o navegar al proyecto

```bash
cd c:\Users\David Gonzalez\Documents\GitHub\IBBSCation
```

### 2. Instalar dependencias PHP

```bash
composer install
```

### 3. Instalar dependencias JavaScript

```bash
npm install
```

### 4. Configurar el archivo .env

El archivo `.env` ya está configurado. Asegúrate de que MySQL esté corriendo con estos valores:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ibbsc
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Crear la base de datos

Abre MySQL y ejecuta:

```sql
CREATE DATABASE ibbsc;
```

O usa phpMyAdmin o cualquier cliente MySQL.

### 6. Ejecutar migraciones

```bash
php artisan migrate:fresh
```

### 7. Sembrar datos iniciales (usuarios de prueba)

```bash
php artisan db:seed --class=AdminUserSeeder
```

Esto creará 3 usuarios:

- **Admin**: admin@ibbsc.com / admin123
- **Tesorero**: tesorero@ibbsc.com / tesorero123
- **General**: general@ibbsc.com / general123

### 8. Compilar assets

```bash
npm run build
```

### 9. Iniciar el servidor

```bash
php artisan serve
```

El sistema estará disponible en: `http://localhost:8000`

## 👤 Roles y Permisos

### Admin
- Acceso completo a todas las funcionalidades
- Puede crear, editar y eliminar todo

### Tesorero
- Acceso a sobres, cultos, asistencia, personas, promesas
- Puede crear y editar registros financieros
- Puede exportar reportes

### General
- Solo puede ver "Ingresos y Asistencia"
- Vista de solo lectura

## 📊 Módulos del Sistema

### 1. Dashboard
- Estadísticas semanales de ingresos
- Gráfico de barras con ingresos por culto
- Gráfico circular con distribución por categorías
- Línea de tendencia de asistencia
- Estado de promesas cumplidas vs pendientes

### 2. Recuento (Sobres)
- Registro digital de sobres por culto
- Número de sobre autogenerado
- Categorías: diezmo, misiones, seminario, campamento, préstamo, construcción, micro
- Método de pago: efectivo o transferencia
- Vinculación opcional con personas
- Cálculo automático de totales

### 3. Asistencia
- Registro por culto
- Capilla: hombres, mujeres, adultos mayores, adultos, jóvenes
- Clases 0-1, 2-6, 7-8, 9-11: alumnos y maestros por género
- Total de asistencia

### 4. Personas
- CRUD completo de miembros
- Teléfono, correo, notas
- Estado activo/inactivo
- Gestión de promesas asociadas
- Seguimiento de cumplimiento de promesas

### 5. Cultos
- Registro de cultos por fecha y hora
- Tipos: domingo, miércoles, sábado, especial
- Vista detallada con totales y sobres

### 6. Ingresos y Asistencia (Vista Pública)
- Totales semanales
- Distribución por categorías
- Asistencia por culto
- Sin opciones de edición

## 🗂️ Estructura de la Base de Datos

### Tablas principales:

- **users**: Usuarios del sistema con roles
- **cultos**: Registros de cultos
- **personas**: Miembros de la iglesia
- **sobres**: Sobres de ofrendas
- **sobre_detalles**: Desglose por categoría de cada sobre
- **ofrenda_suelta**: Ofrendas sueltas por culto
- **asistencia**: Registro de asistencia por culto
- **totales_culto**: Totales calculados automáticamente
- **promesas**: Compromisos de las personas

## 🎨 Tecnologías Utilizadas

- **Backend**: Laravel 12
- **Frontend**: Blade + TailwindCSS 4
- **Gráficos**: Chart.js
- **PDFs**: DomPDF (Laravel-DomPDF)
- **Base de Datos**: MySQL
- **Autenticación**: Laravel Breeze

## 📝 Próximas Funcionalidades (Por Implementar)

- [ ] Vista completa de Recuento con CRUD de sobres
- [ ] Formularios completos de Asistencia
- [ ] Vistas de Personas y Promesas
- [ ] Generación de PDFs:
  - PDF de culto completo
  - PDF mensual de ingresos
  - PDF de asistencia
- [ ] Reportes avanzados
- [ ] Exportación a Excel
- [ ] Búsqueda y filtros avanzados

## 🐛 Solución de Problemas

### Error de conexión a MySQL
Si ves el error "No se puede establecer una conexión":
1. Asegúrate de que MySQL está corriendo
2. Verifica las credenciales en `.env`
3. Crea la base de datos `ibbsc` manualmente

### Errores de permisos
```bash
php artisan cache:clear
php artisan config:clear
composer dump-autoload
```

### Assets no se cargan
```bash
npm run build
```

## 📧 Contacto

Para soporte o consultas sobre el sistema, contacta al desarrollador.

---

**Desarrollado con ❤️ para IBBSC**
