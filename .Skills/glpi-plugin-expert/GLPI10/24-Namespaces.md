# 24 — Namespaces e PSR-4

## Objetivo

Adotar o padrão de namespace moderno `GlpiPlugin\<Nome>\` para todo o código do plugin, entendendo a diferença em relação ao estilo legado `Plugin<Nome><Classe>` e por que a convenção moderna é a escolha correta para todo código novo.

## Conceitos

- **Dois estilos coexistem historicamente no ecossistema GLPI:**
  - **Legado**: classes globais nomeadas `Plugin<Nome><Classe>` (ex.: `PluginMeupluginCoisa`), arquivo em `inc/coisa.class.php`. Sem namespace real — tudo no namespace global do PHP.
  - **Moderno (usar sempre em código novo)**: `GlpiPlugin\<Nome>\<Classe>` (ex.: `GlpiPlugin\Meuplugin\Coisa`), arquivo em `src/Coisa.php`, resolvido via autoload PSR-4 (`23-Composer.md`).
- **O core resolve ambos os estilos por convenção de nome** (para tabela, FK, rights etc.), então migrar/misturar é tecnicamente possível — mas todo plugin novo desta KB nasce 100% no estilo moderno: namespace real, sem poluir o namespace global, com autocomplete de IDE funcionando corretamente e testes mais fáceis de isolar.
- **PSR-4 é a especificação do autoload**: um namespace-prefixo mapeia para um diretório-base. `GlpiPlugin\Meuplugin\` → `src/`, então `GlpiPlugin\Meuplugin\Search\Filtro` deve estar em `src/Search/Filtro.php` — a estrutura de subpastas espelha a estrutura de sub-namespaces.

## Funcionamento interno

Ao encontrar `use GlpiPlugin\Meuplugin\Coisa;`, o autoloader Composer (configurado no `composer.json` do plugin, ver `23-Composer.md`) resolve para `plugins/meuplugin/src/Coisa.php` sem exigir `require` manual. O GLPI, ao processar hooks/rights/tabelas, extrai o "nome do itemtype" da classe totalmente qualificada e aplica as mesmas regras de convenção (tabela, FK) independentemente do estilo (legado ou moderno) — a diferença é inteiramente de organização de código, não de comportamento runtime do core.

## Fluxograma

```
use GlpiPlugin\Meuplugin\Search\Filtro;
      │
      ▼
Composor PSR-4: GlpiPlugin\Meuplugin\ → src/
      │
      ▼
resolve para: plugins/meuplugin/src/Search/Filtro.php
      │
      ▼
classe carregada, sem require manual
```

## Exemplos corretos

### Estrutura consistente namespace ↔ diretório

```
plugins/meuplugin/
├── composer.json      # psr-4: "GlpiPlugin\\Meuplugin\\": "src/"
└── src/
    ├── Coisa.php                    # GlpiPlugin\Meuplugin\Coisa
    ├── Profile.php                  # GlpiPlugin\Meuplugin\Profile
    ├── NotificationTargetCoisa.php  # GlpiPlugin\Meuplugin\NotificationTargetCoisa
    └── Search/
        └── Filtro.php               # GlpiPlugin\Meuplugin\Search\Filtro
```

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin\Search;

/**
 * Sub-namespace espelha subpasta: src/Search/Filtro.php.
 */
final class Filtro
{
    // ...
}
```

### Uso consistente de `use` em vez de FQCN inline

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;          // classes do core GLPI ficam no namespace global
use Session;
use GlpiPlugin\Meuplugin\Search\Filtro;

class Coisa extends CommonDBTM
{
    public function buscar(): array
    {
        Session::checkRight(self::$rightname, READ);
        $filtro = new Filtro();
        return $filtro->aplicar();
    }
}
```

## Exemplos incorretos

```php
// ERRADO: classe nova usando estilo legado sem namespace. Funciona
// (o core reconhece), mas perde tudo que o namespace real oferece
// (isolamento, autocomplete correto, testes desacoplados) sem
// nenhuma vantagem em troca para código escrito hoje.
class PluginMeupluginCoisa extends CommonDBTM
{
    // ...
}
```

```php
// ERRADO: namespace declarado mas estrutura de pasta não bate —
// GlpiPlugin\Meuplugin\Search\Filtro num arquivo em src/Filtro.php
// (faltando a subpasta Search/) quebra a resolução PSR-4.
```

```php
// ERRADO: usar Fully Qualified Class Name inline repetidamente em
// vez de "use" no topo do arquivo — funciona, mas prejudica leitura
// e é inconsistente com o padrão adotado no restante do plugin.
public function metodo(): \GlpiPlugin\Meuplugin\Search\Filtro
{
    return new \GlpiPlugin\Meuplugin\Search\Filtro();
}
```

## Boas práticas

- Todo código novo no estilo moderno `GlpiPlugin\<Nome>\`; nunca introduza classes legadas `Plugin<Nome><Classe>` em plugin novo.
- Sub-namespaces espelham subpastas fielmente — é o contrato do PSR-4.
- `declare(strict_types=1);` no topo de todo arquivo novo (consistente com `27-BestPractices.md`).
- Classes do core GLPI (sem namespace, ex.: `CommonDBTM`, `Session`) são importadas com `use ClasseDoCore;` no topo, não referenciadas com barra invertida solta espalhada pelo corpo do arquivo.

## Anti-patterns

- Misturar estilo legado e moderno dentro do MESMO plugin sem motivo de migração incremental documentado.
- Estrutura de pasta que não espelha o namespace declarado.
- Uso extensivo de FQCN inline em vez de `use` no topo do arquivo.
- Reaproveitar o mesmo nome de classe em dois sub-namespaces diferentes de forma confusa (ex.: `Search\Coisa` e `Coisa` na raiz, sem relação clara).

## Checklist

- [ ] Todo código novo usa `GlpiPlugin\<Nome>\`, nunca o estilo legado
- [ ] Estrutura de subpastas em `src/` espelha exatamente os sub-namespaces
- [ ] `use` no topo do arquivo para toda classe referenciada, sem FQCN inline repetido
- [ ] `declare(strict_types=1);` presente em todo arquivo PHP novo

## Dicas de performance

- Autoload PSR-4 com mapa otimizado (`composer install --optimize-autoloader`, ver `23-Composer.md`) resolve classes por lookup direto, sem overhead de scanning de diretório em produção.

## Dicas de segurança

- Namespace real reduz risco de colisão de nome de classe entre plugins diferentes instalados na mesma base — dois plugins com uma classe global `Coisa` colidiriam no estilo legado; no estilo moderno, `GlpiPlugin\PluginA\Coisa` e `GlpiPlugin\PluginB\Coisa` coexistem sem conflito algum.

## Referências

- Tutorial oficial (uso do namespace moderno em exemplos): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Documentos relacionados: `01-Plugin-Structure.md`, `23-Composer.md`, `27-BestPractices.md`
