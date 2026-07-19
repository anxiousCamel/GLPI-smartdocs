# 11 — Massive Actions

## Objetivo

Declarar ações em massa (massive actions) próprias — tanto para o itemtype do plugin quanto para itemtypes do core — usando o contrato de três métodos que o GLPI espera, sem reimplementar a UI de seleção/lote que o core já resolve.

## Conceitos

- **Massive action = ação aplicada a N itens selecionados numa lista** (a caixinha "Ações" que aparece ao marcar checkboxes numa lista do GLPI). Por padrão o core já oferece "Editar" (campos das search options sem `massiveaction => false`) e "Lixeira/Excluir".
- **Contrato de três pontas:**
  1. **Declaração**: hook `Hooks::USE_MASSIVE_ACTION = true` no `plugin_init`, mais a função mágica `plugin_<chave>_MassiveActions($itemtype)` (para adicionar ação a itemtype do CORE) — OU, para o itemtype do PRÓPRIO plugin, o método estático `getSpecificMassiveActions()` na própria classe.
  2. **Sub-formulário**: `showMassiveActionsSubForm(MassiveAction $ma)` — desenha os campos extras que a ação precisa (ex.: "para qual status mover").
  3. **Processamento**: `processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)` — executa a ação para cada id selecionado, reportando resultado item a item via `$ma->itemDone(...)`.
- **Chave de ação** = `NomeDaClasse` + `MassiveAction::CLASS_ACTION_SEPARATOR` + `chave_da_acao` (ex.: `PluginMeupluginCoisa::MassiveAction::CLASS_ACTION_SEPARATOR . 'aprovar'`), usada para rotear qual `case` do switch em `showMassiveActionsSubForm`/`processMassiveActionsForOneItemtype` deve tratar aquela ação.

## Funcionamento interno

Ao abrir uma lista, o core monta o menu de ações chamando (para cada itemtype elegível) tanto as ações padrão quanto, se `USE_MASSIVE_ACTION` estiver ativo, a função mágica `plugin_<chave>_MassiveActions($itemtype)` — que devolve um array `['Classe' . SEP . 'chave' => 'Rótulo']`. Ao escolher uma ação e confirmar a seleção, o core abre um modal chamando `showMassiveActionsSubForm($ma)` na classe declarada (permite pedir dados extras). Ao submeter, `processMassiveActionsForOneItemtype($ma, $item, $ids)` roda; para CADA id, sua implementação decide sucesso/falha e reporta com `$ma->itemDone($itemtype, $id, MassiveAction::ACTION_OK|ACTION_KO|ACTION_KO_ALL_ACTIONS)`, permitindo ao core exibir o relatório final "N sucesso, M falha" automaticamente.

Sempre chame `parent::showMassiveActionsSubForm($ma)`/`parent::processMassiveActionsForOneItemtype(...)` no `default` do seu switch — preserva as ações padrão do core (Editar, Lixeira) que sua sobrescrita não deve quebrar.

## Fluxograma

```
Lista → seleciona itens → escolhe ação no menu
      │
      ▼
plugin_<k>_MassiveActions($itemtype)   ← declara ações disponíveis (só p/ itemtype do CORE)
   OU Classe::getSpecificMassiveActions() ← itemtype do próprio plugin
      │
      ▼
[usuário confirma] → modal
      │
      ▼
Classe::showMassiveActionsSubForm($ma)   ← campos extras (ex.: valor a aplicar)
      │
      ▼
[usuário submete]
      │
      ▼
Classe::processMassiveActionsForOneItemtype($ma, $item, $ids)
      │  para cada $id: $item->getFromDB($id); regra de negócio; $ma->itemDone(...)
      ▼
Relatório de sucesso/falha (automático, do core)
```

## Exemplos corretos

### Ação extra no PRÓPRIO itemtype do plugin

```php
<?php

declare(strict_types=1);

namespace GlpiPlugin\Meuplugin;

use CommonDBTM;
use Html;
use MassiveAction;
use Session;

class Coisa extends CommonDBTM
{
    public static $rightname = 'plugin_meuplugin_coisa';
    public const APPROVE = 128;

    /**
     * Declara ações extras para o próprio itemtype.
     */
    public static function getSpecificMassiveActions($checkitem = null): array
    {
        $actions = parent::getSpecificMassiveActions($checkitem);

        if (Session::haveRight(self::$rightname, self::APPROVE)) {
            $key = self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'aprovar';
            $actions[$key] = __('Aprovar selecionados', 'meuplugin');
        }

        return $actions;
    }

    /**
     * Sub-formulário exibido no modal de confirmação.
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'aprovar':
                echo __('Comentário da aprovação (opcional):', 'meuplugin');
                echo Html::input('comentario');
                echo Html::submit(__('Aplicar', 'meuplugin'), ['name' => 'massiveaction']);
                return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * Processa a ação para cada item selecionado.
     */
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ): void {
        switch ($ma->getAction()) {
            case 'aprovar':
                $input = $ma->getInput();

                foreach ($ids as $id) {
                    if ($item->getFromDB($id)
                        && $item->update([
                            'id'     => $id,
                            'status' => 'aprovado',
                            'comentario_aprovacao' => $input['comentario'] ?? '',
                        ])
                    ) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        $ma->addMessage(__('Falha ao aprovar o item.', 'meuplugin'));
                    }
                }
                return;
        }

        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }
}
```

