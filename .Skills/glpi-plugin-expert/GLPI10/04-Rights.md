# 04 — Rights (Permissões)

## Objetivo

Dominar o sistema de direitos do GLPI: como declarar um right próprio, como o core armazena e verifica permissões, e como integrar a UI de administração de perfis.

## Conceitos

- **Right = string + bitmask.** Cada itemtype com controle de acesso declara `public static $rightname = 'plugin_meuplugin_coisa';`. O valor concedido a um perfil é um inteiro (bitmask) guardado em `glpi_profilerights` (colunas `profiles_id`, `name`, `rights`).
- **Constantes de nível padrão** (definidas pelo core, em `inc/define.php`): `READ` (1), `UPDATE` (2), `CREATE` (4), `DELETE` (8), `PURGE` (16), depois `READNOTE`, `UPDATENOTE`, `UNLOCK`, e por convenção cada plugin pode somar bits próprios acima de 1024 para direitos customizados (ex.: `APPROVE = 1024`). Como é bitmask, testar right específico usa `&` (AND bit a bit), nunca `===`.
- **Duas perguntas diferentes**: "o perfil do usuário tem esse right?" (`Session::haveRight`) vs "o usuário pode ver/editar ESTE item específico?" (`canViewItem()`/`canEdit()` na instância, que soma right + entidade + regras de dono/atribuição). Checar só a primeira é a causa nº 1 de bug de segurança em plugin.
- **Multi-entidade entra na jogada:** mesmo com o right concedido, o acesso real depende da entidade do item vs entidades do perfil ativo do usuário (recursividade inclusa) — ver `18-Entities.md`.

## Funcionamento interno

`Session::haveRight($rightname, $level)` lê `$_SESSION['glpiactiveprofile']` (carregado no login a partir de `glpi_profilerights`) e testa `$level` contra o bitmask armazenado — sem tocar banco a cada chamada. Métodos de instância como `canViewItem()`/`canCreateItem()` (herdados de `CommonDBTM`) chamam `haveRight` **e** aplicam checagem de entidade (`Session::haveAccessToEntity`) e, quando o itemtype é "recursivo", a árvore de entidades.

A tela *Administração > Perfis* lista os rights registrados via `Profile::getTabNameForItem()`/aba custom que seu plugin adiciona (`Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']])`), desenhando checkboxes/matriz a partir do array que sua classe expõe.

## Fluxograma

```
Usuário faz login
   │
   ▼
carrega perfil ativo → $_SESSION['glpiactiveprofile']['plugin_meuplugin_coisa'] = bitmask
   │
   ▼
front/coisa.php:  Session::checkRight('plugin_meuplugin_coisa', READ)
   │  (sem right → Html::displayRightError() + exit/return)
   ▼
$item->canViewItem()  ── soma: right global + entidade do item + regras da instância
   │
   ▼
exibe / permite ação
```

## Exemplos corretos

### Declarando o right no itemtype

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;

/**
 * Itemtype com right próprio, incluindo um nível customizado (APPROVE).
 */
class Coisa extends CommonDBTM
{
    public static $rightname = 'plugin_meuplugin_coisa';

    /** Nível customizado acima dos padrões do core (16 = PURGE) */
    public const APPROVE = 1024;

    public static function getTypeName($nb = 0): string
    {
        return _n('Coisa', 'Coisas', $nb, 'meuplugin');
    }

    /**
     * Descreve os níveis de direito para a matriz de Perfis.
     * Chamado pelo core ao montar a aba de rights.
     */
    public static function getRightsForForm(): array
    {
        return [
            self::APPROVE => __('Aprovar', 'meuplugin'),
        ];
    }
}
```

### Checagem em entry point (front)

```php
<?php
// front/coisa.form.php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php'); // GLPI 10.x; ver GLPI11 para o equivalente

Session::checkRight(Coisa::$rightname, READ);

