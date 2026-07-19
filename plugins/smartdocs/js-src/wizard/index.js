/**
 * SmartDocs — Wizard de Preenchimento de Documento PDF
 *
 * Entry point: inicializa o wizard a partir dos dados injetados
 * pelo PHP em window.SmartDocsWizard.data.
 */

import { WizardApp } from './WizardApp.js';

document.addEventListener('DOMContentLoaded', () => {
  const data = window.SmartDocsWizard?.data;
  if (!data) {
    console.error('[SmartDocs] Dados do wizard não encontrados.');
    return;
  }

  const root = document.getElementById('smartdocs-wizard-root');
  if (!root) {
    console.error('[SmartDocs] Container #smartdocs-wizard-root não encontrado.');
    return;
  }

  const app = new WizardApp(data, root);
  app.render();
});
