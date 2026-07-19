# 30 — Segurança

## Objetivo

Consolidar, num único documento de referência rápida, todas as práticas de segurança que um plugin GLPI precisa respeitar: right, CSRF, escape de saída, validação de entrada, upload de arquivos, e os vetores de ataque mais comuns em plugins reais.

## Conceitos

- **Toda fronteira é uma fronteira de confiança independente.** `front/`, `ajax/`, endpoint de API próprio, callback de hook, callback de massive action, callback de cron — cada um precisa validar right e sanitizar entrada por conta própria. Nenhum deles herda segurança de outro.
- **Modelo de ameaça de um plugin GLPI**: o atacante mais comum não é um invasor externo sofisticado, é um **usuário autenticado com right insuficiente tentando escalar**, ou um **XSS refletido/armazenado explorado por outro usuário autenticado**. Plugins de negócio raramente são o alvo de ataques anônimos diretos (a instância já exige login), mas isso não reduz a exigência — CSRF e XSS entre usuários autenticados são o risco real e constante.
- **Camadas de defesa, na ordem em que devem ser aplicadas:** (1) right — quem pode fazer isso; (2) entidade — em qual escopo organizacional; (3) CSRF — a requisição veio de um form legítimo; (4) validação/tipagem de entrada — o dado tem o formato esperado; (5) escape de saída — o dado exibido não quebra o contexto (HTML/JS/SQL).

## Checklist de segurança por camada

### 1. Right

- [ ] Todo entry point chama `Session::checkRight()`/`canViewItem()`/equivalente antes de qualquer efeito ou exibição de dado (`04-Rights.md`).
- [ ] Right de AÇÃO (massive action, aprovação) é diferente e checado separadamente do right de visualização (`11-MassiveActions.md`).
- [ ] Rights customizados usam faixa acima de 1024, nunca reaproveitando valores padrão do core (`04-Rights.md`).

### 2. Entidade

- [ ] Todo itemtype de negócio tem `entities_id`/`is_recursive` quando aplicável (`18-Entities.md`).
- [ ] Toda consulta customizada (fora do `Search` nativo) filtra por `Session::getActiveEntities()`.

### 3. CSRF

- [ ] `csrf_compliant` declarado no `plugin_init` (`01-Plugin-Structure.md`).
- [ ] Nenhum form/POST do plugin ignora o token CSRF automático do GLPI.

### 4. Validação de entrada

- [ ] Todo `$_GET`/`$_POST` convertido para o tipo esperado (`(int)`, `(string)`) antes de uso.
- [ ] `prepareInputForAdd`/`prepareInputForUpdate` validam formato/obrigatoriedade antes do INSERT/UPDATE (`02-Lifecycle.md`, `07-CommonDBTM.md`).
- [ ] Nenhum SQL raw — sempre query builder (`05-Database.md`).

### 5. Escape de saída

- [ ] Views em Twig (auto-escape); `htmlescape()`/`jsescape()` fora de Twig quando aplicável (`20-Twig.md`, `GLPI11/00-Migration-Guide.md`).
- [ ] Nenhum `|raw` sobre dado de usuário não sanitizado.
- [ ] Resposta JSON de endpoint AJAX/API não interpola dado de usuário em string antes de `json_encode` (deixe o `json_encode` fazer o escaping).

## Vetores comuns em plugins GLPI reais

| Vetor | Onde costuma aparecer | Mitigação |
|---|---|---|
| XSS refletido | Endpoint AJAX que ecoa parâmetro GET direto no HTML de resposta | Escapar tudo; nunca refletir input cru |
| XSS armazenado | Campo de texto livre (comentário, nome) exibido sem escape numa lista/aba própria fora do form padrão | Twig auto-escape; nunca `echo` manual |
| Escalação de privilégio | Ação de massive action/endpoint que checa right de visualização mas não o right específico da ação | Checar right específico em cada ação |
| Vazamento entre entidades | Query customizada sem filtro de entidade num itemtype multi-tenant | Sempre filtrar por `Session::getActiveEntities()` |
| CSRF | Plugin sem `csrf_compliant`, ou endpoint próprio de API que não reaproveita a sessão do GLPI | Declarar `csrf_compliant`; reaproveitar `Session` |
| Upload malicioso | Endpoint que aceita upload de arquivo sem validar tipo/tamanho | Usar as APIs de `Document`/upload do core, que já validam extensão/mimetype |
| SQL injection | Qualquer `$DB->doQuery()` com string interpolada | Query builder sempre |

## Exemplos corretos

```php
<?php
// Padrão de entry point seguro, camada por camada

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

// 1. Right
Session::checkRight(Coisa::$rightname, UPDATE);

// 4. Validação de entrada
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    Html::displayErrorAndDie(__('ID inválido.', 'meuplugin'));
}

$coisa = new Coisa();
$coisa->getFromDB($id);

// 2. Entidade (via método de instância, não comparação manual)
if (!$coisa->canUpdateItem()) {
    Html::displayRightError();
}

// 3. CSRF já validado automaticamente pelo pipeline do GLPI
//    (csrf_compliant declarado no plugin)

$coisa->update($_POST); // 5. Escape de saída acontece na camada de view (Twig)
```

## Exemplos incorretos

```php
// ERRADO: pula direto para a ação sem nenhuma das cinco camadas.
$coisa = new Coisa();
$coisa->update($_POST); // sem right, sem validação, sem checagem de entidade
```

```php
// ERRADO: right checado, mas endpoint reflete parâmetro cru no HTML —
// XSS refletido mesmo com autenticação/right corretos.
Session::checkRight(Coisa::$rightname, READ);
echo '<div>' . $_GET['busca'] . '</div>';
```

## Boas práticas

- Trate as cinco camadas como um checklist mental obrigatório em TODO entry point novo, não como algo a lembrar "se der tempo".
- Prefira sempre as APIs do core para upload (`Document`) em vez de mover arquivo manualmente.
- Revise plugins de terceiros com a mesma régua antes de instalar em produção — um plugin comunitário mal escrito compromete a instância inteira, não só suas próprias telas.

## Anti-patterns

- Confiar que "só usuários autenticados acessam essa URL" como controle de acesso suficiente.
- Validar right apenas no primeiro passo de um fluxo multi-etapa, assumindo que os passos seguintes herdam a validação.
- Upload de arquivo aceito sem checagem de extensão/mimetype via as classes nativas do GLPI.

## Checklist

Ver checklist por camada acima — é o checklist operacional deste documento.

## Referências

- Consolidação a partir de: `04-Rights.md`, `05-Database.md`, `12-AJAX.md`, `13-Routing.md`, `18-Entities.md`, `20-Twig.md`, `GLPI11/00-Migration-Guide.md`
- Ver também: `Checklists/SecurityChecklist.md` para o checklist operacional de PR.