$coisa = new Coisa();

if (isset($_POST['add'])) {
    Session::checkRight(Coisa::$rightname, CREATE);
    $coisa->check(-1, CREATE, $_POST);
    $id = $coisa->add($_POST);
    Html::back();
} elseif (isset($_GET['id'])) {
    $coisa->checkGlobal(READ);          // right global
    $coisa->getFromDB((int) $_GET['id']);
    if (!$coisa->canViewItem()) {        // right + entidade + regra da instância
        Html::displayRightError();
    }
    $coisa->display(['id' => $_GET['id']]);
}
```

### Verificando bit customizado

```php
if (Session::haveRight(Coisa::$rightname, Coisa::APPROVE)) {
    // usuário pode aprovar
}
```

## Exemplos incorretos

```php
// ERRADO: comparação de igualdade em bitmask — falha para qualquer
// combinação onde o bit está setado junto de outros.
if ($_SESSION['glpiactiveprofile']['plugin_meuplugin_coisa'] === READ) { ... }
// Correto: Session::haveRight(...) faz o AND bit a bit certo.
```

```php
// ERRADO: checar só o right global e ignorar a entidade do item.
// Usuário da Entidade A visualiza/edita item da Entidade B.
if (Session::haveRight(Coisa::$rightname, UPDATE)) {
    $coisa->update($_POST); // faltou canUpdateItem()/checagem de entidade
}
```

```php
// ERRADO: esconder o menu é tratado como controle de acesso.
// Menu oculto não impede acesso direto à URL do front/ajax —
// a checagem OBRIGATÓRIA vive no entry point, sempre.
```

## Boas práticas

- Sempre exponha `getRightsForForm()`/equivalente para que rights customizados apareçam na matriz de Perfis — right sem UI de administração é right que ninguém consegue conceder.
- Prefira os métodos de instância (`canViewItem`, `canCreateItem`, `canUpdateItem`, `canDeleteItem`, `canPurgeItem`) a montar a lógica de entidade manualmente.
- Em massive actions e endpoints AJAX, repita a checagem de right — cada entry point é uma fronteira de confiança própria.
- Documente a semântica de cada bit customizado no `99-Rules.md`/README do plugin.

## Anti-patterns

- Um único right "genérico" para todo o plugin quando existem operações claramente distintas (ver/editar/aprovar) — deveria ser granular desde o início: retrofitar rights depois de haver dados de perfis já quebra migrações.
- Checar right no front mas não no endpoint AJAX correspondente.
- Right verificado no client (JS escondendo botão) sem espelho no servidor.
- Ignorar `is_recursive`/entidade em itemtype que deveria respeitar multi-entidade.

## Checklist

- [ ] Right declarado com `$rightname` próprio e granularidade correta
- [ ] Todo entry point (front + ajax + massive action + API) checa right
- [ ] Métodos `canView/canCreate/canUpdate/canDelete/canPurgeItem` usados em vez de lógica manual
- [ ] Bits customizados documentados e expostos na UI de Perfis
- [ ] Uninstall remove as entradas de `glpi_profilerights` do plugin

## Dicas de performance

- `Session::haveRight` é leitura de sessão, sem custo de banco — não hesite em checar várias vezes por request.
- `canViewItem()` pode disparar consulta de entidade; evite chamá-lo em loop grande sem necessidade (pré-filtre por entidade na query quando listando muitos itens).

## Dicas de segurança

- Right insuficiente deve interromper a execução (`Html::displayRightError()` ou exceção), nunca só "esconder" o resultado mantendo o efeito colateral.
- Right customizado acima de 1024 evita colisão com os níveis padrão do core — nunca reutilize os valores 1/2/4/8/16 para semântica própria.

## Referências

- Developer API — Rights: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/rights.html
- Documentos relacionados: `07-CommonDBTM.md`, `18-Entities.md`, `19-Profiles.md`, `30-Security.md`
