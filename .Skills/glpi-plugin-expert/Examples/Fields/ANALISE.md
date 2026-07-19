# Fields — O que estudar

**Repositório:** https://github.com/pluginsGLPI/fields
**Status:** ainda necessário como plugin mesmo em instâncias 11.x (não foi absorvido pelo core) — deve ser mantido atualizado antes de migrar a instância 10→11.

## O que é

Plugin que permite ao administrador adicionar campos personalizados dinamicamente a QUALQUER itemtype existente (do core ou de outros plugins), sem exigir alteração de código.

## O que estudar

- **Criação dinâmica de tabelas/colunas em runtime** (via configuração do administrador, não via `Migration` fixa no install) — um padrão avançado que estende os conceitos de `06-Migrations.md` para um cenário onde o schema não é conhecido em tempo de desenvolvimento.
- **Injeção de campo em form de itemtype alheio** via hooks (`POST_ITEM_FORM`/similares) — bom exemplo prático além do básico de `03-Hooks.md`.
- **"Containers" que agrupam campos customizados por itemtype/perfil** — um padrão de configuração flexível que vai além de `getAdditionalFields()` simples de `09-CommonDropdown.md`.

## Nota de migração (GLPI 11)

Ao contrário de FormCreator/GenericObject, este plugin **continua sendo necessário** no GLPI 11 e deve ser atualizado como parte do processo de migração da instância (é recomendado atualizá-lo ANTES de migrar para 11, conforme o guia oficial de migração de plugins específicos).

## Quando consultar

Para exemplos de campos personalizados dinâmicos e de injeção de UI em formulários de itemtypes que não pertencem ao próprio plugin.
