# FormCreator — O que estudar

**Repositório:** https://github.com/pluginsGLPI/formcreator
**Status:** funcionalidade migrada para o core no GLPI 11 (Forms nativo) — plugin permanece relevante como referência de padrões em instâncias 10.x, mas deve ser considerado **descontinuado** para novos projetos em 11.x.

## O que é

Um dos plugins GLPI mais complexos e maduros historicamente: cria formulários customizados que geram tickets, com lógica condicional, múltiplos tipos de campo, e rights elaborados por formulário.

## O que estudar (em instâncias ainda 10.x)

- **Rights granulares por instância de recurso** (não só por itemtype, mas por formulário específico) — vai além do padrão simples de `04-Rights.md` e é útil como referência de "right por linha", não só "right por tabela".
- **Estrutura de plugin grande organizado em múltiplos itemtypes relacionados** (formulário → seção → campo → resposta) — bom estudo de caso para `07-CommonDBTM.md` aplicado em escala, com relações `CommonDBRelation`.
- **Interface rica com muitos tipos de campo dinâmicos** — exemplo de `getAdditionalFields`/form customizado além do genérico simples (`09-CommonDropdown.md`, `20-Twig.md`).
- **Geração de `Ticket` a partir de dados de outro itemtype** — integração entre um itemtype de plugin e um itemtype nuclear do core (`ITILObject`), relevante para quem cria plugins que alimentam o helpdesk.

## Nota de migração (GLPI 11)

Para funcionalidade equivalente em instância 11.x, a referência correta passa a ser o módulo de Forms nativo do core, não este plugin. Ver `GLPI11/00-Migration-Guide.md`, seção "Plugins absorvidos pelo core no 11".

## Quando consultar

Ao estudar um exemplo de plugin grande e maduro em GLPI 10.x, especialmente para rights granulares por recurso e geração de tickets a partir de formulário customizado.
