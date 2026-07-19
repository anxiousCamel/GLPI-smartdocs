# 07 — CommonDBTM

## Objetivo

Dominar `CommonDBTM`, a classe-base de todo itemtype persistente do GLPI: ciclo de métodos sobrescrevíveis, exibição de formulário via Twig, e como declarar rights próprios.

## Conceitos

- **`CommonDBTM extends CommonGLPI`** (ver `08-CommonGLPI.md`) e adiciona a camada de persistência: `getTable()`, `add()`, `update()`, `delete()`, `getFromDB()`, além de todo o ciclo `prepareInputFor*`/`post_*Item` descrito em `02-Lifecycle.md`.
- **`$this->fields`** = estado persistido (após `getFromDB`/`add`); **`$this->input`** = dados recebidos do form/API, disponível durante `add`/`update` para os hooks e overrides. Nunca leia `$this->fields` esperando o valor que acabou de vir do form antes do insert — use `$this->input`.
- **Right por classe**: `public static $rightname = 'plugin_meuplugin_coisa';` mais, quando necessário, override de `getRights($interface = 'central')` para acrescentar níveis customizados (ver `04-Rights.md`).
- **Exibição de formulário**: `showForm()` não precisa reimplementar HTML do zero — o padrão oficial é estender o template genérico do core via Twig (`generic_show_form.html.twig`) e usar `TemplateRenderer`.

## Funcionamento interno

`CommonDBTM::add($input)` executa, nesta ordem: `prepareInputForAdd($input)` → hook `pre_item_add` → `$DB->insert()` → grava histórico (se habilitado) → `post_addItem()` → hook `item_add`. `update($input)` é o espelho, populando `$this->oldvalues` a partir do diff entre `$this->fields` atual e `$input`. `delete()`/`restore()`/`getFromDB()` operam sobre a mesma tabela resolvida por `getTable()` (convenção de nome, `05-Database.md`).

`getRights($interface)` no core devolve o array padrão (`READ`, `UPDATE`, `CREATE`, `DELETE`, `PURGE`...) mapeado para rótulos; sobrescrever chamando `parent::getRights()` e somando as próprias entradas é o padrão oficial para expor um right customizado na tela de Perfis.

## Fluxograma

```
$item->add($input)                          $item->update($input)
      │                                            │
      ▼                                            ▼
prepareInputForAdd($input)                 getFromDB($input[id])
      │                                            │
      ▼                                            ▼
hook pre_item_add                          prepareInputForUpdate($input)
      │                                            │
      ▼                                            ▼
$DB->insert(getTable(), fields)            hook pre_item_update
      │                                            │
      ▼                                            ▼
histórico (se $history)                    $DB->update(...) + oldvalues
      │                                            │
      ▼                                            ▼
post_addItem()                             post_updateItem($history)
      │                                            │
      ▼                                            ▼
hook item_add                              hook item_update
```

## Exemplos corretos

### Itemtype completo com right customizado e showForm em Twig

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;

/**
 * Itemtype "Coisa" do plugin Meuplugin.
 * Tabela: glpi_plugin_meuplugin_coisas (resolvida por convenção de nome).
 */
class Coisa extends CommonDBTM
{
    public static $rightname = 'plugin_meuplugin_coisa';

    /** Nível de direito customizado, acima da faixa padrão do core */
    public const APPROVE = 128;

    public static function getTypeName($nb = 0): string
    {
        return _n('Coisa', 'Coisas', $nb, 'meuplugin');
    }

    /**
     * Estende os rights padrão do core com o nível customizado APPROVE,
     * para que ele apareça na matriz de Perfis.
     */
    public function getRights($interface = 'central'): array
    {
        $rights = parent::getRights();
        $rights[self::APPROVE] = __('Aprovar', 'meuplugin');
        return $rights;
    }

    /**
     * Normaliza/valida antes do INSERT. Retornar false aborta a operação.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        if (empty($input['name'])) {
            \Session::addMessageAfterRedirect(
                __('Nome é obrigatório.', 'meuplugin'),
                false,
                ERROR
            );
            return false;
        }

        $input['name'] = trim($input['name']);
        return $input;
    }

    /**
     * Efeito colateral pós-insert: item já existe e tem id.
     */
    public function post_addItem(): void
    {
        // ex.: log de auditoria, envio de evento, etc.
    }

