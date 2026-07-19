# 05 — Banco de Dados

## Objetivo

Usar corretamente o acesso a dados do GLPI: a classe `$DB`, o query builder (`DBmysqlIterator`), convenções de schema, e por que SQL raw é proibido neste ecossistema.

## Conceitos

- **`$DB` é a única porta de entrada ao banco.** É uma instância de `DBmysql`, disponibilizada globalmente pelo bootstrap. Nunca abra conexão própria, nunca use `mysqli`/`PDO` direto.
- **Query builder = array PHP que descreve a query.** `$DB->request([...])` (leitura, retorna iterador), `$DB->insert()/update()/delete()`, todos aceitam array estruturado com chaves como `SELECT`, `FROM`, `WHERE`, `LEFT JOIN`, `ORDER`, `LIMIT`, `START`, `GROUPBY`. Isso é o `DBmysqlIterator` por baixo.
- **Convenção de schema:** tabela = `glpi_plugin_<chave>_<nome_plural_snake>`; PK sempre `id` (`int unsigned auto_increment`); FK sempre `<tabela_singular>_id` (`glpi_plugin_meuplugin_coisas` → FK `plugin_meuplugin_coisas_id` quando referenciada por outra tabela do plugin; para itemtypes do core, `computers_id`, `users_id` etc.); charset/collation `utf8mb4`/`utf8mb4_unicode_ci`; `ENGINE=InnoDB`.
- **Campos "de convenção" que o core espera existirem** quando aplicável: `entities_id`, `is_recursive` (multi-entidade), `is_deleted` (lixeira), `date_creation`, `date_mod` (auditoria automática via `CommonDBTM`).

## Funcionamento interno

`$DB->request($criteria)` monta e executa um `SELECT` via `DBmysqlIterator`, devolvendo um objeto iterável (`foreach` direto, sem `fetch` manual) onde cada linha já é um array associativo. Toda variável de valor entra como **valor do array**, nunca concatenada em string — o builder trata escaping e tipos internamente. Isso é o que blinda contra SQL injection: a superfície de ataque só existe se alguém escrever SQL fora do builder.

No 10.x ainda existem sintaxes alternativas de `$DB->request()` (tabela como 1º parâmetro), mas são depreciadas — usar somente a forma de array único evita retrabalho na migração para 11 (`GLPI11/00-Migration-Guide.md`).

## Fluxograma — leitura típica

```
array de critérios (PHP)
      │
      ▼
$DB->request([...])
      │  (DBmysqlIterator monta SQL parametrizado internamente)
      ▼
iterador
      │
      ▼
foreach ($iterator as $row) { ... }   ← $row já é array associativo
```

## Exemplos corretos

### Leitura simples

```php
<?php

/** @var \DBmysql $DB */
global $DB;

$iterator = $DB->request([
    'SELECT' => ['id', 'name', 'date_mod'],
    'FROM'   => 'glpi_plugin_meuplugin_coisas',
    'WHERE'  => [
        'entities_id' => Session::getActiveEntities(), // array já tratado pelo builder
        'is_deleted'  => 0,
    ],
    'ORDER'  => ['date_mod DESC'],
    'LIMIT'  => 50,
]);

foreach ($iterator as $row) {
    echo $row['name'];
}
```

### JOIN com tabela do core

```php
$iterator = $DB->request([
    'SELECT' => ['c.id', 'c.name', 'u.name AS responsavel'],
    'FROM'   => 'glpi_plugin_meuplugin_coisas AS c',
    'LEFT JOIN' => [
        'glpi_users AS u' => [
            'ON' => ['c', 'u', 'users_id_responsavel', 'id'],
        ],
    ],
    'WHERE' => ['c.is_deleted' => 0],
]);
```

### Insert / update / delete via query builder

```php
$id = $DB->insert('glpi_plugin_meuplugin_coisas', [
    'name'         => $nome,          // valor cru; builder cuida do escape
    'entities_id'  => $entityId,
    'date_creation' => $_SESSION['glpi_currenttime'],
]);

$DB->update('glpi_plugin_meuplugin_coisas', [
    'name' => $novoNome,
], [
    'id' => $id,
]);

$DB->delete('glpi_plugin_meuplugin_coisas', ['id' => $id]);
```

