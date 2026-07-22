/**
 * Editor de canvas usando Konva.js para campos arrastáveis sobre o PDF.
 *
 * Suporta: seleção múltipla (shift-click e retângulo de marquise), mover
 * vários campos juntos, copiar/colar e agrupamento colorido por
 * "equipamento" (mesma página pode ter vários grupos, cada um com campos
 * de tipos diferentes).
 *
 * O stage do Konva sempre fica em escala 1:1 (pageWidth x pageHeight) —
 * zoom/pan são responsabilidade do TemplateEditor (index.js), que escala
 * visualmente o PDF e este canvas juntos via CSS (ver .page-stage), pois
 * são dois elementos DOM separados que precisam ficar sincronizados.
 */

import Konva from 'konva';

const DEFAULT_GROUP_COLOR = { stroke: '#206bc4', fill: 'rgba(32,107,196,0.08)', selFill: 'rgba(32,107,196,0.20)' };

export class CanvasEditor {
  constructor(container, initialFields, onSelect, onChange) {
    this.container = container;
    this.fields = initialFields || [];
    this.onSelect = onSelect;
    this.onChange = onChange;

    this.selectedIds = new Set();
    this.groupNodes = new Map();
    this.pageWidth = 794;
    this.pageHeight = 1123;
    this.clipboard = [];
    this.gridEnabled = false;
    this.gridSize = 20;
    this.nextId = this.fields.length > 0
      ? Math.max(...this.fields.map(f => f.id || 0)) + 1
      : 1;

    this.initStage();
    this.renderFields();
  }

  initStage() {
    this.stage = new Konva.Stage({
      container: this.container,
      width: this.pageWidth,
      height: this.pageHeight,
    });

    this.gridLayer = new Konva.Layer({ listening: false });
    this.stage.add(this.gridLayer);

    this.layer = new Konva.Layer();
    this.stage.add(this.layer);

    this.selectionRect = new Konva.Rect({
      fill: 'rgba(32,107,196,0.1)',
      stroke: '#206bc4',
      dash: [4, 4],
      visible: false,
      listening: false,
    });
    this.layer.add(this.selectionRect);

    // O mousedown que inicia a marquise usa o Konva (para checar
    // e.target === stage com a detecção de clique dele). Mousemove/mouseup
    // ficam no window: se o usuário arrasta o mouse pra fora da área do
    // canvas, o Konva para de disparar eventos (só escuta dentro do seu
    // próprio elemento), e a seleção nunca finalizava — ao voltar o mouse,
    // a caixa de seleção "pulava" e acabava selecionando tudo.
    this.stage.on('mousedown touchstart', (e) => this.onStageMouseDown(e));
    this._onWindowMouseMove = (e) => this.onMarqueeMove(e);
    this._onWindowMouseUp = (e) => this.onMarqueeEnd(e);
  }

  // ------------------------------------------------------------------
  // Seleção por retângulo (marquise)
  // ------------------------------------------------------------------

  /** Converte coordenadas de cliente (clientX/Y) para o espaço do stage,
   * já considerando o zoom aplicado via CSS transform no wrapper pai. */
  clientToStagePos(clientX, clientY) {
    const rect = this.stage.container().getBoundingClientRect();
    const scaleX = this.stage.width() / rect.width;
    const scaleY = this.stage.height() / rect.height;
    return {
      x: (clientX - rect.left) * scaleX,
      y: (clientY - rect.top) * scaleY,
    };
  }

  onStageMouseDown(e) {
    // Botão direito é reservado para mover a tela (ver index.js) — nunca
    // deve iniciar seleção por marquise.
    if (e.evt.button !== 0 || e.target !== this.stage) return;

    const pos = this.stage.getRelativePointerPosition();
    this.marqueeStart = pos;
    this.marqueeShift = e.evt.shiftKey;
    this.selectionRect.setAttrs({ x: pos.x, y: pos.y, width: 0, height: 0, visible: true });

    window.addEventListener('mousemove', this._onWindowMouseMove);
    window.addEventListener('mouseup', this._onWindowMouseUp);
  }

