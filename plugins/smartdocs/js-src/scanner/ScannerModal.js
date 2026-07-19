/**
 * SmartDocs Scanner Modal
 *
 * Modal de upload + OCR com preview de imagem, lista de candidatos
 * e seleção para preenchimento automático de campos do GLPI.
 */

export class ScannerModal {
  constructor({ ajaxUrl, itemtype, itemsId, onSelect }) {
    this.ajaxUrl = ajaxUrl;
    this.itemtype = itemtype;
    this.itemsId = itemsId;
    this.onSelect = onSelect;
    this.overlay = null;
    this.fileInput = null;
    this.previewImg = null;
    this.resultsPanel = null;
    this.typeHint = 'serial';
  }

  open() {
    if (this.overlay) {
      this.overlay.style.display = 'flex';
      return;
    }

    this.overlay = document.createElement('div');
    this.overlay.className = 'smartdocs-scanner-overlay';
    this.overlay.innerHTML = this.buildHTML();
    document.body.appendChild(this.overlay);

    this.bindEvents();
  }

  close() {
    if (this.overlay) {
      this.overlay.style.display = 'none';
    }
  }

  buildHTML() {
    return `
      <div class="smartdocs-scanner-modal">
        <div class="scanner-header">
          <h5>📷 Digitalizar Etiqueta (OCR)</h5>
          <button type="button" class="btn-close scanner-close" aria-label="Fechar"></button>
        </div>
        <div class="scanner-body">
          <div class="scanner-controls">
            <label class="form-label">Tipo de informação:</label>
            <select class="form-select form-select-sm scanner-type-hint">
              <option value="serial" selected>Nº de Série / Serial</option>
              <option value="patrimonio">Patrimônio / Asset Tag</option>
              <option value="modelo">Modelo / Produto</option>
            </select>
            <label class="form-label mt-2">Imagem ou PDF da etiqueta:</label>
            <input type="file" class="form-control scanner-file" accept="image/*,.pdf">
            <small class="form-text text-muted">Aceita PNG, JPEG, TIFF, BMP ou PDF.</small>
          </div>
          <div class="scanner-preview mt-3" style="display:none;">
            <img class="scanner-preview-img img-thumbnail" style="max-height:200px;">
          </div>
          <div class="scanner-results mt-3" style="display:none;">
            <label class="form-label">Resultados detectados:</label>
            <div class="scanner-candidates list-group"></div>
            <div class="scanner-raw mt-2">
              <small class="text-muted">Texto bruto:</small>
              <pre class="scanner-raw-text bg-light p-2"></pre>
            </div>
          </div>
          <div class="scanner-loading mt-3 text-center" style="display:none;">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Processando OCR...</p>
          </div>
        </div>
        <div class="scanner-footer">
          <button type="button" class="btn btn-secondary scanner-close">Cancelar</button>
          <button type="button" class="btn btn-primary scanner-submit" disabled>Digitalizar</button>
        </div>
      </div>
    `;
  }

  bindEvents() {
    this.overlay.querySelectorAll('.scanner-close').forEach((btn) => {
      btn.addEventListener('click', () => this.close());
    });

    this.overlay.addEventListener('click', (e) => {
      if (e.target === this.overlay) this.close();
    });

    this.typeHint = this.overlay.querySelector('.scanner-type-hint').value;
    this.overlay.querySelector('.scanner-type-hint').addEventListener('change', (e) => {
      this.typeHint = e.target.value;
    });

    this.fileInput = this.overlay.querySelector('.scanner-file');
    this.previewImg = this.overlay.querySelector('.scanner-preview-img');
    this.resultsPanel = this.overlay.querySelector('.scanner-results');

    this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));

    const submitBtn = this.overlay.querySelector('.scanner-submit');
    submitBtn.addEventListener('click', () => this.submitScan());
  }

  handleFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;

    const previewPanel = this.overlay.querySelector('.scanner-preview');
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (ev) => {
        this.previewImg.src = ev.target.result;
        previewPanel.style.display = 'block';
      };
      reader.readAsDataURL(file);
    } else {
      this.previewImg.src = '';
      this.previewImg.alt = file.name;
      previewPanel.style.display = 'block';
    }

    this.overlay.querySelector('.scanner-submit').disabled = false;
    this.resultsPanel.style.display = 'none';
  }

  async submitScan() {
    const file = this.fileInput.files[0];
    if (!file) return;

    const loading = this.overlay.querySelector('.scanner-loading');
    loading.style.display = 'block';
    this.resultsPanel.style.display = 'none';
    this.overlay.querySelector('.scanner-submit').disabled = true;

    const formData = new FormData();
    formData.append('scan', file);
    formData.append('type_hint', this.typeHint);

    try {
      const response = await fetch(this.ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error || 'Erro desconhecido no OCR');
      }

      this.renderResults(data);
    } catch (err) {
      alert('Erro no OCR: ' + err.message);
    } finally {
      loading.style.display = 'none';
      this.overlay.querySelector('.scanner-submit').disabled = false;
    }
  }

  renderResults(data) {
    const container = this.overlay.querySelector('.scanner-candidates');
    container.innerHTML = '';

    if (data.candidates && data.candidates.length > 0) {
      data.candidates.forEach((c) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        btn.innerHTML = `
          <span><strong>${this.labelType(c.type)}</strong>: ${this.escapeHtml(c.value)}</span>
          <span class="badge bg-primary rounded-pill">${Math.round((c.confidence || 0) * 100)}%</span>
        `;
        btn.addEventListener('click', () => {
          this.onSelect(c);
          this.close();
        });
        container.appendChild(btn);
      });
    } else {
      container.innerHTML = '<div class="list-group-item text-muted">Nenhum candidato detectado.</div>';
    }

    this.overlay.querySelector('.scanner-raw-text').textContent = data.raw_text || '';
    this.resultsPanel.style.display = 'block';
  }

  labelType(type) {
    const map = { serial: 'Série', patrimonio: 'Patrimônio', modelo: 'Modelo' };
    return map[type] || type;
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
}
