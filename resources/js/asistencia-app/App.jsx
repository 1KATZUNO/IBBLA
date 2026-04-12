import React, { useState, useEffect } from 'react';
import api from './api';
import Login from './screens/Login';
import Home from './screens/Home';
import ActionPicker from './screens/ActionPicker';
import ClassSelect from './screens/ClassSelect';
import ClassAttendance from './screens/ClassAttendance';
import Chapel from './screens/Chapel';
import CultoSummary from './screens/CultoSummary';

export default function App() {
    const [user, setUser] = useState(null);
    const [screen, setScreen] = useState('home');
    const [screenData, setScreenData] = useState({});
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);

    useEffect(() => {
        api.get('/user')
            .then(res => setUser(res.data))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    const navigate = (screen, data = {}) => {
        setScreen(screen);
        setScreenData(data);
    };

    const showToast = (message) => {
        setToast(message);
        setTimeout(() => setToast(null), 3000);
    };

    if (loading) {
        return (
            <div className="app-container" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: '100vh' }}>
                <div className="spinner" />
            </div>
        );
    }

    if (!user) {
        return <Login onLogin={(u) => { setUser(u); setScreen('home'); }} />;
    }

    return (
        <div className="app-container">
            {screen === 'home' && <Home user={user} navigate={navigate} onLogout={() => { setUser(null); setScreen('home'); }} />}
            {screen === 'actions' && <ActionPicker culto={screenData.culto} navigate={navigate} />}
            {screen === 'class-select' && <ClassSelect culto={screenData.culto} navigate={navigate} />}
            {screen === 'class-attendance' && <ClassAttendance culto={screenData.culto} clase={screenData.clase} navigate={navigate} showToast={showToast} />}
            {screen === 'chapel' && <Chapel culto={screenData.culto} navigate={navigate} showToast={showToast} />}
            {screen === 'summary' && <CultoSummary culto={screenData.culto} navigate={navigate} />}

            {toast && (
                <div className="toast">{toast}</div>
            )}
        </div>
    );
}
