/**
 * Painel direito com propriedades do(s) campo(s) selecionado(s).
 *
 * Com 1 campo selecionado: editor completo (posição, binding, grupo...).
 * Com vários selecionados: editor em lote (só propriedades comuns:
 * grupo/equipamento, escopo, página) aplicado a todos de uma vez.
 */

export class PropertiesPanel {
  constructor(container, onUpdate, bindingKeys = []) {
    this.container = container;
    this.onUpdate = onUpdate;
    this.bindingKeys = bindingKeys;
    this.currentFields = [];
    this.renderEmpty();
  }

  renderEmpty() {
    this.currentFields = [];
    this.container.innerHTML = `
      <h5 class="mb-3">${this.t('Propriedades')}</h5>
      <p class="text-muted small">Selecione um campo no canvas para editar.</p>
      <p class="text-muted small">Dica: Shift+clique ou arraste um retângulo para selecionar vários campos.</p>
    `;
  }

  /**
   * @param {object[]} fields campos selecionados (0, 1 ou vários)
   */
  show(fields) {
    this.currentFields = fields || [];

    if (this.currentFields.length === 0) {
      this.renderEmpty();
    } else if (this.currentFields.length === 1) {
      this.renderSingle(this.currentFields[0]);
    } else {
      this.renderBatch(this.currentFields);
    }
  }

  /**
   * Atualiza os campos de posição/tamanho na tela (e a referência interna
   * do campo) sem re-renderizar o painel inteiro — chamado sempre que o
   * canvas move/redimensiona o campo atualmente exibido, pra que os
   * inputs nunca fiquem com um valor antigo (que seria reenviado e
   * "resetaria" o tamanho na próxima edição de qualquer outro campo).
   */
  syncPosition(field) {
    if (this.currentFields.length !== 1 || this.currentFields[0].id !== field.id) return;

    this.currentFields = [field];
    const pos = this.parsePosition(field.position);
    const fields = { x: pos.x * 100, y: pos.y * 100, w: pos.width * 100, h: pos.height * 100 };

    Object.entries(fields).forEach(([suffix, value]) => {
      const el = document.getElementById('prop-' + suffix);
      if (el && document.activeElement !== el) {
        el.value = value.toFixed(2);
      }
    });
  }

  renderSingle(field) {
    const pos = this.parsePosition(field.position);
    const cfg = this.parseConfig(field.config);

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

      <div class="prop-group" id="prop-group-wrapper" style="${field.scope === 'global' ? 'display:none' : ''}">
        <label>Grupo (equipamento)</label>
        <div class="d-flex align-items-center gap-2">
          <span id="prop-group-swatch" class="prop-group-swatch" style="background:${this.groupColor(field.group_label).stroke}"></span>
          <select id="prop-group-select" style="flex:1">
            ${this.buildGroupOptions(field.group_label)}
          </select>
        </div>
        <input type="text" id="prop-group-new" class="mt-2" placeholder="Nome do novo grupo (ex.: Compressor 1)" style="display:none">
        <small class="text-muted">Campos no mesmo grupo formam um equipamento — mesma etiqueta (G1, G2...) e cor na folha.</small>
        <small id="prop-slot-info" class="text-muted d-block"></small>
      </div>

      <div class="prop-group">
        <label>Binding Key</label>
        <select id="prop-binding">
          ${this.buildBindingOptions(field.binding_key)}
        </select>
        <input type="text" id="prop-binding-custom" class="mt-2" placeholder="ex.: eq.campo_customizado" style="display:none">
        <small class="text-muted">Selecione um campo do GLPI ou digite uma chave customizada.</small>
      </div>

      ${field.type === 'text' ? this.renderFontControls(cfg) : ''}

      <div class="prop-group">
        <label>
          Escopo
          <i class="ti ti-info-circle text-muted" style="cursor:help" title="${this.scopeTooltip()}"></i>
        </label>
        <select id="prop-scope">
          <option value="global" ${field.scope === 'global' ? 'selected' : ''}>Compartilhado (mesmo valor pra todos)</option>
          <option value="item" ${field.scope === 'item' ? 'selected' : ''}>Por equipamento (um valor por grupo)</option>
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

    this.bindSingleInputs();
  }

