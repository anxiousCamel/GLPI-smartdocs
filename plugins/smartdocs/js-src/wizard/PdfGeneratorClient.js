/**
 * PdfGeneratorClient — Cliente para enfileirar e acompanhar geração de PDF.
 */

export class PdfGeneratorClient {
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
    this.pollInterval = 3000;
    this.maxAttempts = 60; // 3 minutos
  }

  async enqueue(documentId) {
    const response = await fetch(`${this.ajaxUrl}generate-pdf.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ document_id: documentId }),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Erro ao enfileirar PDF');
    }

    return data;
  }

  poll(jobId, callback) {
    let attempts = 0;

    const check = async () => {
      attempts++;

      try {
        const response = await fetch(`${this.ajaxUrl}job-status.php?job_id=${jobId}`);
        const data = await response.json();

        if (!response.ok) {
          callback('ERROR', { message: data.message || 'Erro desconhecido' });
          return;
        }

        if (data.status === 'DONE') {
          callback('DONE', data);
          return;
        }

        if (data.status === 'ERROR') {
          callback('ERROR', data);
          return;
        }

        if (attempts >= this.maxAttempts) {
          callback('ERROR', { message: 'Tempo esgotado aguardando geração do PDF.' });
          return;
        }

        callback(data.status, data);
        setTimeout(check, this.pollInterval);
      } catch (e) {
        callback('ERROR', { message: e.message });
      }
    };

    check();
  }
}
