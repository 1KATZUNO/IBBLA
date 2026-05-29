import React, { useState, useEffect, useMemo } from 'react';
import {
  Calendar, Users, UserPlus, BarChart3, Cake, Phone, MapPin, Plus, Pencil,
  Trash2, Download, ArrowRightLeft, X, Check, ChevronLeft, ChevronRight,
  Home, TrendingUp, AlertCircle, Search, BookOpen, HeartHandshake, FileText, LogOut
} from 'lucide-react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line
} from 'recharts';
import * as XLSX from 'xlsx';
import { api, configure } from './api';

/* ============================================================
   ClaseApp — versión genérica de "Registro Horeb"
   Conectada al backend Laravel via REST. Cada clase del sistema
   (Horeb 55-64, Jóvenes, etc.) usa esta misma app montada en
   /app/clase/{slug}.
   ============================================================ */

const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const MONTHS_SHORT = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

const PALETTE = {
  ink: '#1A1726', indigo: '#2A2570', indigoDeep: '#1E1B4B',
  amber: '#C97A1A', amberSoft: '#F5C572',
  cream: '#FBF7EE', paper: '#FFFFFF', line: '#E8E2D5',
  muted: '#6B6359', rose: '#B23A48', sage: '#5A7A4F',
};

const fmtKey = (d) => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
const fmtShort = (d) => `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]}`;
function parseBirthday(s) {
  if (!s) return null;
  const parts = String(s).split(/[\/\-\.]/).map(p => parseInt(p, 10));
  if (parts.length < 2 || isNaN(parts[0]) || isNaN(parts[1])) return null;
  return { day: parts[0], month: parts[1] };
}

function GlobalStyles() {
  return (
    <style>{`
      @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap');
      .ff-display { font-family: 'Fraunces', Georgia, serif; font-optical-sizing: auto; }
      .ff-body { font-family: 'DM Sans', system-ui, sans-serif; }
      .ff-num { font-family: 'Fraunces', Georgia, serif; font-feature-settings: "tnum"; }
      .clase-app { font-family: 'DM Sans', system-ui, sans-serif; }
      .clase-app *::-webkit-scrollbar { width: 8px; height: 8px; }
      .clase-app *::-webkit-scrollbar-thumb { background: #d6cfc0; border-radius: 4px; }
      .grain { background-image: radial-gradient(rgba(0,0,0,0.025) 1px, transparent 1px); background-size: 3px 3px; }
    `}</style>
  );
}

export default function ClaseApp(props) {
  const { slug, claseNombre, claseColor, tenantSiglas, tenantNombre, userName, csrfToken } = props;
  const [year] = useState(new Date().getFullYear());
  const [state, setState] = useState({ members: [], visitors: [], attendance: {}, visitations: {}, cultos: [], clase: { nombre: claseNombre, color: claseColor } });
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState('inicio');
  const today = new Date();
  const initialMonth = today.getFullYear() === year ? today.getMonth() : 0;
  const [month, setMonth] = useState(initialMonth);
  const [toast, setToast] = useState(null);

  useEffect(() => {
    configure({ slug, csrfToken });
    api.getData(year).then(data => {
      setState(data);
      setLoading(false);
    }).catch(err => {
      console.error(err);
      setLoading(false);
    });
  }, [slug, year, csrfToken]);

  const reload = async () => {
    const data = await api.getData(year);
    setState(data);
  };

  const showToast = (msg) => {
    setToast(msg);
    setTimeout(() => setToast(null), 2500);
  };

  // Sundays calendario (para asistencia / cumpleañeros / etc.)
  const SUNDAYS = useMemo(() => MONTHS.map((_, m) => {
    const out = []; const d = new Date(year, m, 1);
    while (d.getMonth() === m) { if (d.getDay() === 0) out.push(new Date(d)); d.setDate(d.getDate() + 1); }
    return out;
  }), [year]);
  const ALL_SUNDAYS = SUNDAYS.flat();

  if (loading) {
    return (
      <div className="clase-app min-h-screen flex items-center justify-center" style={{ background: PALETTE.cream }}>
        <GlobalStyles />
        <div className="text-center">
          <BookOpen size={32} style={{ color: PALETTE.indigo }} className="mx-auto mb-3 animate-pulse" />
          <p className="ff-display text-lg" style={{ color: PALETTE.ink }}>Cargando registro…</p>
        </div>
      </div>
    );
  }

  return (
    <div className="clase-app min-h-screen" style={{ background: PALETTE.cream, color: PALETTE.ink }}>
      <GlobalStyles />
      <Header user={{ name: userName }} clase={state.clase} tenantNombre={tenantNombre} tenantSiglas={tenantSiglas} year={year} />
      <Nav tab={tab} setTab={setTab} state={state} />
      <main className="max-w-6xl mx-auto px-4 sm:px-6 pb-20 pt-4 sm:pt-6">
        {tab === 'inicio'    && <Inicio state={state} month={month} setMonth={setMonth} setTab={setTab} SUNDAYS={SUNDAYS} year={year} />}
        {tab === 'miembros'  && <Miembros state={state} reload={reload} showToast={showToast} />}
        {tab === 'visitas'   && <Visitas state={state} reload={reload} showToast={showToast} />}
        {tab === 'asistencia'&& <Asistencia state={state} reload={reload} showToast={showToast} month={month} setMonth={setMonth} SUNDAYS={SUNDAYS} year={year} />}
        {tab === 'visitacion'&& <Visitacion state={state} reload={reload} month={month} setMonth={setMonth} year={year} />}
        {tab === 'reportes'  && <Reportes state={state} month={month} setMonth={setMonth} SUNDAYS={SUNDAYS} ALL_SUNDAYS={ALL_SUNDAYS} year={year} />}
      </main>
      <Footer tenantSiglas={tenantSiglas} year={year} />
      {toast && (
        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-full text-sm shadow-lg ff-body"
             style={{ background: PALETTE.indigoDeep, color: PALETTE.cream }}>{toast}</div>
      )}
    </div>
  );
}

/* ============================================================
   ENCABEZADO
   ============================================================ */
