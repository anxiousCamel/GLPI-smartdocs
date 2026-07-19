/**
 * AssetSelector — Cliente para busca de ativos GLPI.
 */

export class AssetSelector {
  constructor(ajaxUrl) {
    this.ajaxUrl = ajaxUrl;
  }

  async search(query, types = ['Computer']) {
    if (!query || query.length < 2) {
      return [];
    }

    // Busca via Search do GLPI (simplificado — busca por nome/serial)
    const results = [];

    for (const itemtype of types) {
      try {
        const response = await fetch(
          `${this.ajaxUrl}asset-search.php?q=${encodeURIComponent(query)}&itemtype=${itemtype}`,
          { method: 'GET', headers: { 'Content-Type': 'application/json' } }
        );

        if (response.ok) {
          const data = await response.json();
          if (data.results) {
            results.push(...data.results);
          }
        }
      } catch (e) {
        console.warn(`[SmartDocs] Erro na busca de ${itemtype}:`, e);
      }
    }

    return results;
  }
}
