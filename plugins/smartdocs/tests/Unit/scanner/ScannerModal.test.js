import { describe, it, expect, vi } from 'vitest';
import { ScannerModal } from '../../../js-src/scanner/ScannerModal.js';

describe('ScannerModal', () => {
  it('renderiza resultados corretamente', () => {
    const modal = new ScannerModal({
      ajaxUrl: '/test',
      itemtype: 'Computer',
      itemsId: 1,
      onSelect: vi.fn(),
    });

    document.body.innerHTML = '<div id="test-root"></div>';
    modal.open();

    modal.renderResults({
      candidates: [
        { type: 'serial', value: 'ABC123', confidence: 0.95 },
        { type: 'patrimonio', value: '0042', confidence: 0.80 },
      ],
      raw_text: 'Serial: ABC123\nPat: 0042',
    });

    const items = modal.overlay.querySelectorAll('.scanner-candidates .list-group-item');
    expect(items.length).toBe(2);
    expect(items[0].textContent).toContain('ABC123');
    expect(items[0].textContent).toContain('95%');
  });

  it('escapeHtml previne XSS', () => {
    const modal = new ScannerModal({
      ajaxUrl: '/test',
      itemtype: 'Computer',
      itemsId: 1,
      onSelect: vi.fn(),
    });

    const evil = '<script>alert(1)</script>';
    expect(modal.escapeHtml(evil)).toBe('<script>alert(1)</script>');
  });
});
