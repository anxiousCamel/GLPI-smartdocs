/**
 * WizardApp — Controlador principal do wizard de preenchimento de documentos PDF.
 *
 * Suporta os dois modos de preenchimento:
 * 1. Único (fill_mode = 'single'): preenchimento manual/vínculo por equipamento (G1, G2...) + Campos Globais.
 * 2. Repetição em grade (fill_mode = 'repeat'): popular ativos GLPI por tipo + localização em lote.
 */

import { FieldRenderer } from './FieldRenderer.js';
import { AssetSelector } from './AssetSelector.js';
import { PdfGeneratorClient } from './PdfGeneratorClient.js';

export class WizardApp {
  constructor(data, rootElement) {
    this.data = data;
    this.root = rootElement;
    this.currentItem = 0; // Pode ser número (0..total_items-1) ou 'global'
    this.values = {};
    this.assetSelector = new AssetSelector(data.ajax_url);
    this.pdfClient = new PdfGeneratorClient(data.ajax_url);
    this.renderer = new FieldRenderer(this);
  }

  getItemsPerPage() {
    const fields = this.data.fields || [];
    const slots = new Set();
    fields.forEach((f) => {
      if (f.scope === 'item' && f.slot_index !== null && f.slot_index !== undefined) {
        slots.add(Number(f.slot_index));
      }
    });
    return slots.size || 1;
  }

  getGroupNameForSlot(slotIndex) {
    const fields = this.data.fields || [];
    const itemFields = fields.filter((f) => {
      if (f.scope !== 'item') return false;
      const s = f.slot_index !== null && f.slot_index !== undefined ? Number(f.slot_index) : 0;
      return s === slotIndex;
    });

    if (itemFields.length > 0 && itemFields[0].group_label) {
      return itemFields[0].group_label;
    }
    return `G${slotIndex + 1}`;
  }

  render() {
    const isRepeat = this.data.template?.fill_mode === 'repeat';
    const isEmptyRepeat = isRepeat && this.data.total_items === 0;

    this.root.innerHTML = `
      <style>
        /* GLPI aplica um .small { width: 1% } (herdado de estilos de tabela)
           que colapsa qualquer elemento de bloco com a classe .small para
           ~1 caractere de largura. Aqui neutralizamos isso dentro do wizard. */
        .smartdocs-wizard .small { width: auto !important; }
      </style>
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
          <div class="flex-fill" style="min-width:0">
            <h3 class="card-title mb-1">${this.escapeHtml(this.data.document_name)}</h3>
            <div class="card-subtitle text-muted" style="font-size:0.875rem">
              <strong>${this.escapeHtml(this.data.template.name)}</strong> —
              <span class="badge ${isRepeat ? 'bg-purple-lt' : 'bg-blue-lt'} me-2">
                ${isRepeat ? __('Repetição em Grade', 'smartdocs') : __('Preenchimento Único', 'smartdocs')}
              </span>
              ${isEmptyRepeat ? __('Aguardando seleção do tipo de ativo', 'smartdocs') : this.data.total_items + ' ' + __('equipamento(s)', 'smartdocs')}
            </div>
          </div>
          ${isRepeat && !isEmptyRepeat ? `
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-repopulate">
              <i class="ti ti-playlist-add me-1"></i>${__('Repopular Ativos', 'smartdocs')}
            </button>
          ` : ''}
        </div>
        <div class="card-body p-4">
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
      <div id="wizard-fields-container" class="bg-white p-3 border rounded"></div>
      <div id="wizard-actions" class="mt-4 d-flex justify-content-between align-items-center">
        ${this.renderActions()}
      </div>
    `;
  }

