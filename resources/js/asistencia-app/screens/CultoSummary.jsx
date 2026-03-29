import React, { useEffect, useState } from 'react';
import api from '../api';
import Header from '../components/Header';

export default function CultoSummary({ culto, navigate }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        api.get(`/cultos/${culto.id}/resumen`)
            .then(res => setData(res.data))
            .catch(err => setError(err.response?.data?.message || 'Error al cargar resumen'))
            .finally(() => setLoading(false));
    }, [culto.id]);

    const formatDate = (dateStr) => {
        const d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('es-CR', { weekday: 'long', day: 'numeric', month: 'long' });
    };

    if (loading) {
        return (
            <>
                <Header title="Resumen" onBack={() => navigate('actions', { culto })} />
                <div style={{ textAlign: 'center', padding: 60 }}><div className="spinner" /></div>
            </>
        );
    }

    if (error) {
        return (
            <>
                <Header title="Resumen" onBack={() => navigate('actions', { culto })} />
                <div style={{ textAlign: 'center', padding: 40, color: 'var(--gray-500)' }}>{error}</div>
            </>
        );
    }

    const { totales, capilla, por_clase, especiales } = data;

    return (
        <>
            <Header title="Resumen del Culto" onBack={() => navigate('actions', { culto })} />

            <div style={{ padding: '12px 16px 8px', fontSize: 14, color: 'var(--gray-500)' }}>
                {formatDate(data.culto.fecha)}
            </div>

            {/* Big total */}
            <div style={{ textAlign: 'center', padding: '20px 16px', background: 'linear-gradient(135deg, var(--primary), var(--purple))', margin: '0 16px', borderRadius: 16 }}>
                <div style={{ fontSize: 48, fontWeight: 800, color: 'white' }}>{totales.total_general}</div>
                <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 14 }}>Total General</div>
            </div>

            {/* Stats grid */}
            <div className="section">
                <div className="stat-grid">
                    <div className="stat-card">
                        <div className="stat-value" style={{ color: 'var(--primary)' }}>{totales.total_hombres}</div>
                        <div className="stat-label">Hombres</div>
                    </div>
                    <div className="stat-card">
                        <div className="stat-value" style={{ color: 'var(--purple)' }}>{totales.total_mujeres}</div>
                        <div className="stat-label">Mujeres</div>
                    </div>
                    <div className="stat-card">
                        <div className="stat-value" style={{ color: 'var(--warning)' }}>{totales.total_maestros}</div>
                        <div className="stat-label">Maestros/as</div>
                    </div>
                    <div className="stat-card">
                        <div className="stat-value" style={{ color: 'var(--success)' }}>{totales.total_ninos}</div>
                        <div className="stat-label">Ninos (clases)</div>
                    </div>
                </div>
            </div>

            {/* Chapel details */}
            <div className="section" style={{ paddingTop: 0 }}>
                <p className="section-title">Capilla ({capilla.total})</p>
                <div className="card" style={{ margin: 0 }}>
                    <div className="detail-row"><span className="label">Adultos H</span><span className="value">{capilla.adultos_hombres}</span></div>
                    <div className="detail-row"><span className="label">Adultos M</span><span className="value">{capilla.adultos_mujeres}</span></div>
                    <div className="detail-row"><span className="label">Jovenes M</span><span className="value">{capilla.jovenes_masculinos}</span></div>
                    <div className="detail-row"><span className="label">Jovenes F</span><span className="value">{capilla.jovenes_femeninas}</span></div>
                </div>
            </div>

            {/* Per-class breakdown */}
            {por_clase.length > 0 && (
                <div className="section" style={{ paddingTop: 0 }}>
                    <p className="section-title">Por Clase ({totales.total_clases})</p>
                    {por_clase.map((c, idx) => (
                        <div key={idx} className="card" style={{ margin: '0 0 8px 0', borderLeft: `4px solid ${c.color}` }}>
                            <div className="card-title">{c.clase}</div>
                            <div style={{ display: 'flex', gap: 16, fontSize: 14, color: 'var(--gray-500)' }}>
                                <span>Alumnos: <strong style={{ color: 'var(--gray-900)' }}>{c.total_alumnos}</strong></span>
                                <span>Maestros: <strong style={{ color: 'var(--gray-900)' }}>{c.total_maestros}</strong></span>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Specials */}
            <div className="section" style={{ paddingTop: 0 }}>
                <p className="section-title">Especiales</p>
                <div className="stat-grid" style={{ gridTemplateColumns: '1fr 1fr 1fr' }}>
                    <div className="stat-card" style={{ background: 'var(--purple-light)' }}>
                        <div className="stat-value" style={{ color: 'var(--purple)', fontSize: 24 }}>{especiales.visitas}</div>
                        <div className="stat-label">Visitas</div>
                    </div>
                    <div className="stat-card" style={{ background: 'var(--success-light)' }}>
                        <div className="stat-value" style={{ color: 'var(--success)', fontSize: 24 }}>{especiales.salvos}</div>
                        <div className="stat-label">Salvos</div>
                    </div>
                    <div className="stat-card" style={{ background: 'var(--primary-light)' }}>
                        <div className="stat-value" style={{ color: 'var(--primary)', fontSize: 24 }}>{especiales.bautismos}</div>
                        <div className="stat-label">Bautismos</div>
                    </div>
                </div>

                {especiales.registros && especiales.registros.length > 0 && (
                    <div style={{ marginTop: 12 }}>
                        <p style={{ fontSize: 13, fontWeight: 600, color: 'var(--gray-500)', marginBottom: 8 }}>Detalle registros:</p>
                        {especiales.registros.map((r, idx) => (
                            <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '6px 0', borderBottom: '1px solid var(--gray-100)', fontSize: 14 }}>
                                <span className="pill" style={{
                                    background: r.tipo === 'visita' ? 'var(--purple-light)' : r.tipo === 'salvo' ? 'var(--success-light)' : 'var(--primary-light)',
                                    color: r.tipo === 'visita' ? 'var(--purple)' : r.tipo === 'salvo' ? 'var(--success)' : 'var(--primary)',
                                }}>
                                    {r.tipo}
                                </span>
                                <strong>{r.nombre}</strong>
                                <span style={{ color: 'var(--gray-500)' }}>{r.genero} {r.edad ? `· ${r.edad}a` : ''}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div style={{ height: 24 }} />
        </>
    );
}
