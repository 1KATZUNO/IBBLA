import React, { useState } from 'react';

export default function SpecialEntryForm({ onClose, onAdd }) {
    const [tipo, setTipo] = useState('visita');
    const [nombre, setNombre] = useState('');
    const [genero, setGenero] = useState('M');
    const [edad, setEdad] = useState('');
    const [telefono, setTelefono] = useState('');
    const [fechaNacimiento, setFechaNacimiento] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        onAdd({
            tipo,
            nombre,
            genero,
            edad: edad ? parseInt(edad) : null,
            telefono: telefono || null,
            fecha_nacimiento: fechaNacimiento || null,
        });
    };

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-content" onClick={e => e.stopPropagation()}>
                <h2 className="modal-title">Registro Especial</h2>

                <form onSubmit={handleSubmit}>
                    {/* Type selector */}
                    <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                        {['visita', 'salvo', 'bautismo'].map(t => (
                            <button
                                key={t}
                                type="button"
                                className={`special-type-btn ${tipo === t ? 'active' : ''}`}
                                onClick={() => setTipo(t)}
                            >
                                {t.charAt(0).toUpperCase() + t.slice(1)}
                            </button>
                        ))}
                    </div>

                    <div style={{ marginBottom: 12 }}>
                        <label className="label">Nombre *</label>
                        <input className="input" value={nombre} onChange={e => setNombre(e.target.value)} required />
                    </div>

                    <div style={{ display: 'flex', gap: 12, marginBottom: 12 }}>
                        <div style={{ flex: 1 }}>
                            <label className="label">Genero *</label>
                            <div style={{ display: 'flex', gap: 8 }}>
                                <button
                                    type="button"
                                    className={`special-type-btn ${genero === 'M' ? 'active' : ''}`}
                                    onClick={() => setGenero('M')}
                                    style={{ flex: 1 }}
                                >
                                    Masculino
                                </button>
                                <button
                                    type="button"
                                    className={`special-type-btn ${genero === 'F' ? 'active' : ''}`}
                                    onClick={() => setGenero('F')}
                                    style={{ flex: 1 }}
                                >
                                    Femenino
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: 12, marginBottom: 12 }}>
                        <div style={{ flex: 1 }}>
                            <label className="label">Edad</label>
                            <input className="input" type="number" min="0" max="120" value={edad} onChange={e => setEdad(e.target.value)} />
                        </div>
                        <div style={{ flex: 1 }}>
                            <label className="label">Telefono</label>
                            <input className="input" type="tel" value={telefono} onChange={e => setTelefono(e.target.value)} />
                        </div>
                    </div>

                    {tipo === 'bautismo' && (
                        <div style={{ marginBottom: 12 }}>
                            <label className="label">Fecha de nacimiento</label>
                            <input className="input" type="date" value={fechaNacimiento} onChange={e => setFechaNacimiento(e.target.value)} />
                        </div>
                    )}

                    <div style={{ display: 'flex', gap: 12, marginTop: 20 }}>
                        <button type="button" className="btn btn-outline" onClick={onClose}>Cancelar</button>
                        <button type="submit" className="btn btn-primary" disabled={!nombre.trim()}>Agregar</button>
                    </div>
                </form>
            </div>
        </div>
    );
}
