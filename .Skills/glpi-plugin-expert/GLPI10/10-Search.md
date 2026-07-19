# 10 — Search (Search Options e Motor de Busca)

## Objetivo

Entender o motor de busca/listas do GLPI (`Search`): como declarar `getSearchOptions()` no seu itemtype, como adicionar colunas pesquisáveis a itemtypes do CORE a partir de um plugin, e como interferir na query gerada (join, where, select) sem tocar SQL raw.

## Conceitos

- **Search Options = contrato declarativo de coluna pesquisável.** Cada itemtype expõe `getSearchOptions()` (ou, historicamente, `getSearchOptionsNew()`) retornando um array indexado por um **ID numérico estável** — esse ID aparece em URLs de busca salva, display preferences e dashboards, então **nunca reutilize nem renumere IDs já publicados**.
- **Cada entrada descreve uma coluna:** `table`, `field`, `name` (rótulo), `datatype` (`text`, `dropdown`, `itemlink`, `bool`, `date`, `datetime`, `number`, `decimal`, `email`...), e opcionalmente `massiveaction => false`, `joinparams` (para tabelas que exigem N joins, ex. relação N:N).
- **Dois cenários de extensão:**
  1. **Seu próprio itemtype** implementa `getSearchOptions()` normalmente (herda `id`, `name`, `comment` de `CommonDBTM`/`CommonDropdown` e você soma o resto).
  2. **Adicionar colunas a um itemtype do CORE** (ex.: uma coluna do seu plugin aparecendo na lista de `Computer`) — isso é feito via as funções mágicas do `hook.php`: `plugin_<chave>_getAddSearchOptionsNew($itemtype)` (retorna array de novas entradas) e, quando a query precisa de dado que só existe com JOIN/WHERE extra, `plugin_<chave>_addDefaultJoin`/`addDefaultWhere`/`addDefaultSelect`.
- **IDs de plugin usam uma faixa alta reservada** (convenção: `> 8000` combinando um prefixo derivado do nome do plugin) para não colidir com IDs do core nem de outros plugins.

## Funcionamento interno

Ao montar uma lista (`Search::getDatas`/`showList` no front do itemtype), o core monta a query SQL a partir das search options ativas (colunas escolhidas + filtros aplicados), delegando ao query builder — nunca concatenando texto livre. Para itemtypes de CORE, depois de montar a lista de options nativas, o core varre plugins ativos chamando `plugin_<chave>_getAddSearchOptionsNew($itemtype)`; se o plugin devolver entradas para aquele `$itemtype`, elas são mescladas. Quando a nova coluna depende de uma tabela que ainda não está no `JOIN` da query, o core chama `plugin_<chave>_addDefaultJoin($itemtype, $ref_table, &$already_link_tables)` — que deve retornar o fragmento produzido por `Search::addLeftJoin(...)` (helper do core, não SQL manual).

`giveItem($itemtype, $id, $data, $num)` (função mágica) é chamado quando o core precisa renderizar o **conteúdo de uma célula** de uma coluna que seu plugin declarou — é o ponto certo para transformar um valor cru em link/formatação.

## Fluxograma

```
front/coisa.php (lista)
      │
      ▼
Search::getDatas('GlpiPlugin\Meuplugin\Coisa', $_GET)
      │
      ├── getSearchOptions() do próprio itemtype
      ├── (se itemtype = core) plugin_<k>_getAddSearchOptionsNew($itemtype)
      ├── plugin_<k>_addDefaultJoin(...)   ← só se a coluna exigir JOIN extra
      ├── plugin_<k>_addDefaultWhere(...)  ← filtro obrigatório (ex.: direito)
      ▼
monta query via query builder (nunca SQL concatenado)
      ▼
para cada célula de coluna de plugin: plugin_<k>_giveItem(...)  ← formatação
```

## Exemplos corretos

### Search options do próprio itemtype

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

/**
 * Declara as colunas pesquisáveis/exibíveis da lista de Coisa.
 * IDs 1-2 seguem convenção do core (name/id); IDs próprios do plugin
 * começam em 10 e NUNCA são renumerados depois de publicados.
 */
public function getSearchOptions(): array
{
    $tab = parent::getSearchOptions(); // id, name, comment já inclusos

    $tab[] = [
        'id'       => 10,
        'table'    => self::getTable(),
        'field'    => 'status',
        'name'     => __('Status', 'meuplugin'),
        'datatype' => 'specific', // renderização própria via giveItem/getSpecificValueToDisplay
    ];

    $tab[] = [
        'id'       => 11,
        'table'    => \Entity::getTable(),
        'field'    => 'completename',
        'name'     => \Entity::getTypeName(1),
        'datatype' => 'dropdown',
    ];

    return $tab;
}
```

### Adicionando coluna do plugin a um itemtype do CORE (`hook.php`)

```php
<?php
// hook.php