  onMarqueeMove(e) {
    if (!this.marqueeStart) return;

    const pos = this.clientToStagePos(e.clientX, e.clientY);
    this.selectionRect.setAttrs({
      x: Math.min(pos.x, this.marqueeStart.x),
      y: Math.min(pos.y, this.marqueeStart.y),
      width: Math.abs(pos.x - this.marqueeStart.x),
      height: Math.abs(pos.y - this.marqueeStart.y),
    });
    this.layer.batchDraw();
  }

  onMarqueeEnd(e) {
    if (!this.marqueeStart) return;

    window.removeEventListener('mousemove', this._onWindowMouseMove);
    window.removeEventListener('mouseup', this._onWindowMouseUp);

    const box = this.selectionRect.getClientRect({ relativeTo: this.layer });
    const moved = box.width > 3 || box.height > 3;
    const shiftHeld = this.marqueeShift;

    if (moved) {
      const hitIds = [];
      this.groupNodes.forEach((node, id) => {
        const r = node.getClientRect({ relativeTo: this.layer });
        const intersects = !(r.x > box.x + box.width || r.x + r.width < box.x || r.y > box.y + box.height || r.y + r.height < box.y);
        if (intersects) hitIds.push(id);
      });

      if (shiftHeld) {
        hitIds.forEach(id => this.selectedIds.add(id));
      } else {
        this.selectedIds = new Set(hitIds);
      }
      this.emitSelection();
    } else if (!shiftHeld) {
      this.deselectAll();
    }

    this.selectionRect.visible(false);
    this.marqueeStart = null;
    this.renderFields();
  }

  // ------------------------------------------------------------------
  // Renderização
  // ------------------------------------------------------------------

  setPageSize(width, height) {
    this.pageWidth = width;
    this.pageHeight = height;
    this.stage.width(width);
    this.stage.height(height);
    this.container.style.width = width + 'px';
    this.container.style.height = height + 'px';
    this.renderFields();
    this.renderGrid();
  }

  /**
   * Liga/desliga a grade de alinhamento (linhas guia + snap ao arrastar
   * ou redimensionar campos).
   * @returns {boolean} novo estado (true = ativada)
   */
  toggleGrid() {
    this.gridEnabled = !this.gridEnabled;
    this.renderGrid();
    return this.gridEnabled;
  }

  renderGrid() {
    this.gridLayer.destroyChildren();

    if (this.gridEnabled) {
      const size = this.gridSize;
      for (let x = 0; x <= this.pageWidth; x += size) {
        const major = x % (size * 5) === 0;
        this.gridLayer.add(new Konva.Line({
          points: [x, 0, x, this.pageHeight],
          stroke: '#206bc4',
          strokeWidth: major ? 1 : 0.5,
          opacity: major ? 0.45 : 0.22,
        }));
      }
      for (let y = 0; y <= this.pageHeight; y += size) {
        const major = y % (size * 5) === 0;
        this.gridLayer.add(new Konva.Line({
          points: [0, y, this.pageWidth, y],
          stroke: '#206bc4',
          strokeWidth: major ? 1 : 0.5,
          opacity: major ? 0.45 : 0.22,
        }));
      }
    }

    this.gridLayer.batchDraw();
  }

  snapToGrid(value) {
    if (!this.gridEnabled) return value;
    return Math.round(value / this.gridSize) * this.gridSize;
  }

  setFields(fields) {
    this.fields = fields;
    this.nextId = this.fields.length > 0
      ? Math.max(...this.fields.map(f => f.id || 0)) + 1
      : 1;
    this.selectedIds = new Set([...this.selectedIds].filter(id => fields.some(f => f.id === id)));
    this.renderFields();
  }

  renderFields() {
    this.groupNodes.forEach(node => node.destroy());
    this.groupNodes.clear();
    this.groupIndexMap = this.getGroupIndexMap();

    this.fields.forEach((field) => {
      const pos = this.parsePosition(field.position);
      const group = this.createFieldGroup(field, pos);
      this.groupNodes.set(field.id, group);
      this.layer.add(group);
    });

    this.selectionRect.moveToTop();
    this.layer.draw();
  }

