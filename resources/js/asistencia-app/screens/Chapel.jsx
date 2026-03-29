import React, { useState } from 'react';
import api from '../api';
import Header from '../components/Header';
import NumCounter from '../components/NumCounter';
import SpecialEntryForm from './SpecialEntryForm';

export default function Chapel({ culto, navigate, showToast }) {
    const [data, setData] = useState({
        chapel_adultos_hombres: 0,
        chapel_adultos_mujeres: 0,
        chapel_jovenes_masculinos: 0,
        chapel_jovenes_femeninas: 0,
    });

    const [salvos, setSalvos] = useState({ adulto_hombre: 0, adulto_mujer: 0, joven_hombre: 0, joven_mujer: 0, nino: 0, nina: 0 });
    const [bautismos, setBautismos] = useState({ adulto_hombre: 0, adulto_mujer: 0, joven_hombre: 0, joven_mujer: 0, nino: 0, nina: 0 });
    const [visitas, setVisitas] = useState({ adulto_hombre: 0, adulto_mujer: 0, joven_hombre: 0, joven_mujer: 0, nino: 0, nina: 0 });

    const [registrosEspeciales, setRegistrosEspeciales] = useState([]);
    const [showSpecialForm, setShowSpecialForm] = useState(false);
    const [saving, setSaving] = useState(false);
    const [openSection, setOpenSection] = useState('capilla');

    const updateField = (field, val) => {
        setData(prev => ({ ...prev, [field]: val }));
    };

    const total = Object.values(data).reduce((s, v) => s + v, 0);

    const handleSave = async () => {
        setSaving(true);
        try {
            const payload = {
                culto_id: culto.id,
                ...data,
                ...Object.fromEntries(Object.entries(salvos).map(([k, v]) => [`salvos_${k}`, v])),
                ...Object.fromEntries(Object.entries(bautismos).map(([k, v]) => [`bautismos_${k}`, v])),
                ...Object.fromEntries(Object.entries(visitas).map(([k, v]) => [`visitas_${k}`, v])),
                registros_especiales: registrosEspeciales,
            };
            await api.post('/asistencia/capilla', payload);
            showToast('Capilla guardada');
            navigate('actions', { culto });
        } catch (err) {
            showToast(err.response?.data?.message || 'Error al guardar');
        } finally {
            setSaving(false);
        }
    };

    const addRegistro = (registro) => {
        setRegistrosEspeciales(prev => [...prev, registro]);
        setShowSpecialForm(false);
    };

    const removeRegistro = (idx) => {
        setRegistrosEspeciales(prev => prev.filter((_, i) => i !== idx));
    };

    const toggleSection = (name) => {
        setOpenSection(openSection === name ? null : name);
    };

    const countersLabels = [
        ['chapel_adultos_hombres', 'Adultos Hombres'],
        ['chapel_adultos_mujeres', 'Adultos Mujeres'],
        ['chapel_jovenes_masculinos', 'Jovenes Masculinos'],
        ['chapel_jovenes_femeninas', 'Jovenes Femeninas'],
    ];

    const salvosLabels = [
        ['adulto_hombre', 'Adulto H'], ['adulto_mujer', 'Adulto M'],
        ['joven_hombre', 'Joven H'], ['joven_mujer', 'Joven M'],
        ['nino', 'Nino'], ['nina', 'Nina'],
    ];

    return (
        <>
            <Header
                title="Capilla"
                onBack={() => navigate('actions', { culto })}
                rightAction={<span style={{ fontSize: 14, opacity: 0.9 }}>Total: {total}</span>}
            />

            {/* Capilla counts */}
            <div className="section">
                <button className="collapsible-header" onClick={() => toggleSection('capilla')} type="button" style={{ width: '100%', border: 'none' }}>
                    <h3>&#9962; Capilla</h3>
                    <span style={{ transform: openSection === 'capilla' ? 'rotate(180deg)' : 'none', transition: '0.2s' }}>&#9660;</span>
                </button>
                {openSection === 'capilla' && countersLabels.map(([field, label]) => (
                    <NumCounter key={field} label={label} value={data[field]} onChange={(v) => updateField(field, v)} />
                ))}
            </div>

            {/* Salvos */}
            <div className="section" style={{ paddingTop: 0 }}>
                <button className="collapsible-header" onClick={() => toggleSection('salvos')} type="button" style={{ width: '100%', border: 'none', background: 'var(--success-light)' }}>
                    <h3 style={{ color: 'var(--success)' }}>&#10013; Salvos</h3>
                    <span style={{ transform: openSection === 'salvos' ? 'rotate(180deg)' : 'none', transition: '0.2s' }}>&#9660;</span>
                </button>
                {openSection === 'salvos' && salvosLabels.map(([field, label]) => (
                    <NumCounter key={field} label={label} value={salvos[field]} onChange={(v) => setSalvos(prev => ({ ...prev, [field]: v }))} />
                ))}
            </div>

            {/* Bautismos */}
            <div className="section" style={{ paddingTop: 0 }}>
                <button className="collapsible-header" onClick={() => toggleSection('bautismos')} type="button" style={{ width: '100%', border: 'none', background: 'var(--primary-light)' }}>
                    <h3 style={{ color: 'var(--primary)' }}>&#128167; Bautismos</h3>
                    <span style={{ transform: openSection === 'bautismos' ? 'rotate(180deg)' : 'none', transition: '0.2s' }}>&#9660;</span>
                </button>
                {openSection === 'bautismos' && salvosLabels.map(([field, label]) => (
                    <NumCounter key={field} label={label} value={bautismos[field]} onChange={(v) => setBautismos(prev => ({ ...prev, [field]: v }))} />
                ))}
            </div>

            {/* Visitas */}
            <div className="section" style={{ paddingTop: 0 }}>
                <button className="collapsible-header" onClick={() => toggleSection('visitas')} type="button" style={{ width: '100%', border: 'none', background: 'var(--purple-light)' }}>
                    <h3 style={{ color: 'var(--purple)' }}>&#128101; Visitas</h3>
                    <span style={{ transform: openSection === 'visitas' ? 'rotate(180deg)' : 'none', transition: '0.2s' }}>&#9660;</span>
                </button>
                {openSection === 'visitas' && salvosLabels.map(([field, label]) => (
                    <NumCounter key={field} label={label} value={visitas[field]} onChange={(v) => setVisitas(prev => ({ ...prev, [field]: v }))} />
                ))}
            </div>

            {/* Registros Especiales */}
            <div className="section" style={{ paddingTop: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                    <p className="section-title" style={{ margin: 0 }}>Registros Especiales ({registrosEspeciales.length})</p>
                    <button className="btn btn-sm btn-outline" onClick={() => setShowSpecialForm(true)} type="button">
                        + Agregar
                    </button>
                </div>

                {registrosEspeciales.map((reg, idx) => (
                    <div key={idx} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid var(--gray-100)' }}>
                        <div>
                            <span className="pill" style={{
                                background: reg.tipo === 'visita' ? 'var(--purple-light)' : reg.tipo === 'salvo' ? 'var(--success-light)' : 'var(--primary-light)',
                                color: reg.tipo === 'visita' ? 'var(--purple)' : reg.tipo === 'salvo' ? 'var(--success)' : 'var(--primary)',
                                marginRight: 8
                            }}>
                                {reg.tipo}
                            </span>
                            <strong>{reg.nombre}</strong>
                            <span style={{ fontSize: 13, color: 'var(--gray-500)', marginLeft: 6 }}>
                                {reg.genero} {reg.edad ? `· ${reg.edad} anos` : ''}
                            </span>
                        </div>
                        <button onClick={() => removeRegistro(idx)} style={{ background: 'none', border: 'none', color: 'var(--danger)', cursor: 'pointer', fontSize: 18 }}>
                            &times;
                        </button>
                    </div>
                ))}
            </div>

            {/* Save button */}
            <div style={{ padding: '16px', position: 'sticky', bottom: 0, background: 'white', borderTop: '1px solid var(--gray-200)' }}>
                <button className="btn btn-success" onClick={handleSave} disabled={saving}>
                    {saving ? <span className="spinner" style={{ width: 20, height: 20 }} /> : 'Guardar Capilla'}
                </button>
            </div>

            {showSpecialForm && (
                <SpecialEntryForm onClose={() => setShowSpecialForm(false)} onAdd={addRegistro} />
            )}
        </>
    );
}
