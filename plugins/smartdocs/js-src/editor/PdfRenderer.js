/**
 * Renderiza páginas de PDF no browser usando pdfjs-dist.
 */

import * as pdfjsLib from 'pdfjs-dist';
import PdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = PdfWorker;

export class PdfRenderer {
  constructor(container, pdfUrl) {
    this.container = container;
    this.pdfUrl = pdfUrl;
    this.pdfDoc = null;
    this.scale = 1.5;
  }

  async load() {
    if (!this.pdfUrl) return { width: 794, height: 1123 };

    try {
      const loadingTask = pdfjsLib.getDocument(this.pdfUrl);
      this.pdfDoc = await loadingTask.promise;

      // Renderiza a primeira página como preview
      const page = await this.pdfDoc.getPage(1);
      const viewport = page.getViewport({ scale: this.scale });

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      canvas.style.width = viewport.width + 'px';
      canvas.style.height = viewport.height + 'px';

      await page.render({ canvasContext: ctx, viewport }).promise;

      this.container.innerHTML = '';
      this.container.appendChild(canvas);

      // Renderiza miniaturas das demais páginas
      this.renderThumbnails();

      return { width: viewport.width, height: viewport.height };
    } catch (err) {
      console.error('[SmartDocs] Erro ao carregar PDF:', err);
      this.container.innerHTML = `<div class="alert alert-danger">Erro ao carregar PDF: ${err.message}</div>`;
      return { width: 794, height: 1123 };
    }
  }

  async renderThumbnails() {
    const thumbContainer = document.getElementById('page-thumbnails');
    if (!thumbContainer || !this.pdfDoc) return;

    thumbContainer.innerHTML = '';

    for (let i = 1; i <= this.pdfDoc.numPages; i++) {
      const page = await this.pdfDoc.getPage(i);
      const viewport = page.getViewport({ scale: 0.2 });

      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      canvas.width = viewport.width;
      canvas.height = viewport.height;

      await page.render({ canvasContext: ctx, viewport }).promise;

      const thumb = document.createElement('div');
      thumb.className = 'page-thumb' + (i === 1 ? ' active' : '');
      thumb.textContent = i;
      thumb.title = `Página ${i}`;
      thumb.addEventListener('click', () => this.goToPage(i));
      thumbContainer.appendChild(thumb);
    }
  }

  goToPage(pageNum) {
    // Por enquanto apenas marca ativa; expansão futura: scroll
    document.querySelectorAll('.page-thumb').forEach((t, i) => {
      t.classList.toggle('active', i + 1 === pageNum);
    });
  }
}