  /**
   * Índice curto e estável (G1, G2...) para cada grupo/equipamento
   * distinto, na ordem alfabética do nome — usado na etiqueta do canvas
   * e no dropdown de grupos do painel de propriedades, pra dar um
   * identificador fácil de citar em vez do nome completo.
   */
  getGroupIndexMap() {
    const groups = [...new Set(this.fields.map(f => f.group_label).filter(Boolean))].sort();
    const map = new Map();
    groups.forEach((g, i) => map.set(g, i + 1));
    return map;
  }

  groupColor(groupLabel) {
    if (!groupLabel) return DEFAULT_GROUP_COLOR;

    // Nomes de grupo sequenciais (ex.: "Equip 1", "Equip 2"...) diferem só
    // no último char, e um hash de rolagem simples reflete isso quase
    // 1:1 no resultado — o hue mod 360 saía a 1-3° de distância entre
    // grupos, indistinguível a olho. Usa o índice alfabético do grupo
    // (já calculado em getGroupIndexMap) espaçado pelo ângulo áureo, que
    // garante grupos vizinhos bem separados no círculo de cores
    // independente de quão parecido o nome for. Hash do label só entra
    // como fallback/offset caso o índice ainda não esteja disponível.
    const index = this.groupIndexMap?.get(groupLabel);
    let hue;
    if (index !== undefined) {
      hue = (index * 137.508) % 360;
    } else {
      let hash = 0;
      for (let i = 0; i < groupLabel.length; i++) {
        hash = groupLabel.charCodeAt(i) + ((hash << 5) - hash);
      }
      hue = Math.abs(hash) % 360;
    }
    return {
      stroke: `hsl(${hue}, 65%, 42%)`,
      fill: `hsla(${hue}, 65%, 42%, 0.12)`,
      selFill: `hsla(${hue}, 65%, 42%, 0.26)`,
    };
  }