> Em código de itemtype (`CommonDBTM`), prefira `$item->add()/update()/delete()` — eles chamam os métodos de `$DB` internamente E disparam hooks/histórico/notificações. Use `$DB->insert/update/delete` direto só fora do ciclo de vida de itemtype (ex.: tabela puramente auxiliar sem hooks).

## Exemplos incorretos

```php
// ERRADO — SQL raw com interpolação: SQL injection direta e viola
// a regra "sempre query builder".
$nome = $_GET['nome'];
$DB->doQuery("SELECT * FROM glpi_plugin_meuplugin_coisas WHERE name = '$nome'");
```

```php
// ERRADO — sintaxe depreciada (tabela como 1º parâmetro), evite mesmo
// no 10.x para já nascer compatível com o 11.
$DB->request('glpi_plugin_meuplugin_coisas', ['id' => 5]);
// Correto:
$DB->request(['FROM' => 'glpi_plugin_meuplugin_coisas', 'WHERE' => ['id' => 5]]);
```

```php
// ERRADO — nome de FK fora de convenção quebra getForeignKeyField()
// e a resolução automática do core (ex.: em Search/Migration).
// coluna 'id_computer' em vez de 'computers_id'
```

## Boas práticas

- Toda tabela nova segue as convenções de nome (schema) mesmo quando "ninguém vai perceber" — é o que permite ao core (e a você, seis meses depois) raciocinar por convenção.
- Prefira `$item->find($criteria)` / métodos do próprio `CommonDBTM` quando existir; caia para `$DB->request` bruto só quando a necessidade for genuinamente fora do CRUD padrão (relatórios, agregações).
- Use `COUNT`, `SUM` etc. do próprio builder (`'COUNT' => 'cnt'`) em vez de trazer todas as linhas para agregar em PHP.
- Adicione índices (`KEY`) em toda coluna usada em `WHERE`/`JOIN` com frequência — ver `29-Performance.md`.

## Anti-patterns

- Qualquer `mysqli_query`, `PDO::query`, ou `$DB->doQuery("...")` com valor de usuário interpolado.
- Montar WHERE concatenando strings condicionalmente (`if ($x) $where .= " AND ..."`) em vez de montar o array de critérios do builder.
- Ignorar `entities_id`/multi-entidade em consultas de itemtype recursivo, vazando dados entre clientes/setores.
- N+1: um `$DB->request` dentro de loop de outro `$DB->request` quando um JOIN resolveria.

## Checklist

- [ ] Nenhum SQL raw / string interpolada em query
- [ ] Toda tabela segue convenção de nome, PK, FK, charset e collation
- [ ] Filtros de entidade aplicados onde o itemtype é multi-entidade
- [ ] Sintaxe de array único no `$DB->request` (compatível com 11)
- [ ] Agregações feitas no banco, não em PHP após trazer tudo

## Dicas de performance

- Selecione só as colunas necessárias (`SELECT` explícito) em listas grandes — evita transferir dados inúteis.
- `LIMIT`/`START` no builder para paginação real, nunca "trazer tudo e fatiar em PHP".
- Índices compostos quando o filtro mais comum usa múltiplas colunas juntas.

## Dicas de segurança

- O query builder é a barreira contra SQL injection; abandoná-lo em qualquer ponto reabre a superfície de ataque inteira.
- Dados vindos de `$DB->request` já passaram pelo banco — ainda assim, ao reexibir em HTML, escape na camada de view (Twig/`htmlescape`) continua obrigatório; "veio do banco" não é "seguro para HTML".

## Referências

- Query builder oficial: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/database/dbiterator.html
- Upgrade 11 (sintaxe única obrigatória): https://glpi-developer-documentation.readthedocs.io/en/master/upgradeguides/glpi-11.0.html
- Documentos relacionados: `06-Migrations.md`, `07-CommonDBTM.md`, `10-Search.md`, `29-Performance.md`
