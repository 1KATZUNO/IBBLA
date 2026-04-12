import React, { useEffect, useState } from 'react';
import api from '../api';
import Header from '../components/Header';

export default function ClassSelect({ culto, navigate }) {
    const [clases, setClases] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/clases')
            .then(res => setClases(res.data))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    return (
        <>
            <Header title="Clases" onBack={() => navigate('actions', { culto })} />

            <div className="section">
                {loading ? (
                    <div style={{ textAlign: 'center', padding: 40 }}><div className="spinner" /></div>
                ) : clases.length === 0 ? (
                    <div style={{ textAlign: 'center', padding: 40, color: 'var(--gray-500)' }}>
                        No hay clases activas
                    </div>
                ) : (
                    clases.map(clase => (
                        <button
                            key={clase.id}
                            className="action-item"
                            onClick={() => navigate('class-attendance', { culto, clase })}
                        >
                            <div
                                className="action-icon"
                                style={{ background: clase.color + '20', color: clase.color }}
                            >
                                &#128100;
                            </div>
                            <div style={{ flex: 1 }}>
                                <div style={{ fontWeight: 600 }}>{clase.nombre}</div>
                                <div style={{ fontSize: 13, color: 'var(--gray-500)' }}>
                                    {clase.estudiantes.length} alumnos
                                    {clase.tiene_maestros && ` · ${clase.maestros.length} maestros`}
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
