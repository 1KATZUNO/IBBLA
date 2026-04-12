import React, { useState, useEffect } from 'react';
import api from '../api';
import Header from '../components/Header';
import QuickAddStudent from './QuickAddStudent';
import Birthdays from './Birthdays';

export default function ClassAttendance({ culto, clase, navigate, showToast }) {
    const [maestrosChecked, setMaestrosChecked] = useState({});
    const [studentsPresent, setStudentsPresent] = useState({});
    const [students, setStudents] = useState(clase.estudiantes || []);
    const [showQuickAdd, setShowQuickAdd] = useState(false);
    const [showBirthdays, setShowBirthdays] = useState(false);
    const [saving, setSaving] = useState(false);

    // Initialize all maestros unchecked, all students absent
    useEffect(() => {
        const mc = {};
        (clase.maestros || []).forEach(m => { mc[m.id] = false; });
        setMaestrosChecked(mc);

        const sp = {};
        (clase.estudiantes || []).forEach(s => { sp[s.id] = false; });
        setStudentsPresent(sp);
    }, [clase]);

    const toggleMaestro = (id) => {
        setMaestrosChecked(prev => ({ ...prev, [id]: !prev[id] }));
    };

    const toggleStudent = (id) => {
        setStudentsPresent(prev => ({ ...prev, [id]: !prev[id] }));
    };

    const selectedMaestros = Object.entries(maestrosChecked).filter(([, v]) => v).map(([k]) => Number(k));
    const presentStudents = Object.entries(studentsPresent).filter(([, v]) => v).map(([k]) => Number(k));
    const totalPresent = presentStudents.length + selectedMaestros.length;

    const handleSave = async () => {
        setSaving(true);
        try {
            await api.post('/asistencia/clase', {
                culto_id: culto.id,
                clase_asistencia_id: clase.id,
                hombres: presentStudents.length, // Total students (no gender data)
                mujeres: 0,
                maestros_ids: selectedMaestros,
                estudiantes_presentes_ids: presentStudents,
            });
            showToast('Asistencia guardada');
            navigate('class-select', { culto });
        } catch (err) {
            showToast(err.response?.data?.message || 'Error al guardar');
        } finally {
            setSaving(false);
        }
    };

    const handleStudentAdded = (newStudent) => {
        setStudents(prev => [...prev, newStudent]);
        setStudentsPresent(prev => ({ ...prev, [newStudent.id]: true }));
        setShowQuickAdd(false);
        showToast(`${newStudent.nombre} agregado`);
    };

    return (
        <>
            <Header
                title={clase.nombre}
                onBack={() => navigate('class-select', { culto })}
                rightAction={
                    <span style={{ fontSize: 14, opacity: 0.9 }}>{totalPresent} presentes</span>
                }
            />

            {/* Maestros section */}
            {clase.tiene_maestros && clase.maestros.length > 0 && (
                <div className="section">
                    <p className="section-title">Maestros</p>
                    {clase.maestros.map(maestro => (
                        <div key={maestro.id} className="checkbox-row">
                            <input
                                type="checkbox"
                                checked={maestrosChecked[maestro.id] || false}
                                onChange={() => toggleMaestro(maestro.id)}
                            />
                            <span style={{ fontSize: 15 }}>{maestro.nombre}</span>
                        </div>
                    ))}
                </div>
            )}

            {/* Students section */}
            <div className="section">
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                    <p className="section-title" style={{ margin: 0 }}>Alumnos ({presentStudents.length}/{students.length})</p>
                    <button
                        className="btn btn-sm btn-outline"
                        onClick={() => setShowQuickAdd(true)}
                        type="button"
                    >
                        + Agregar
                    </button>
                </div>

                {students.length === 0 ? (
                    <p style={{ color: 'var(--gray-500)', fontSize: 14, textAlign: 'center', padding: 20 }}>
                        No hay alumnos en esta clase
                    </p>
                ) : (
                    students.map(student => (
                        <div key={student.id} className="student-row">
                            <span className="student-name">{student.nombre}</span>
                            <button
                                className={`toggle ${studentsPresent[student.id] ? 'active' : ''}`}
                                onClick={() => toggleStudent(student.id)}
                                type="button"
                            />
                        </div>
                    ))
                )}
            </div>

            {/* Birthdays collapsible */}
            <div className="section" style={{ paddingTop: 0 }}>
                <button
                    className="collapsible-header"
                    onClick={() => setShowBirthdays(!showBirthdays)}
                    type="button"
                    style={{ width: '100%', border: 'none' }}
                >
                    <h3>&#127874; Cumpleaneros del mes</h3>
                    <span style={{ transform: showBirthdays ? 'rotate(180deg)' : 'none', transition: '0.2s' }}>&#9660;</span>
                </button>
                {showBirthdays && <Birthdays claseId={clase.id} />}
            </div>

            {/* Save button */}
            <div style={{ padding: '16px', position: 'sticky', bottom: 0, background: 'white', borderTop: '1px solid var(--gray-200)' }}>
                <button className="btn btn-success" onClick={handleSave} disabled={saving}>
                    {saving ? <span className="spinner" style={{ width: 20, height: 20 }} /> : 'Guardar Asistencia'}
                </button>
            </div>

            {/* Quick Add Modal */}
            {showQuickAdd && (
                <QuickAddStudent
                    claseId={clase.id}
                    onClose={() => setShowQuickAdd(false)}
                    onAdded={handleStudentAdded}
                />
            )}
        </>
    );
}
