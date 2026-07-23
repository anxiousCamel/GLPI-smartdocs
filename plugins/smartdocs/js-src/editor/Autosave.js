/**
 * Autosave com debounce de 5 segundos.
 */

export class Autosave {
  constructor(ajaxUrl, templateId, intervalMs = 5000) {
    this.ajaxUrl = ajaxUrl;
    this.templateId = templateId;
    this.intervalMs = intervalMs;
    this.timer = null;
    this.pendingFields = null;
  }

  schedule(fields) {
    this.pendingFields = fields;

    if (this.timer) {
      clearTimeout(this.timer);
    }

    this.timer = setTimeout(() => this.save(), this.intervalMs);
  }

  /**
   * Cancela o debounce e salva imediatamente, se houver alterações
   * pendentes. Usado antes de ações que dependem do estado persistido
   * (ex.: publicar).
   *
   * @return {Promise<boolean>} true se salvou (ou não havia nada a salvar)
   */
  async flush() {
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }

    if (!this.pendingFields) return true;

    return this.save();
  }

  async save() {
    if (!this.pendingFields) return true;

    const statusEl = document.getElementById('autosave-status');
    if (statusEl) statusEl.textContent = 'Salvando...';

    let success = false;

    try {
      const res = await fetch(this.ajaxUrl + 'save-fields.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          template_id: this.templateId,
          fields: this.pendingFields,
        }),
      });

      const text = await res.text();
      let json = null;
      try {
        json = JSON.parse(text);
      } catch (e) {
        console.error('[SmartDocs] Resposta não-JSON no autosave:', text);
      }

      if (res.status === 401 || res.status === 403) {
        if (statusEl) statusEl.textContent = 'Sessão expirada. Recarregue a página.';
        return false;
      }

      success = !!(json && json.success);
      if (success && statusEl) {
        statusEl.textContent = 'Salvo em ' + new Date().toLocaleTimeString();
      } else if (statusEl) {
        const msg = (json && json.message) ? json.message : 'Falha ao salvar (tentando de novo em 5s)';
        statusEl.textContent = msg;
      }
    } catch (err) {
      console.error('[SmartDocs] Autosave falhou:', err);
      if (statusEl) statusEl.textContent = 'Falha de rede ao salvar (tentando de novo em 5s)';
    }

    if (success) {
      this.pendingFields = null;
    } else {
      // Mantém pendingFields e reagenda — se não sobrescrevermos com null,
      // a próxima mudança do usuário (ou este próprio retry) reenvia. Sem
      // isto o autosave desistia silenciosamente após um único 500/erro de
      // rede e o rascunho nunca mais salvava até o usuário editar de novo.
      this.timer = setTimeout(() => this.save(), this.intervalMs);
    }

    return success;
  }
}
