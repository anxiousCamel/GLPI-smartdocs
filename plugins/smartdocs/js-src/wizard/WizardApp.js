/**
 * WizardApp — Controlador principal do wizard de preenchimento.
 */

import { FieldRenderer } from './FieldRenderer.js';
import { AssetSelector } from './AssetSelector.js';
import { PdfGeneratorClient } from './PdfGeneratorClient.js';

export class WizardApp {
  constructor(data, rootElement) {
    this.data = data;
    this.root = rootElement;
    this.currentItem = 0;
    this.values = {};
    this.assetSelector = new AssetSelector(data.ajax_url);
    this.pdfClient = new PdfGeneratorClient(data.ajax_url);
    this.renderer = new FieldRenderer(this);
  }

  render() {
    const isEmptyRepeat = this.data.total_items === 0;

    this.root.innerHTML = `
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">${this.escapeHtml(this.data.document_name)}</h3>
          <div class="card-subtitle text-muted">
            ${this.data.template.name} — ${isEmptyRepeat ? __('Aguardando preenchimento', 'smartdocs') : this.data.total_items + ' ' + __('item(s)', 'smartdocs')}
          </div>
        </div>
        <div class="card-body">
          ${isEmptyRepeat ? this.renderPopulateBlock() : this.renderWizardBody()}
          <div id="wizard-status" class="mt-3"></div>
        </div>
      </div>
    `;

    this.bindEvents();
    if (!isEmptyRepeat) {
      this.showItem(0);
    }
  }

  renderWizardBody() {
    return `
      ${this.renderProgressBar()}
      ${this.renderItemTabs()}
      <div id="wizard-fields-container"></div>
      <div id="wizard-actions" class="mt-4 d-flex justify-content-between">
        ${this.renderActions()}
      </div>
    `;
  }

  renderPopulateBlock() {
    const itemtypes = [
      { value: 'Computer', label: __('Computador', 'smartdocs') },
      { value: 'Monitor', label: __('Monitor', 'smartdocs') },
      { value: 'NetworkEquipment', label: __('Equipamento de Rede', 'smartdocs') },
      { value: 'Peripheral', label: __('Periférico', 'smartdocs') },
      { value: 'Printer', label: __('Impressora', 'smartdocs') },
      { value: 'Phone', label: __('Telefone', 'smartdocs') },
    ];

    const typeOptions = itemtypes.map(t =>
      `<option value="${this.escapeHtml(t.value)}">${this.escapeHtml(t.label)}</option>`
    ).join('');

    return `
      <div class="alert alert-info mb-3">
        <i class="ti ti-info-circle"></i>
        ${__('Este documento está configurado para repetição em grade. Selecione o tipo de ativo e a localização para preencher automaticamente.', 'smartdocs')}
      </div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">${__('Tipo de ativo', 'smartdocs')}</label>
          <select class="form-select" id="populate-itemtype">
            <option value="">— ${__('Selecione', 'smartdocs')} —</option>
            ${typeOptions}
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">${__('Localização (opcional)', 'smartdocs')}</label>
          <input type="number" class="form-control" id="populate-location" placeholder="ID da localização" min="0">
          <small class="text-muted">${__('Deixe em branco para todos os locais.', 'smartdocs')}</small>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="button" class="btn btn-primary w-100" id="btn-populate">
            <i class="ti ti-playlist-add"></i> ${__('Popular equipamentos', 'smartdocs')}
          </button>
        </div>
      </div>
    `;
  }

  renderProgressBar() {
    const percent = Math.round(((this.currentItem + 1) / this.data.total_items) * 100);
    return `
      <div class="progress mb-3">
        <div class="progress-bar" style="width: ${percent}%" role="progressbar"
             aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100">
          ${percent}%
        </div>
      </div>
    `;
  }

  renderItemTabs() {
    if (this.data.total_items <= 1) return '';

    const tabs = Array.from({ length: this.data.total_items }, (_, i) => `
      <li class="nav-item">
        <a class="nav-link ${i === 0 ? 'active' : ''}" href="#"
           data-item="${i}" role="tab">
          ${__('Item', 'smartdocs')} ${i + 1}
        </a>
      </li>
    `).join('');

    return `<ul class="nav nav-tabs mb-3">${tabs}</ul>`;
  }

  renderActions() {
    const prevDisabled = this.currentItem === 0 ? 'disabled' : '';
    const nextLabel = this.currentItem < this.data.total_items - 1
      ? __('Próximo', 'smartdocs')
      : __('Gerar PDF', 'smartdocs');

    return `
      <button type="button" class="btn btn-secondary" id="wizard-prev" ${prevDisabled}>
        ${__('Anterior', 'smartdocs')}
      </button>
      <button type="button" class="btn btn-primary" id="wizard-next">
        ${nextLabel}
      </button>
    `;
  }

  bindEvents() {
    this.root.addEventListener('click', (e) => {
      const tab = e.target.closest('[data-item]');
      if (tab) {
        e.preventDefault();
        this.showItem(parseInt(tab.dataset.item, 10));
        return;
      }

      if (e.target.closest('#wizard-prev')) {
        this.prevItem();
        return;
      }

      if (e.target.closest('#wizard-next')) {
        this.nextOrGenerate();
        return;
      }

      if (e.target.closest('#btn-populate')) {
        this.onPopulate();
        return;
      }
    });

    this.root.addEventListener('change', (e) => {
      const input = e.target.closest('[data-field-id]');
      if (input) {
        this.onFieldChange(input);
      }
    });
  }

