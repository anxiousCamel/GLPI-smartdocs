# Generic Object — O que estudar

**Repositório:** https://github.com/pluginsGLPI/genericobject
**Status:** funcionalidade migrada para o core no GLPI 11 (Custom Assets) — descontinuado para novos projetos em 11.x, mas referência histórica valiosa para 10.x.

## O que é

Plugin que permite ao administrador criar NOVOS tipos de item (itemtypes inteiros) dinamicamente via UI, sem escrever código PHP — essencialmente um "meta-plugin" que gera itemtypes em runtime.

## O que estudar (em instâncias ainda 10.x)

- **Geração dinâmica de tabela + itemtype a partir de configuração do administrador** — um nível acima do `Migration` estático de `06-Migrations.md`, útil para entender os limites e riscos de gerar schema em runtime.
- **Como o plugin implementa CRUD, Search Options e rights genéricos** que se adaptam a QUALQUER estrutura definida pelo admin — um estudo de caso de generalização máxima sobre os padrões de `07-CommonDBTM.md`/`10-Search.md`/`04-Rights.md`.
- **Migração manual de dados criados pelo plugin quando a instância evolui para 11** — o guia oficial de migração de plugins específicos (ver `References/OfficialDocumentation.md`) documenta esse processo, incluindo a mensagem de que "tipos de família de objeto não são importados, pois não são tratados pelo GLPI" — um lembrete de que generalização extrema em runtime tem custo de migração alto.

## Nota de migração (GLPI 11)

Para necessidade equivalente em instância 11.x, a referência correta é o recurso nativo de **Custom Assets**. Novos projetos não devem replicar este padrão de "gerar itemtype em runtime" — é significativamente mais simples e sustentável definir itemtypes em código (como esta KB ensina) do que reconstruir um meta-sistema de geração dinâmica.

## Quando consultar

Apenas como referência histórica/conceitual sobre os limites de generalização de itemtype; não como modelo a seguir para plugins novos.
