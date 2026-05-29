/**
 * Cliente API para la app de Clase (Registro Horeb genérico).
 * Cada función llama al backend Laravel usando sesión actual + CSRF.
 */

let CSRF = '';
let BASE = '';

export function configure({ slug, csrfToken }) {
    CSRF = csrfToken;
    BASE = `/clase-app/${slug}`;
}

async function req(path, opts = {}) {
    const res = await fetch(BASE + path, {
        ...opts,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
            ...(opts.headers || {}),
        },
    });
    if (!res.ok) {
        const txt = await res.text();
        throw new Error(`HTTP ${res.status}: ${txt}`);
    }
    return res.json();
}

export const api = {
    getData: (año) => req(`/data${año ? '?año=' + año : ''}`),

    storeMember: (data) => req('/personas/miembro', { method: 'POST', body: JSON.stringify(data) }),
    storeVisitor: (data) => req('/personas/visita', { method: 'POST', body: JSON.stringify(data) }),
    updatePersona: (id, data) => req(`/personas/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
    deletePersona: (id) => req(`/personas/${id}`, { method: 'DELETE' }),
    convertirVisita: (id) => req(`/personas/${id}/convertir`, { method: 'POST' }),

    toggleAsistencia: (personaId, cultoId) =>
        req('/asistencia/toggle', { method: 'POST', body: JSON.stringify({ persona_id: personaId, culto_id: cultoId }) }),

    upsertVisitacion: (data) =>
        req('/visitacion', { method: 'POST', body: JSON.stringify(data) }),
};
