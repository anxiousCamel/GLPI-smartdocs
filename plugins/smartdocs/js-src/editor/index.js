/**
 * SmartDocs — Editor Visual de Templates PDF
 *
 * Entry point do bundle. Inicializa todos os componentes do editor
 * quando o DOM estiver pronto.
 */

import { PdfRenderer } from './PdfRenderer.js';
import { CanvasEditor } from './CanvasEditor.js';
import { FieldPanel } from './FieldPanel.js';
import { PropertiesPanel } from './PropertiesPanel.js';
import { HistoryManager } from './HistoryManager.js';
import { Autosave } from './Autosave.js';

class TemplateEditor {
  constructor(data) {
    this.data = data;
    this.fields = data.fields || [];
    this.selectedFieldId = null;

    this.container = document.getElementById('smartdocs-editor-root');
    if (!this.container) {
      console.error('[SmartDocs] Container #smartdocs-editor-root não encontrado.');
      return;
    }

    this.initLayout();
    this.initComponents();
    this.bindEvents();
  }

  initLayout() {
    this.container.innerHTML = `
      <div class="smartdocs-editor-layout">
        <div class="editor-toolbar">
          <div class="toolbar-left">
            <h3 class="template-name">${this.escapeHtml(this.data.name)}</h3>
            <span class="badge ${this.data.status === 'PUBLISHED' ? 'bg-green-lt' : 'bg-yellow-lt'}">
              ${this.data.status === 'PUBLISHED' ? 'Publicado' : 'Rascunho'}
            </span>
            <span class="autosave-status text-muted small ms-2" id="autosave-status"></span>
          </div>
          <div class="toolbar-right">
            <button type="button" class="btn btn-secondary btn-sm" id="btn-undo" title="Desfazer (Ctrl+Z)">
              <i class="ti ti-arrow-back-up"></i>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-redo" title="Refazer (Ctrl+Y)">
              <i class="ti ti-arrow-forward-up"></i>
            </button>
            <button type="button" class="btn btn-primary btn-sm ms-2" id="btn-publish"
              ${this.data.status === 'PUBLISHED' ? 'disabled' : ''}>
              <i class="ti ti-check"></i> Publicar
            </button>
          </div>
        </div>
        <div class="editor-body">
          <div class="editor-sidebar" id="field-panel"></div>
          <div class="editor-canvas-wrapper" id="canvas-wrapper">
            <div id="pdf-container"></div>
            <div id="konva-container"></div>
          </div>
          <div class="editor-sidebar" id="properties-panel"></div>
        </div>
        <div class="editor-footer">
          <div class="page-thumbnails" id="page-thumbnails"></div>
        </div>
      </div>
    `;

    this.injectStyles();
  }

  injectStyles() {
    if (document.getElementById('smartdocs-editor-styles')) return;

    const style = document.createElement('style');
    style.id = 'smartdocs-editor-styles';
    style.textContent = `
      .smartdocs-editor-layout {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 180px);
        min-height: 600px;
        background: #f4f6f8;
        border-radius: 4px;
        overflow: hidden;
      }
      .editor-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: #fff;
        border-bottom: 1px solid #e2e8f0;
      }
      .toolbar-left { display: flex; align-items: center; gap: 8px; }
      .template-name { margin: 0; font-size: 1.1rem; }
      .editor-body {
        display: flex;
        flex: 1;
        overflow: hidden;
      }
      .editor-sidebar {
        width: 220px;
        background: #fff;
        border-right: 1px solid #e2e8f0;
        padding: 12px;
        overflow-y: auto;
      }
      .editor-sidebar:last-child { border-right: none; border-left: 1px solid #e2e8f0; }
      .editor-canvas-wrapper {
        flex: 1;
        position: relative;
        overflow: auto;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 24px;
      }
      #pdf-container {
        position: relative;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        background: #fff;
      }
      #konva-container {
        position: absolute;
        top: 24px;
        left: 50%;
        transform: translateX(-50%);
        pointer-events: auto;
      }
      .editor-footer {
        height: 80px;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        padding: 8px 16px;
        overflow-x: auto;
      }
      .page-thumbnails { display: flex; gap: 8px; }
      .page-thumb {
        width: 60px;
        height: 80px;
        border: 2px solid #e2e8f0;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: #666;
      }
      .page-thumb.active { border-color: #206bc4; }
      .field-item {
        padding: 10px 12px;
        margin-bottom: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        cursor: grab;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        transition: all 0.15s;
      }
      .field-item:hover { border-color: #206bc4; background: #f0f7ff; }
      .field-item:active { cursor: grabbing; }
      .field-item i { font-size: 16px; color: #206bc4; }
      .field-canvas {
        position: absolute;
        border: 2px dashed #206bc4;
        background: rgba(32,107,196,0.08);
        border-radius: 3px;
        cursor: move;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #206bc4;
        user-select: none;
      }
      .field-canvas.selected {
        border-style: solid;
        background: rgba(32,107,196,0.18);
        box-shadow: 0 0 0 3px rgba(32,107,196,0.2);
      }
      .field-canvas .resize-handle {
        position: absolute;
        width: 8px;
        height: 8px;
        background: #206bc4;
        border-radius: 50%;
        bottom: -4px;
        right: -4px;
        cursor: nwse-resize;
      }
      .prop-group { margin-bottom: 14px; }
      .prop-group label { display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px; color: #444; }
      .prop-group input, .prop-group select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 13px;
      }
      .prop-group input:focus, .prop-group select:focus {
        outline: none;
        border-color: #206bc4;
        box-shadow: 0 0 0 2px rgba(32,107,196,0.15);
      }
    `;
    document.head.appendChild(style);
  }