function Header({ user, clase, tenantNombre, tenantSiglas, year }) {
  return (
    <header className="relative overflow-hidden" style={{ background: PALETTE.indigoDeep, color: PALETTE.cream }}>
      <div className="absolute inset-0 grain opacity-30" />
      <div className="absolute -right-12 -top-12 w-44 h-44 rounded-full" style={{ background: PALETTE.amber, opacity: 0.15 }} />
      <div className="absolute right-16 top-20 w-20 h-20 rounded-full" style={{ background: PALETTE.amberSoft, opacity: 0.25 }} />
      <div className="relative max-w-6xl mx-auto px-4 sm:px-6 py-7 sm:py-9">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="ff-body text-[11px] sm:text-xs uppercase tracking-[0.2em]" style={{ opacity: 0.7 }}>
              {tenantSiglas}{tenantNombre ? ` · ${tenantNombre}` : ''}
            </p>
            <h1 className="ff-display text-3xl sm:text-5xl font-medium mt-2 leading-none">
              Clase <em style={{ color: PALETTE.amberSoft, fontStyle: 'italic' }}>{clase.nombre}</em>
            </h1>
            <p className="ff-body text-sm mt-3" style={{ opacity: 0.85 }}>
              Registro digital · Escuela Dominical
            </p>
          </div>
          <div className="flex flex-col items-end gap-2">
            <div className="ff-num text-3xl sm:text-5xl leading-none" style={{ color: PALETTE.amberSoft }}>{year}</div>
            <a href="/principal" className="flex items-center gap-2 px-3 py-1.5 rounded-full transition-all hover:opacity-90"
               style={{ background: 'rgba(251, 247, 238, 0.1)', border: '1px solid rgba(251, 247, 238, 0.25)', color: PALETTE.cream }}>
              <span className="ff-body text-xs max-w-[120px] truncate">{user.name}</span>
              <LogOut size={12} style={{ opacity: 0.8 }} />
            </a>
          </div>
        </div>
      </div>
    </header>
  );
}

function Nav({ tab, setTab, state }) {
  const items = [
    { id: 'inicio', label: 'Inicio', icon: Home },
    { id: 'miembros', label: 'Miembros', icon: Users, badge: state.members.length },
    { id: 'visitas', label: 'Visitas', icon: UserPlus, badge: state.visitors.length },
    { id: 'asistencia', label: 'Asistencia', icon: Calendar },
    { id: 'visitacion', label: 'Visitación', icon: HeartHandshake },
    { id: 'reportes', label: 'Reportes', icon: BarChart3 },
  ];
  return (
    <nav className="sticky top-0 z-30" style={{ background: PALETTE.cream, borderBottom: `1px solid ${PALETTE.line}` }}>
      <div className="max-w-6xl mx-auto px-2 sm:px-4">
        <div className="flex overflow-x-auto gap-1 py-2">
          {items.map(it => {
            const active = tab === it.id;
            const Icon = it.icon;
            return (
              <button key={it.id} onClick={() => setTab(it.id)}
                className="flex items-center gap-2 px-3 sm:px-4 py-2 rounded-full whitespace-nowrap transition-all"
                style={{
                  background: active ? PALETTE.indigoDeep : 'transparent',
                  color: active ? PALETTE.cream : PALETTE.ink,
                  border: `1px solid ${active ? PALETTE.indigoDeep : PALETTE.line}`,
                }}>
                <Icon size={15} />
                <span className="ff-body text-sm font-medium">{it.label}</span>
                {it.badge !== undefined && it.badge > 0 && (
                  <span className="ff-num text-xs px-1.5 rounded-full" style={{
                    background: active ? PALETTE.amberSoft : PALETTE.line,
                    color: active ? PALETTE.indigoDeep : PALETTE.ink,
                  }}>{it.badge}</span>
                )}
              </button>
            );
          })}
        </div>
      </div>
    </nav>
  );
}

