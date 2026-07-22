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
    this.selectedIds = [];

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
            <div class="zoom-controls me-2">
              <button type="button" class="btn btn-secondary btn-sm" id="btn-zoom-out" title="Diminuir zoom">
                <i class="ti ti-zoom-out"></i>
              </button>
              <span id="zoom-level" class="zoom-level">100%</span>
              <button type="button" class="btn btn-secondary btn-sm" id="btn-zoom-in" title="Aumentar zoom">
                <i class="ti ti-zoom-in"></i>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btn-zoom-reset" title="Resetar zoom (scroll para zoom, botão direito+arrastar para mover)">
                <i class="ti ti-focus-2"></i>
              </button>
            </div>
            <button type="button" class="btn btn-secondary btn-sm me-2" id="btn-toggle-grid" title="Ativar/desativar grade de alinhamento">
              <i class="ti ti-grid-dots"></i>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-undo" title="Desfazer (Ctrl+Z)">
              <i class="ti ti-arrow-back-up"></i>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-redo" title="Refazer (Ctrl+Y)">
              <i class="ti ti-arrow-forward-up"></i>
            </button>
            <button type="button" class="btn btn-primary btn-sm ms-2" id="btn-publish">
              <i class="ti ti-check"></i> ${this.data.status === 'PUBLISHED' ? 'Atualizar publicação' : 'Publicar'}
            </button>
          </div>
        </div>
        <div class="editor-body">
          <div class="editor-sidebar" id="field-sidebar">
            <button type="button" class="sidebar-collapse-btn" id="toggle-field-sidebar" title="Recolher painel">
              <i class="ti ti-chevron-left"></i>
            </button>
            <div class="sidebar-inner" id="field-panel"></div>
          </div>
          <div class="editor-canvas-wrapper" id="canvas-wrapper">
            <div class="page-spacer" id="page-spacer">
              <div class="page-stage" id="page-stage">
                <div id="pdf-container"></div>
                <div id="konva-container"></div>
              </div>
            </div>
          </div>
          <div class="editor-sidebar" id="properties-sidebar">
            <button type="button" class="sidebar-collapse-btn" id="toggle-properties-sidebar" title="Recolher painel">
              <i class="ti ti-chevron-right"></i>
            </button>
            <div class="sidebar-inner" id="properties-panel"></div>
          </div>
        </div>
        <div class="editor-footer">
          <div class="page-thumbnails" id="page-thumbnails"></div>
          <div class="shortcuts-hints">
            <div class="shortcut-item">
              <i class="ti ti-scroll"></i>
              <span><strong>Scroll</strong> zoom</span>
            </div>
            <div class="shortcut-item">
              <i class="ti ti-mouse-2"></i>
              <span><strong>Botão direito</strong> + arrastar: mover</span>
            </div>
            <div class="shortcut-item">
              <i class="ti ti-click"></i>
              <span><strong>Shift</strong> + clique/arrastar: seleção</span>
            </div>
            <div class="shortcut-item">
              <i class="ti ti-copy-check"></i>
              <span><strong>Ctrl+C/V</strong> copiar/colar</span>
            </div>
          </div>
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
        min-width: 220px;
        background: #fff;
        border-right: 1px solid #e2e8f0;
        padding: 12px;
        overflow-y: auto;
        transition: width 0.15s, min-width 0.15s;
        position: relative;
      }
      .editor-sidebar:last-child { border-right: none; border-left: 1px solid #e2e8f0; }
      .editor-sidebar.collapsed {
        width: 36px;
        min-width: 36px;
        padding: 8px 4px;
        overflow: hidden;
      }
      .editor-sidebar.collapsed .sidebar-inner { display: none; }
      .sidebar-collapse-btn {
        width: 100%;
        background: transparent;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        padding: 6px;
        margin-bottom: 8px;
        cursor: pointer;
        color: #667;
        text-align: center;
        border-radius: 4px;
      }
      .sidebar-collapse-btn:hover { background: #f4f6f8; color: #206bc4; }
      .editor-sidebar.collapsed .sidebar-collapse-btn { border-bottom: none; margin-bottom: 0; }
      .editor-canvas-wrapper {
        flex: 1;
        position: relative;
        overflow: auto;
        padding: 24px;
      }
      .page-spacer {
        position: relative;
        margin: 0 auto;
      }
      .page-stage {
        position: absolute;
        top: 0;
        left: 0;
        transform-origin: 0 0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      }
      #pdf-container {
        position: relative;
        background: #fff;
      }
      #konva-container {
        position: absolute;
        top: 0;
        left: 0;
        pointer-events: auto;
      }
      .editor-footer {
        height: 80px;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        padding: 8px 16px;
        overflow-x: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      }
      .shortcuts-hints {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        font-size: 12px;
      }
      .shortcut-item {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: #f4f6f8;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
      }
      .shortcut-item i {
        font-size: 14px;
        color: #206bc4;
        flex-shrink: 0;
      }
      .shortcut-item span {
        color: #444;
      }
      .shortcut-item strong {
        font-weight: 600;
        color: #206bc4;
      }
      .zoom-controls { display: inline-flex; align-items: center; gap: 4px; }
      .zoom-level { font-size: 12px; color: #666; width: 42px; text-align: center; display: inline-block; }
      .prop-group-swatch {
        display: inline-block;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        flex-shrink: 0;
        border: 1px solid rgba(0,0,0,0.15);
      }
      .prop-group label { display: flex; align-items: center; gap: 4px; }
      .prop-group label .ti-info-circle { font-size: 13px; }
      #prop-font-bold.active, .align-btn.active {
        background: #206bc4;
        border-color: #206bc4;
        color: #fff;
      }
      #btn-toggle-grid.active-toggle {
        background: #206bc4;
        border-color: #206bc4;
        color: #fff;
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
      (data) => this.canvasEditor.updateSelectedFields(data),
      this.data.binding_keys || []
    );

    this.pdfRenderer = new PdfRenderer(
      document.getElementById('pdf-container'),
      this.data.pdf_url
    );

    this.canvasEditor = new CanvasEditor(
      document.getElementById('konva-container'),
      this.fields,
      (ids) => this.onSelectionChange(ids),
      (fields) => this.onFieldsChange(fields)
    );

    this.initZoomPan();
  }

  /**
   * Zoom/pan do canvas. O PDF (<canvas> do pdf.js) e o Konva ficam em
   * elementos DOM separados dentro de #page-stage — escalamos os dois
   * juntos via CSS transform nesse wrapper compartilhado, em vez de
   * escalar só o stage do Konva (o que descolava os campos do PDF).
   * O "spacer" ao redor define a área de scroll real (transform não
   * altera o tamanho de layout do elemento).
   */
  initZoomPan() {
    this.zoom = 1;
    this.minZoom = 0.2;
    this.maxZoom = 4;

    this.wrapperEl = document.getElementById('canvas-wrapper');
    this.spacerEl = document.getElementById('page-spacer');
    this.stageEl = document.getElementById('page-stage');

    this.wrapperEl.addEventListener('wheel', (e) => {
      // Sem exigir Ctrl: scroll sozinho já dá zoom (uso com uma mão só).
      // O pan (botão direito+arrastar) cobre a navegação lateral/vertical,
      // então o wheel não precisa mais rolar a página normalmente.
      e.preventDefault();

      const rect = this.wrapperEl.getBoundingClientRect();
      const pointerX = e.clientX - rect.left + this.wrapperEl.scrollLeft;
      const pointerY = e.clientY - rect.top + this.wrapperEl.scrollTop;
      const unscaledX = pointerX / this.zoom;
      const unscaledY = pointerY / this.zoom;

      const factor = e.deltaY > 0 ? 1 / 1.08 : 1.08;
      this.setZoom(this.zoom * factor);

      this.wrapperEl.scrollLeft = unscaledX * this.zoom - (e.clientX - rect.left);
      this.wrapperEl.scrollTop = unscaledY * this.zoom - (e.clientY - rect.top);
    }, { passive: false });

    // Botão direito + arrastar move a tela (padrão CAD). Evitamos a barra
    // de espaço: o navegador rola a página com ela por padrão e cancelar
    // isso de forma confiável em todo contexto (botões, links) é frágil —
    // media o "teleporte" relatado.
    let panning = false;
    let panStart = null;
    let suppressNextContextMenu = false;

    this.wrapperEl.addEventListener('mousedown', (e) => {
      if (e.button !== 2) return;
      e.preventDefault();
      panning = true;
      suppressNextContextMenu = true;
      panStart = { x: e.clientX, y: e.clientY, scrollLeft: this.wrapperEl.scrollLeft, scrollTop: this.wrapperEl.scrollTop };
      this.wrapperEl.style.cursor = 'grabbing';
    });
    window.addEventListener('mousemove', (e) => {
      if (!panning) return;
      this.wrapperEl.scrollLeft = panStart.scrollLeft - (e.clientX - panStart.x);
      this.wrapperEl.scrollTop = panStart.scrollTop - (e.clientY - panStart.y);
    });
    window.addEventListener('mouseup', (e) => {
      if (e.button !== 2 || !panning) return;
      panning = false;
      this.wrapperEl.style.cursor = 'default';
    });
    // O botão direito abriria o menu de contexto do navegador ao soltar —
    // bloqueia sempre que o arrasto começou no canvas, mesmo se o mouse
    // tiver saído da área (o evento 'contextmenu' dispara em qualquer
    // elemento sob o cursor no momento do soltar).
    window.addEventListener('contextmenu', (e) => {
      if (suppressNextContextMenu) {
        e.preventDefault();
        suppressNextContextMenu = false;
      }
    });
  }

  setZoom(zoom) {
    this.zoom = Math.max(this.minZoom, Math.min(this.maxZoom, zoom));
    this.stageEl.style.transform = `scale(${this.zoom})`;
    this.spacerEl.style.width = (this.canvasEditor.pageWidth * this.zoom) + 'px';
    this.spacerEl.style.height = (this.canvasEditor.pageHeight * this.zoom) + 'px';
    document.getElementById('zoom-level').textContent = Math.round(this.zoom * 100) + '%';
  }

  zoomBy(factor) {
    this.setZoom(this.zoom * factor);
  }

  zoomReset() {
    this.setZoom(1);
  }

  bindEvents() {
    document.getElementById('btn-undo').addEventListener('click', () => this.undo());
    document.getElementById('btn-redo').addEventListener('click', () => this.redo());
    document.getElementById('btn-publish').addEventListener('click', () => this.publish());
    document.getElementById('btn-zoom-in').addEventListener('click', () => this.zoomBy(1.2));
    document.getElementById('btn-zoom-out').addEventListener('click', () => this.zoomBy(1 / 1.2));
    document.getElementById('btn-zoom-reset').addEventListener('click', () => this.zoomReset());

    document.getElementById('btn-toggle-grid').addEventListener('click', (e) => {
      const enabled = this.canvasEditor.toggleGrid();
      e.currentTarget.classList.toggle('active-toggle', enabled);
    });

    document.getElementById('toggle-field-sidebar').addEventListener('click', () => {
      const el = document.getElementById('field-sidebar');
      const collapsed = el.classList.toggle('collapsed');
      el.querySelector('.sidebar-collapse-btn i').className = collapsed ? 'ti ti-chevron-right' : 'ti ti-chevron-left';
    });

    document.getElementById('toggle-properties-sidebar').addEventListener('click', () => {
      const el = document.getElementById('properties-sidebar');
      const collapsed = el.classList.toggle('collapsed');
      el.querySelector('.sidebar-collapse-btn i').className = collapsed ? 'ti ti-chevron-left' : 'ti ti-chevron-right';
    });

    document.addEventListener('keydown', (e) => {
      const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);

      if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
        e.preventDefault();
        this.undo();
        return;
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
        e.preventDefault();
        this.redo();
        return;
      }
      if (isTyping) return;

      if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        this.canvasEditor.selectAll();
        return;
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
        e.preventDefault();
        this.canvasEditor.copySelected();
        return;
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
        e.preventDefault();
        this.canvasEditor.paste();
        return;
      }
      if (e.key === 'Escape') {
        this.canvasEditor.deselectAll();
        return;
      }
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (this.selectedIds.length > 0) {
          e.preventDefault();
          this.canvasEditor.deleteSelected();
        }
      }
    });

    // Inicializa PDF quando carregar
    if (this.data.pdf_url) {
      this.pdfRenderer.load().then(({ width, height }) => {
        this.canvasEditor.setPageSize(width, height);
        this.setZoom(this.zoom);
      });
    } else {
      // Fallback: página A4 em 96 DPI
      this.canvasEditor.setPageSize(794, 1123);
      this.setZoom(this.zoom);
    }
  }

  onSelectionChange(ids) {
    this.selectedIds = ids;
    const selectedFields = this.fields.filter(f => ids.includes(f.id));
    this.propertiesPanel.setKnownGroups(this.getKnownGroups());
    this.propertiesPanel.show(selectedFields);
  }

  onFieldsChange(fields) {
    const groupIndexMap = this.canvasEditor.getGroupIndexMap();
    this.fields = fields.map((f) => {
      const updated = { ...f };
      if (updated.scope === 'item') {
        const gi = groupIndexMap.get(updated.group_label);
        updated.slot_index = gi !== undefined ? gi - 1 : null;
      } else {
        updated.slot_index = null;
      }
      return updated;
    });
    this.history.push([...this.fields]);
    this.autosave.schedule(this.fields);
    document.getElementById('autosave-status').textContent = 'Modificado';

    // Arrastar/redimensionar no canvas muda a posição sem passar pelo
    // painel de Propriedades — sem isto, os campos de X/Y/Largura/Altura
    // ficavam com o valor antigo na tela, e qualquer outra edição nesse
    // campo (label, grupo...) reenviava esse valor antigo e "resetava"
    // o tamanho que acabou de ser ajustado no canvas.
    if (this.selectedIds.length === 1) {
      const updated = fields.find(f => f.id === this.selectedIds[0]);
      if (updated) this.propertiesPanel.syncPosition(updated);
    }
  }

  /** @returns {{label: string, index: number}[]} grupos com o mesmo índice (G1, G2...) exibido no canvas */
  getKnownGroups() {
    const indexMap = this.canvasEditor.getGroupIndexMap();
    return [...indexMap.entries()]
      .map(([label, index]) => ({ label, index }))
      .sort((a, b) => a.index - b.index);
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
    const idleLabel = '<i class="ti ti-check"></i> ' + (this.data.status === 'PUBLISHED' ? 'Atualizar publicação' : 'Publicar');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 ti-spin"></i> Publicando...';

    const saved = await this.autosave.flush();
    if (!saved) {
      alert('Não foi possível salvar os campos antes de publicar. Tente novamente.');
      btn.disabled = false;
      btn.innerHTML = idleLabel;
      return;
    }

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
        btn.innerHTML = idleLabel;
      }
    } catch (err) {
      alert('Erro de comunicação: ' + err.message);
      btn.disabled = false;
      btn.innerHTML = idleLabel;
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