  /**
   * Constrói as opções do dropdown de Binding Key dinamicamente a partir
   * das categorias fornecidas pelo backend. Suporta chaves customizadas.
   */
  buildBindingOptions(selectedKey) {
    const allKnownValues = new Set();
    let html = `<option value="">— Manual —</option>`;

    // Gera optgroups dinamicamente a partir do backend
    this.bindingKeys.forEach((category) => {
      if (!category.keys || category.keys.length === 0) return;
      html += `<optgroup label="${this.escape(category.label)}">`;
      category.keys.forEach((key) => {
        allKnownValues.add(key.value);
        const sel = selectedKey === key.value ? 'selected' : '';
        html += `<option value="${this.escape(key.value)}" ${sel}>${this.escape(key.label)}</option>`;
      });
      html += `</optgroup>`;
    });

    // Se a chave selecionada não está na lista → custom
    const isCustom = selectedKey && !allKnownValues.has(selectedKey);
    html += `<optgroup label="${this.escape('Outro')}">`;
    html += `<option value="__custom__" ${isCustom ? 'selected' : ''}>— Customizado / Outro —</option>`;
    html += `</optgroup>`;

    // Agendamento: após renderizar, preencher input custom se necessário
    if (isCustom) {
      requestAnimationFrame(() => {
        const customInput = document.getElementById('prop-binding-custom');
        if (customInput) {
          customInput.style.display = 'block';
          customInput.value = selectedKey;
        }
      });
    }

    return html;
  }

  /**
   * Controles de fonte (só aparece para campos de tipo texto). O valor
   * final impresso no PDF vem do preenchimento em runtime — isto define
   * como esse texto vai ser desenhado (fonte/tamanho/negrito/alinhamento).
   */
  renderFontControls(cfg) {
    const align = cfg.align || 'L';
    return `
      <div class="prop-group">
        <label>Fonte</label>
        <select id="prop-font-family">
          <option value="helvetica" ${cfg.font_family === 'helvetica' || !cfg.font_family ? 'selected' : ''}>Helvetica</option>
          <option value="times" ${cfg.font_family === 'times' ? 'selected' : ''}>Times</option>
          <option value="courier" ${cfg.font_family === 'courier' ? 'selected' : ''}>Courier</option>
        </select>
      </div>

      <div class="prop-group">
        <label>Tamanho / Estilo</label>
        <div class="d-flex gap-2">
          <input type="number" id="prop-font-size" value="${cfg.font_size ?? 10}" min="6" max="72" step="1" style="width:70px">
          <button type="button" class="btn btn-outline-secondary btn-sm font-toggle-bold ${cfg.bold ? 'active' : ''}" id="prop-font-bold" title="Negrito">
            <i class="ti ti-bold"></i>
          </button>
          <div class="btn-group flex-fill" role="group">
            <button type="button" class="btn btn-outline-secondary btn-sm align-btn ${align === 'L' ? 'active' : ''}" data-align="L" title="Alinhar à esquerda">
              <i class="ti ti-align-left"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm align-btn ${align === 'C' ? 'active' : ''}" data-align="C" title="Centralizar">
              <i class="ti ti-align-center"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm align-btn ${align === 'R' ? 'active' : ''}" data-align="R" title="Alinhar à direita">
              <i class="ti ti-align-right"></i>
            </button>
          </div>
        </div>
      </div>
    `;
  }

  scopeTooltip() {
    return 'Compartilhado: o mesmo valor aparece em todos os equipamentos da folha. Por equipamento: cada grupo (G1, G2...) tem seu próprio valor independente.';
  }

  renderBatch(fields) {
    const groups = [...new Set(fields.map(f => f.group_label).filter(Boolean))];
    const commonGroup = groups.length === 1 ? groups[0] : '';

    this.container.innerHTML = `
      <h5 class="mb-3">${this.t('Propriedades')}</h5>
      <p class="text-muted small mb-3">${fields.length} campos selecionados</p>

      <div class="prop-group">
        <label>Grupo (equipamento)</label>
        <select id="batch-group-select">
          ${this.buildGroupOptions(commonGroup)}
        </select>
        <input type="text" id="batch-group-new" class="mt-2" placeholder="Nome do novo grupo (ex.: Compressor 1)" style="display:none">
        <small class="text-muted">Move todos os campos selecionados para o grupo escolhido (mesma etiqueta/cor).</small>
        <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" id="btn-apply-group">Aplicar grupo</button>
      </div>

      <div class="prop-group">
        <label>
          Escopo
          <i class="ti ti-info-circle text-muted" style="cursor:help" title="${this.scopeTooltip()}"></i>
        </label>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="btn-scope-global">Compartilhado</button>
          <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="btn-scope-item">Por equipamento</button>
        </div>
      </div>

      <button type="button" class="btn btn-danger btn-sm w-100 mt-3" id="btn-delete-batch">
        <i class="ti ti-trash"></i> Remover ${fields.length} campos
      </button>
    `;

    this.bindGroupNewToggle('batch-group-select', 'batch-group-new');

    document.getElementById('btn-apply-group')?.addEventListener('click', () => {
      const select = document.getElementById('batch-group-select');
      const newInput = document.getElementById('batch-group-new');
      const value = select.value === '__new__'
        ? (newInput.value.trim() || null)
        : (select.value || null);
      this.onUpdate({ group_label: value });
    });

    document.getElementById('btn-scope-global')?.addEventListener('click', () => this.onUpdate({ scope: 'global' }));
    document.getElementById('btn-scope-item')?.addEventListener('click', () => this.onUpdate({ scope: 'item' }));

    document.getElementById('btn-delete-batch')?.addEventListener('click', () => {
      this.onUpdate({ __delete: true });
      this.renderEmpty();
    });
  }

