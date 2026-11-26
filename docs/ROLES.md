# Roles y permisos de IBBLA

Matriz definitiva (HU-REC-5). Fuente: decisiones del cliente del 13/10 y reglas del líder en
`docs/plan-oct-2026/04-DECISIONES.md`, y la matriz observada del QA inicial (`QA-INICIAL.md`, sección 3).
Si este documento y el código no coinciden, es un error del código.

## Roles

| Rol | Quién es | Entra a |
|---|---|---|
| **Administrador** | Roy y quien administre la iglesia en la app | Principal |
| **Tesorero** | Quien cuenta y registra los sobres | Principal |
| **Asistente** | Quien registra la asistencia | Principal |
| **Servidor** | Hermano que marca su asistencia con el QR del culto | Principal |
| **Miembro** | Hermano que consulta lo suyo | Mi perfil |

- **Invitado: eliminado** (decisión del cliente 13/10, QA-017). No hay botón ni ruta `login/guest`, y el
  rol no se ofrece ni se acepta en Usuarios. La fila del rol puede seguir en `tenant_roles` de iglesias
  existentes (se dejó para no romper datos): sin permisos y sin forma de entrar.
- **Super Admin**: administra la plataforma (iglesias) desde su panel; ningún administrador de iglesia
  lo ve, edita ni borra (QA-012).
- "Maestra" no es un rol: es un usuario (normalmente miembro) asignado a una clase
  (`users.clase_asistencia_id`) o persona marcada como maestra de la clase. Usa la App de Clases y la
  app de asistencia **solo para su clase**.

## La regla única: "sin permiso = oculto o deshabilitado con motivo"

`App\Support\Permiso` + componente `<x-permiso>` (ver `resources/views/components/permiso.blade.php`):

- Si el **rol nunca** puede hacer algo (un tesorero no gestiona usuarios), el botón **no se muestra**
  (`Permiso::ocultar`).
- Si el rol **sí puede pero ahora no** (culto cerrado, culto futuro, sin acceso de edición vigente), el
  botón se ve **gris, sin poder tocarlo, con el motivo** en un globo al pasar el mouse, al tocarlo o al
  llegar con el teclado (`Permiso::deshabilitar`).
- El backend responde **403 con el mismo motivo** si alguien intenta la acción por URL.

Uso en una vista:

```blade
<x-permiso :permiso="Gate::inspect('puedeEditarCulto', $culto)">
    <a href="..." class="btn-primary">+ Agregar sobre</a>
</x-permiso>
```

Reglas (policies y métodos) que deciden, una sola vez, para pantalla y backend:

| Regla | Dónde | Qué decide |
|---|---|---|
| `puedeEditarCulto` | `CultoPolicy` | Sobres, dinero suelto, egresos y asignar persona: admin siempre; tesorería con el culto abierto o con acceso de edición vigente (módulo Accesos); los demás, oculto |
| `cerrar` | `CultoPolicy` | Cerrar recuento: igual que editar, y además no antes del día del culto ni con el culto vacío (deshabilitado con motivo) |
| `corregirFirmas` | `CultoPolicy` | Firmas: admin y tesorería, también con el culto cerrado; auditado |
| `delete` | `CultoPolicy` | Eliminar culto: solo admin; cerrado o con recuento pide confirmación y motivo; auditado |
| `create` | `CultoPolicy` | Crear cultos: admin y quien tenga un acceso de edición vigente |
| `rutaDeInicio()` | `User` | A dónde va cada rol al entrar o al abrir `/`, `/login` con sesión (QA-011) |
| `tieneMiPerfil()` | `User` | "Yo / Mi perfil" solo con persona vinculada, también para el admin (QA-014) |
| `puedeVerDashboardAsistencia()` | `User` | Dashboard de asistencia: admin, tesorero, asistente |
| `puedeAltaRapidaPersonas()` | `User` | Alta rápida de personas: admin y tesorero |
| `puedeCorregirCerrada()` | `AsistenciaController` | Asistencia cerrada: el admin la corrige (aviso + auditoría); los demás la ven en solo lectura con el motivo y el guardado da 403 (QE-015) |
| `index()` | `ClaseAppController` | App de Clases: admin todas; maestra su clase; miembro sin clase, mensaje; los demás, 403 (QE-016) |

## Matriz

"Sí" = lo ve y lo usa. "Oculto" = no aparece (y 403 por URL). "Gris" = se ve deshabilitado con el motivo.

