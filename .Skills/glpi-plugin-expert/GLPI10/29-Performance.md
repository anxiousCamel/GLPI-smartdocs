# 29 — Performance

## Objetivo

Consolidar as práticas de performance específicas ao ambiente GLPI: onde o custo realmente se acumula (init de plugin, hooks de item, queries de lista), e como usar o cache nativo do GLPI para dados caros e estáveis.

## Conceitos

- **`plugin_init` roda em toda request** de qualquer página do GLPI (não só das telas do próprio plugin) — é o ponto de maior alavancagem de performance negativa se algo pesado for colocado ali (`01-Plugin-Structure.md`).
- **Hooks de item (`ITEM_ADD`/`ITEM_UPDATE`/etc.) rodam em toda operação daquele itemtype**, incluindo importações em massa e inventário — precisam ser O(1)/baratos (`03-Hooks.md`).
- **`GLPI_CACHE`** é a camada de cache nativa configurável (arquivo, Redis, Memcached conforme a instância) acessível via `Config`/wrapper de cache do core — o lugar certo para dados caros e estáveis (configuração resolvida, resultado de cálculo pouco variável), em vez de reimplementar cache próprio com arquivo/variável estática.
- **Índices de banco são a alavanca #1** de performance de query — toda coluna usada em `WHERE`/`JOIN` com frequência precisa de `KEY` (`05-Database.md`).

## Funcionamento interno

O GLPI expõe uma abstração de cache (`Config`/`Toolbox` conforme a versão, ou diretamente via `\Glpi\Cache` nas versões mais recentes) que abstrai o backend configurado pelo administrador (arquivo local, Redis, Memcached). Usar essa abstração garante que o cache do plugin se beneficie da mesma infraestrutura e política de invalidação já operada pela equipe de infra do cliente, em vez de introduzir um mecanismo paralelo que ninguém monitora.

## Fluxograma

```
Requisição
      │
      ▼
plugin_init (barato: só registra hooks)
      │
      ▼
Página específica do plugin
      │
      ├── dado caro/estável? → cache (GLPI_CACHE) → hit? retorna
      │                                           → miss? calcula + grava cache
      │
      └── lista/busca → Search/query builder com índices adequados
```

## Exemplos corretos

### Cache de configuração cara

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use GLPICacheInterfaceCompat as Cache; // nome ilustrativo — usar a API de cache real da versão instalada

final class ConfigCache
{
    private const CHAVE = 'plugin_meuplugin_config_resolvida';

    public static function obter(): array
    {
        $cache = self::cacheDoGlpi();

        if ($cache->has(self::CHAVE)) {
            return $cache->get(self::CHAVE);
        }

        $valor = self::calcularConfigCara();
        $cache->set(self::CHAVE, $valor, 3600); // TTL de 1h

        return $valor;
    }

    private static function calcularConfigCara(): array
    {
        // ... cálculo/consulta cara
        return [];
    }

    private static function cacheDoGlpi()
    {
        global $GLPI_CACHE;
        return $GLPI_CACHE;
    }
}
```

### Índices corretos numa tabela de alto tráfego

```sql
CREATE TABLE `glpi_plugin_meuplugin_coisas` (
    `id`           int unsigned NOT NULL AUTO_INCREMENT,
    `name`         varchar(255) DEFAULT NULL,
    `entities_id`  int unsigned NOT NULL DEFAULT '0',
    `status`       int NOT NULL DEFAULT '0',
    `date_mod`     timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `entities_id` (`entities_id`),
    KEY `status` (`status`),
    KEY `entities_id_status` (`entities_id`, `status`) -- composto para filtro comum combinado
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Exemplos incorretos

```php
// ERRADO: query cara recalculada em toda request dentro de plugin_init.
function plugin_init_meuplugin(): void
{
    global $DB, $PLUGIN_HOOKS;
    $total = $DB->request(['FROM' => 'glpi_plugin_meuplugin_coisas'])->count(); // NÃO
    // ...
}
```

```php
// ERRADO: cache próprio via arquivo estático no diretório do plugin,
// sem TTL nem invalidação — diverge da infraestrutura de cache que
// o administrador já opera (Redis/Memcached) e nunca é limpo.
file_put_contents(__DIR__ . '/cache.json', json_encode($dado));
```

```php
// ERRADO: hook de item fazendo uma query pesada (JOIN complexo) a
// cada item processado numa importação de 10.000 registros —
// transforma uma operação O(n) do core em O(n × custo_da_query).
```

## Boas práticas

- Meça antes de otimizar: use o modo debug (`26-Debugging.md`) para confirmar onde o tempo realmente vai antes de adicionar cache/índice especulativamente.
- Prefira índice composto quando o filtro mais comum combina duas colunas com frequência (ex.: `entities_id` + `status`).
- Cache com TTL explícito e chave namespaced pelo plugin — nunca cache "para sempre" sem plano de invalidação.
- Processamento em lote (cron, massive action) sempre com limite por execução — nunca "tudo de uma vez" (`11-MassiveActions.md`, `15-Cron.md`).

## Anti-patterns

- Qualquer I/O de banco/rede dentro de `plugin_init`.
- Cache próprio reinventado (arquivo, variável estática) em vez de `GLPI_CACHE`.
- Hook de item com custo não-O(1).
- Tabela de alto tráfego sem índice nas colunas de filtro mais comuns.

## Checklist

- [ ] `plugin_init` livre de I/O pesado
- [ ] Hooks de item mantidos O(1)
- [ ] Dado caro/estável usa `GLPI_CACHE` com TTL, não mecanismo próprio
- [ ] Toda coluna de filtro frequente tem índice (simples ou composto)
- [ ] Processamento em lote (cron/massive action) limitado por execução

## Dicas de segurança

- Cache nunca deve guardar dado sensível sem considerar quem tem acesso ao backend de cache (Redis compartilhado, por exemplo) — trate como extensão da superfície de dados do plugin.

## Referências

- Developer API (visão geral de infraestrutura): https://glpi-developer-documentation.readthedocs.io/en/master/devapi/index.html
- Documentos relacionados: `01-Plugin-Structure.md`, `03-Hooks.md`, `05-Database.md`, `15-Cron.md`, `26-Debugging.md`