  renderPopulateBlock() {
    const itemtypes = [
      { value: 'Peripheral', label: __('Periférico / Balança / Dispositivo', 'smartdocs') },
      { value: 'Computer', label: __('Computador', 'smartdocs') },
      { value: 'Printer', label: __('Impressora', 'smartdocs') },
      { value: 'Monitor', label: __('Monitor', 'smartdocs') },
      { value: 'NetworkEquipment', label: __('Equipamento de Rede', 'smartdocs') },
      { value: 'Phone', label: __('Telefone', 'smartdocs') },
    ];

    const typeOptions = itemtypes.map(t =>
      `<option value="${this.escapeHtml(t.value)}">${this.escapeHtml(t.label)}</option>`
    ).join('');

    const locations = this.data.locations || [];
    const locationOptions = locations.map(l =>
      `<option value="${l.id}">${this.escapeHtml(l.name)}</option>`
    ).join('');

    return `
      <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex">
          <i class="ti ti-info-circle fs-2 me-3 flex-shrink-0"></i>
          <div>
            <h5 class="alert-title mb-1">${__('Modo Repetição em Grade', 'smartdocs')}</h5>
            <p class="mb-0 small">${__('Selecione o tipo de equipamento do GLPI e a localização desejada. O sistema irá buscar automaticamente todos os ativos correspondentes e preencher os grupos de posições no documento.', 'smartdocs')}</p>
          </div>
        </div>
      </div>
      <div class="card bg-light border-0">
        <div class="card-body p-4">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label font-weight-bold">${__('Tipo de Ativo no GLPI', 'smartdocs')}</label>
              <select class="form-select" id="populate-itemtype">
                ${typeOptions}
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label font-weight-bold">${__('Localização / Setor (GLPI)', 'smartdocs')}</label>
              <select class="form-select" id="populate-location">
                <option value="">— ${__('Todas as localizações', 'smartdocs')} —</option>
                ${locationOptions}
              </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-primary w-100" id="btn-populate">
                <i class="ti ti-playlist-add me-1"></i> ${__('Popular', 'smartdocs')}
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  renderProgressBar() {
    if (this.data.total_items <= 1) return '';
    const numericIndex = typeof this.currentItem === 'number' ? this.currentItem : 0;
    const percent = Math.round(((numericIndex + 1) / this.data.total_items) * 100);
    return `
      <div class="progress mb-3" style="height: 6px;">
        <div class="progress-bar bg-primary" style="width: ${percent}%" role="progressbar"
             aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100">
        </div>
      </div>
    `;
  }

  renderItemTabs() {
    const isSingle = this.data.template?.fill_mode === 'single';
    const totalItems = this.data.total_items;
    const itemsPerPage = this.getItemsPerPage();

    let tabsHtml = '';

    for (let i = 0; i < totalItems; i++) {
      const slotIndex = i % itemsPerPage;
      const groupName = this.getGroupNameForSlot(slotIndex);
      const isActive = this.currentItem === i;
      tabsHtml += `
        <li class="nav-item">
          <a class="nav-link ${isActive ? 'active' : ''}" href="#" data-item="${i}" role="tab">
            <i class="ti ti-box me-1"></i>${__('Equipamento', 'smartdocs')} ${i + 1}
            <span class="badge bg-blue-lt ms-1">${this.escapeHtml(groupName)}</span>
          </a>
        </li>
      `;
    }

    // Aba de Campos Globais
    const hasGlobal = (this.data.fields || []).some(f => f.scope === 'global');
    if (hasGlobal) {
      const isActive = this.currentItem === 'global';
      tabsHtml += `
        <li class="nav-item">
          <a class="nav-link ${isActive ? 'active' : ''}" href="#" data-item="global" role="tab">
            <i class="ti ti-world me-1"></i>${__('Campos Globais', 'smartdocs')}
          </a>
        </li>
      `;
    }

    let addBtn = '';
    if (isSingle) {
      addBtn = `
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="btn-add-item">
          <i class="ti ti-plus me-1"></i>${__('Adicionar Equipamento', 'smartdocs')}
        </button>
      `;
    }

    return `
      <div class="d-flex align-items-center mb-3">
        <ul class="nav nav-tabs flex-fill mb-0">${tabsHtml}</ul>
        ${addBtn}
      </div>
    `;
  }

  renderActions() {
    const isGlobal = this.currentItem === 'global';
    const isNumeric = typeof this.currentItem === 'number';
    const prevDisabled = (isNumeric && this.currentItem === 0) ? 'disabled' : '';

    const nextLabel = (isNumeric && this.currentItem < this.data.total_items - 1)
      ? __('Próximo Equipamento', 'smartdocs')
      : __('Gerar PDF', 'smartdocs');

    return `
      <button type="button" class="btn btn-secondary" id="wizard-prev" ${prevDisabled}>
        <i class="ti ti-arrow-left me-1"></i>${__('Anterior', 'smartdocs')}
      </button>
      <button type="button" class="btn btn-primary ms-auto" id="wizard-next">
        ${nextLabel} <i class="ti ti-arrow-right ms-1"></i>
      </button>
    `;
  }

  bindEvents() {
    this.root.addEventListener('click', (e) => {
      const tab = e.target.closest('[data-item]');
      if (tab) {
        e.preventDefault();
        const raw = tab.dataset.item;
        this.showItem(raw === 'global' ? 'global' : parseInt(raw, 10));
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

      if (e.target.closest('#btn-repopulate')) {
        this.data.total_items = 0;
        this.render();
        return;
      }

      if (e.target.closest('#btn-add-item')) {
        this.addEquipmentItem();
        return;
      }

      const searchBtn = e.target.closest('[data-action="search-asset"]');
      if (searchBtn) {
        const itemIndex = parseInt(searchBtn.dataset.item, 10);
        this.performAssetSearch(itemIndex);
        return;
      }

      const selectBtn = e.target.closest('[data-action="select-asset"]');
      if (selectBtn) {
        const itemIndex = parseInt(selectBtn.dataset.item, 10);
        const itemtype = selectBtn.dataset.itemtype;
        const itemsId = parseInt(selectBtn.dataset.itemsid, 10);
        this.linkAssetToItem(itemIndex, itemtype, itemsId);
        return;
      }

      const toggleSearchBtn = e.target.closest('[data-action="toggle-search"]');
      if (toggleSearchBtn) {
        const itemIndex = parseInt(toggleSearchBtn.dataset.item, 10);
        const wrapper = document.getElementById(`asset-search-wrapper-${itemIndex}`);
        if (wrapper) {
          wrapper.style.display = wrapper.style.display === 'none' ? 'block' : 'none';
        }
        return;
      }
    });

    this.root.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.matches('[id^="asset-search-"]')) {
        e.preventDefault();
        const itemIndex = parseInt(e.target.id.replace('asset-search-', ''), 10);
        this.performAssetSearch(itemIndex);
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
      const raw = tab.dataset.item;
      const match = (index === 'global' && raw === 'global') || (typeof index === 'number' && parseInt(raw, 10) === index);
      tab.classList.toggle('active', match);
    });

    // Atualiza barra de progresso
    const progressBar = this.root.querySelector('.progress-bar');
    if (progressBar && typeof index === 'number') {
      const percent = Math.round(((index + 1) / this.data.total_items) * 100);
      progressBar.style.width = `${percent}%`;
      progressBar.setAttribute('aria-valuenow', String(percent));
    }

    // Atualiza ações
    const actions = this.root.querySelector('#wizard-actions');
    if (actions) {
      actions.innerHTML = this.renderActions();
    }

    // Renderiza campos da aba
    const container = this.root.querySelector('#wizard-fields-container');
    if (container) {
      container.innerHTML = this.renderer.renderFieldsForItem(index);
    }
  }

  prevItem() {
    if (typeof this.currentItem === 'number' && this.currentItem > 0) {
      this.showItem(this.currentItem - 1);
    } else if (this.currentItem === 'global' && this.data.total_items > 0) {
      this.showItem(this.data.total_items - 1);
    }
  }

  nextOrGenerate() {
    if (typeof this.currentItem === 'number' && this.currentItem < this.data.total_items - 1) {
      this.showItem(this.currentItem + 1);
    } else if (typeof this.currentItem === 'number' && (this.data.fields || []).some(f => f.scope === 'global')) {
      this.showItem('global');
    } else {
      this.generatePdf();
    }
  }

  async performAssetSearch(itemIndex) {
    const typeEl = document.getElementById(`asset-type-${itemIndex}`);
    const inputEl = document.getElementById(`asset-search-${itemIndex}`);
    const resultsEl = document.getElementById(`asset-results-${itemIndex}`);

    if (!inputEl || !resultsEl) return;

    const query = inputEl.value.trim();
    const type = typeEl ? typeEl.value : 'Computer';

    if (query.length < 2) {
      resultsEl.style.display = 'block';
      resultsEl.innerHTML = `<div class="alert alert-warning py-1 px-2 small mb-0">${__('Digite pelo menos 2 caracteres para buscar.', 'smartdocs')}</div>`;
      return;
    }

    resultsEl.style.display = 'block';
    resultsEl.innerHTML = `<div class="text-muted small py-2"><i class="ti ti-loader-2 ti-spin me-1"></i>${__('Buscando no GLPI...', 'smartdocs')}</div>`;

    const results = await this.assetSelector.search(query, [type]);

    if (results.length === 0) {
      resultsEl.innerHTML = `<div class="alert alert-info py-1 px-2 small mb-0">${__('Nenhum ativo encontrado no GLPI.', 'smartdocs')}</div>`;
      return;
    }

    let html = `<div class="list-group list-group-flush border rounded shadow-sm overflow-auto" style="max-height: 180px;">`;
    results.forEach((r) => {
      const serialInfo = r.serial ? ` — Serial: ${this.escapeHtml(r.serial)}` : '';
      html += `
        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
          <div>
            <strong>${this.escapeHtml(r.name)}</strong>
            <small class="text-muted d-block">${this.escapeHtml(r.itemtype)}${serialInfo}</small>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary"
                  data-action="select-asset" data-item="${itemIndex}"
                  data-itemtype="${this.escapeHtml(r.itemtype)}" data-itemsid="${r.id}">
            <i class="ti ti-link me-1"></i>${__('Vincular', 'smartdocs')}
          </button>
        </div>
      `;
    });
    html += `</div>`;
    resultsEl.innerHTML = html;
  }

  async linkAssetToItem(itemIndex, itemtype, itemsId) {
    const resultsEl = document.getElementById(`asset-results-${itemIndex}`);
    if (resultsEl) {
      resultsEl.innerHTML = `<div class="text-muted small py-2"><i class="ti ti-loader-2 ti-spin me-1"></i>${__('Vinculando ativo e buscando dados no GLPI...', 'smartdocs')}</div>`;
    }

    try {
      const response = await fetch(`${this.data.ajax_url}select-asset.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          document_id: this.data.document_id,
          item_index: itemIndex,
          itemtype: itemtype,
          items_id: itemsId,
        }),
      });

      const json = await response.json();
      if (response.ok && json.success) {
        if (json.filled && Array.isArray(json.filled)) {
          json.filled.forEach((item) => {
            const key = `${item.field_id}:${itemIndex}`;
            this.values[key] = item.value;
          });
        }
        if (!this.data.metadata) this.data.metadata = {};
        if (!this.data.metadata.assignments) this.data.metadata.assignments = [];
        this.data.metadata.assignments[itemIndex] = {
          itemtype: itemtype,
          items_id: itemsId,
          name: json.filled?.find(f => f.binding_key === 'eq.name')?.value || itemtype,
        };

        this.showItem(itemIndex);
      } else {
        alert(json.message || __('Erro ao vincular ativo.', 'smartdocs'));
      }
    } catch (err) {
      alert(__('Erro de comunicação: ', 'smartdocs') + err.message);
    }
  }

  async addEquipmentItem() {
    try {
      const response = await fetch(`${this.data.ajax_url}add-item.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ document_id: this.data.document_id }),
      });

      const json = await response.json();
      if (response.ok && json.success) {
        this.data.total_items = json.total_items;
        this.render();
        this.showItem(this.data.total_items - 1);
      } else {
        alert(json.message || __('Erro ao adicionar equipamento.', 'smartdocs'));
      }
    } catch (err) {
      alert(__('Erro de comunicação: ', 'smartdocs') + err.message);
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
    btn.innerHTML = `<i class="ti ti-loader-2 ti-spin me-1"></i> ${__('Populando...', 'smartdocs')}`;

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
        btn.innerHTML = `<i class="ti ti-playlist-add me-1"></i> ${__('Popular', 'smartdocs')}`;
        return;
      }

      statusEl.innerHTML = `<div class="alert alert-success">${__('Populado com sucesso:', 'smartdocs')} ${result.total_items} ${__('itens em', 'smartdocs')} ${result.total_pages} ${__('página(s).', 'smartdocs')}</div>`;

      window.location.reload();
    } catch (e) {
      statusEl.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(e.message)}</div>`;
      btn.disabled = false;
      btn.innerHTML = `<i class="ti ti-playlist-add me-1"></i> ${__('Popular', 'smartdocs')}`;
    }
  }

  onFieldChange(input) {
    const fieldId = input.dataset.fieldId;
    const itemIndex = parseInt(input.dataset.itemIndex || '0', 10);
    const value = input.type === 'checkbox' ? input.checked : input.value;

    const key = `${fieldId}:${itemIndex}`;
    this.values[key] = value;

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
          <div class="alert alert-success d-flex align-items-center justify-content-between">
            <div><i class="ti ti-check me-2"></i>${__('PDF gerado com sucesso!', 'smartdocs')}</div>
            <a href="${this.data.ajax_url}../front/document.send.php?id=${data.generated_pdf_id}"
               class="btn btn-sm btn-primary" target="_blank">
              <i class="ti ti-download me-1"></i>${__('Download PDF', 'smartdocs')}
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
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }
}