  initComponents() {
    this.history = new HistoryManager(this.fields);
    this.autosave = new Autosave(this.data.ajax_url, this.data.template_id);

    this.fieldPanel = new FieldPanel(
      document.getElementById('field-panel'),
      (type) => this.canvasEditor.addField(type)
    );

    this.propertiesPanel = new PropertiesPanel(
      document.getElementById('properties-panel'),
      (fieldData) => this.canvasEditor.updateSelectedField(fieldData)
    );

    this.pdfRenderer = new PdfRenderer(
      document.getElementById('pdf-container'),
      this.data.pdf_url
    );

    this.canvasEditor = new CanvasEditor(
      document.getElementById('konva-container'),
      this.fields,
      (fieldId) => this.onFieldSelect(fieldId),
      (fields) => this.onFieldsChange(fields)
    );
  }

  bindEvents() {
    document.getElementById('btn-undo').addEventListener('click', () => this.undo());
    document.getElementById('btn-redo').addEventListener('click', () => this.redo());
    document.getElementById('btn-publish').addEventListener('click', () => this.publish());

    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
        e.preventDefault();
        this.undo();
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
        e.preventDefault();
        this.redo();
      }
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (this.selectedFieldId && document.activeElement.tagName !== 'INPUT') {
          this.canvasEditor.deleteField(this.selectedFieldId);
        }
      }
    });

    // Inicializa PDF quando carregar
    if (this.data.pdf_url) {
      this.pdfRenderer.load().then(({ width, height }) => {
        this.canvasEditor.setPageSize(width, height);
      });
    } else {
      // Fallback: página A4 em 96 DPI
      this.canvasEditor.setPageSize(794, 1123);
    }
  }

  onFieldSelect(fieldId) {
    this.selectedFieldId = fieldId;
    const field = this.fields.find(f => f.id === fieldId);
    this.propertiesPanel.show(field);
  }

  onFieldsChange(fields) {
    this.fields = fields;
    this.history.push([...fields]);
    this.autosave.schedule(fields);
    document.getElementById('autosave-status').textContent = 'Modificado';
  }

  undo() {
    const previous = this.history.undo();
    if (previous) {
      this.fields = previous;
      this.canvasEditor.setFields(previous);
      this.autosave.schedule(previous);
    }
  }

  redo() {
    const next = this.history.redo();
    if (next) {
      this.fields = next;
      this.canvasEditor.setFields(next);
      this.autosave.schedule(next);
    }
  }

  async publish() {
    const btn = document.getElementById('btn-publish');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> Publicando...';

    try {
      const res = await fetch(this.data.ajax_url + 'publish-template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ template_id: this.data.template_id }),
      });
      const json = await res.json();
      if (json.success) {
        btn.innerHTML = '<i class="ti ti-check"></i> Publicado';
        btn.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => location.reload(), 800);
      } else {
        alert(json.message || 'Erro ao publicar.');
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check"></i> Publicar';
      }
    } catch (err) {
      alert('Erro de comunicação: ' + err.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="ti ti-check"></i> Publicar';
    }
  }

  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Inicializa quando o DOM estiver pronto
if (window.SmartDocsEditor && window.SmartDocsEditor.data) {
  new TemplateEditor(window.SmartDocsEditor.data);
} else {
  document.addEventListener('DOMContentLoaded', () => {
    if (window.SmartDocsEditor && window.SmartDocsEditor.data) {
      new TemplateEditor(window.SmartDocsEditor.data);
    }
  });
}

export { TemplateEditor };