use Search;

/**
 * Expõe colunas do plugin na lista de Computer.
 */
function plugin_meuplugin_getAddSearchOptionsNew(string $itemtype): array
{
    $tab = [];

    if ($itemtype === \Computer::class) {
        $tab[] = [
            'id'            => 8001, // faixa reservada do plugin
            'table'         => 'glpi_plugin_meuplugin_coisas',
            'field'         => 'name',
            'name'          => __('Coisa vinculada', 'meuplugin'),
            'datatype'      => 'dropdown',
            'joinparams'    => [
                'jointype' => 'itemtype_item',
            ],
        ];
    }

    return $tab;
}

/**
 * JOIN necessário para a coluna acima funcionar.
 */
function plugin_meuplugin_addDefaultJoin(string $itemtype, string $ref_table, array &$already_link_tables): string
{
    if ($itemtype === \Computer::class) {
        return Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            'glpi_plugin_meuplugin_coisas',
            'computers_id'
        );
    }

    return '';
}
```

## Exemplos incorretos

```php
// ERRADO: montar o JOIN como string SQL livre em vez de usar o helper
// Search::addLeftJoin — perde a integração com o mecanismo de aliases
// e reabre risco de SQL malformado/injeção.
function plugin_meuplugin_addDefaultJoin($itemtype, $ref_table, &$already_link_tables)
{
    return " LEFT JOIN glpi_plugin_meuplugin_coisas ON ...";
}
```

```php
// ERRADO: reutilizar/renumerar um ID de search option já publicado.
// Quebra buscas salvas, display preferences e dashboards de quem já
// usa o plugin em produção.
```

```php
// ERRADO: reimplementar a listagem com $DB->request manual em vez de
// declarar Search Options — perde paginação, ordenação, exportação
// CSV/PDF e integração com display preferences que o motor já dá de graça.
```

## Boas práticas

- Reserve uma faixa de IDs (documentada no README do plugin) e nunca a reutilize entre versões.
- Para colunas de renderização especial (link, badge, ícone), use `datatype => 'specific'` + `giveItem`/`getSpecificValueToDisplay` em vez de forçar um `datatype` existente que não encaixa.
- `addDefaultWhere` é o lugar certo para aplicar um filtro de segurança obrigatório (ex.: só itens da entidade do usuário) que não deve depender do usuário lembrar de filtrar manualmente.
- Teste a lista com display preferences customizadas e com exportação CSV/PDF — search options mal declaradas quebram silenciosamente nesses caminhos.

## Anti-patterns

- SQL concatenado em qualquer um dos hooks de Search (`addDefaultJoin/Where/Select`).
- IDs de search option "aleatórios"/sem faixa reservada — colide com outro plugin instalado na mesma base.
- Declarar coluna sem `joinparams` quando a tabela de origem não está automaticamente disponível — a coluna aparece na configuração mas quebra a query em runtime.
- Ignorar `giveItem`/`getSpecificValueToDisplay` e deixar o core tentar formatar um dado que não é texto simples.

## Checklist

- [ ] `getSearchOptions()` do itemtype próprio inclui todos os campos relevantes
- [ ] IDs de plugin em faixa reservada e documentada, nunca renumerados
- [ ] Colunas adicionadas a itemtype do core via `getAddSearchOptionsNew` + `addDefaultJoin` quando necessário
- [ ] `Search::addLeftJoin` usado em vez de SQL livre
- [ ] Renderização especial via `datatype => 'specific'` + `giveItem` quando aplicável
- [ ] Testado com export CSV/PDF e display preferences

## Dicas de performance

- Cada coluna extra ativada pelo usuário adiciona JOIN/SELECT à query da lista — evite declarar dezenas de colunas "só porque pode"; privilegie as realmente úteis.
- Prefira `datatype` nativo (que o core já sabe otimizar) a `specific` quando o dado realmente encaixa em um tipo padrão.

## Dicas de segurança

- `addDefaultWhere` que aplica right/entidade de forma automática é mais seguro que confiar em o usuário sempre adicionar o filtro manualmente na busca.
- Toda coluna exposta via Search vaza dado para quem tem acesso à lista — confirme que o right do itemtype cobre exatamente os campos que a coluna revela (ex.: não exponha campo sensível de outra tabela via JOIN sem checar se o usuário poderia vê-lo diretamente).

## Referências

- Search Engine oficial: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/search.html
- Wiki do plugin example (giveItem, hooks de busca): https://github.com/pluginsGLPI/example/wiki/How-to-hook
- Documentos relacionados: `03-Hooks.md`, `05-Database.md`, `07-CommonDBTM.md`, `11-MassiveActions.md`
