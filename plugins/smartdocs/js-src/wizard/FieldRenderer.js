/**
 * FieldRenderer — Renderiza os campos do template separados por equipamento/grupo e campos globais.
 */

export class FieldRenderer {
  constructor(app) {
    this.app = app;
  }

  renderFieldsForItem(itemIndex) {
    const isGlobalTab = itemIndex === 'global';

    if (isGlobalTab) {
      const globalFields = (this.app.data.fields || []).filter((f) => f.scope === 'global');
      if (globalFields.length === 0) {
        return `<div class="alert alert-info">${__('Este template não possui campos globais.', 'smartdocs')}</div>`;
      }
      let html = `<div class="mb-4">
        <h5 class="border-bottom pb-2 mb-3">
          <i class="ti ti-world me-2 text-primary"></i>${__('Campos Globais (Válidos para todo o documento)', 'smartdocs')}
        </h5>`;
      for (const field of globalFields) {
        html += this.renderField(field, 0);
      }
      html += '</div>';
      return html;
    }

    // Para abas de Equipamento (itemIndex numérico: 0, 1, 2...):
    const numericIndex = Number(itemIndex);
    const itemsPerPage = this.app.getItemsPerPage();
    const slotIndex = numericIndex % Math.max(1, itemsPerPage);
    const groupName = this.app.getGroupNameForSlot(slotIndex);

    // Filtra os campos que pertencem a esta posição/grupo (slot_index === slotIndex)
    const itemFields = (this.app.data.fields || []).filter((f) => {
      if (f.scope !== 'item') return false;
      const fSlot = f.slot_index !== null && f.slot_index !== undefined ? Number(f.slot_index) : 0;
      return fSlot === slotIndex;
    });

    let html = `<div>
      <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
        <h5 class="mb-0">
          <i class="ti ti-box me-2 text-primary"></i>${__('Equipamento', 'smartdocs')} ${numericIndex + 1}
          <span class="badge bg-blue-lt ms-2">${this.escapeHtml(groupName)}</span>
        </h5>
        ${this.renderAssignmentBadge(numericIndex)}
      </div>`;

    // Seletor de Ativo GLPI (Permite buscar/vincular equipamento do GLPI no modo Único)
    html += this.renderAssetSelector(numericIndex);

    if (itemFields.length === 0) {
      html += `<div class="alert alert-light border text-muted">${__('Nenhum campo específico configurado para este grupo no template.', 'smartdocs')}</div>`;
    } else {
      html += `<h6 class="text-muted uppercase small tracking-wider mt-4 mb-3">${__('Campos do Equipamento', 'smartdocs')}</h6>`;
      for (const field of itemFields) {
        html += this.renderField(field, numericIndex);
      }
    }
    html += '</div>';

    return html;
  }

  renderAssignmentBadge(itemIndex) {
    const assignments = this.app.data.metadata?.assignments || [];
    const assignment = assignments[itemIndex];
    if (!assignment) return '';

    return `
      <span class="badge bg-green-lt me-1">
        <i class="ti ti-check me-1"></i>${this.escapeHtml(assignment.name || assignment.itemtype)}
      </span>
    `;
  }