  createFieldGroup(field, pos) {
    const isSelected = this.selectedIds.has(field.id);
    const color = this.groupColor(field.group_label);

    const group = new Konva.Group({
      x: pos.x * this.pageWidth,
      y: pos.y * this.pageHeight,
      width: pos.width * this.pageWidth,
      height: pos.height * this.pageHeight,
      draggable: true,
      id: String(field.id),
    });

    const rect = new Konva.Rect({
      width: group.width(),
      height: group.height(),
      fill: isSelected ? color.selFill : color.fill,
      stroke: color.stroke,
      strokeWidth: isSelected ? 2.5 : 2,
      dash: isSelected ? [] : [4, 4],
      cornerRadius: 3,
    });

    // Pré-visualização da fonte configurada (só relevante pra campos de
    // texto — o valor real vem em runtime no preenchimento do documento).
    const fontConfig = field.type === 'text' ? (this.parseConfig(field.config)) : {};
    const alignMap = { L: 'left', C: 'center', R: 'right' };

    const label = new Konva.Text({
      text: field.label || field.binding_key || field.type,
      fontSize: fontConfig.font_size ? Math.min(16, Math.max(8, fontConfig.font_size)) : 11,
      fontFamily: fontConfig.font_family || 'Arial',
      fontStyle: fontConfig.bold ? 'bold' : 'normal',
      align: alignMap[fontConfig.align] || 'left',
      fill: color.stroke,
      padding: 4,
      width: group.width(),
      ellipsis: true,
    });

    const resizeHandle = new Konva.Circle({
      x: group.width(),
      y: group.height(),
      radius: 5,
      fill: color.stroke,
      draggable: true,
      name: 'resizeHandle',
      visible: isSelected,
    });

    // Restringe/snap a posição durante o arrasto via dragBoundFunc (a forma
    // correta no Konva). Setar resizeHandle.x()/y() manualmente dentro do
    // 'dragmove' — como era feito antes — conflita com o rastreamento
    // interno de posição do próprio Konva.DD durante um arrasto real do
    // mouse, e a caixa "voltava" ao tamanho antigo ao soltar o botão.
    resizeHandle.dragBoundFunc((pos) => {
      let localX = pos.x - group.x();
      let localY = pos.y - group.y();
      localX = Math.max(20, this.snapToGrid(localX));
      localY = Math.max(20, this.snapToGrid(localY));
      return { x: group.x() + localX, y: group.y() + localY };
    });

    group.add(rect, label, resizeHandle);

    if (field.group_label) {
      const groupIndex = this.groupIndexMap.get(field.group_label);
      const badge = new Konva.Label({ x: -6, y: -10 });
      badge.add(new Konva.Tag({ fill: color.stroke, cornerRadius: 3 }));
      badge.add(new Konva.Text({
        text: `G${groupIndex}`,
        fontSize: 10,
        fontStyle: 'bold',
        fill: '#fff',
        padding: 3,
      }));
      group.add(badge);
    }

    group.on('dragstart', () => {
      if (this.selectedIds.has(field.id) && this.selectedIds.size > 1) {
        this.multiDragStart = {};
        this.selectedIds.forEach(id => {
          const node = this.groupNodes.get(id);
          if (node) this.multiDragStart[id] = { x: node.x(), y: node.y() };
        });
        this.multiDragAnchorId = field.id;
      }
    });

    group.on('dragmove', () => {
      if (this.gridEnabled) {
        group.x(this.snapToGrid(group.x()));
        group.y(this.snapToGrid(group.y()));
      }
      if (this.multiDragStart && this.multiDragAnchorId === field.id) {
        const anchorStart = this.multiDragStart[field.id];
        const dx = group.x() - anchorStart.x;
        const dy = group.y() - anchorStart.y;
        Object.entries(this.multiDragStart).forEach(([idStr, start]) => {
          const id = Number(idStr);
          if (id === field.id) return;
          const node = this.groupNodes.get(id);
          if (node) node.position({ x: start.x + dx, y: start.y + dy });
        });
        this.layer.batchDraw();
      }
    });

    group.on('dragend', () => {
      const ids = this.multiDragStart ? Object.keys(this.multiDragStart).map(Number) : [field.id];
      ids.forEach((id) => {
        const node = this.groupNodes.get(id);
        const f = this.fields.find(x => x.id === id);
        if (node && f) {
          f.position = {
            x: node.x() / this.pageWidth,
            y: node.y() / this.pageHeight,
            width: node.width() / this.pageWidth,
            height: node.height() / this.pageHeight,
          };
        }
      });
      this.multiDragStart = null;
      this.multiDragAnchorId = null;
      this.onChange(this.fields);
    });

    group.on('click tap', (e) => {
      if (e.evt.button !== undefined && e.evt.button !== 0) return;
      e.cancelBubble = true;
      if (e.evt.shiftKey) {
        if (this.selectedIds.has(field.id)) {
          this.selectedIds.delete(field.id);
        } else {
          this.selectedIds.add(field.id);
        }
      } else if (!this.selectedIds.has(field.id) || this.selectedIds.size === 1) {
        this.selectedIds = new Set([field.id]);
      }
      this.renderFields();
      this.emitSelection();
    });

    resizeHandle.on('dragstart', (e) => {
      e.cancelBubble = true;
    });

    resizeHandle.on('dragmove', (e) => {
      // Sem isto, o dragmove do handle borbulha pro 'dragmove' do group
      // (que trata como se fosse o campo inteiro sendo movido).
      e.cancelBubble = true;
      // A posição já vem restringida/snapada pelo dragBoundFunc acima —
      // só refletimos ela no retângulo/label, sem reescrever x()/y() aqui.
      const newW = resizeHandle.x();
      const newH = resizeHandle.y();
      rect.width(newW);
      rect.height(newH);
      label.width(newW);
      this.layer.batchDraw();
    });

    resizeHandle.on('dragend', (e) => {
      // Crítico: 'dragend' borbulha do handle (filho) pro group (pai) por
      // padrão no Konva. Sem cancelBubble, o 'dragend' do GROUP logo
      // abaixo também disparava em seguida e sobrescrevia f.position
      // usando node.width()/height() — o tamanho ESTÁTICO do Group (só
      // setado na criação, nunca atualizado pelo resize) — apagando a
      // largura/altura recém ajustada e voltando pro valor antigo. X/Y
      // "sobreviviam" por coincidência (não mudam durante um resize),
      // por isso só largura/altura pareciam sempre resetar.
      e.cancelBubble = true;
      this.updateFieldPosition(field.id, {
        x: group.x() / this.pageWidth,
        y: group.y() / this.pageHeight,
        width: rect.width() / this.pageWidth,
        height: rect.height() / this.pageHeight,
      });
    });

    return group;
  }

