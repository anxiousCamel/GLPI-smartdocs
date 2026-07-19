# PDF — O que estudar

**Repositório:** https://github.com/pluginsGLPI/pdf

## O que é

Plugin que adiciona geração de documentos PDF a partir de itemtypes do GLPI (ex.: exportar um ticket ou um relatório de ativos como PDF formatado).

## O que estudar

- **Geração de documento a partir de dados de itemtype**, tipicamente combinando template (HTML/Twig renderizado) + biblioteca de geração de PDF — bom exemplo de composição entre `20-Twig.md` (renderização) e uma dependência externa gerenciada via `23-Composer.md`.
- **Plugin que expõe pontos de extensão para OUTROS plugins** (ex.: permitir que outro plugin registre seu próprio "template de exportável PDF") — um padrão de hooks *fornecidos pelo plugin*, não apenas consumidos do core; relevante para quem projeta um plugin que pretende ser extensível por terceiros.
- **Download de arquivo binário via entry point autenticado** — reforça a importância de checar right antes de servir qualquer arquivo (`04-Rights.md`, `12-AJAX.md`/`13-Routing.md`), já que um PDF pode conter dado sensível do itemtype de origem.

## Quando consultar

Ao implementar exportação/geração de documento a partir de dados de itemtype, e como referência de plugin que oferece pontos de extensão próprios para outros plugins.
