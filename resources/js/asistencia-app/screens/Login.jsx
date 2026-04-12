import React, { useState } from 'react';
import api from '../api';

export default function Login({ onLogin }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const res = await api.post('/login', { email, password });
            onLogin(res.data.user);
        } catch (err) {
            setError(err.response?.data?.message || 'Error de conexion');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="app-container" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'center', padding: 24, minHeight: '100vh' }}>
            <div style={{ textAlign: 'center', marginBottom: 32 }}>
                <div style={{ fontSize: 48, marginBottom: 8 }}>&#9854;</div>
                <h1 style={{ fontSize: 24, fontWeight: 700 }}>Asistencia</h1>
                <p style={{ color: 'var(--gray-500)', fontSize: 14, marginTop: 4 }}>Inicia sesion para continuar</p>
            </div>

            <form onSubmit={handleSubmit}>
                <div style={{ marginBottom: 16 }}>
                    <label className="label">Correo electronico</label>
                    <input
                        className="input"
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        placeholder="tu@correo.com"
                        required
                        autoComplete="email"
                    />
                </div>
                <div style={{ marginBottom: 24 }}>
                    <label className="label">Contrasena</label>
                    <input
                        className="input"
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="********"
                        required
                        autoComplete="current-password"
                    />
                </div>

                {error && (
                    <div style={{ background: 'var(--danger-light)', color: 'var(--danger)', padding: 12, borderRadius: 10, marginBottom: 16, fontSize: 14, textAlign: 'center' }}>
                        {error}
                    </div>
                )}

                <button className="btn btn-primary" type="submit" disabled={loading}>
                    {loading ? <span className="spinner" style={{ width: 20, height: 20 }} /> : 'Iniciar Sesion'}
                </button>
            </form>
        </div>
    );
}
