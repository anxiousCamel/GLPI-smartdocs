/**
 * SmartDocs Scanner App
 *
 * Gerencia o botão de scanner injetado nas telas de ativos do GLPI
 * e orquestra o modal de OCR.
 */

import { ScannerModal } from './ScannerModal.js';

export class ScannerApp {
  constructor(rootEl) {
    this.root = rootEl;
    this.ajaxUrl = rootEl.dataset.ajaxUrl;
    this.itemtype = rootEl.dataset.itemtype;
    this.itemsId = parseInt(rootEl.dataset.itemsId || '0', 10);
    this.modal = null;
    this.button = null;
  }

  init() {
    this.button = document.createElement('button');
    this.button.type = 'button';
    this.button.className = 'btn btn-outline-info btn-sm ms-2';
    this.button.innerHTML = '📷 ' + window._smartdocs_i18n?.scan || 'Digitalizar';
    this.button.title = window._smartdocs_i18n?.scanHint || 'Digitalizar etiqueta para preencher campos automaticamente';
    this.button.addEventListener('click', () => this.openModal());

    // Tenta inserir ao lado do botão de atualização ou no topo do form
    const formHeader = document.querySelector('.asset .card-header, .tab-content form, #mainformtable');
    if (formHeader) {
      const actions = formHeader.querySelector('.card-header-actions, .float-end, .actions-header');
      if (actions) {
        actions.prepend(this.button);
      } else {
        formHeader.prepend(this.button);
      }
    } else {
      this.root.appendChild(this.button);
    }
  }

  openModal() {
    if (!this.modal) {
      this.modal = new ScannerModal({
        ajaxUrl: this.ajaxUrl,
        itemtype: this.itemtype,
        itemsId: this.itemsId,
        onSelect: (candidate) => this.fillField(candidate),
      });
    }
    this.modal.open();
  }

  fillField(candidate) {
    const type = candidate.type;
    const value = candidate.value;

    const fieldMap = {
      serial: ['serial', 'otherserial', 'serial_number'],
      patrimonio: ['otherserial', 'asset_tag', 'patrimonio'],
      modelo: ['model', 'product_number', 'modelo'],
    };

    const possibleNames = fieldMap[type] || [];
    let filled = false;

    for (const name of possibleNames) {
      const input = document.querySelector(
        `input[name="${name}"], textarea[name="${name}"], select[name="${name}"]`
      );
      if (input && !input.value) {
        input.value = value;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        filled = true;
        break;
      }
    }

    if (!filled) {
      // Se não encontrou campo vazio, tenta copiar para clipboard
      navigator.clipboard?.writeText(value).catch(() => {});
      alert(`Valor detectado (${type}): ${value}\nCopiado para a área de transferência.`);
    }
  }
}