function Section({ title, subtitle, children, icon: Icon, action }) {
  return (
    <section>
      <div className="flex items-end justify-between mb-3">
        <div className="flex items-center gap-2">
          {Icon && <Icon size={20} style={{ color: PALETTE.amber }} />}
          <div>
            <h2 className="ff-display text-xl sm:text-2xl">{title}</h2>
            {subtitle && <p className="ff-body text-xs mt-0.5" style={{ color: PALETTE.muted }}>{subtitle}</p>}
          </div>
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

function StatCard({ icon: Icon, label, value, suffix, accent }) {
  return (
    <div className="rounded-2xl p-4 sm:p-5" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
      <Icon size={18} style={{ color: accent }} />
      <div className="ff-num text-3xl sm:text-4xl mt-3" style={{ color: PALETTE.indigoDeep }}>{value}</div>
      <div className="ff-body text-xs mt-1" style={{ color: PALETTE.muted }}>
        {label}{suffix && <span className="ml-1 opacity-70">{suffix}</span>}
      </div>
    </div>
  );
}

function QuickAction({ icon: Icon, label, onClick, bg, fg }) {
  return (
    <button onClick={onClick} className="rounded-2xl p-4 flex items-center gap-3 transition-transform hover:scale-[1.02]"
            style={{ background: bg, color: fg }}>
      <Icon size={20} /><span className="ff-body font-medium">{label}</span>
    </button>
  );
}

function MonthSelector({ month, setMonth, year }) {
  return (
    <div className="flex items-center justify-between rounded-2xl p-3 sm:p-4"
         style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
      <button onClick={() => setMonth((month + 11) % 12)} className="p-2 rounded-full" style={{ background: PALETTE.cream }}>
        <ChevronLeft size={18} />
      </button>
      <div className="text-center">
        <div className="ff-body text-[10px] uppercase tracking-widest" style={{ color: PALETTE.muted }}>Mes</div>
        <div className="ff-display text-2xl sm:text-3xl">{MONTHS[month]} <span style={{ color: PALETTE.amber }}>{year}</span></div>
      </div>
      <button onClick={() => setMonth((month + 1) % 12)} className="p-2 rounded-full" style={{ background: PALETTE.cream }}>
        <ChevronRight size={18} />
      </button>
    </div>
  );
}

function EmptyState({ icon: Icon, title, message }) {
  return (
    <div className="rounded-2xl p-10 text-center" style={{ background: PALETTE.paper, border: `1px dashed ${PALETTE.line}` }}>
      <Icon size={28} style={{ color: PALETTE.muted }} className="mx-auto mb-3" />
      <div className="ff-display text-lg">{title}</div>
      <div className="ff-body text-sm mt-1" style={{ color: PALETTE.muted }}>{message}</div>
    </div>
  );
}

/* ============================================================
   INICIO
   ============================================================ */
function Inicio({ state, month, setMonth, setTab, SUNDAYS, year }) {
  const sundays = SUNDAYS[month];
  const monthAvg = useMemo(() => {
    const counts = sundays.map(d => {
      const k = fmtKey(d); const day = state.attendance[k] || {};
      const m = Object.values(day.members || {}).filter(Boolean).length;
      const v = Object.values(day.visitors || {}).filter(Boolean).length;
      return m + v;
    });
    const filled = counts.filter(c => c > 0);
    return filled.length ? Math.round(filled.reduce((a,b)=>a+b,0) / filled.length) : 0;
  }, [state.attendance, sundays]);

  const birthdaysThisMonth = useMemo(() => {
    const all = [...state.members.map(m => ({...m, type:'Miembro'})), ...state.visitors.map(v => ({...v, type:'Visita'}))];
    return all.filter(p => {
      const b = parseBirthday(p.cumpleanos); return b && b.month === month + 1;
    }).sort((a,b) => parseBirthday(a.cumpleanos).day - parseBirthday(b.cumpleanos).day);
  }, [state, month]);

  return (
    <div className="space-y-6">
      <MonthSelector month={month} setMonth={setMonth} year={year} />
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <StatCard icon={Users} label="Miembros activos" value={state.members.length} accent={PALETTE.indigo} />
        <StatCard icon={UserPlus} label="Visitas registradas" value={state.visitors.length} accent={PALETTE.amber} />
        <StatCard icon={TrendingUp} label={`Promedio ${MONTHS_SHORT[month]}`} value={monthAvg} accent={PALETTE.sage} />
        <StatCard icon={Calendar} label="Domingos del mes" value={sundays.length} accent={PALETTE.rose} />
      </div>

      <Section title={`Domingos de ${MONTHS[month]}`} subtitle={`${sundays.length} reuniones`}>
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
          {sundays.map((d, i) => {
            const k = fmtKey(d);
            const day = state.attendance[k] || {};
            const m = Object.values(day.members || {}).filter(Boolean).length;
            const v = Object.values(day.visitors || {}).filter(Boolean).length;
            const total = m + v;
            const isToday = fmtKey(new Date()) === k;
            return (
              <button key={k} onClick={() => setTab('asistencia')}
                className="text-left rounded-2xl p-4"
                style={{ background: PALETTE.paper, border: `1px solid ${isToday ? PALETTE.amber : PALETTE.line}` }}>
                <div className="ff-body text-[10px] uppercase tracking-widest" style={{ color: PALETTE.muted }}>Domingo {i + 1}</div>
                <div className="ff-num text-3xl mt-1" style={{ color: PALETTE.indigoDeep }}>{d.getDate()}</div>
                <div className="ff-body text-xs mt-1" style={{ color: PALETTE.muted }}>
                  {total > 0 ? <span style={{ color: PALETTE.sage, fontWeight: 600 }}>{total} asistentes</span> : 'Sin marcar'}
                </div>
              </button>
            );
          })}
        </div>
      </Section>

      {birthdaysThisMonth.length > 0 && (
        <Section title={`Cumpleañeros de ${MONTHS[month]}`} icon={Cake}>
          <div className="space-y-2">
            {birthdaysThisMonth.map(p => {
              const b = parseBirthday(p.cumpleanos);
              return (
                <div key={p.id} className="flex items-center justify-between rounded-xl p-3" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full flex items-center justify-center ff-num text-sm" style={{ background: PALETTE.amberSoft, color: PALETTE.indigoDeep }}>{b.day}</div>
                    <div>
                      <div className="ff-body font-semibold">{p.nombre}</div>
                      <div className="ff-body text-xs" style={{ color: PALETTE.muted }}>{p.type}</div>
                    </div>
                  </div>
                  <Cake size={18} style={{ color: PALETTE.amber }} />
                </div>
              );
            })}
          </div>
        </Section>
      )}

      <Section title="Acciones rápidas">
        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <QuickAction icon={Users} label="Agregar miembro" onClick={() => setTab('miembros')} bg={PALETTE.indigoDeep} fg={PALETTE.cream} />
          <QuickAction icon={UserPlus} label="Registrar visita" onClick={() => setTab('visitas')} bg={PALETTE.amber} fg={PALETTE.paper} />
          <QuickAction icon={Calendar} label="Marcar asistencia" onClick={() => setTab('asistencia')} bg={PALETTE.sage} fg={PALETTE.paper} />
          <QuickAction icon={HeartHandshake} label="Visitación pastoral" onClick={() => setTab('visitacion')} bg={PALETTE.rose} fg={PALETTE.paper} />
        </div>
      </Section>
    </div>
  );
}

/* ============================================================
   PERSONAS: Miembros y Visitas (CRUD via API)
   ============================================================ */
function PersonaList({ tipo, items, reload, showToast }) {
  const [editing, setEditing] = useState(null);
  const [showForm, setShowForm] = useState(false);
  const [search, setSearch] = useState('');
  const [pendingDelete, setPendingDelete] = useState(null);
  const [pendingConvert, setPendingConvert] = useState(null);

  const isVisitor = tipo === 'visita';
  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return items;
    return items.filter(m => m.nombre?.toLowerCase().includes(q) || m.telefono?.toLowerCase().includes(q));
  }, [items, search]);

  const save = async (form) => {
    try {
      if (editing) {
        await api.updatePersona(editing.id, form);
        showToast('Actualizado');
      } else {
        if (isVisitor) await api.storeVisitor(form);
        else await api.storeMember(form);
        showToast(isVisitor ? 'Visita registrada' : 'Miembro agregado');
      }
      setEditing(null); setShowForm(false);
      await reload();
    } catch (e) {
      showToast('Error al guardar');
      console.error(e);
    }
  };

  const doRemove = async () => {
    if (!pendingDelete) return;
    try {
      await api.deletePersona(pendingDelete.id);
      setPendingDelete(null);
      showToast('Eliminado de la clase');
      await reload();
    } catch (e) {
      showToast('Error al eliminar');
    }
  };

  const doConvert = async () => {
    if (!pendingConvert) return;
    try {
      await api.convertirVisita(pendingConvert.id);
      setPendingConvert(null);
      showToast('Convertido en miembro');
      await reload();
    } catch (e) {
      showToast('Error al convertir');
    }
  };

  return (
    <div className="space-y-4">
      <Section title={isVisitor ? 'Visitas' : 'Miembros de la clase'} subtitle={`${items.length} registrados`}
        action={
          <button onClick={() => { setEditing(null); setShowForm(true); }}
                  className="flex items-center gap-2 px-4 py-2 rounded-full ff-body text-sm font-medium"
                  style={{ background: isVisitor ? PALETTE.amber : PALETTE.indigoDeep, color: isVisitor ? PALETTE.paper : PALETTE.cream }}>
            <Plus size={16} /> {isVisitor ? 'Registrar' : 'Agregar'}
          </button>
        }>
        <div className="relative mb-3">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: PALETTE.muted }} />
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Buscar por nombre o teléfono"
                 className="w-full pl-9 pr-3 py-2.5 rounded-xl ff-body text-sm focus:outline-none"
                 style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }} />
        </div>
        {filtered.length === 0 ? (
          <EmptyState icon={isVisitor ? UserPlus : Users}
                      title={isVisitor ? 'Sin visitas aún' : 'Sin miembros aún'}
                      message={isVisitor ? 'Cuando alguien visite la clase, regístralo aquí.' : 'Agrega el primer miembro de la clase para comenzar.'} />
        ) : (
          <div className="space-y-2">
            {filtered.map((p, i) => (
              <PersonCard key={p.id} index={i + 1} person={p} type={isVisitor ? 'Visita' : 'Miembro'} showAge={isVisitor}
                onEdit={() => { setEditing(p); setShowForm(true); }}
                onDelete={() => setPendingDelete(p)}
                onConvert={isVisitor ? () => setPendingConvert(p) : null} />
            ))}
          </div>
        )}
      </Section>

      {showForm && <PersonForm initial={editing} type={tipo === 'visita' ? 'visitor' : 'member'} onSave={save} onClose={() => { setEditing(null); setShowForm(false); }} />}
      {pendingDelete && (
        <ConfirmModal title={isVisitor ? 'Eliminar visita' : 'Eliminar miembro'}
                      message={`¿Eliminar a ${pendingDelete.nombre}? Sus marcas de asistencia se conservarán en el historial.`}
                      confirmText="Eliminar" confirmIcon={Trash2} danger
                      onConfirm={doRemove} onCancel={() => setPendingDelete(null)} />
      )}
      {pendingConvert && (
        <ConfirmModal title="Convertir en miembro"
                      message={`¿Convertir a "${pendingConvert.nombre}" en miembro permanente de la clase? Su historial de asistencia se conservará.`}
                      confirmText="Convertir" confirmIcon={ArrowRightLeft}
                      onConfirm={doConvert} onCancel={() => setPendingConvert(null)} />
      )}
    </div>
  );
}

