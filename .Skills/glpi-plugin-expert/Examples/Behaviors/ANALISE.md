# Behaviors — O que estudar

**Repositório:** https://github.com/yllen/behaviors

## O que é

Plugin focado em alterar/estender o COMPORTAMENTO de negócio do core (regras condicionais sobre eventos de itens) sem modificar o core — uso extensivo de hooks de ciclo de vida.

## O que estudar

- **Uso extensivo e disciplinado de `pre_item_add`/`pre_item_update`** para impor regras de negócio customizadas sobre itemtypes do core — o exemplo mais direto do padrão descrito em `GLPI10/03-Hooks.md` aplicado em profundidade (não só "um hook", mas um sistema inteiro construído sobre hooks).
- **Configuração de regras via UI** em vez de hardcode — relevante para quem projeta plugins que precisam ser configuráveis pelo administrador sem exigir código novo por regra.
- **Cuidado necessário ao vetar operações** (`$item->input = false`) de forma que a mensagem de erro seja clara para o usuário — bom estudo de caso de UX de validação server-side (`02-Lifecycle.md`).

## Ponto de atenção ao estudar

Plugins que alteram comportamento de itemtypes do core via hooks (em vez de apenas adicionar itemtypes próprios) têm risco maior de interação inesperada com outros plugins inscritos nos mesmos hooks — reforça a recomendação de `03-Hooks.md` de nunca assumir exclusividade num hook.

## Quando consultar

Ao projetar um plugin cujo propósito principal é impor regras de negócio customizadas sobre itemtypes já existentes do core, em vez de introduzir itemtypes totalmente novos.