  showItem(index) {
    this.currentItem = index;

    // Atualiza tabs
    this.root.querySelectorAll('[data-item]').forEach((tab) => {
      tab.classList.toggle('active', parseInt(tab.dataset.item, 10) === index);
    });

    // Atualiza progresso
    const progressBar = this.root.querySelector('.progress-bar');
    if (progressBar) {
      const percent = Math.round(((index + 1) / this.data.total_items) * 100);
      progressBar.style.width = `${percent}%`;
      progressBar.textContent = `${percent}%`;
      progressBar.setAttribute('aria-valuenow', String(percent));
    }

    // Atualiza ações
    const actions = this.root.querySelector('#wizard-actions');
    if (actions) {
      actions.innerHTML = this.renderActions();
    }

    // Renderiza campos
    const container = this.root.querySelector('#wizard-fields-container');
    if (container) {
      container.innerHTML = this.renderer.renderFieldsForItem(index);
    }
  }

  prevItem() {
    if (this.currentItem > 0) {
      this.showItem(this.currentItem - 1);
    }
  }

  nextOrGenerate() {
    if (this.currentItem < this.data.total_items - 1) {
      this.showItem(this.currentItem + 1);
    } else {
      this.generatePdf();
    }
  }

  async onPopulate() {
    const itemtypeEl = document.getElementById('populate-itemtype');
    const locationEl = document.getElementById('populate-location');
    const statusEl = this.root.querySelector('#wizard-status');

    const itemtype = itemtypeEl?.value || '';
    const locationsId = locationEl?.value || null;

    if (!itemtype) {
      statusEl.innerHTML = `<div class="alert alert-warning">${__('Selecione um tipo de ativo.', 'smartdocs')}</div>`;
      return;
    }

    const btn = document.getElementById('btn-populate');
    btn.disabled = true;
    btn.innerHTML = `<i class="ti ti-loader-2 ti-spin"></i> ${__('Populando...', 'smartdocs')}`;

    try {
      const response = await fetch(`${this.data.ajax_url}populate-document.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          document_id: this.data.document_id,
          itemtype: itemtype,
          locations_id: locationsId,
        }),
      });

      const result = await response.json();

      if (!response.ok || !result.success) {
        statusEl.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(result.message || __('Erro ao popular.', 'smartdocs'))}</div>`;
        btn.disabled = false;
        btn.innerHTML = `<i class="ti ti-playlist-add"></i> ${__('Popular equipamentos', 'smartdocs')}`;
        return;
      }

      statusEl.innerHTML = `<div class="alert alert-success">${__('Populado com sucesso:', 'smartdocs')} ${result.total_items} ${__('itens em', 'smartdocs')} ${result.total_pages} ${__('página(s).', 'smartdocs')}</div>`;

      // Recarrega a página para exibir a grade com os itens preenchidos
      window.location.reload();
    } catch (e) {
      statusEl.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(e.message)}</div>`;
      btn.disabled = false;
      btn.innerHTML = `<i class="ti ti-playlist-add"></i> ${__('Popular equipamentos', 'smartdocs')}`;
    }
  }

  onFieldChange(input) {
    const fieldId = input.dataset.fieldId;
    const itemIndex = parseInt(input.dataset.itemIndex || '0', 10);
    const value = input.type === 'checkbox' ? input.checked : input.value;

    const key = `${fieldId}:${itemIndex}`;
    this.values[key] = value;

    // Autosave
    this.saveField(fieldId, itemIndex, value);
  }

  async saveField(fieldId, itemIndex, value) {
    try {
      const response = await fetch(`${this.data.ajax_url}fill-field.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          document_id: this.data.document_id,
          field_id: parseInt(fieldId, 10),
          item_index: itemIndex,
          value: String(value),
        }),
      });

      if (!response.ok) {
        const err = await response.json();
        console.warn('[SmartDocs] Erro ao salvar campo:', err);
      }
    } catch (e) {
      console.warn('[SmartDocs] Falha de rede ao salvar campo:', e);
    }
  }

  async generatePdf() {
    const status = this.root.querySelector('#wizard-status');
    status.innerHTML = `<div class="alert alert-info">${__('Enfileirando geração do PDF...', 'smartdocs')}</div>`;

    try {
      const result = await this.pdfClient.enqueue(this.data.document_id);
      status.innerHTML = `<div class="alert alert-info">${__('PDF em processamento. Aguarde...', 'smartdocs')}</div>`;
      this.pollJob(result.job_id);
    } catch (e) {
      status.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(e.message)}</div>`;
    }
  }

  pollJob(jobId) {
    this.pdfClient.poll(jobId, (status, data) => {
      const statusEl = this.root.querySelector('#wizard-status');

      if (status === 'DONE') {
        statusEl.innerHTML = `
          <div class="alert alert-success">
            ${__('PDF gerado com sucesso!', 'smartdocs')}
            <a href="${this.data.ajax_url}../front/document.send.php?id=${data.generated_pdf_id}"
               class="btn btn-sm btn-primary ms-2" target="_blank">
              ${__('Download', 'smartdocs')}
            </a>
          </div>
        `;
      } else if (status === 'ERROR') {
        statusEl.innerHTML = `<div class="alert alert-danger">${__('Erro ao gerar PDF:', 'smartdocs')} ${this.escapeHtml(data.message || '')}</div>`;
      } else {
        statusEl.innerHTML = `<div class="alert alert-info">${__('Processando PDF...', 'smartdocs')} (${status})</div>`;
      }
    });
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}