    /**
     * Renderiza o form usando o template genérico do core, estendido
     * pelo template próprio do plugin (padrão oficial documentado).
     *
     * @param int   $ID
     * @param array $options
     */
    public function showForm($ID, $options = []): bool
    {
        $this->initForm($ID, $options);

        TemplateRenderer::getInstance()->display('@meuplugin/coisa.form.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);

        return true;
    }
}
```

```twig
{# templates/coisa.form.html.twig — registrado sob o namespace @meuplugin #}
{% extends "generic_show_form.html.twig" %}
{% import "components/form/fields_macros.html.twig" as fields %}

{% block more_fields %}
    {{ fields.textField('name', item.fields['name'], __('Nome', 'meuplugin')) }}
{% endblock %}
```

## Exemplos incorretos

```php
// ERRADO: showForm() reimplementando HTML manualmente do zero, com
// echo direto e sem escape — reinventa o que o template genérico já
// resolve e abre superfície de XSS.
public function showForm($ID, $options = [])
{
    echo '<form>';
    echo '<input name="name" value="' . $this->fields['name'] . '">'; // sem escape
    echo '</form>';
    return true;
}
```

```php
// ERRADO: lógica de negócio pesada (chamadas externas, envio de e-mail
// síncrono) dentro de prepareInputForAdd — a operação ainda pode ser
// vetada por outro hook depois. Isso pertence a post_addItem().
public function prepareInputForAdd($input)
{
    Mailer::enviar(...); // efeito colateral cedo demais
    return $input;
}
```

```php
// ERRADO: reaproveitar um nível de right padrão do core (ex.: 16=PURGE)
// para uma semântica própria ("aprovar"). Colide com a matriz de
// Perfis e com qualquer código que assuma o significado padrão do bit.
public const APPROVE = 16; // conflita com PURGE
```

## Boas práticas

- Prefira sempre estender `generic_show_form.html.twig` a reescrever o form inteiro — herda acessibilidade, layout e comportamento de erro do core de graça.
- Valide em `prepareInputFor*`; efeitos colaterais em `post_*Item`.
- `getRights()` sempre chama `parent::getRights()` primeiro e soma — nunca substitui o array padrão.
- Use `$this->fields` só depois de `getFromDB`/após persistência; durante `add`/`update`, a fonte de verdade do que está sendo gravado é `$this->input`.

## Anti-patterns

- HTML manual fora de Twig para forms de itemtype.
- Reutilizar valores de bit padrão do core para rights customizados.
- Colocar SQL raw dentro de métodos de `CommonDBTM` em vez de usar `$DB`/query builder (ver `05-Database.md`).
- Sobrescrever `add()`/`update()`/`delete()` inteiros quando bastaria usar os hooks de ciclo de vida (`prepareInputFor*`, `post_*Item`).

## Checklist

- [ ] `$rightname` declarado e usado nos entry points
- [ ] `getRights()` estende, não substitui, o padrão do core
- [ ] `prepareInputFor*` valida; `post_*Item` executa efeitos colaterais
- [ ] `showForm` usa Twig (`generic_show_form.html.twig` como base)
- [ ] `getTypeName()` traduzido com domínio do plugin

## Dicas de performance

- `getFromDB()` já usa índice de PK; evite recarregar o item múltiplas vezes na mesma request — reuse a instância.
- Histórico (`$history`) tem custo; em operações em massa/importação, avalie desabilitar (`$item->update($input, false)`) quando o log não for necessário.

## Dicas de segurança

- Twig escapa por padrão — é a razão para nunca voltar a `echo` manual em `showForm`.
- `post_*Item` é o lugar certo para side effects, mas ainda assim exige checagem de right se o efeito colateral for uma ação sensível (ex.: notificar, criar outro registro).

## Referências

- Developer API — Main framework objects: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/mainobjects.html
- Tutorial oficial (showForm + Twig + getRights): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Código-fonte: https://github.com/glpi-project/glpi/blob/10.0/bugfixes/src/CommonDBTM.php
- Documentos relacionados: `02-Lifecycle.md`, `04-Rights.md`, `08-CommonGLPI.md`, `20-Twig.md`