  bindSingleInputs() {
    const ids = ['label', 'binding', 'scope', 'page', 'x', 'y', 'w', 'h', 'font-family', 'font-size'];
    ids.forEach(id => {
      const el = document.getElementById('prop-' + id);
      if (!el) return;
      el.addEventListener('change', () => this.emitUpdate());
      el.addEventListener('input', () => this.emitUpdate());
    });

    this.bindGroupNewToggle('prop-group-select', 'prop-group-new', () => this.emitUpdate());
    document.getElementById('prop-group-new')?.addEventListener('input', () => this.emitUpdate());

    // Toggle do input custom de binding key
    const bindingSelect = document.getElementById('prop-binding');
    const bindingCustom = document.getElementById('prop-binding-custom');
    if (bindingSelect && bindingCustom) {
      bindingSelect.addEventListener('change', () => {
        if (bindingSelect.value === '__custom__') {
          bindingCustom.style.display = 'block';
          bindingCustom.value = '';
          bindingCustom.focus();
        } else {
          bindingCustom.style.display = 'none';
          bindingCustom.value = '';
        }
        this.emitUpdate();
      });
      bindingCustom.addEventListener('input', () => this.emitUpdate());
    }

    document.getElementById('prop-font-bold')?.addEventListener('click', (e) => {
      e.currentTarget.classList.toggle('active');
      this.emitUpdate();
    });

    this.container.querySelectorAll('.align-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        this.container.querySelectorAll('.align-btn').forEach(b => b.classList.remove('active'));
        e.currentTarget.classList.add('active');
        this.emitUpdate();
      });
    });

    // Toggle de visibilidade do grupo conforme escopo
    const scopeSelect = document.getElementById('prop-scope');
    const groupWrapper = document.getElementById('prop-group-wrapper');
    const slotInfo = document.getElementById('prop-slot-info');
    if (scopeSelect && groupWrapper) {
      const updateSlotInfo = () => {
        const gsel = document.getElementById('prop-group-select');
        const gval = gsel ? (gsel.value === '__new__' ? null : (gsel.value || null)) : null;
        const known = this.knownGroups.find(g => g.label === gval);
        if (slotInfo) {
          if (scopeSelect.value === 'item' && known) {
            slotInfo.textContent = `Slot ${known.index - 1} (G${known.index}) — aparece na posição ${known.index}ª da folha`;
          } else {
            slotInfo.textContent = '';
          }
        }
      };
      scopeSelect.addEventListener('change', () => {
        groupWrapper.style.display = scopeSelect.value === 'global' ? 'none' : '';
        updateSlotInfo();
      });
      document.getElementById('prop-group-select')?.addEventListener('change', updateSlotInfo);
      updateSlotInfo();
    }

    document.getElementById('btn-delete-field')?.addEventListener('click', () => {
      this.onUpdate({ __delete: true });
      this.renderEmpty();
    });
  }

  /**
   * Liga o <select> de grupo: escolher "+ Criar novo grupo…" revela o
   * campo de texto pra nomear; escolher um grupo existente (ou "Sem
   * grupo") aplica na hora e dispara onChange (se informado).
   */
  bindGroupNewToggle(selectId, newInputId, onChange) {
    const select = document.getElementById(selectId);
    const newInput = document.getElementById(newInputId);
    if (!select || !newInput) return;

    select.addEventListener('change', () => {
      if (select.value === '__new__') {
        newInput.style.display = 'block';
        newInput.value = '';
        newInput.focus();
      } else {
        newInput.style.display = 'none';
        newInput.value = '';
        onChange?.();
      }
    });
  }

  /**
   * Monta as opções do dropdown de grupo: "Sem grupo", cada grupo
   * existente como "G# — nome" (mesmo índice mostrado na etiqueta do
   * canvas) e uma opção pra criar um novo grupo na hora.
   */
  buildGroupOptions(selectedLabel) {
    let html = `<option value="">— Sem grupo —</option>`;
    this.knownGroups.forEach(({ label, index }) => {
      const sel = label === selectedLabel ? 'selected' : '';
      html += `<option value="${this.escape(label)}" ${sel}>G${index} — ${this.escape(label)}</option>`;
    });
    html += `<option value="__new__">+ Criar novo grupo…</option>`;
    return html;
  }

  emitUpdate() {
    if (this.currentFields.length !== 1) return;

    const pos = {
      x: parseFloat(document.getElementById('prop-x').value) / 100,
      y: parseFloat(document.getElementById('prop-y').value) / 100,
      width: parseFloat(document.getElementById('prop-w').value) / 100,
      height: parseFloat(document.getElementById('prop-h').value) / 100,
    };

    const select = document.getElementById('prop-group-select');
    const newInput = document.getElementById('prop-group-new');
    const groupValue = select.value === '__new__'
      ? (newInput.value.trim() || null)
      : (select.value || null);

    const groupSwatch = document.getElementById('prop-group-swatch');
    if (groupSwatch) groupSwatch.style.background = this.groupColor(groupValue).stroke;

    // Binding key: se custom está ativo, pega do input; senão do select
    const bindingSelect = document.getElementById('prop-binding');
    const bindingCustom = document.getElementById('prop-binding-custom');
    let bindingValue = null;
    if (bindingSelect) {
      if (bindingSelect.value === '__custom__' && bindingCustom) {
        bindingValue = bindingCustom.value.trim() || null;
      } else if (bindingSelect.value) {
        bindingValue = bindingSelect.value;
      }
    }

    const scopeValue = document.getElementById('prop-scope').value;

    // Se escopo é 'item' e não há grupo, força criação automática
    let finalGroup = groupValue;
    if (scopeValue === 'item' && !finalGroup) {
      finalGroup = this.currentFields[0].group_label || this.autoGroupName();
    }
    if (scopeValue === 'global') {
      finalGroup = null;
    }

    const update = {
      label: document.getElementById('prop-label').value,
      group_label: finalGroup,
      binding_key: bindingValue,
      scope: scopeValue,
      page_index: parseInt(document.getElementById('prop-page').value, 10) || 0,
      position: pos,
    };

    const fontFamilyEl = document.getElementById('prop-font-family');
    if (fontFamilyEl) {
      const alignBtn = this.container.querySelector('.align-btn.active');
      update.config = {
        ...this.parseConfig(this.currentFields[0].config),
        font_family: fontFamilyEl.value,
        font_size: parseInt(document.getElementById('prop-font-size').value, 10) || 10,
        bold: document.getElementById('prop-font-bold').classList.contains('active'),
        align: alignBtn ? alignBtn.dataset.align : 'L',
      };
    }

    this.onUpdate(update);
  }

  parseConfig(configJson) {
    try {
      const c = typeof configJson === 'string' ? JSON.parse(configJson) : configJson;
      return c && typeof c === 'object' ? c : {};
    } catch {
      return {};
    }
  }

  /**
   * Lista de grupos já usados no template, com o índice curto (G1, G2...)
   * que também aparece na etiqueta do canvas. Atualizada pelo chamador
   * (index.js) sempre que os campos mudarem.
   * @param {{label: string, index: number}[]} groups
   */
  setKnownGroups(groups) {
    this.knownGroups = groups;
  }

  get knownGroups() {
    return this._knownGroups || [];
  }

  set knownGroups(value) {
    this._knownGroups = value;
  }

  /** Mesma fórmula de matiz do CanvasEditor.groupColor — precisa bater com
   * a cor mostrada no canvas para o swatch aqui fazer sentido. Usa o
   * índice do grupo (G1, G2...) espaçado pelo ângulo áureo em vez de um
   * hash do nome, senão grupos com nomes parecidos (ex. "Equip 1"/"Equip
   * 2") saíam com o mesmo matiz. */
  groupColor(groupLabel) {
    if (!groupLabel) return { stroke: '#206bc4' };
    const known = this.knownGroups.find(g => g.label === groupLabel);
    let hue;
    if (known) {
      hue = (known.index * 137.508) % 360;
    } else {
      let hash = 0;
      for (let i = 0; i < groupLabel.length; i++) {
        hash = groupLabel.charCodeAt(i) + ((hash << 5) - hash);
      }
      hue = Math.abs(hash) % 360;
    }
    return { stroke: `hsl(${hue}, 65%, 42%)` };
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

  /**
   * Gera um nome de grupo automático quando o usuário muda o escopo
   * para 'item' mas não selecionou um grupo existente.
   */
  autoGroupName() {
    const maxIndex = this.knownGroups.reduce((max, g) => Math.max(max, g.index), 0);
    return `Grupo ${maxIndex + 1}`;
  }

  t(key) {
    return key;
  }
}
