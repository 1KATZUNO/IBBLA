import React from 'react';

export default function NumCounter({ label, value, onChange }) {
    return (
        <div className="counter-row">
            <span className="counter-label">{label}</span>
            <div className="counter-controls">
                <button
                    className="counter-btn"
                    onClick={() => onChange(Math.max(0, value - 1))}
                    type="button"
                >
                    -
                </button>
                <span className="counter-value">{value}</span>
                <button
                    className="counter-btn"
                    onClick={() => onChange(value + 1)}
                    type="button"
                >
                    +
                </button>
            </div>
        </div>
    );
}
