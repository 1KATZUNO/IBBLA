import React, { useEffect, useState } from 'react';
import api from '../api';
import Header from '../components/Header';

export default function Home({ user, navigate, onLogout }) {
    const [cultos, setCultos] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/cultos')
            .then(res => setCultos(res.data))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const handleLogout = async () => {
        try {
            await api.post('/login', {}); // We'll just clear state
        } catch {}
        onLogout();
    };

    const tipoLabels = {
        'domingo_am': 'Domingo AM',
        'domingo_pm': 'Domingo PM',
        'miercoles': 'Miercoles',
    };

    const formatDate = (dateStr) => {
        const d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('es-CR', { weekday: 'short', day: 'numeric', month: 'short' });
    };

    return (
        <>
            <Header
                title="Asistencia"
                rightAction={
                    <button onClick={handleLogout} style={{ background: 'none', border: 'none', color: 'white', fontSize: 13, cursor: 'pointer', opacity: 0.8 }}>
                        Salir
                    </button>
                }
            />

            <div className="section">
                <p style={{ fontSize: 14, color: 'var(--gray-500)', marginBottom: 4 }}>Hola, <strong>{user.name}</strong></p>
                <p className="section-title">Cultos abiertos</p>

                {loading ? (
                    <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" /></div>
                ) : cultos.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: 40, color: 'var(--gray-500)' }}>
                        No hay cultos abiertos
                    </div>
                ) : (
                    cultos.map(culto => (
                        <button
                            key={culto.id}
                            className="action-item"
                            onClick={() => navigate('actions', { culto })}
                        >
                            <div className="action-icon" style={{ background: 'var(--primary-light)', color: 'var(--primary)' }}>
                                &#128218;
                            </div>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 600 }}>{formatDate(culto.fecha)}</div>
                                <div style={{ fontSize: 13, color: 'var(--gray-500)' }}>
                                    {tipoLabels[culto.tipo_culto] || culto.tipo_culto}
                                    {culto.tiene_asistencia && <span className="pill" style={{ background: 'var(--success-light)', color: 'var(--success)', marginLeft: 8 }}>Con datos</span>}
                                </div>
                            </div>
                            <span style={{ color: 'var(--gray-300)', fontSize: 20 }}>&rsaquo;</span>
                        </button>
                    ))
                )}
            </div>
        </>
    );
}