| Pantalla / acción | Admin | Tesorero | Asistente | Servidor | Miembro |
|---|---|---|---|---|---|
| Inicio al entrar, `/` y app instalada | Principal | Principal | Principal | Principal | Mi perfil |
| Yo / Mi perfil y su estado de cuenta | Sí, si tiene persona | Sí, si tiene persona | Sí, si tiene persona | Sí, si tiene persona | Sí |
| Cumpleañeros | Sí | Sí | Sí | Oculto* (hoy se ve) | Oculto* (hoy se ve) |
| **Recuento**: lista de cultos y pantalla del culto | Sí | Sí | Oculto | Oculto | Oculto |
| Registrar / editar / eliminar sobres, suelto, egresos (culto abierto) | Sí | Sí | Oculto | Oculto | Oculto |
| Lo mismo con el culto **cerrado** | Sí (corrección auditada) | Gris ("Cerrado el dd/mm por X…"), salvo acceso de edición | Oculto | Oculto | Oculto |
| Asignar persona a un sobre anónimo | Sí | Sí (cerrado: gris) | Oculto | Oculto | Oculto |
| Cerrar recuento | Sí; gris si es futuro o está vacío | Ídem | Oculto | Oculto | Oculto |
| Firmas del recuento (también cerrado) | Sí, auditado | Sí, auditado | Oculto | Oculto | Oculto |
| PDF de recuento | Sí | Sí | Oculto | Oculto | Oculto |
| Crear cultos | Sí | Solo con acceso de edición vigente (si no, gris) | Oculto | Oculto | Oculto |
| Gestionar Cultos (lista, editar) | Sí | Oculto | Oculto | Oculto | Oculto |
| Eliminar culto | Sí (cerrado o con recuento: confirmación + motivo, auditado) | Oculto | Oculto | Oculto | Oculto |
| Módulo Accesos (`/admin/accesos`) | Sí | Oculto | Oculto | Oculto | Oculto |
| **Agregar persona (alta rápida)**: nombre, teléfono, correo | Sí | Sí (desde el sobre y desde Principal) | Oculto | Oculto | Oculto |
| Gestionar Personas: lista, fichas, compromisos, editar | Sí | **Oculto** | Oculto | Oculto | Oculto |
| Tarjetas con QR: tarjeta flotante en Personas, su PDF y el PDF masivo (`/personas/tarjetas`) | Sí | Oculto (403) | Oculto (403) | Oculto (403) | Oculto (403) |
| Tarjeta: **Ver contraseña** (solo cuentas de rol miembro, con `IBBLA_CONTRASENA_VISIBLE` encendido) | Sí, auditado | Oculto (403) | Oculto (403) | Oculto (403) | Oculto (403) |
| Tarjeta: **Restablecer acceso** (solo cuentas miembro) y **Generar QR nuevo** | Sí, auditado | Oculto (403) | Oculto (403) | Oculto (403) | Oculto (403) |
| Escanear su tarjeta (`/t/{token}`, pública con límite de intentos) | Inicia sesión con su contraseña | Ídem | Ídem | Ídem | Crea su acceso (usuario o correo) la primera vez; después, inicia sesión con su contraseña |
| Promesas futuras (`/promesas-futuras`): cortes, personas a mano, importar Excel, plantilla | Sí (auditado) | Oculto (403) | Oculto (403) | Oculto (403) | Oculto (403) |
| Asistencia web: registrar, editar, cerrar | Sí | Oculto | Sí | Oculto | Oculto |
| Asistencia **cerrada** | Sí: "Corregir" con aviso "la estás corrigiendo como administrador", auditado | Oculto | Gris ("Cerrada el dd/mm a las hh:mm por X · solo lectura"); guardar da 403 | Oculto | Oculto |
| App de asistencia (`/asistencia-app/*`) | Sí | 403 | Sí | 403 | 403 (maestra: solo su clase) |
| App de Clases | Todas | Oculto (403 por URL) | Oculto (403), salvo que sea maestra | Solo su clase si es maestra; si no, mensaje | Solo su clase si es maestra; si no, "No tenés una clase asignada. Pedile al administrador que te asigne una." |
| Marcar mi asistencia (QR) | — | — | — | Sí | — |
| Dashboard general y de promesas | Sí | Sí | Oculto | Oculto | Oculto |
| Dashboard de asistencia | Sí | Sí | **Sí** | Oculto | Oculto |
| Reportes financieros (ingresos, promesas, Reporte General, estados de cuenta, cumplimiento, lote) | Sí | Sí | Oculto | Oculto | Solo lo suyo (Mi perfil) |
| Excel financiero (ingresos, promesas) | Sí | Sí | **Oculto** | Oculto | Oculto |
| Reportes y Excel de asistencia (también el Excel del Dashboard de asistencia) | Sí | Sí | Sí | Oculto | Oculto |
| Avisos de WhatsApp | Sí | Sí | Oculto | Oculto | Oculto |
| Ministerios, Clases, Usuarios, Servidores, Cargar histórico | Sí | Oculto | Oculto | Oculto | Oculto |
| Usuarios: editar o borrar al Super Admin o a otra iglesia | No (404) | — | — | — | — |
| Auditoría e Historial de ingresos | Sí | Oculto | Oculto | Oculto | Oculto |

