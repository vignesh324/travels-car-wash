import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import App from './App';
import { appPath } from './utils/appBasePath';

const root = document.getElementById('root');
if (!root) throw new Error('Root element not found');

createRoot(root).render(
    <StrictMode>
        <App />
    </StrictMode>
);

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(appPath('/sw.js'))
            .catch((err) => console.warn('Service worker registration failed:', err));
    });
}
