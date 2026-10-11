# IBBLA · Plan octubre 2026

Lider tecnico: Claude (sesion principal). Equipo: 4 agentes dev + 1 QA.
Rama de integracion: `integracion/oct-2026` (sale de `main`). Cada item en `feat/<slug>`.
`main` NO se toca hasta cierre de fase. Deploy a Hostinger (ibbla.eu) al cerrar cada fase.

## Origen (Roy, 28/08 - 23/09/2026)
1. Reporte de asistencia por clase por rango de fechas: ninos, ninas, maestros y
   PROMEDIO del rango. Exportable PDF / CSV / Excel con el promedio ya calculado.
2. Dashboard filtrable por rango de fechas (hoy solo anio + mes).
3. Cargar los 30 ministerios reales (Roy pasa la lista) + poder agregar
   ministerios manualmente desde la app.
4. Historial de ingreso a la plataforma: quien entro, cuando y a que hora.
5. Asistencias: el tope de 100 por casilla se queda corto; subirlo.
6. "Reporte General" (dado vs esperado) en Gestion de Personas no genera:
   504 Gateway Time-out de nginx.
Del lider del proyecto (David):
7. Revision visual/usabilidad completa, tablet a PC: botones muy abajo,
   flujos dificiles. Debe ser facil de usar.
8. WhatsApp desde la plataforma: numero virtual; enviar el informe de una
   persona con un clic; avisos a roles (culto creado, >=10 sobres, etc).
9. Guia de usuario.

## Fases
- FASE 1 (ahora): items 1-7. Entrega al cliente al cerrar.
- FASE 2: item 8 (WhatsApp) + QA integral + ambiente QA.
- FASE 3: item 9 (guia de usuario).

## Asignacion Fase 1
| Agente | Modelo | Rama | Alcance |
|---|---|---|---|
| A | opus | feat/reporte-general-504 | Entorno local (MySQL Laragon, receta en docs/LOCAL_SETUP.md) + fix del 504 del Reporte General (item 6) con medicion antes/despues |
| B | opus | feat/ux-responsive | Item 7: layouts, navegacion, formularios, tablet->PC; rebuild Tailwind |
| C | sonnet | feat/asistencia-reporte-rango | Items 1 y 5 |
| D | sonnet | feat/dashboard-ministerios-ingresos | Items 2, 3 y 4 |
| QA | opus | (sobre integracion) | Visual + funcional al cerrar merges; reporta bugs a los devs |

## Reglas para todos
- Leer CLAUDE.md y README_SISTEMA.md antes de tocar nada.
- Worktree propio (ya creado en C:\Users\rgonh\Documents\IBBLA-wt\<slug>). vendor se copia con
  robocopy desde el repo principal (NUNCA junction). DB propia: ibbla_<slug>.
- Verificar: php -l; Blade sin directivas pegadas a letra
  (grep -nE '\w@(if|else|elseif|endif|foreach|endforeach|php|endphp)\b' vacio);
  phpunit del feature cuando aplique; prueba manual con artisan serve.
- Commits en espanol, estilo oracion como el historial, explicando el porque.
  Sin trailer Co-Authored-By. No push: el lider mergea a integracion.
- No tocar archivos ajenos al alcance. Los layouts son del agente B.

## Estado
- 2026-10-11: plan creado, vendor instalado en repo principal, rama integracion creada.
