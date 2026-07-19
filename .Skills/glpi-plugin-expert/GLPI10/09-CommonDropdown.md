# 09 — CommonDropdown

## Objetivo

Criar listas de seleção (dicionários) próprias do plugin usando `CommonDropdown`, entendendo o que o core já resolve de graça (form, campo `name`/`comment`, listagem) e o que cabe à classe declarar (`getAdditionalFields`).

## Conceitos

- **`CommonDropdown extends CommonDBTM`.** É a base de toda tabela "simples" de apoio: categorias, tipos, status customizados, fabricantes próprios etc. — qualquer coisa que na UI aparece como um `<select>` alimentado por uma tabela administrável.
- **O core já resolve:** form padrão com campo `name` (ou `designation`, em casos legados de `CommonDevice`) e `comment`, listagem simples, dropdown JS de dados (`Dropdown::show()`), rights padrão (`canCreate`/`canView`/`canPurge` herdados, geralmente delegando a `config` ou a right próprio).
- **`CommonTreeDropdown`** (subclasse de `CommonDropdown`) é usada quando o dicionário é hierárquico (tem "pai"), como `Location`. Ganha `getAdditionalFields` com um campo `type => 'parent'` e métodos de árvore (`getSonsOf`, `getAncestorsOf`).
- **`getAdditionalFields()`** é o único método que a maioria dos dropdowns próprios realmente precisa sobrescrever: retorna um array de definição de campos extras (além de name/comment) que o core desenha automaticamente no form padrão.

## Funcionamento interno

`CommonDropdown` herda o ciclo de `CommonDBTM` (`add`/`update`/`prepareInputFor*`/hooks) e adiciona: `showForm()` genérico que desenha `name`+`comment`+os campos de `getAdditionalFields()` numa tabela HTML padronizada; `getSearchOptions()` já populado com `id`, `name`, `comment`; integração automática com `Dropdown::show($itemtype)` (o widget de busca incremental usado em outros forms para referenciar este dropdown).

Cada entrada de `getAdditionalFields()` é um array associativo: `name` (coluna), `label` (rótulo traduzido), `type` (`text`, `bool`, `dropdownValue`, `password`, ou um tipo customizado tratado no próprio form), e opcionalmente `list => true/false` (aparece na listagem).

## Fluxograma

```
Administração > Dicionários > MeuDicionario
      │
      ▼
CommonDropdown::showForm()
      │  desenha automaticamente: name, comment
      │  + itera getAdditionalFields() do seu dropdown
      ▼
POST → prepareInputForAdd/Update (sua classe pode sobrescrever)
      │
      ▼
CommonDBTM::add()/update() (ciclo padrão, hooks inclusos)
```

## Exemplos corretos

### Dropdown simples com campo extra

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDropdown;

/**
 * Dicionário de "Categoria de Coisa" — dropdown simples usado
 * como referência (FK) na classe Coisa.
 */
class CategoriaCoisa extends CommonDropdown
{
    public static $rightname = 'plugin_meuplugin_coisa';

    public static function getTypeName($nb = 0): string
    {
        return _n('Categoria de Coisa', 'Categorias de Coisa', $nb, 'meuplugin');
    }

    /**
     * Campos extras desenhados pelo form genérico do core,
     * além dos padrões name/comment.
     *
     * @return array<int, array{name: string, label: string, type: string}>
     */
    public function getAdditionalFields(): array
    {
        return [
            [
                'name'  => 'is_active',
                'label' => __('Ativo', 'meuplugin'),
                'type'  => 'bool',
            ],
            [
                'name'  => 'cor',
                'label' => __('Cor de destaque', 'meuplugin'),
                'type'  => 'text',
            ],
        ];
    }
}
```

### Uso do dropdown como FK em outro itemtype

```php
// No install da Coisa, coluna FK seguindo convenção:
// `plugin_meuplugin_categoriacoisas_id` int unsigned DEFAULT NULL

