import React, { useEffect, useState } from 'react';
import api from '../api';

export default function Birthdays({ claseId }) {
    const [cumpleaneros, setCumpleaneros] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get(`/clases/${claseId}/cumpleaneros`)
            .then(res => setCumpleaneros(res.data))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [claseId]);

    if (loading) {
        return <div style={{ textAlign: 'center', padding: 20 }}><div className="spinner" /></div>;
    }

    if (cumpleaneros.length === 0) {
        return <p style={{ color: 'var(--gray-500)', fontSize: 14, textAlign: 'center', padding: 16 }}>No hay cumpleaneros este mes</p>;
    }

    const meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    return (
        <div>
            {cumpleaneros.map(c => {
                const fecha = new Date(c.fecha_nacimiento + 'T12:00:00');
                const hoy = new Date();
                const edad = hoy.getFullYear() - fecha.getFullYear();
                return (
                    <div key={c.id} className="birthday-item">
                        <div className="birthday-day">{c.dia}</div>
                        <div>
                            <div style={{ fontWeight: 600, fontSize: 14 }}>{c.nombre}</div>
                            <div style={{ fontSize: 12, color: 'var(--gray-500)' }}>
                                Cumple {edad} anos
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
