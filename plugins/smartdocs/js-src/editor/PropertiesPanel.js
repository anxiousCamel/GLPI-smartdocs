/**
 * Painel direito com propriedades do campo selecionado.
 */

export class PropertiesPanel {
  constructor(container, onUpdate) {
    this.container = container;
    this.onUpdate = onUpdate;
    this.currentField = null;
    this.renderEmpty();
  }

  renderEmpty() {
    this.container.innerHTML = `
      <h5 class="mb-3">${this.t('Propriedades')}</h5>
      <p class="text-muted small">Selecione um campo no canvas para editar.</p>
    `;
  }

  show(field) {
    if (!field) {
      this.renderEmpty();
      return;
    }

    this.currentField = field;
    const pos = this.parsePosition(field.position);

    this.container.innerHTML = `
      <h5 class="mb-3">${this.t('Propriedades')}</h5>

      <div class="prop-group">
        <label>Tipo</label>
        <select id="prop-type" disabled>
          <option value="text" ${field.type === 'text' ? 'selected' : ''}>Texto</option>
          <option value="image" ${field.type === 'image' ? 'selected' : ''}>Imagem</option>
          <option value="signature" ${field.type === 'signature' ? 'selected' : ''}>Assinatura</option>
          <option value="checkbox" ${field.type === 'checkbox' ? 'selected' : ''}>Checkbox</option>
        </select>
      </div>

      <div class="prop-group">
        <label>Label</label>
        <input type="text" id="prop-label" value="${this.escape(field.label || '')}">
      </div>

      <div class="prop-group">
        <label>Binding Key</label>
        <select id="prop-binding">
          <option value="">— Manual —</option>
          <optgroup label="Equipamento">
            <option value="eq.serie" ${field.binding_key === 'eq.serie' ? 'selected' : ''}>Número de série</option>
            <option value="eq.patrimonio" ${field.binding_key === 'eq.patrimonio' ? 'selected' : ''}>Patrimônio</option>
            <option value="eq.modelo" ${field.binding_key === 'eq.modelo' ? 'selected' : ''}>Modelo</option>
            <option value="eq.numero" ${field.binding_key === 'eq.numero' ? 'selected' : ''}>Nome/Ativo</option>
            <option value="eq.ip" ${field.binding_key === 'eq.ip' ? 'selected' : ''}>IP</option>
            <option value="eq.localizacao" ${field.binding_key === 'eq.localizacao' ? 'selected' : ''}>Localização</option>
          </optgroup>
          <optgroup label="Chamado">
            <option value="ticket.id" ${field.binding_key === 'ticket.id' ? 'selected' : ''}>ID do chamado</option>
            <option value="ticket.titulo" ${field.binding_key === 'ticket.titulo' ? 'selected' : ''}>Título do chamado</option>
          </optgroup>
          <optgroup label="Usuário">
            <option value="user.nome" ${field.binding_key === 'user.nome' ? 'selected' : ''}>Nome do técnico</option>
            <option value="user.email" ${field.binding_key === 'user.email' ? 'selected' : ''}>E-mail do técnico</option>
          </optgroup>
          <optgroup label="Entidade">
            <option value="entity.nome" ${field.binding_key === 'entity.nome' ? 'selected' : ''}>Nome da entidade</option>
          </optgroup>
        </select>
      </div>

      <div class="prop-group">
        <label>Escopo</label>
        <select id="prop-scope">
          <option value="global" ${field.scope === 'global' ? 'selected' : ''}>Global</option>
          <option value="item" ${field.scope === 'item' ? 'selected' : ''}>Por item</option>
        </select>
      </div>

      <div class="prop-group">
        <label>Página</label>
        <input type="number" id="prop-page" value="${field.page_index ?? 0}" min="0">
      </div>

      <div class="prop-group">
        <label>Posição X (%)</label>
        <input type="number" id="prop-x" value="${(pos.x * 100).toFixed(2)}" step="0.01" min="0" max="100">
      </div>

      <div class="prop-group">
        <label>Posição Y (%)</label>
        <input type="number" id="prop-y" value="${(pos.y * 100).toFixed(2)}" step="0.01" min="0" max="100">
      </div>

      <div class="prop-group">
        <label>Largura (%)</label>
        <input type="number" id="prop-w" value="${(pos.width * 100).toFixed(2)}" step="0.01" min="0" max="100">
      </div>

      <div class="prop-group">
        <label>Altura (%)</label>
        <input type="number" id="prop-h" value="${(pos.height * 100).toFixed(2)}" step="0.01" min="0" max="100">
      </div>

      <button type="button" class="btn btn-danger btn-sm w-100 mt-2" id="btn-delete-field">
        <i class="ti ti-trash"></i> Remover campo
      </button>
    `;

    this.bindInputs();
  }

  bindInputs() {
    const ids = ['label', 'binding', 'scope', 'page', 'x', 'y', 'w', 'h'];
    ids.forEach(id => {
      const el = document.getElementById('prop-' + id);
      if (!el) return;
      el.addEventListener('change', () => this.emitUpdate());
      el.addEventListener('input', () => this.emitUpdate());
    });

    document.getElementById('btn-delete-field')?.addEventListener('click', () => {
      if (this.currentField) {
        this.onUpdate({ __delete: true, id: this.currentField.id });
        this.renderEmpty();
      }
    });
  }

  emitUpdate() {
    if (!this.currentField) return;

    const pos = {
      x: parseFloat(document.getElementById('prop-x').value) / 100,
      y: parseFloat(document.getElementById('prop-y').value) / 100,
      width: parseFloat(document.getElementById('prop-w').value) / 100,
      height: parseFloat(document.getElementById('prop-h').value) / 100,
    };

    this.onUpdate({
      id: this.currentField.id,
      label: document.getElementById('prop-label').value,
      binding_key: document.getElementById('prop-binding').value || null,
      scope: document.getElementById('prop-scope').value,
      page_index: parseInt(document.getElementById('prop-page').value, 10) || 0,
      position: JSON.stringify(pos),
    });
  }

  parsePosition(positionJson) {
    try {
      const p = typeof positionJson === 'string' ? JSON.parse(positionJson) : positionJson;
      return {
        x: p.x ?? 0,
        y: p.y ?? 0,
        width: p.width ?? 0.2,
        height: p.height ?? 0.04,
      };
    } catch {
      return { x: 0, y: 0, width: 0.2, height: 0.04 };
    }
  }

  escape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  t(key) {
    return key;
  }
}
