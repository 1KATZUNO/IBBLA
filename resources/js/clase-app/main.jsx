import React from 'react';
import { createRoot } from 'react-dom/client';
import ClaseApp from './ClaseApp.jsx';

const el = document.getElementById('clase-app');
if (el) {
    const props = {
        slug: el.dataset.claseSlug,
        claseNombre: el.dataset.claseNombre,
        claseColor: el.dataset.claseColor,
        tenantSiglas: el.dataset.tenantSiglas,
        tenantNombre: el.dataset.tenantNombre,
        userId: parseInt(el.dataset.userId, 10),
        userName: el.dataset.userName,
        csrfToken: el.dataset.csrf,
    };
    createRoot(el).render(<ClaseApp {...props} />);
}
