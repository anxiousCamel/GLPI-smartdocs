/**
 * SmartDocs Scanner Bundle
 *
 * Entry point para o módulo de scanner OCR injetado nas telas de ativos.
 */

import './scanner.css';
import { ScannerApp } from './ScannerApp.js';

function initScanner() {
  const root = document.getElementById('smartdocs-scanner-root');
  if (!root) return;

  const app = new ScannerApp(root);
  app.init();
}

// GLPI carrega scripts no <head>; aguarda DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initScanner);
} else {
  initScanner();
}