\* Decisión 2.7: cumpleañeros para admin, tesorero y asistente (el miembro no). La ruta sigue abierta a
todos hasta que el menú (equipo 4) lo oculte; cerrarla antes dejaría un enlace que da 403.
Reportes de asistencia: admin, tesorero y asistente (decisión 5 de `04-DECISIONES.md`, QE-012).

## Cuentas y contraseñas (Tarjetas con QR)

- Una cuenta se identifica con su **correo** o con un **nombre de usuario** sin @ (`users.usuario`, creado al escanear la
  tarjeta por quien no tiene correo). El login normal acepta los dos: con @ busca por correo exactamente como siempre;
  sin @, por usuario.
- **Contraseña visible para el administrador** (pedido expreso del cliente): copia cifrada con la llave de la app
  (`users.password_visible`, cast `encrypted`) **solo de cuentas de rol miembro** (`User::esCuentaMiembro()`); nunca
  de administrador, tesorero, asistente, servidor ni súper admin (el modelo la borra al guardar si el rol no es
  miembro). Se vacía si la contraseña cambia por una vía que no la actualiza (recuperar por correo). Interruptor
  `IBBLA_CONTRASENA_VISIBLE` (`config/ibbla.php`, encendido por defecto): apagado no se guarda, no se muestra y el
  botón no existe. El miembro ve el aviso donde escribe su contraseña (tarjeta y Mi cuenta).
- **Restablecer acceso** (recomendado): la contraseña queda imposible de adivinar, se cierran sus sesiones y el
  próximo escaneo pide una nueva (`users.debe_crear_contrasena`).

## Auditoría de acciones sensibles

Quedan en `audit_logs` con usuario, IP, fecha y detalle (`AuditLog::registrar`):

- `Eliminar culto`: culto, fecha, tipo, si estaba cerrado y por quién, total, cantidad de sobres,
  dinero suelto y egresos, motivo y la lista de sobres (número, persona, total, moneda, método).
- `Corregir firmas del recuento` / `Firmar recuento`: qué firma cambió (pastor, tesorero N), antes y
  después (nombre y una huella corta de la imagen, no la imagen) y si el culto estaba cerrado.
- `Asignar persona a sobre anónimo`: sobre, culto, total y persona asignada.
- `Alta rápida de persona`: persona creada.
- Cambios en cultos cerrados: corrección del admin o edición con acceso (módulo Accesos).
- Tarjetas con QR (`docs/plan-oct-2026/07-TARJETAS-QR.md`): `Ver contraseña de un miembro` (quién vio la de quién y si
  había copia; **nunca el valor**), `Restablecer acceso de un miembro`, `Generar QR nuevo de una persona` (sin el
  código), y las de la persona al escanear: `Crear acceso con la tarjeta QR` y `Crear contraseña nueva con la tarjeta QR`.
- `Corrección de culto cerrado` con la acción "Corregir asistencia cerrada del culto del dd/mm/aaaa": la
  asistencia, la fecha del culto y las cifras antes y después (QE-015). Sale también en Accesos >
  Últimos cambios.

## Pendientes de otros equipos para que la app cumpla la matriz

- **Equipo 4 (layouts, único que los toca)**, `resources/views/layouts/admin.blade.php`:
  - Línea del menú "Yo" (`mi-perfil.index`): condición `$u->tieneMiPerfil()` en lugar de
    `$u->isMiembro() || in_array($u->rol, [...])`.
  - "Dashboard de asistencia": condición `$u->puedeVerDashboardAsistencia()` (hoy `$puedeRecuento`, el
    asistente no lo ve).
  - Agregar "Agregar persona" (`personas.alta-rapida`) visible con
    `$u->puedeAltaRapidaPersonas() && ! $u->canAccessAdmin()`.
  - Cumpleañeros: ocultar a miembro y servidor (decisión 2.7) o dejarlo y corregir esta matriz.
- **Equipo 3a (reportes, QA-010)**: rutas `excel-ingresos` y `excel-promesas` al grupo admin+tesorero y
  los botones de Excel según esta matriz (hecho).
- **Equipo 5**: Mi perfil sin persona (vista `mi-perfil.sin-persona`), ya en su rama.
