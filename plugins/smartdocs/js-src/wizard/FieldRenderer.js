/**
 * FieldRenderer — Renderiza os campos do template para um item específico.
 */

export class FieldRenderer {
  constructor(app) {
    this.app = app;
  }

  renderFieldsForItem(itemIndex) {
    const globalFields = this.app.data.fields.filter((f) => f.scope === 'global');
    const itemFields = this.app.data.fields.filter((f) => f.scope === 'item');

    let html = '';

    // Campos globais (apenas no primeiro item)
    if (itemIndex === 0 && globalFields.length > 0) {
      html += `<div class="mb-4"><h5>${__('Campos Globais', 'smartdocs')}</h5>`;
      for (const field of globalFields) {
        html += this.renderField(field, 0);
      }
      html += '</div>';
    }

    // Campos por item
    if (itemFields.length > 0) {
      html += `<div><h5>${__('Campos do Item', 'smartdocs')} ${itemIndex + 1}</h5>`;

      // Seletor de ativo
      html += this.renderAssetSelector(itemIndex);

      for (const field of itemFields) {
        html += this.renderField(field, itemIndex);
      }
      html += '</div>';
    }

    return html;
  }

  renderAssetSelector(itemIndex) {
    return `
      <div class="mb-3">
        <label class="form-label">${__('Ativo GLPI', 'smartdocs')}</label>
        <div class="input-group">
          <input type="text" class="form-control"
                 id="asset-search-${itemIndex}"
                 placeholder="${__('Buscar por nome, serial ou patrimônio...', 'smartdocs')}">
          <button class="btn btn-outline-secondary" type="button"
                  data-action="search-asset" data-item="${itemIndex}">
            ${__('Buscar', 'smartdocs')}
          </button>
        </div>
        <div id="asset-results-${itemIndex}" class="mt-2"></div>
        <input type="hidden" id="asset-itemtype-${itemIndex}" value="">
        <input type="hidden" id="asset-itemsid-${itemIndex}" value="">
      </div>
    `;
  }

  renderField(field, itemIndex) {
    const type = field.type || 'text';
    const label = field.config?.label || `Campo ${field.id}`;
    const key = `${field.id}:${itemIndex}`;
    const value = this.app.values[key] ?? (field.filled_values?.[itemIndex] ?? '');
    const bindingInfo = field.binding_key
      ? `<small class="text-muted">(${field.binding_key})</small>`
      : '';

    let inputHtml = '';

    switch (type) {
      case 'text':
        inputHtml = `<input type="text" class="form-control"
          data-field-id="${field.id}" data-item-index="${itemIndex}"
          value="${this.escapeAttr(value)}" ${field.binding_key ? 'readonly' : ''}>`;
        break;
      case 'checkbox':
        inputHtml = `<div class="form-check">
          <input class="form-check-input" type="checkbox"
            data-field-id="${field.id}" data-item-index="${itemIndex}"
            ${value ? 'checked' : ''}>
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

    return `
      <div class="mb-3">
        <label class="form-label">${this.escapeHtml(label)} ${bindingInfo}</label>
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
