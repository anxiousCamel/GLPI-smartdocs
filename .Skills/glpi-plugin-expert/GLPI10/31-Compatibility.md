# 31 — Compatibilidade (10.x e 11.x)

## Objetivo

Definir a estratégia para escrever um plugin que funcione corretamente tanto em GLPI 10.x quanto 11.x — seja porque o cliente ainda não migrou, seja porque o plugin precisa suportar ambos durante uma janela de transição.

## Conceitos

- **Toda quebra do GLPI 11 documentada em `GLPI11/00-Migration-Guide.md` tem uma forma de escrever o código no 10.x que já funciona igual no 11.** Isso significa que, na maioria dos casos, **não existe trade-off real entre "compatível com 10" e "compatível com 11"** — o padrão moderno funciona nos dois.
- **As únicas exceções genuínas** (onde 10 e 11 exigem código diferente) são features novas do 11 sem equivalente no 10 (ex.: `Firewall::addPluginStrategyForLegacyScripts`, `SessionManager::registerPluginStatelessPath`) — nesses casos, feature-detect via `class_exists()`.
- **Declarar a faixa de compatibilidade honestamente** no `setup.php` (`requirements.glpi.min/max`) é parte do contrato com o administrador — não é só formalidade.

## Tabela de decisão rápida

| Prática | Funciona em 10.x? | Funciona em 11.x? | Ação |
|---|---|---|---|
| Query builder com array único (`['FROM' => ..., 'WHERE' => ...]`) | Sim | Sim (obrigatório) | Usar sempre, mesmo em 10.x |
| Query builder com tabela como 1º parâmetro | Sim (depreciado) | Não | Nunca usar, mesmo em 10.x |
| Views 100% Twig | Sim | Sim | Usar sempre |
| `echo` HTML manual sem escape | Funciona "por acidente" (auto-sanitize) | Quebra (XSS real) | Nunca usar, mesmo em 10.x — usar Twig/`htmlescape()` |
| `exit()`/`die()` para erro HTTP | Sim | Desencorajado/pode falhar em cenários específicos | Usar exceção HTTP sempre |
| `Plugin::getWebDir()` | Sim (depreciado) | Não | Usar caminho fixo `/plugins/<nome>/...` sempre |
| `include inc/includes.php` | Necessário | Não removido, mas redundante | Manter por ora (compatibilidade), mas não expandir a dependência nele |
| `Firewall::addPluginStrategyForLegacyScripts` | Não existe | Existe | `if (class_exists(...))` |
| `SessionManager::registerPluginStatelessPath` | Não existe | Existe | `if (class_exists(...))` |
| `addslashes`/`Toolbox::addslashes_deep` em input | Necessário só se confiar no auto-sanitize (não confie) | Quebra (dupla escaping) | Nunca usar em nenhuma versão — escreva como se auto-sanitize não existisse |

## Estratégia recomendada

1. **Escreva sempre no "modo GLPI 11 compatível"**, mesmo visando 10.x primariamente — a tabela acima mostra que isso quase sempre já funciona em 10.x também.
2. **Use feature-detect (`class_exists`/`method_exists`) apenas para as poucas APIs genuinamente novas do 11** que não têm equivalente no 10.
3. **Teste a suíte (ver `25-Testing.md`) contra ambas as versões** quando o plugin declarar suporte a ambas — não assuma compatibilidade sem testar.
4. **Documente no README** exatamente quais versões foram testadas, não apenas quais "deveriam" funcionar.

## Exemplos corretos

### Código único que funciona em 10.x e 11.x

```php
<?php

// Query builder correto nas duas versões
$iterator = $DB->request([
    'FROM'  => 'glpi_plugin_meuplugin_coisas',
    'WHERE' => ['entities_id' => Session::getActiveEntities()],
]);

// View em Twig — auto-escape funciona igual nas duas versões
TemplateRenderer::getInstance()->display('@meuplugin/coisa.form.html.twig', [
    'item' => $this,
]);

// Erro HTTP via exceção — funciona nas duas versões
if (!$item->getFromDB($id)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}
```

### Feature-detect para API exclusiva do 11

```php
<?php
// setup.php

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;
    // ... registros comuns

    if (class_exists(\Glpi\Http\Firewall::class)) {
        // Só existe/é necessário no GLPI 11
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
            'meuplugin',
            '#^/front/publico.php$#',
            \Glpi\Http\Firewall::STRATEGY_NO_CHECK
        );
    }
}
```

## Exemplos incorretos

```php
// ERRADO: código que "funciona" no 10.x só por causa do auto-sanitize,
// e quebra silenciosamente no 11 porque a sanitização automática some.
$nome = $_POST['name']; // sem validação/tipo
$DB->insert('glpi_plugin_meuplugin_coisas', ['name' => addslashes($nome)]);
// addslashes aqui é redundante no 10 (auto-sanitize já fez) e ERRADO
// no 11 (double-escaping, já que não há auto-sanitize para compensar).
```

```php
// ERRADO: branch de código totalmente duplicado (10 vs 11) quando a
// tabela de decisão mostra que uma única forma já serve para ambos —
// gera manutenção dobrada sem necessidade real.
if (versaoDoGlpiEh10()) {
    // implementação A
} else {
    // implementação B, quase idêntica à A
}
```

## Boas práticas

- Prefira UMA implementação que sirva para as duas versões (a tabela de decisão mostra que isso é possível na esmagadora maioria dos casos) a duas implementações paralelas.
- Feature-detect (`class_exists`) apenas para as exceções genuínas listadas na tabela.
- Trate a lista de `GLPI11/00-Migration-Guide.md` como um checklist de "nunca fazer isso, mesmo em 10.x" — não como algo a corrigir só quando migrar.

## Anti-patterns

- Duas implementações paralelas quando uma única já cobriria ambas as versões.
- Confiar no auto-sanitize do 10.x para qualquer coisa — é uma muleta que desaparece no 11.
- Declarar suporte a uma faixa de versão sem tê-la testado de fato.

## Checklist

- [ ] Nenhum item da lista de quebras do GLPI 11 presente no código, mesmo visando só 10.x
- [ ] Feature-detect usado apenas para APIs genuinamente exclusivas de uma versão
- [ ] Faixa de versão no `setup.php` reflete o que foi realmente testado
- [ ] Suíte de testes rodada contra cada versão declarada como suportada

## Referências

- `GLPI11/00-Migration-Guide.md` — lista completa e detalhada de quebras com diffs
- Documentos relacionados: `05-Database.md`, `13-Routing.md`, `20-Twig.md`, `25-Testing.md`