  // ------------------------------------------------------------------
  // Ações de campo
  // ------------------------------------------------------------------

  addField(type) {
    const newField = {
      id: this.nextId++,
      type: type,
      page_index: 0,
      position: { x: 0.1, y: 0.1, width: 0.2, height: 0.04 },
      scope: 'global',
      binding_key: null,
      group_label: null,
      config: null,
      label: this.getFieldLabel(type),
    };

    this.fields.push(newField);
    this.selectedIds = new Set([newField.id]);
    this.renderFields();
    this.emitSelection();
    this.onChange(this.fields);
  }

  deleteSelected() {
    if (this.selectedIds.size === 0) return;
    this.fields = this.fields.filter(f => !this.selectedIds.has(f.id));
    this.selectedIds = new Set();
    this.renderFields();
    this.emitSelection();
    this.onChange(this.fields);
  }

  selectAll() {
    this.selectedIds = new Set(this.fields.map(f => f.id));
    this.renderFields();
    this.emitSelection();
  }

  deselectAll() {
    this.selectedIds = new Set();
    this.renderFields();
    this.emitSelection();
  }

  emitSelection() {
    if (this.onSelect) this.onSelect(Array.from(this.selectedIds));
  }

  getSelectedFields() {
    return this.fields.filter(f => this.selectedIds.has(f.id));
  }

  updateFieldPosition(id, pos) {
    const field = this.fields.find(f => f.id === id);
    if (field) {
      field.position = pos;
      this.onChange(this.fields);
    }
  }

  /**
   * Aplica `data` a todos os campos selecionados (edição em lote). Usado
   * tanto para o painel de propriedades de campo único quanto para o
   * editor em lote de múltiplos campos.
   */
  updateSelectedFields(data) {
    if (this.selectedIds.size === 0) return;

    if (data.__delete) {
      this.deleteSelected();
      return;
    }

    this.fields.forEach((f) => {
      if (this.selectedIds.has(f.id)) Object.assign(f, data);
    });
    this.renderFields();
    this.onChange(this.fields);
  }

  // ------------------------------------------------------------------
  // Copiar / colar
  // ------------------------------------------------------------------

  copySelected() {
    const fields = this.getSelectedFields();
    this.clipboard = fields.map(f => ({ ...f, position: this.parsePosition(f.position) }));
    return this.clipboard.length;
  }

  paste() {
    if (this.clipboard.length === 0) return;

    const offset = 0.02;
    const newIds = [];

    this.clipboard.forEach((f) => {
      const newField = {
        ...f,
        id: this.nextId++,
        position: {
          x: Math.min(0.95, f.position.x + offset),
          y: Math.min(0.95, f.position.y + offset),
          width: f.position.width,
          height: f.position.height,
        },
      };
      this.fields.push(newField);
      newIds.push(newField.id);
    });

    this.selectedIds = new Set(newIds);
    this.renderFields();
    this.emitSelection();
    this.onChange(this.fields);
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

  parseConfig(configJson) {
    try {
      const c = typeof configJson === 'string' ? JSON.parse(configJson) : configJson;
      return c && typeof c === 'object' ? c : {};
    } catch {
      return {};
    }
  }

  getFieldLabel(type) {
    const map = {
      text: 'Texto',
      image: 'Imagem',
      signature: 'Assinatura',
      checkbox: 'Checkbox',
    };
    return map[type] || type;
  }
}