// No form da Coisa, exibindo o widget de busca do dropdown:
\Dropdown::show(CategoriaCoisa::class, [
    'name'  => 'plugin_meuplugin_categoriacoisas_id',
    'value' => $this->fields['plugin_meuplugin_categoriacoisas_id'] ?? 0,
]);
```

### Dropdown hierárquico

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonTreeDropdown;

/**
 * Dicionário hierárquico (tem pai/filhos), útil para taxonomias.
 */
class GrupoCoisa extends CommonTreeDropdown
{
    public static $rightname = 'plugin_meuplugin_coisa';

    public static function getTypeName($nb = 0): string
    {
        return _n('Grupo de Coisa', 'Grupos de Coisa', $nb, 'meuplugin');
    }
}
```

## Exemplos incorretos

```php
// ERRADO: reimplementar showForm() do zero para adicionar um campo —
// jogando fora toda a UI padrão (name, comment, layout) que o core
// já fornece. Use getAdditionalFields().
class CategoriaCoisa extends \CommonDropdown
{
    public function showForm($ID, $options = [])
    {
        echo '<form>...</form>'; // reinventa a roda, sem padrão visual
    }
}
```

```php
// ERRADO: dropdown "achatado" (CommonDropdown) para dado que é
// claramente hierárquico (categorias com subcategorias). Deveria
// estender CommonTreeDropdown para ganhar navegação em árvore de graça.
```

```php
// ERRADO: nome de campo que colide com os padrões do core sem motivo
// ('name' já existe; reintroduzir 'nome' quebra a convenção que o
// form genérico espera).
```

## Boas práticas

- Sempre que o dado for "N itens simples, sem hierarquia", herde `CommonDropdown` puro; para hierarquia, `CommonTreeDropdown`.
- Deixe o core desenhar o form padrão (`getAdditionalFields`) — só sobrescreva `showForm()` quando o layout genérico for genuinamente insuficiente.
- Referencie dropdowns próprios como FK seguindo a convenção `<tabela_singular>_id` e exiba com `Dropdown::show()`.
- Adicione `is_active`/similar quando o dicionário precisa de "soft toggle" sem apagar histórico de uso.

## Anti-patterns

- Reimplementar listagem/form manualmente quando `CommonDropdown` já entrega isso.
- Usar `CommonDropdown` para uma entidade que na verdade tem ciclo de vida rico (rights próprios elaborados, várias abas, regras de negócio) — nesse caso o certo é `CommonDBTM` puro com form customizado.
- Esquecer de popular `getSearchOptions()` complementar quando adicionar campos que devem ser pesquisáveis nas listas (ver `10-Search.md`).

## Checklist

- [ ] Herda `CommonDropdown` (achatado) ou `CommonTreeDropdown` (hierárquico), conforme a natureza do dado
- [ ] `getAdditionalFields()` cobre todos os campos extras, com `type` correto
- [ ] FK para o dropdown segue convenção de nome e usa `Dropdown::show()`
- [ ] Right herdado ou declarado de forma coerente com o restante do plugin
- [ ] Campos extras relevantes também aparecem em `getSearchOptions()`

## Dicas de performance

- Dropdowns costumam ser lidos com muita frequência (todo form que os referencia dispara `Dropdown::show`) — mantenha a tabela pequena e indexada por `name`.
- Evite campos `type => 'dropdownValue'` encadeados demais (dropdown que referencia dropdown que referencia dropdown) sem necessidade — cada nível é uma consulta adicional ao abrir o form.

## Dicas de segurança

- Mesmo dropdowns "administrativos" precisam de right — não assuma que por serem dados de configuração, qualquer usuário autenticado pode editá-los.
- Campos `type => 'text'` livres (como `cor` no exemplo) ainda passam pelo pipeline de escape do form genérico do core — não recrie o campo manualmente achando que precisa escapar você mesmo.

## Referências

- Main framework objects (CommonDropdown/CommonTreeDropdown): https://glpi-developer-documentation.readthedocs.io/en/master/devapi/mainobjects.html
- Documentos relacionados: `07-CommonDBTM.md`, `10-Search.md`, `20-Twig.md`