const Miembros = (props) => <PersonaList tipo="miembro" items={props.state.members} {...props} />;
const Visitas = (props) => <PersonaList tipo="visita" items={props.state.visitors} {...props} />;

function PersonCard({ index, person, type, showAge, onEdit, onDelete, onConvert }) {
  return (
    <div className="rounded-2xl p-4" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-full flex items-center justify-center ff-num text-sm flex-shrink-0"
             style={{ background: type === 'Miembro' ? PALETTE.indigoDeep : PALETTE.amber, color: PALETTE.cream }}>
          {String(index).padStart(2,'0')}
        </div>
        <div className="flex-1 min-w-0">
          <div className="ff-display text-lg leading-tight">{person.nombre}</div>
          <div className="flex flex-wrap gap-x-3 gap-y-1 mt-1.5 ff-body text-xs" style={{ color: PALETTE.muted }}>
            {person.telefono && <span className="flex items-center gap-1"><Phone size={12} />{person.telefono}</span>}
            {showAge && person.edad && <span>{person.edad} años</span>}
            {person.cumpleanos && <span className="flex items-center gap-1"><Cake size={12} />{person.cumpleanos}</span>}
            {person.direccion && <span className="flex items-center gap-1 truncate"><MapPin size={12} />{person.direccion}</span>}
          </div>
        </div>
        <div className="flex flex-col sm:flex-row gap-1 flex-shrink-0">
          {onConvert && (
            <button onClick={onConvert} title="Convertir en miembro" className="p-2 rounded-lg" style={{ background: PALETTE.cream, color: PALETTE.indigoDeep }}>
              <ArrowRightLeft size={15} />
            </button>
          )}
          <button onClick={onEdit} className="p-2 rounded-lg" style={{ background: PALETTE.cream }}><Pencil size={15} /></button>
          <button onClick={onDelete} className="p-2 rounded-lg" style={{ background: PALETTE.cream, color: PALETTE.rose }}><Trash2 size={15} /></button>
        </div>
      </div>
    </div>
  );
}

function Field({ label, value, onChange, placeholder, textarea, type = 'text', autoFocus }) {
  return (
    <label className="block">
      <span className="ff-body text-[11px] uppercase tracking-widest" style={{ color: PALETTE.muted }}>{label}</span>
      {textarea ? (
        <textarea rows={2} value={value || ''} onChange={e => onChange(e.target.value)} placeholder={placeholder}
                  className="w-full mt-1 px-3 py-2.5 rounded-xl ff-body text-sm focus:outline-none resize-none"
                  style={{ background: PALETTE.cream, border: `1px solid ${PALETTE.line}` }} />
      ) : (
        <input type={type} value={value || ''} onChange={e => onChange(e.target.value)} placeholder={placeholder} autoFocus={autoFocus}
               className="w-full mt-1 px-3 py-2.5 rounded-xl ff-body text-sm focus:outline-none"
               style={{ background: PALETTE.cream, border: `1px solid ${PALETTE.line}` }} />
      )}
    </label>
  );
}

