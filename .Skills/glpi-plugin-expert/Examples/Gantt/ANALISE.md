# Gantt — O que estudar

**Repositório:** https://github.com/pluginsGLPI/gantt

## O que é

Plugin que adiciona visualização Gantt para `Project`/`ProjectTask`, integrando um componente visual JS acoplado a itemtypes já existentes do core.

## O que estudar

- **Integração de aba em itemtype do core** (`Project`): como o plugin se registra via `Plugin::registerClass(..., ['addtabon' => ['Project']])` e implementa `getTabNameForItem`/`displayTabContentForItem` — comparar com `GLPI10/08-CommonGLPI.md`.
- **Carregamento de JS externo**: como o plugin injeta bibliotecas de gráfico via hooks `ADD_JAVASCRIPT`/`ADD_CSS`, relevante para `GLPI10/21-Vue.md`/`22-Tabler.md` (decisão de quando vale a pena trazer JS pesado para uma visualização).
- **Serialização de dados para o componente visual**: como dados de `ProjectTask` (datas, dependências) são transformados em JSON consumível pelo componente Gantt no cliente — bom exemplo de endpoint AJAX que serve dados estruturados (`GLPI10/12-AJAX.md`).

## Quando consultar

Ao integrar uma visualização rica (calendário, gráfico, timeline) a um itemtype do core, especialmente quando a interatividade justifica trazer uma biblioteca JS externa em vez de HTML/Twig estático.