  renderAssetSelector(itemIndex) {
    const assignments = this.app.data.metadata?.assignments || [];
    const assignment = assignments[itemIndex];

    return `
      <div class="card bg-light border-0 mb-4">
        <div class="card-body p-3">
          <label class="form-label font-weight-bold d-flex align-items-center mb-2">
            <i class="ti ti-database me-2 text-primary"></i>${__('Vincular Ativo do GLPI (Auto-preenchimento)', 'smartdocs')}
          </label>
          ${assignment ? `
            <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-0">
              <div>
                <i class="ti ti-device-desktop me-2"></i>
                <strong>${this.escapeHtml(assignment.name || '')}</strong>
                <span class="badge bg-blue-lt ms-2">${this.escapeHtml(assignment.itemtype)}</span>
                ${assignment.locationName ? `<span class="badge bg-secondary-lt ms-1">${this.escapeHtml(assignment.locationName)}</span>` : ''}
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-action="toggle-search" data-item="${itemIndex}">
                <i class="ti ti-refresh me-1"></i>${__('Alterar Ativo', 'smartdocs')}
              </button>
            </div>
          ` : ''}
          <div id="asset-search-wrapper-${itemIndex}" style="${assignment ? 'display:none;' : ''}" class="${assignment ? 'mt-3' : ''}">
            <div class="row g-2">
              <div class="col-md-4">
                <select class="form-select form-select-sm" id="asset-type-${itemIndex}">
                  <option value="Computer">${__('Computador', 'smartdocs')}</option>
                  <option value="Peripheral">${__('Periférico / Balança', 'smartdocs')}</option>
                  <option value="Printer">${__('Impressora', 'smartdocs')}</option>
                  <option value="Monitor">${__('Monitor', 'smartdocs')}</option>
                  <option value="NetworkEquipment">${__('Equipamento de Rede', 'smartdocs')}</option>
                  <option value="Phone">${__('Telefone', 'smartdocs')}</option>
                </select>
              </div>
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm"
                       id="asset-search-${itemIndex}"
                       placeholder="${__('Digite o nome, serial ou patrimônio...', 'smartdocs')}">
              </div>
              <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100" type="button"
                        data-action="search-asset" data-item="${itemIndex}">
                  <i class="ti ti-search me-1"></i>${__('Buscar', 'smartdocs')}
                </button>
              </div>
            </div>
            <div id="asset-results-${itemIndex}" class="mt-2" style="display:none;"></div>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Resolve um rótulo legível para o campo, nunca expondo o ID bruto do
   * banco (ex.: "Campo 1008"). Ordem de prioridade:
   *   1. field.label (definido no editor)
   *   2. field.config.label (decodificando JSON string se necessário)
   *   3. Dicionário por binding key (eq.name → "Nome do Equipamento"...)
   *   4. Rótulo amigável por tipo de campo (texto, assinatura...)
   */
  resolveLabel(field) {
    if (field.label && String(field.label).trim() !== '') {
      return field.label;
    }

    let config = field.config;
    if (typeof config === 'string') {
      try { config = JSON.parse(config); } catch { config = null; }
    }
    if (config && config.label && String(config.label).trim() !== '') {
      return config.label;
    }

    const bindingLabels = {
      'eq.name': __('Nome do Equipamento', 'smartdocs'),
      'eq.locations_id': __('Localização / Setor', 'smartdocs'),
      'eq.serial': __('Número de Série', 'smartdocs'),
      'eq.otherserial': __('Patrimônio', 'smartdocs'),
      'eq.model': __('Modelo', 'smartdocs'),
      'eq.manufacturer': __('Fabricante', 'smartdocs'),
      'user.name': __('Responsável', 'smartdocs'),
      'user.email': __('E-mail do Responsável', 'smartdocs'),
      'system.data_hora': __('Data e Hora', 'smartdocs'),
      'entity.name': __('Entidade', 'smartdocs'),
    };
    if (field.binding_key && bindingLabels[field.binding_key]) {
      return bindingLabels[field.binding_key];
    }

    const typeLabels = {
      text: __('Campo de Texto', 'smartdocs'),
      checkbox: __('Marcação', 'smartdocs'),
      image: __('Imagem / Foto', 'smartdocs'),
      signature: __('Assinatura', 'smartdocs'),
    };
    return typeLabels[field.type] || __('Campo', 'smartdocs');
  }

  renderField(field, itemIndex) {
    const type = field.type || 'text';
    const label = this.resolveLabel(field);
    const key = `${field.id}:${itemIndex}`;
    const value = this.app.values[key] ?? (field.filled_values?.[itemIndex] ?? '');
    const bindingInfo = field.binding_key
      ? `<span class="badge bg-blue-lt ms-2" title="${__('Preenchido automaticamente do GLPI', 'smartdocs')}"><i class="ti ti-link me-1"></i>${this.escapeHtml(field.binding_key)}</span>`
      : '';

    let inputHtml = '';

    switch (type) {
      case 'text':
        inputHtml = `<input type="text" class="form-control"
          data-field-id="${field.id}" data-item-index="${itemIndex}"
          value="${this.escapeAttr(value)}">`;
        break;
      case 'checkbox':
        const checked = value === '1' || value === 'true' || value === true || value === 'on';
        inputHtml = `<div class="form-check">
          <input class="form-check-input" type="checkbox"
            data-field-id="${field.id}" data-item-index="${itemIndex}"
            ${checked ? 'checked' : ''}>
          <label class="form-check-label">${this.escapeHtml(label)}</label>
        </div>`;
        break;
      case 'image':
      case 'signature':
        inputHtml = `<input type="file" class="form-control"
          data-field-id="${field.id}" data-item-index="${itemIndex}" accept="image/*">`;
        break;
      default:
        inputHtml = `<input type="text" class="form-control"
          data-field-id="${field.id}" data-item-index="${itemIndex}"
          value="${this.escapeAttr(value)}">`;
    }

    if (type === 'checkbox') {
      return `<div class="mb-3">${inputHtml}</div>`;
    }

    return `
      <div class="mb-3">
        <label class="form-label d-flex align-items-center justify-content-between">
          <span>${this.escapeHtml(label)}</span>
          ${bindingInfo}
        </label>
        ${inputHtml}
      </div>
    `;
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
  }

  escapeAttr(text) {
    return String(text ?? '').replace(/"/g, '"');
  }
}
