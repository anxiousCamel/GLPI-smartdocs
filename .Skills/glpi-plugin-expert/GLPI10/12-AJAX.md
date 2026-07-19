# 12 — AJAX

## Objetivo

Escrever endpoints `ajax/*.php` de plugin seguindo o padrão do core: entry point fino, CSRF, right, resposta previsível (JSON ou HTML parcial) e sem lógica de negócio embutida no script.

## Conceitos

- **`ajax/` é para chamadas assíncronas do front-end** (autocomplete, atualização de campo dependente, submissão parcial de formulário) — diferente de `front/`, que serve páginas completas.
- **Mesmo pipeline de segurança que `front/`:** bootstrap, checagem de right, checagem de CSRF em POST. Um endpoint AJAX é uma fronteira de confiança tão sensível quanto um `front/*.php` — é acessível diretamente por URL, hooks de UI não protegem nada.
- **Dois formatos de resposta comuns:** JSON (`Html::json_encode` / `echo json_encode(...)` com `Content-Type: application/json`) para consumo por JS, ou fragmento HTML puro (quando o JS só injeta o retorno num container via `innerHTML`/jQuery `.html()`).

## Funcionamento interno

O JS do front-end dispara um `fetch`/`$.ajax` para `/plugins/meuplugin/ajax/algo.php` com parâmetros (GET ou POST). O script inclui o bootstrap, valida direito e CSRF (quando POST), processa a requisição delegando a uma classe de `src/` (nunca a lógica embutida no próprio script), e imprime a resposta no formato esperado pelo JS chamador — terminando a execução sem retornar HTML de página completa.

## Fluxograma

```
JS do plugin (fetch/$.ajax)
      │  POST /plugins/meuplugin/ajax/atualizaCampo.php
      ▼
ajax/atualizaCampo.php
      │  bootstrap → Session::checkRight → checkCSRF (automático via csrf_compliant)
      ▼
delega para Classe::metodo() em src/
      │
      ▼
echo json_encode([...]) ou fragmento HTML
      ▼
JS injeta na página
```

## Exemplos corretos

### Endpoint AJAX retornando JSON

```php
<?php
// ajax/getStatusOptions.php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php'); // GLPI 10.x

header('Content-Type: application/json; charset=UTF-8');

Session::checkRight(Coisa::$rightname, READ);

$categoriaId = (int) ($_GET['categorias_id'] ?? 0);

echo json_encode(
    Coisa::getStatusOptionsParaCategoria($categoriaId)
);
```

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;

class Coisa extends CommonDBTM
{
    /**
     * Retorna as opções de status válidas para uma categoria,
     * usadas para popular um <select> dependente via AJAX.
     *
     * @return array<int, string>
     */
    public static function getStatusOptionsParaCategoria(int $categoriaId): array
    {
        if ($categoriaId <= 0) {
            return [];
        }

        // ... lógica de negócio real, via query builder
        return [
            1 => __('Aberto', 'meuplugin'),
            2 => __('Em análise', 'meuplugin'),
        ];
    }
}
```

### Endpoint AJAX retornando fragmento HTML

```php
<?php
// ajax/coisaDropdown.php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

Session::checkRight(Coisa::$rightname, READ);

// Dropdown::show() já escreve HTML pronto para o container do JS
\Dropdown::show(Coisa::class, [
    'name'      => $_GET['name'] ?? 'plugin_meuplugin_coisas_id',
    'entity'    => $_GET['entity_restrict'] ?? -1,
    'value'     => (int) ($_GET['value'] ?? 0),
]);
```

## Exemplos incorretos

```php
// ERRADO: endpoint sem checagem de right — qualquer usuário autenticado
// (ou, dependendo de configuração, não autenticado) consegue chamar
// diretamente a URL e obter/alterar dado sensível.
include('../../../inc/includes.php');
echo json_encode(Coisa::listarTudo());
```

```php
// ERRADO: lógica de negócio pesada dentro do próprio script ajax/*.php.
// Impossível de testar isoladamente e impossível de reusar em outro
// entry point (front, API, cron).
include('../../../inc/includes.php');
$db = ...;
$resultado = $db->query("SELECT ..."); // além de SQL raw, é lógica solta
echo json_encode($resultado);
```

```php
// ERRADO: retornar HTML sem escape a partir de dado de usuário.
echo '<option>' . $_GET['categoria'] . '</option>'; // XSS direto
```

## Boas práticas

- Um endpoint, uma responsabilidade — se o JS precisa de dois formatos de resposta diferentes, são dois scripts, não um script com múltiplos "modos" via parâmetro.
- Delegue toda lógica não trivial para métodos estáticos/instância em `src/`; o script é só cola (bootstrap → right → chamada → resposta).
- Declare `Content-Type` explicitamente quando responder JSON — evita o navegador tentar interpretar como HTML.
- Valide e converta tipos de entrada (`(int)`, `(string)` explícitos) antes de repassar a qualquer método — nunca passe `$_GET`/`$_POST` cru adiante.

## Anti-patterns

- Endpoint AJAX sem `Session::checkRight`.
- Lógica de negócio (consultas, regras) inline no script em vez de em classe testável.
- Misturar responsabilidade de leitura e escrita no mesmo endpoint sem distinguir GET (idempotente) de POST (efeito colateral, exige CSRF).
- Responder HTML sem escapar dado de entrada refletido na saída.

## Checklist

- [ ] `Session::checkRight` antes de qualquer processamento
- [ ] POST valida CSRF (via `csrf_compliant`, herdado do plugin)
- [ ] Lógica delegada a classe de `src/`, script apenas orquestra
- [ ] `Content-Type` correto para o formato de resposta
- [ ] Toda saída HTML escapada; toda entrada tipada/validada

## Dicas de performance

- Endpoints AJAX costumam ser chamados com alta frequência (autocomplete, digitação) — mantenha a query subjacente indexada e rápida; considere debounce no lado do JS.
- Evite recarregar configuração pesada do plugin a cada chamada; cacheie o que for estável entre requests (ver `29-Performance.md`).

## Dicas de segurança

- Um endpoint AJAX é tão acessível por URL direta quanto um `front/*.php` — hooks de UI (esconder botão) não substituem a checagem de right no servidor.
- Nunca reflita `$_GET`/`$_POST` diretamente na resposta HTML sem escape — endpoints AJAX são um vetor comum de XSS refletido em plugins.

## Referências

- Tutorial oficial (padrões de request/AJAX): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Documentos relacionados: `04-Rights.md`, `13-Routing.md`, `30-Security.md`
