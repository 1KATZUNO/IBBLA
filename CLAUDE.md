# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

IBBSC - Church administration system built with Laravel 12 and TailwindCSS 4.0. Manages income tracking (tithes, offerings), attendance, member commitments, and financial reporting for a Costa Rican church (currency: ₡ colones, timezone: America/Costa_Rica, locale: es).

## Development Commands

```bash
# Full development environment (server + queue + logs + Vite HMR)
composer dev

# Individual commands
php artisan serve              # Laravel dev server
npm run dev                    # Vite dev server with HMR
npm run build                  # Build assets for production

# Testing
composer test                  # Run PHPUnit tests
php artisan test               # Alternative test command

# Code formatting
./vendor/bin/pint              # Laravel Pint (PSR-12)

# Database
php artisan migrate            # Run migrations
php artisan migrate:fresh --seed  # Reset database with seeders
php artisan db:seed            # Run seeders only

# Cache management
php artisan optimize:clear     # Clear all caches
php artisan config:cache       # Cache config (production)
php artisan route:cache        # Cache routes (production)
php artisan view:cache         # Cache views (production)
```

## Architecture

### Tech Stack
- **Backend**: Laravel 12, PHP 8.2+, Eloquent ORM, Laravel Breeze (auth)
- **Frontend**: Blade templates, TailwindCSS 4.0, Alpine.js, Chart.js
- **Database**: MySQL 8.0+ (SQLite for testing)
- **Build**: Vite 7, NPM
- **PDF**: DomPDF

### Key Directories
- `app/Services/` - Business logic (e.g., `CalculoTotalesCultoService` for totals calculation)
- `app/Http/Middleware/` - Role-based access control (`CheckRole`, `RoleMiddleware`, `AuditLogMiddleware`)
- `resources/views/pdfs/` - DomPDF templates for report generation
- `resources/views/layouts/` - Main app layout with role-aware sidebar

### Role System
Five roles with middleware-based authorization: `admin`, `tesorero`, `asistente`, `invitado`, `miembro`. Routes are protected by role groups in `routes/web.php`.

### Core Models & Relationships
- `Persona` - Church members (can link to `User` for login access)
- `Culto` - Church services (Domingo AM/PM, Miércoles) with open/closed state
- `Sobre` / `SobreDetalle` - Offering envelopes with category breakdown (diezmos, misiones, construcción, etc.)
- `OfrendaSuelta` - Loose offerings without envelope
- `Asistencia` - Attendance by demographics and age classes
- `Promesa` / `Compromiso` - Financial commitments with monthly balance tracking
- `TotalesCulto` - Calculated totals when service closes (locks editing)
- `Egreso` - Expenses/expenditures
- `AuditLog` - Compliance tracking for admin actions

### Workflow Pattern
Services (cultos) are created → Income/attendance recorded while open → Closed to lock data → Totals calculated → PDF reports generated. Once closed, records cannot be edited.

## Code Conventions

- Controllers follow RESTful resource patterns (index, create, store, show, edit, update, destroy)
- Forms use custom Request classes in `app/Http/Requests/` for validation
- Views use Blade components in `resources/views/components/`
- Custom Tailwind theme: `gemini` color palette, custom gradients and animations defined in `tailwind.config.js`

## Testing

PHPUnit configured in `phpunit.xml`. Uses SQLite in-memory for test database:
```bash
composer test                   # Full test suite
php artisan test --filter=Name  # Run specific test
```

## Deployment

Production deployment via cPanel (`.cpanel.yml`) to Verpex hosting. Alternative bash script in `deploy.sh` for manual deployment.

Default admin credentials after seeding: `admin@ibbsc.com` / `password`