function PersonForm({ initial, type, onSave, onClose }) {
  const [form, setForm] = useState(initial || { nombre: '', telefono: '', direccion: '', edad: '', cumpleanos: '', notas: '' });
  const [error, setError] = useState('');
  const isVisitor = type === 'visitor';
  const handle = (k, v) => setForm(f => ({ ...f, [k]: v }));
  const submit = () => {
    setError('');
    if (!form.nombre?.trim()) { setError('El nombre es obligatorio'); return; }
    onSave(form);
  };
  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" style={{ background: 'rgba(26, 23, 38, 0.6)' }}>
      <div className="w-full max-w-lg rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 max-h-[92vh] overflow-y-auto" style={{ background: PALETTE.paper }}>
        <div className="flex items-center justify-between mb-4">
          <div>
            <p className="ff-body text-[11px] uppercase tracking-widest" style={{ color: PALETTE.muted }}>{initial ? 'Editar' : 'Nuevo'} · {isVisitor ? 'Visita' : 'Miembro'}</p>
            <h3 className="ff-display text-2xl">{initial?.nombre || 'Datos personales'}</h3>
          </div>
          <button onClick={onClose} className="p-2 rounded-full" style={{ background: PALETTE.cream }}><X size={18} /></button>
        </div>
        <div className="space-y-3">
          <Field label="Nombre completo" value={form.nombre} onChange={v => handle('nombre', v)} placeholder="Ej. María Hernández" autoFocus />
          <div className="grid grid-cols-2 gap-3">
            <Field label="Teléfono" value={form.telefono} onChange={v => handle('telefono', v)} placeholder="8888-8888" />
            {isVisitor
              ? <Field label="Edad" value={form.edad} onChange={v => handle('edad', v)} placeholder="60" type="number" />
              : <Field label="Cumpleaños" value={form.cumpleanos} onChange={v => handle('cumpleanos', v)} placeholder="DD/MM" />}
          </div>
          {isVisitor && <Field label="Cumpleaños" value={form.cumpleanos} onChange={v => handle('cumpleanos', v)} placeholder="DD/MM" />}
          <Field label="Dirección" value={form.direccion} onChange={v => handle('direccion', v)} placeholder="Heredia centro, 100m sur de…" textarea />
          <Field label="Notas" value={form.notas} onChange={v => handle('notas', v)} placeholder="Observaciones de la clase" textarea />
        </div>
        {error && (
          <div className="flex items-center gap-2 px-3 py-2 rounded-xl mt-4" style={{ background: PALETTE.rose + '12', color: PALETTE.rose }}>
            <AlertCircle size={14} /><span className="ff-body text-sm">{error}</span>
          </div>
        )}
        <div className="flex gap-2 mt-5">
          <button onClick={onClose} className="flex-1 py-3 rounded-xl ff-body font-medium" style={{ background: PALETTE.cream, color: PALETTE.ink }}>Cancelar</button>
          <button onClick={submit} className="flex-1 py-3 rounded-xl ff-body font-medium flex items-center justify-center gap-2" style={{ background: PALETTE.indigoDeep, color: PALETTE.cream }}>
            <Check size={16} /> Guardar
          </button>
        </div>
      </div>
    </div>
  );
}

