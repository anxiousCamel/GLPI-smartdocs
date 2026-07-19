/**
 * Painel esquerdo com tipos de campo arrastáveis.
 */

export class FieldPanel {
  constructor(container, onAddField) {
    this.container = container;
    this.onAddField = onAddField;
    this.render();
  }

  render() {
    this.container.innerHTML = `
      <h5 class="mb-3">${this.t('Campos')}</h5>
      <p class="text-muted small mb-3">Clique para adicionar ao template</p>
      <div class="field-list"></div>
    `;

    const list = this.container.querySelector('.field-list');
    const types = [
      { type: 'text', label: this.t('Texto'), icon: 'ti-text-scan-2' },
      { type: 'image', label: this.t('Imagem'), icon: 'ti-photo' },
      { type: 'signature', label: this.t('Assinatura'), icon: 'ti-writing-sign' },
      { type: 'checkbox', label: this.t('Checkbox'), icon: 'ti-checkbox' },
    ];

    types.forEach(({ type, label, icon }) => {
      const item = document.createElement('div');
      item.className = 'field-item';
      item.innerHTML = `<i class="ti ${icon}"></i> <span>${label}</span>`;
      item.addEventListener('click', () => this.onAddField(type));
      list.appendChild(item);
    });
  }

  t(key) {
    // Fallback simples — o GLPI carrega as traduções via locale.php
    return key;
  }
}