### Ação extra em itemtype do CORE (ex.: `Computer`)

```php
<?php
// hook.php

use GlpiPlugin\Meuplugin\ComputerExtra;
use MassiveAction;
use Computer;

/**
 * Declara ação extra na lista de Computer.
 */
function plugin_meuplugin_MassiveActions(string $itemtype): array
{
    $actions = [];

    if ($itemtype === Computer::class) {
        $key = ComputerExtra::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'sincronizar';
        $actions[$key] = __('Sincronizar com Meuplugin', 'meuplugin');
    }

    return $actions;
}
```

```php
// Em GlpiPlugin\Meuplugin\ComputerExtra (a classe declarada na ação acima)

public static function showMassiveActionsSubForm(MassiveAction $ma)
{
    switch ($ma->getAction()) {
        case 'sincronizar':
            echo Html::submit(__('Sincronizar agora', 'meuplugin'), ['name' => 'massiveaction']);
            return true;
    }
    return parent::showMassiveActionsSubForm($ma);
}

public static function processMassiveActionsForOneItemtype(MassiveAction $ma, \CommonDBTM $item, array $ids)
{
    switch ($ma->getAction()) {
        case 'sincronizar':
            foreach ($ids as $id) {
                // $item aqui é um Computer; $id é o id do Computer selecionado
                $ok = self::sincronizarComputador((int) $id);
                $ma->itemDone($item->getType(), $id, $ok ? MassiveAction::ACTION_OK : MassiveAction::ACTION_KO);
            }
            return;
    }
    parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
}
```

## Exemplos incorretos

```php
// ERRADO: processMassiveActionsForOneItemtype sem checar right por item —
// a lista já filtrou por right de visualização, mas a AÇÃO pode exigir
// um right diferente (ex.: APPROVE) que não foi checado aqui.
public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
{
    switch ($ma->getAction()) {
        case 'aprovar':
            foreach ($ids as $id) {
                $item->getFromDB($id);
                $item->update(['id' => $id, 'status' => 'aprovado']); // sem checar APPROVE
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
            }
            return;
    }
}
```

```php
// ERRADO: não chamar parent::processMassiveActionsForOneItemtype no
// switch — quebra silenciosamente as ações padrão do core (Editar,
// Lixeira) para esse itemtype quando o switch não bate em nenhum case.
```

```php
// ERRADO: reportar sucesso mesmo quando a operação falhou. O usuário
// perde a única fonte de verdade de que algo deu errado no lote.
$item->update([...]);
$ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK); // sem checar retorno
```

## Boas práticas

- Sempre delegue ao `parent::` no `default`/fora do seu `case` para preservar as ações nativas.
- Checagem de right específica da ação (não apenas do itemtype) dentro de `processMassiveActionsForOneItemtype`, já que a ação pode exigir um nível diferente do de visualização.
- Use `$ma->itemDone()` item a item — nunca decida "tudo ou nada" para o lote inteiro quando alguns itens podem falhar independentemente.
- Mensagens de erro específicas via `$ma->addMessage()` ajudam o usuário a entender o que falhou sem vasculhar logs.

## Anti-patterns

- Processar o lote inteiro numa única query/transação sem reportar item a item (perde granularidade do relatório).
- Reaproveitar a mesma chave de ação (`chave_da_acao`) entre itemtypes diferentes sem necessidade — dificulta rastrear em qual contexto a ação está rodando.
- Ignorar `MassiveAction::ACTION_KO_ALL_ACTIONS` quando um erro deveria interromper o restante do lote (ex.: falha de pré-condição global).

## Checklist

- [ ] `Hooks::USE_MASSIVE_ACTION` registrado quando aplicável
- [ ] Ação exposta via `getSpecificMassiveActions` (itemtype próprio) ou `plugin_<k>_MassiveActions` (itemtype do core)
- [ ] `showMassiveActionsSubForm` e `processMassiveActionsForOneItemtype` chamam `parent::` fora do próprio case
- [ ] Right específico da ação checado dentro do processamento
- [ ] `$ma->itemDone()` chamado para cada id, refletindo o resultado real

## Dicas de performance

- Para lotes grandes, evite recarregar dados relacionados a cada iteração se puderem ser buscados uma vez fora do loop (ex.: configuração global do plugin).
- Prefira `update()`/`add()` do itemtype (mantém hooks e histórico) a `$DB->update` direto, a menos que o volume exija otimização explícita — nesse caso, documente a exceção.

## Dicas de segurança

- Right de visualização (que permitiu o item aparecer na lista) não é o mesmo right da ação — sempre confirme o right específico dentro do processamento.
- Nunca confie apenas nos `$ids` recebidos como confiáveis — sempre releia o item via `getFromDB` e valide seu estado antes de agir.

## Referências

- Massive Actions oficial: https://glpi-developer-documentation.readthedocs.io/en/master/devapi/massiveactions.html
- Plugin tutorial (exemplo completo): https://glpi-developer-documentation.readthedocs.io/en/master/plugins/tutorial.html
- Guia de plugins — massive actions: https://glpi-developer-documentation.readthedocs.io/en/master/plugins/massiveactions.html
- Documentos relacionados: `03-Hooks.md`, `04-Rights.md`, `10-Search.md`