function ConfirmModal({ title, message, confirmText = 'Confirmar', cancelText = 'Cancelar', confirmIcon: ConfirmIcon, danger, onConfirm, onCancel }) {
  return (
    <div className="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4" style={{ background: 'rgba(26, 23, 38, 0.6)' }} onClick={onCancel}>
      <div className="w-full max-w-sm rounded-t-3xl sm:rounded-3xl p-6" style={{ background: PALETTE.paper }} onClick={e => e.stopPropagation()}>
        <h3 className="ff-display text-2xl">{title}</h3>
        <p className="ff-body text-sm mt-2 whitespace-pre-line" style={{ color: PALETTE.muted }}>{message}</p>
        <div className="flex gap-2 mt-5">
          <button onClick={onCancel} className="flex-1 py-3 rounded-xl ff-body font-medium" style={{ background: PALETTE.cream, color: PALETTE.ink }}>{cancelText}</button>
          <button onClick={onConfirm} className="flex-1 py-3 rounded-xl ff-body font-medium flex items-center justify-center gap-2"
                  style={{ background: danger ? PALETTE.rose : PALETTE.indigoDeep, color: PALETTE.cream }}>
            {ConfirmIcon && <ConfirmIcon size={16} />} {confirmText}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ============================================================
   ASISTENCIA — marca individual por culto
   ============================================================ */
function Asistencia({ state, reload, showToast, month, setMonth, SUNDAYS, year }) {
  const sundays = SUNDAYS[month];
  const [sundayIdx, setSundayIdx] = useState(0);
  const sunday = sundays[Math.min(sundayIdx, sundays.length - 1)];
  const key = fmtKey(sunday);
  const day = state.attendance[key] || { members: {}, visitors: {} };

  // Buscar culto matching para esta fecha
  const cultoMatch = useMemo(() => state.cultos.find(c => c.fecha === key), [state.cultos, key]);

  const togglePerson = async (kind, personaId) => {
    if (!cultoMatch) {
      showToast('No hay culto registrado en esa fecha. Crea el culto primero.');
      return;
    }
    try {
      await api.toggleAsistencia(personaId, cultoMatch.id);
      await reload();
    } catch (e) {
      showToast('Error al marcar');
    }
  };

  const presentMembers = Object.values(day.members || {}).filter(Boolean).length;
  const presentVisitors = Object.values(day.visitors || {}).filter(Boolean).length;
  const total = presentMembers + presentVisitors;

  return (
    <div className="space-y-4">
      <MonthSelector month={month} setMonth={(m) => { setMonth(m); setSundayIdx(0); }} year={year} />
      <div className="rounded-2xl p-3" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
        <p className="ff-body text-[11px] uppercase tracking-widest mb-2" style={{ color: PALETTE.muted }}>Domingo a registrar</p>
        <div className="flex gap-2 overflow-x-auto">
          {sundays.map((d, i) => {
            const active = i === sundayIdx;
            return (
              <button key={i} onClick={() => setSundayIdx(i)} className="flex-shrink-0 px-4 py-2 rounded-xl ff-body text-sm"
                style={{ background: active ? PALETTE.indigoDeep : PALETTE.cream, color: active ? PALETTE.cream : PALETTE.ink, border: `1px solid ${active ? PALETTE.indigoDeep : PALETTE.line}` }}>
                <div className="ff-num text-xl">{d.getDate()}</div>
                <div className="text-[10px] uppercase tracking-widest opacity-70">{MONTHS_SHORT[d.getMonth()]}</div>
              </button>
            );
          })}
        </div>
      </div>
      {!cultoMatch && (
        <div className="flex items-center gap-2 px-3 py-2 rounded-xl" style={{ background: PALETTE.rose + '12', color: PALETTE.rose }}>
          <AlertCircle size={14} />
          <span className="ff-body text-sm">No hay culto creado en {key}. Pídele a un admin que lo cree desde /cultos antes de marcar asistencia.</span>
        </div>
      )}
      <div className="grid grid-cols-3 gap-3">
        <div className="rounded-2xl p-4 text-center" style={{ background: PALETTE.indigoDeep, color: PALETTE.cream }}>
          <div className="ff-num text-3xl">{presentMembers}</div>
          <div className="ff-body text-[10px] uppercase tracking-widest opacity-80 mt-1">Miembros</div>
        </div>
        <div className="rounded-2xl p-4 text-center" style={{ background: PALETTE.amber, color: PALETTE.paper }}>
          <div className="ff-num text-3xl">{presentVisitors}</div>
          <div className="ff-body text-[10px] uppercase tracking-widest opacity-90 mt-1">Visitas</div>
        </div>
        <div className="rounded-2xl p-4 text-center" style={{ background: PALETTE.sage, color: PALETTE.paper }}>
          <div className="ff-num text-3xl">{total}</div>
          <div className="ff-body text-[10px] uppercase tracking-widest opacity-90 mt-1">Total</div>
        </div>
      </div>
      <Section title="Miembros" subtitle="Toca el círculo para marcar asistencia">
        {state.members.length === 0
          ? <EmptyState icon={Users} title="Sin miembros" message="Primero agrega miembros." />
          : <div className="space-y-2">{state.members.map((m, i) =>
              <AttendanceRow key={m.id} index={i+1} person={m} present={!!day.members?.[m.id]}
                             onToggle={() => togglePerson('members', m.id)} type="Miembro" disabled={!cultoMatch} />
            )}</div>}
      </Section>
      <Section title="Visitas" subtitle="Marca quiénes visitaron este domingo">
        {state.visitors.length === 0
          ? <EmptyState icon={UserPlus} title="Sin visitas registradas" message="Registra visitas." />
          : <div className="space-y-2">{state.visitors.map((v, i) =>
              <AttendanceRow key={v.id} index={i+1} person={v} present={!!day.visitors?.[v.id]}
                             onToggle={() => togglePerson('visitors', v.id)} type="Visita" disabled={!cultoMatch} />
            )}</div>}
      </Section>
    </div>
  );
}

function AttendanceRow({ index, person, present, onToggle, type, disabled }) {
  return (
    <button onClick={onToggle} disabled={disabled}
      className="w-full text-left rounded-2xl p-3 sm:p-4 flex items-center gap-3 disabled:opacity-50"
      style={{ background: PALETTE.paper, border: `1px solid ${present ? PALETTE.sage : PALETTE.line}` }}>
      <div className="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
           style={{ background: present ? PALETTE.sage : PALETTE.cream, color: present ? PALETTE.paper : PALETTE.muted, border: `1px solid ${present ? PALETTE.sage : PALETTE.line}` }}>
        {present ? <Check size={18} /> : <span className="ff-num text-sm">{String(index).padStart(2,'0')}</span>}
      </div>
      <div className="flex-1 min-w-0">
        <div className="ff-body font-semibold truncate">{person.nombre}</div>
        <div className="ff-body text-xs" style={{ color: PALETTE.muted }}>
          {type}{person.telefono ? ` · ${person.telefono}` : ''}
        </div>
      </div>
      <div className="ff-body text-xs font-medium px-2 py-1 rounded-full"
           style={{ background: present ? `${PALETTE.sage}15` : 'transparent', color: present ? PALETTE.sage : PALETTE.muted }}>
        {present ? 'Presente' : 'Ausente'}
      </div>
    </button>
  );
}

/* ============================================================
   VISITACIÓN PASTORAL
   ============================================================ */
function Visitacion({ state, reload, month, setMonth, year }) {
  const monthKey = `${year}-${String(month + 1).padStart(2, '0')}`;
  const monthData = state.visitations?.[monthKey] || {};
  const [expandedId, setExpandedId] = useState(null);
  const [search, setSearch] = useState('');

  const toggleWeek = async (personaId, week) => {
    const cur = monthData[personaId]?.[week] || {};
    await api.upsertVisitacion({
      persona_id: personaId, año: year, mes: month + 1, semana: week,
      visited: !cur.visited,
      fecha: !cur.visited && !cur.date ? new Date().toISOString().slice(0, 10) : cur.date,
      notas: cur.notes,
    });
    await reload();
  };

  const updateField = async (personaId, week, field, value) => {
    const cur = monthData[personaId]?.[week] || {};
    await api.upsertVisitacion({
      persona_id: personaId, año: year, mes: month + 1, semana: week,
      visited: true,
      fecha: field === 'date' ? value : cur.date,
      notas: field === 'notes' ? value : cur.notes,
    });
    await reload();
  };

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return state.members;
    return state.members.filter(m => m.nombre?.toLowerCase().includes(q));
  }, [state.members, search]);

  const stats = useMemo(() => {
    let totalVisits = 0, membersVisited = 0;
    state.members.forEach(m => {
      const mData = monthData[m.id] || {};
      const wks = [1, 2, 3, 4, 5].filter(w => mData[w]?.visited).length;
      totalVisits += wks;
      if (wks > 0) membersVisited++;
    });
    return { totalVisits, membersVisited, pending: state.members.length - membersVisited };
  }, [state.members, monthData]);

  return (
    <div className="space-y-4">
      <MonthSelector month={month} setMonth={setMonth} year={year} />
      <div className="grid grid-cols-3 gap-3">
        <div className="rounded-2xl p-4 text-center" style={{ background: PALETTE.sage, color: PALETTE.paper }}>
          <div className="ff-num text-3xl">{stats.membersVisited}</div>
          <div className="ff-body text-[10px] uppercase tracking-widest opacity-90 mt-1">Visitados</div>
        </div>
        <div className="rounded-2xl p-4 text-center" style={{ background: PALETTE.amber, color: PALETTE.paper }}>
          <div className="ff-num text-3xl">{stats.pending}</div>
          <div className="ff-body text-[10px] uppercase tracking-widest opacity-90 mt-1">Pendientes</div>
        </div>
        <div className="rounded-2xl p-4 text-center" style={{ background: PALETTE.indigoDeep, color: PALETTE.cream }}>
          <div className="ff-num text-3xl">{stats.totalVisits}</div>
          <div className="ff-body text-[10px] uppercase tracking-widest opacity-80 mt-1">Visitas hechas</div>
        </div>
      </div>

      <Section title={`Visitación de ${MONTHS[month]}`} subtitle="Marca las semanas en que visitaste a cada miembro">
        <div className="relative mb-3">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: PALETTE.muted }} />
          <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Buscar miembro"
                 className="w-full pl-9 pr-3 py-2.5 rounded-xl ff-body text-sm focus:outline-none"
                 style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }} />
        </div>
        {state.members.length === 0 ? (
          <EmptyState icon={Users} title="Sin miembros" message="Primero agrega miembros." />
        ) : filtered.length === 0 ? (
          <EmptyState icon={Search} title="Sin resultados" message="No hay miembros que coincidan." />
        ) : (
          <div className="space-y-2">
            {filtered.map((m, i) => {
              const mData = monthData[m.id] || {};
              const wks = [1, 2, 3, 4, 5].filter(w => mData[w]?.visited).length;
              const isExpanded = expandedId === m.id;
              return (
                <div key={m.id} className="rounded-2xl" style={{ background: PALETTE.paper, border: `1px solid ${wks > 0 ? PALETTE.sage + '50' : PALETTE.line}` }}>
                  <div className="p-3">
                    <div className="flex items-center gap-3 mb-3">
                      <div className="w-10 h-10 rounded-full flex items-center justify-center ff-num text-sm flex-shrink-0"
                           style={{ background: wks > 0 ? PALETTE.sage : PALETTE.indigoDeep, color: PALETTE.cream }}>
                        {wks > 0 ? <HeartHandshake size={16} /> : String(i + 1).padStart(2, '0')}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="ff-body font-semibold truncate">{m.nombre}</div>
                        <div className="ff-body text-xs" style={{ color: PALETTE.muted }}>
                          {wks > 0 ? <span style={{ color: PALETTE.sage, fontWeight: 600 }}>{wks} visita{wks > 1 ? 's' : ''} este mes</span> : 'Sin visitas este mes'}
                          {m.telefono && <span> · {m.telefono}</span>}
                        </div>
                      </div>
                      <button onClick={() => setExpandedId(isExpanded ? null : m.id)} className="p-2 rounded-lg flex items-center gap-1" style={{ background: PALETTE.cream }}>
                        <FileText size={13} />
                        <ChevronRight size={13} style={{ transform: isExpanded ? 'rotate(90deg)' : 'none', transition: 'transform .2s' }} />
                      </button>
                    </div>
                    <div className="grid grid-cols-5 gap-1.5">
                      {[1, 2, 3, 4, 5].map(w => {
                        const wd = mData[w] || {};
                        return (
                          <button key={w} onClick={() => toggleWeek(m.id, w)} className="rounded-lg py-2 text-center"
                                  style={{ background: wd.visited ? PALETTE.sage : PALETTE.cream, color: wd.visited ? PALETTE.paper : PALETTE.muted, border: `1px solid ${wd.visited ? PALETTE.sage : PALETTE.line}` }}>
                            <div className="ff-body text-[9px] uppercase tracking-widest">Sem</div>
                            <div className="ff-num text-base leading-none">{w}</div>
                            {wd.visited && <Check size={11} className="mx-auto mt-1" />}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                  {isExpanded && (
                    <div className="px-3 pb-3 pt-2 space-y-2" style={{ borderTop: `1px solid ${PALETTE.line}` }}>
                      <p className="ff-body text-[10px] uppercase tracking-widest" style={{ color: PALETTE.muted }}>Detalles de las visitas</p>
                      {wks === 0 ? (
                        <p className="ff-body text-xs text-center py-3" style={{ color: PALETTE.muted }}>Marca alguna semana para agregar fecha y notas.</p>
                      ) : (
                        [1, 2, 3, 4, 5].map(w => {
                          const wd = mData[w] || {};
                          if (!wd.visited) return null;
                          return (
                            <div key={w} className="rounded-xl p-2.5" style={{ background: PALETTE.cream }}>
                              <div className="flex items-center gap-2 mb-2">
                                <span className="ff-body text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-widest" style={{ background: PALETTE.sage, color: PALETTE.paper }}>Semana {w}</span>
                                <input type="date" value={wd.date || ''} onChange={e => updateField(m.id, w, 'date', e.target.value)}
                                       className="ff-body text-xs px-2 py-1.5 rounded-lg flex-1 focus:outline-none"
                                       style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }} />
                              </div>
                              <textarea rows={2} value={wd.notes || ''} onChange={e => updateField(m.id, w, 'notes', e.target.value)}
                                        placeholder="¿Cómo está? Tema conversado, peticiones de oración…"
                                        className="w-full ff-body text-sm px-2.5 py-2 rounded-lg resize-none focus:outline-none"
                                        style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }} />
                            </div>
                          );
                        })
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </Section>
    </div>
  );
}

/* ============================================================
   REPORTES
   ============================================================ */
function Reportes({ state, month, setMonth, SUNDAYS, ALL_SUNDAYS, year }) {
  const sundays = SUNDAYS[month];
  const monthlyChart = useMemo(() => sundays.map(d => {
    const k = fmtKey(d); const day = state.attendance[k] || {};
    const m = Object.values(day.members || {}).filter(Boolean).length;
    const v = Object.values(day.visitors || {}).filter(Boolean).length;
    return { dia: fmtShort(d), Miembros: m, Visitas: v, Total: m + v };
  }), [state.attendance, sundays]);

  const yearlyChart = useMemo(() => MONTHS.map((name, m) => {
    const counts = SUNDAYS[m].map(d => {
      const k = fmtKey(d); const day = state.attendance[k] || {};
      return Object.values(day.members || {}).filter(Boolean).length + Object.values(day.visitors || {}).filter(Boolean).length;
    }).filter(c => c > 0);
    const avg = counts.length ? Math.round(counts.reduce((a,b)=>a+b,0) / counts.length) : 0;
    return { mes: MONTHS_SHORT[m], Promedio: avg };
  }), [state.attendance, SUNDAYS]);

  const absentees = useMemo(() => {
    const today = new Date();
    const past = ALL_SUNDAYS.filter(d => d <= today);
    if (past.length === 0) return [];
    const last3 = past.slice(-3);
    return state.members.filter(m => last3.every(d => !state.attendance[fmtKey(d)]?.members?.[m.id]));
  }, [state, ALL_SUNDAYS]);

  const individualMonth = useMemo(() => state.members.map(m => {
    const present = sundays.filter(d => state.attendance[fmtKey(d)]?.members?.[m.id]).length;
    return { id: m.id, nombre: m.nombre, presentes: present, total: sundays.length };
  }).sort((a,b) => b.presentes - a.presentes), [state, sundays]);

  const exportExcel = () => generateExcel(state, year, SUNDAYS, ALL_SUNDAYS);

  return (
    <div className="space-y-6">
      <MonthSelector month={month} setMonth={setMonth} year={year} />
      <div className="flex justify-end">
        <button onClick={exportExcel} className="flex items-center gap-2 px-4 py-2.5 rounded-full ff-body text-sm font-medium" style={{ background: PALETTE.sage, color: PALETTE.paper }}>
          <Download size={16} /> Exportar a Excel
        </button>
      </div>
      <Section title={`Asistencia · ${MONTHS[month]}`} subtitle="Por domingo del mes seleccionado">
        <div className="rounded-2xl p-4" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={monthlyChart} margin={{ top: 10, right: 10, bottom: 0, left: -20 }}>
              <CartesianGrid stroke={PALETTE.line} strokeDasharray="3 3" />
              <XAxis dataKey="dia" tick={{ fontSize: 11, fill: PALETTE.muted }} />
              <YAxis tick={{ fontSize: 11, fill: PALETTE.muted }} />
              <Tooltip />
              <Bar dataKey="Miembros" stackId="a" fill={PALETTE.indigoDeep} />
              <Bar dataKey="Visitas" stackId="a" fill={PALETTE.amber} radius={[6,6,0,0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </Section>
      <Section title="Comparativo anual" subtitle={`Promedio mensual de asistencia (miembros + visitas) — ${year}`}>
        <div className="rounded-2xl p-4" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
          <ResponsiveContainer width="100%" height={260}>
            <LineChart data={yearlyChart} margin={{ top: 10, right: 10, bottom: 0, left: -20 }}>
              <CartesianGrid stroke={PALETTE.line} strokeDasharray="3 3" />
              <XAxis dataKey="mes" tick={{ fontSize: 11, fill: PALETTE.muted }} />
              <YAxis tick={{ fontSize: 11, fill: PALETTE.muted }} />
              <Tooltip />
              <Line type="monotone" dataKey="Promedio" stroke={PALETTE.indigoDeep} strokeWidth={2.5}
                    dot={{ r: 4, fill: PALETTE.amber, stroke: PALETTE.indigoDeep, strokeWidth: 2 }} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </Section>
      <Section title={`Asistencia individual · ${MONTHS[month]}`} subtitle="Cuántos domingos asistió cada miembro">
        {individualMonth.length === 0
          ? <EmptyState icon={Users} title="Sin miembros" message="Agrega miembros para ver su asistencia." />
          : <div className="space-y-1.5">{individualMonth.map(p => {
              const pct = p.total > 0 ? (p.presentes / p.total) * 100 : 0;
              return (
                <div key={p.id} className="rounded-xl p-3" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}` }}>
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="ff-body text-sm font-medium truncate">{p.nombre}</span>
                    <span className="ff-num text-sm" style={{ color: PALETTE.indigoDeep }}>{p.presentes}<span style={{ color: PALETTE.muted }}>/{p.total}</span></span>
                  </div>
                  <div className="h-2 rounded-full overflow-hidden" style={{ background: PALETTE.cream }}>
                    <div className="h-full rounded-full" style={{ width: `${pct}%`, background: pct >= 75 ? PALETTE.sage : pct >= 50 ? PALETTE.amber : PALETTE.rose }} />
                  </div>
                </div>
              );
            })}</div>}
      </Section>
      <Section title="Atención pastoral" subtitle="Miembros ausentes los últimos 3 domingos consecutivos" icon={AlertCircle}>
        {absentees.length === 0 ? (
          <div className="rounded-2xl p-5 text-center ff-body text-sm" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.line}`, color: PALETTE.muted }}>
            Todos los miembros han asistido recientemente. ✦
          </div>
        ) : (
          <div className="space-y-2">{absentees.map(m => (
            <div key={m.id} className="rounded-xl p-3 flex items-center justify-between" style={{ background: PALETTE.paper, border: `1px solid ${PALETTE.rose}40` }}>
              <div>
                <div className="ff-body font-semibold">{m.nombre}</div>
                {m.telefono && <div className="ff-body text-xs flex items-center gap-1 mt-0.5" style={{ color: PALETTE.muted }}><Phone size={12} />{m.telefono}</div>}
              </div>
              <AlertCircle size={18} style={{ color: PALETTE.rose }} />
            </div>
          ))}</div>
        )}
      </Section>
    </div>
  );
}

/* ============================================================
   EXPORTACIÓN A EXCEL
   ============================================================ */
function generateExcel(state, year, SUNDAYS, ALL_SUNDAYS) {
  const wb = XLSX.utils.book_new();
  const resumen = [
    [`Registro de Clase · ${state.clase.nombre} · Año ${year}`],
    [],
    ['Generado:', new Date().toLocaleString('es-CR')],
    ['Total miembros:', state.members.length],
    ['Total visitas:', state.visitors.length],
    ['Total domingos del año:', ALL_SUNDAYS.length],
  ];
  const ws1 = XLSX.utils.aoa_to_sheet(resumen);
  ws1['!cols'] = [{ wch: 28 }, { wch: 30 }];
  XLSX.utils.book_append_sheet(wb, ws1, 'Resumen');

  const memberHeader = ['#', 'Nombre', 'Teléfono', 'Dirección', 'Cumpleaños',
    ...ALL_SUNDAYS.map(d => `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]}`), 'Total presente'];
  const memberRows = state.members.map((m, i) => {
    const row = [i+1, m.nombre, m.telefono || '', m.direccion || '', m.cumpleanos || ''];
    let total = 0;
    ALL_SUNDAYS.forEach(d => {
      const present = !!state.attendance[fmtKey(d)]?.members?.[m.id];
      row.push(present ? '✓' : '');
      if (present) total++;
    });
    row.push(total);
    return row;
  });
  const ws2 = XLSX.utils.aoa_to_sheet([memberHeader, ...memberRows]);
  XLSX.utils.book_append_sheet(wb, ws2, 'Miembros');

  const visitorHeader = ['#', 'Nombre', 'Edad', 'Cumpleaños', 'Teléfono',
    ...ALL_SUNDAYS.map(d => `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]}`), 'Total visitas'];
  const visitorRows = state.visitors.map((v, i) => {
    const row = [i+1, v.nombre, v.edad || '', v.cumpleanos || '', v.telefono || ''];
    let total = 0;
    ALL_SUNDAYS.forEach(d => {
      const present = !!state.attendance[fmtKey(d)]?.visitors?.[v.id];
      row.push(present ? '✓' : '');
      if (present) total++;
    });
    row.push(total);
    return row;
  });
  const ws3 = XLSX.utils.aoa_to_sheet([visitorHeader, ...visitorRows]);
  XLSX.utils.book_append_sheet(wb, ws3, 'Visitas');

  XLSX.writeFile(wb, `Clase_${state.clase.slug || 'clase'}_${year}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

function Footer({ tenantSiglas, year }) {
  return (
    <footer className="py-6 text-center" style={{ background: PALETTE.cream }}>
      <div className="ff-display text-sm" style={{ color: PALETTE.muted }}>
        {tenantSiglas} · <em style={{ color: PALETTE.indigoDeep }}>Registro de Clase</em> · {year}
      </div>
    </footer>
  );
}
