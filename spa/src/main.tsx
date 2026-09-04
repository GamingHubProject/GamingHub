import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
// Ours first, so react-grid-layout's own rules win where they overlap.
import './theme.css';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import { App } from './App';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>
);
