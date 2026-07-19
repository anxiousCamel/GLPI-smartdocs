# 18 — Entidades (Multi-Entidade)

## Objetivo

Entender o modelo multi-entidade do GLPI e o que um itemtype de plugin precisa implementar para participar corretamente dele: campos, recursividade, filtragem automática em listas e formulários.

## Conceitos

- **Entidade = unidade organizacional hierárquica** (empresa, filial, departamento). Toda instância GLPI tem pelo menos a entidade raiz; instâncias reais costumam ter uma árvore (`Entity` é ele mesmo um `CommonTreeDropdown`).
- **Todo itemtype "de negócio" deveria ser multi-entidade** — isso significa ter a coluna `entities_id` (FK para `glpi_entities`) e, quando aplicável, `is_recursive` (booleano: "visível também nas sub-entidades da entidade indicada, não só nela").
- **Recursividade não é tudo-ou-nada por tabela**, é por LINHA: dois registros da mesma tabela podem ter `is_recursive` diferente entre si. Um item com `is_recursive = 0` só é visto por quem está na entidade exata; com `is_recursive = 1`, também por quem está em qualquer sub-entidade dela.
- **O usuário logado tem um conjunto de "entidades ativas"** (`Session::getActiveEntities()`), que pode ser uma única entidade ou várias (dependendo de como o perfil foi ativado — "ver todas as sub-entidades" é uma opção na troca de entidade ativa).

## Funcionamento interno

`CommonDBTM` já sabe lidar com `entities_id`/`is_recursive` quando esses campos existem na tabela: `canViewItem()`/`canUpdateItem()` chamam `Session::haveAccessToEntity($this->fields['entities_id'], $this->fields['is_recursive'] ?? false)`, que verifica se a entidade do item está dentro do conjunto de entidades ativas do usuário (considerando recursividade em ambas as pontas — da entidade ativa do usuário E do `is_recursive` do item). O motor de `Search` também filtra automaticamente por entidade quando a tabela tem essas colunas — não é necessário adicionar esse filtro manualmente em cada busca.

## Fluxograma

```
Usuário ativa Entidade "Filial A" (+ sub-entidades: sim)
      │
      ▼
Session::getActiveEntities() = [Filial A, SubFilial A1, SubFilial A2, ...]
      │
      ▼
Item X: entities_id = SubFilial A1, is_recursive = 0
      │
      ▼
Session::haveAccessToEntity(SubFilial A1, false)
      │  SubFilial A1 está no conjunto ativo? SIM → acesso permitido
      ▼
canViewItem() = true
```

## Exemplos corretos

### Tabela de itemtype multi-entidade

```sql
CREATE TABLE `glpi_plugin_meuplugin_coisas` (
    `id`            int unsigned NOT NULL AUTO_INCREMENT,
    `name`          varchar(255) DEFAULT NULL,
    `entities_id`   int unsigned NOT NULL DEFAULT '0',
    `is_recursive`  tinyint NOT NULL DEFAULT '0',
    `date_creation` timestamp NULL DEFAULT NULL,
    `date_mod`      timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `entities_id` (`entities_id`),
    KEY `is_recursive` (`is_recursive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Preenchendo entidade automaticamente no add

```php
<?php

// Em GlpiPlugin\Meuplugin\Coisa

public function prepareInputForAdd($input)
{
    if (!isset($input['entities_id'])) {
        $input['entities_id'] = \Session::getActiveEntity();
    }

    return $input;
}
```

### Widget de escolha de entidade no form

```php
// No showForm(), delegando ao helper padrão do core:
\Entity::dropdown([
    'value'     => $this->fields['entities_id'],
    'entity'    => $this->fields['entities_id'],
]);

\Html::showCheckbox([
    'name'    => 'is_recursive',
    'checked' => $this->fields['is_recursive'],
]);
```

## Exemplos incorretos

```php
// ERRADO: tabela sem entities_id para um itemtype que claramente
// pertence a um contexto organizacional (ex.: "Categoria de Chamado
// customizada por filial"). Sem essa coluna, o item é global e
// visível/editável por TODAS as entidades, mesmo sem essa intenção.
```

```php
// ERRADO: checar acesso manualmente comparando entities_id do item
// com a entidade ativa do usuário via == simples, ignorando
// recursividade em ambas as direções.
if ($item->fields['entities_id'] == Session::getActiveEntity()) {
    // permite acesso
}
// Correto: usar canViewItem()/Session::haveAccessToEntity(), que já
// tratam corretamente recursividade e múltiplas entidades ativas.
```

```php
// ERRADO: query manual sem filtro de entidade em itemtype que deveria
// ser multi-entidade — vaza dados de uma entidade para usuários de outra.
$DB->request(['FROM' => 'glpi_plugin_meuplugin_coisas']); // sem WHERE de entidade
```

## Boas práticas

- Toda tabela nova de itemtype de negócio nasce com `entities_id` + `is_recursive`, mesmo que a instância inicial do cliente só use uma entidade — retrofitar isso depois com dados em produção é doloroso.
- Prefira sempre os métodos de instância (`canViewItem`, `canUpdateItem`) e os helpers de sessão (`Session::haveAccessToEntity`, `Session::getActiveEntities`) a comparações manuais.
- Quando listar itens fora do `Search` padrão (ex.: numa consulta customizada para dashboard), inclua explicitamente o filtro de entidades ativas via `Session::getActiveEntities()` no `WHERE`.

## Anti-patterns

- Comparação de igualdade simples entre `entities_id` do item e a entidade ativa, ignorando recursividade.
- Itemtype de negócio sem suporte a multi-entidade "porque o cliente atual só usa uma entidade" — decisão que não escala e é cara de corrigir depois.
- Consulta customizada (fora do `Search`) que esquece o filtro de entidade.

## Checklist

- [ ] Tabela do itemtype tem `entities_id` (e `is_recursive` quando fizer sentido)
- [ ] `prepareInputForAdd` preenche `entities_id` com `Session::getActiveEntity()` quando não informado
- [ ] Toda checagem de acesso usa `canViewItem()`/`Session::haveAccessToEntity()`, nunca comparação manual
- [ ] Toda consulta customizada (fora do Search nativo) filtra por `Session::getActiveEntities()`
- [ ] Form expõe o dropdown de entidade + checkbox de recursividade quando aplicável

## Dicas de performance

- Índice em `entities_id` (e em `is_recursive` se muito consultado em conjunto) é obrigatório — é o filtro mais comum em qualquer listagem multi-entidade.
- `Session::getActiveEntities()` já é calculado uma vez por sessão/troca de entidade — reutilize o array já resolvido em vez de recalcular consultas de árvore de entidade repetidamente.

## Dicas de segurança

- Multi-entidade mal implementada é uma das formas mais comuns de vazamento de dados entre clientes/departamentos num GLPI multi-tenant — trate como requisito de segurança, não só de organização.
- Right global concedido não substitui a checagem de entidade — os dois são independentes e ambos precisam passar.

## Referências

- Developer API — Entidades e sessão: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/mainobjects.html
- Documentos relacionados: `04-Rights.md`, `05-Database.md`, `07-CommonDBTM.md`
