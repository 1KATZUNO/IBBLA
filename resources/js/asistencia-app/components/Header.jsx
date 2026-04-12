import React from 'react';

export default function Header({ title, onBack, rightAction }) {
    return (
        <div className="app-header">
            {onBack && (
                <button className="back-btn" onClick={onBack}>&larr;</button>
            )}
            <h1>{title}</h1>
            {rightAction}
        </div>
    );
}
