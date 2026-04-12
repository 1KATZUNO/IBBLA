import React from 'react';
import Header from '../components/Header';

export default function ActionPicker({ culto, navigate }) {
    const tipoLabels = {
        'domingo_am': 'Domingo AM',
        'domingo_pm': 'Domingo PM',
        'miercoles': 'Miercoles',
    };

    const formatDate = (dateStr) => {
        const d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('es-CR', { weekday: 'long', day: 'numeric', month: 'long' });
    };

    return (
        <>
            <Header title={tipoLabels[culto.tipo_culto] || culto.tipo_culto} onBack={() => navigate('home')} />

            <div style={{ padding: '12px 16px 8px', fontSize: 14, color: 'var(--gray-500)' }}>
                {formatDate(culto.fecha)}
            </div>

            <div className="section">
                <button className="action-item" onClick={() => navigate('class-select', { culto })}>
                    <div className="action-icon" style={{ background: 'var(--purple-light)', color: 'var(--purple)' }}>
                        &#128218;
                    </div>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 600 }}>Clases</div>
                        <div style={{ fontSize: 13, color: 'var(--gray-500)' }}>Asistencia por clase</div>
                    </div>
                    <span style={{ color: 'var(--gray-300)', fontSize: 20 }}>&rsaquo;</span>
                </button>

                <button className="action-item" onClick={() => navigate('chapel', { culto })}>
                    <div className="action-icon" style={{ background: 'var(--primary-light)', color: 'var(--primary)' }}>
                        &#9962;
                    </div>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 600 }}>Capilla</div>
                        <div style={{ fontSize: 13, color: 'var(--gray-500)' }}>Conteo demografico + especiales</div>
                    </div>
                    <span style={{ color: 'var(--gray-300)', fontSize: 20 }}>&rsaquo;</span>
                </button>

                <button className="action-item" onClick={() => navigate('summary', { culto })}>
                    <div className="action-icon" style={{ background: 'var(--success-light)', color: 'var(--success)' }}>
                        &#128202;
                    </div>
                    <div style={{ flex: 1 }}>
                        <div style={{ fontWeight: 600 }}>Resumen</div>
                        <div style={{ fontSize: 13, color: 'var(--gray-500)' }}>Resumen general del culto</div>
                    </div>
                    <span style={{ color: 'var(--gray-300)', fontSize: 20 }}>&rsaquo;</span>
                </button>
            </div>
        </>
    );
}
