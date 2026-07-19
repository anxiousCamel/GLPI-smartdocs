/**
 * Editor de canvas usando Konva.js para campos arrastáveis sobre o PDF.
 */

import Konva from 'konva';

export class CanvasEditor {
  constructor(container, initialFields, onSelect, onChange) {
    this.container = container;
    this.fields = initialFields || [];
    this.onSelect = onSelect;
    this.onChange = onChange;
    this.selectedId = null;
    this.pageWidth = 794;
    this.pageHeight = 1123;
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

    this.layer = new Konva.Layer();
    this.stage.add(this.layer);

    // Clique no fundo desseleciona
    this.stage.on('click tap', (e) => {
      if (e.target === this.stage) {
        this.deselect();
      }
    });
  }

  setPageSize(width, height) {
    this.pageWidth = width;
    this.pageHeight = height;
    this.stage.width(width);
    this.stage.height(height);
    this.container.style.width = width + 'px';
    this.container.style.height = height + 'px';
    this.layer.draw();
  }

  setFields(fields) {
    this.fields = fields;
    this.nextId = this.fields.length > 0
      ? Math.max(...this.fields.map(f => f.id || 0)) + 1
      : 1;
    this.renderFields();
  }

  renderFields() {
    this.layer.destroyChildren();

    this.fields.forEach((field) => {
      const pos = this.parsePosition(field.position);
      const group = this.createFieldGroup(field, pos);
      this.layer.add(group);
    });

    this.layer.draw();
  }

  createFieldGroup(field, pos) {
    const group = new Konva.Group({
      x: pos.x * this.pageWidth,
      y: pos.y * this.pageHeight,
      width: pos.width * this.pageWidth,
      height: pos.height * this.pageHeight,
      draggable: true,
      id: String(field.id),
    });

    // Fundo do campo
    const rect = new Konva.Rect({
      width: group.width(),
      height: group.height(),
      fill: field.id === this.selectedId ? 'rgba(32,107,196,0.18)' : 'rgba(32,107,196,0.08)',
      stroke: field.id === this.selectedId ? '#206bc4' : '#206bc4',
      strokeWidth: 2,
      dash: field.id === this.selectedId ? [] : [4, 4],
      cornerRadius: 3,
    });

    // Label do campo
    const label = new Konva.Text({
      text: field.label || field.binding_key || field.type,
      fontSize: 11,
      fill: '#206bc4',
      padding: 4,
      width: group.width(),
      ellipsis: true,
    });

    // Handle de redimensionamento
    const resizeHandle = new Konva.Circle({
      x: group.width(),
      y: group.height(),
      radius: 5,
      fill: '#206bc4',
      draggable: true,
      name: 'resizeHandle',
    });

    group.add(rect, label, resizeHandle);

    // Eventos
    group.on('dragend', () => {
      this.updateFieldPosition(field.id, {
        x: group.x() / this.pageWidth,
        y: group.y() / this.pageHeight,
        width: group.width() / this.pageWidth,
        height: group.height() / this.pageHeight,
      });
    });

    group.on('click tap', (e) => {
      e.cancelBubble = true;
      this.selectField(field.id);
    });

    resizeHandle.on('dragmove', () => {
      let newW = Math.max(20, resizeHandle.x());
      let newH = Math.max(20, resizeHandle.y());
      rect.width(newW);
      rect.height(newH);
      label.width(newW);
      resizeHandle.x(newW);
      resizeHandle.y(newH);
      this.layer.batchDraw();
    });

    resizeHandle.on('dragend', () => {
      this.updateFieldPosition(field.id, {
        x: group.x() / this.pageWidth,
        y: group.y() / this.pageHeight,
        width: rect.width() / this.pageWidth,
        height: rect.height() / this.pageHeight,
      });
    });

    return group;
  }

  addField(type) {
    const newField = {
      id: this.nextId++,
      type: type,
      page_index: 0,
      position: JSON.stringify({ x: 0.1, y: 0.1, width: 0.2, height: 0.04 }),
      scope: 'global',
      binding_key: null,
      config: null,
      label: this.getFieldLabel(type),
    };

    this.fields.push(newField);
    this.renderFields();
    this.selectField(newField.id);
    this.onChange(this.fields);
  }

  deleteField(id) {
    this.fields = this.fields.filter(f => f.id !== id);
    this.selectedId = null;
    this.renderFields();
    this.onChange(this.fields);
    if (this.onSelect) this.onSelect(null);
  }

  selectField(id) {
    this.selectedId = id;
    this.renderFields();
    if (this.onSelect) this.onSelect(id);
  }

  deselect() {
    this.selectedId = null;
    this.renderFields();
    if (this.onSelect) this.onSelect(null);
  }

  updateFieldPosition(id, pos) {
    const field = this.fields.find(f => f.id === id);
    if (field) {
      field.position = JSON.stringify(pos);
      this.onChange(this.fields);
    }
  }

  updateSelectedField(data) {
    if (!this.selectedId) return;

    const field = this.fields.find(f => f.id === this.selectedId);
    if (!field) return;

    Object.assign(field, data);
    this.renderFields();
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
