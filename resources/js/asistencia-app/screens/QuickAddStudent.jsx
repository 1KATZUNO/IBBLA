import React, { useState } from 'react';
import api from '../api';

export default function QuickAddStudent({ claseId, onClose, onAdded }) {
    const [nombre, setNombre] = useState('');
    const [fechaNacimiento, setFechaNacimiento] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        try {
            const res = await api.post('/personas/quick-add', {
                nombre,
                fecha_nacimiento: fechaNacimiento || null,
                clase_asistencia_id: claseId,
            });
            onAdded(res.data);
        } catch (err) {
            setError(err.response?.data?.message || 'Error al agregar');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <h2 className="modal-title">Agregar Alumno</h2>

                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: 12 }}>
                        <label className="label">Nombre completo *</label>
                        <input
                            className="input"
                            value={nombre}
                            onChange={e => setNombre(e.target.value)}
                            placeholder="Nombre del alumno"
                            required
                            autoFocus
                        />
                    </div>

                    <div style={{ marginBottom: 16 }}>
                        <label className="label">Fecha de nacimiento</label>
                        <input
                            className="input"
                            type="date"
                            value={fechaNacimiento}
                            onChange={e => setFechaNacimiento(e.target.value)}
                        />
                    </div>

                    {error && (
                        <div style={{ background: 'var(--danger-light)', color: 'var(--danger)', padding: 10, borderRadius: 8, marginBottom: 12, fontSize: 14 }}>
                            {error}
                        </div>
                    )}

                    <div style={{ display: 'flex', gap: 12 }}>
                        <button type="button" className="btn btn-outline" onClick={onClose}>Cancelar</button>
                        <button type="submit" className="btn btn-primary" disabled={saving || !nombre.trim()}>
                            {saving ? <span className="spinner" style={{ width: 20, height: 20 }} /> : 'Agregar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
