# Changelog

Todos los cambios notables de este proyecto serán documentados en este archivo.

## [1.0.0] - 2025-11-22

### ✨ Características Principales

#### Gestión de Ingresos
- Sistema completo de recuento de sobres con 7 categorías
- Registro de ofertas sueltas
- Soporte para efectivo y transferencias
- Cierre de cultos con bloqueo de ediciones
- Generación de PDFs con símbolo ₡

#### Gestión de Personas
- CRUD completo de personas
- Sistema de roles (Admin, Tesorero, Asistente, Invitado, Miembro)
- Acceso opcional para miembros con email/password
- Vista "Yo" para que miembros vean su progreso
- Gestión de promesas y compromisos

#### Asistencia
- Registro detallado por chapel y clases
- Categorías demográficas (hombres, mujeres, niños)
- Maestros por clase
- Cierre de asistencias
- Reportes PDF mensuales

#### Dashboard
- Selector dinámico de mes/año
- 9 stat cards con categorías de ingresos
- Vista adaptada por rol de usuario
- Animaciones de entrada suaves

#### Seguridad
- Rate limiting en login (5 intentos)
- CSRF protection en todos los formularios
- Middleware de roles
- Páginas de error personalizadas (403, 404, 500)
- Password hashing con Bcrypt (12 rounds)

#### UI/UX
- Diseño responsivo completo
- Sidebar dinámico por rol
- Modales elegantes sin confirm() nativo
- Animaciones de transición en login/logout
- Iconos de redes sociales (Instagram, Facebook)
- Símbolo ₡ en toda la aplicación

### 🐛 Correcciones

- Agregado `step="1"` en inputs numéricos de asistencia para evitar redondeo automático
- Corregido cálculo de compromisos usando `saldo_actual < 0` en lugar de columna `deuda`
- Reemplazados todos los `$` por `₡` en vistas y PDFs
- Corregido formato de fechas en PDFs de asistencia (incluye día de la semana)

### 🔧 Mejoras Técnicas

- Configuración de locale español (es) y timezone Costa Rica
- Optimización de AppServiceProvider con Carbon en español
- Handler de excepciones con páginas de error personalizadas
- .env.example completo y documentado
- Scripts de deployment para Linux y Windows
- README completo con documentación exhaustiva

### 📦 Dependencias

- Laravel 12.x
- TailwindCSS 4.0
- Laravel Breeze
- DomPDF
- Carbon
- MySQL 8.0+

### 🔄 Migraciones

- Tabla `users` con campo `rol` (enum)
- Tabla `personas` con `user_id` y `password` opcionales
- Tabla `compromisos` con `saldo_actual` para cálculos
- Tabla `culto_totales` para almacenar totales al cerrar
- Enum actualizado en `users` para incluir rol 'miembro'

### 📝 Documentación

- README.md completo con instrucciones de instalación
- Documentación de roles y permisos
- Guía de troubleshooting
- Comandos útiles para mantenimiento
- Checklist de producción

---

## Formato

Este changelog sigue el formato de [Keep a Changelog](https://keepachangelog.com/es/1.0.0/),
y este proyecto adhiere a [Semantic Versioning](https://semver.org/lang/es/).

### Tipos de Cambios

- **✨ Características**: Nuevas funcionalidades
- **🐛 Correcciones**: Corrección de bugs
- **🔧 Mejoras**: Mejoras de código existente
- **📦 Dependencias**: Actualizaciones de dependencias
- **🔒 Seguridad**: Mejoras de seguridad
- **📝 Documentación**: Cambios en documentación
- **🗑️ Deprecated**: Funcionalidades obsoletas
- **🔥 Removed**: Funcionalidades eliminadas
