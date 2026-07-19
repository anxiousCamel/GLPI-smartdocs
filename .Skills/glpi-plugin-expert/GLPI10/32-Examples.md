# 32 — Exemplos Completos Comentados

## Objetivo

Reunir num só lugar exemplos de ponta a ponta que combinam múltiplos documentos desta KB (estrutura + rights + Search + massive action + notificação), servindo como referência de "como tudo se encaixa" além dos exemplos isolados de cada documento técnico.

## Exemplo completo: itemtype "Coisa" com ciclo de vida pleno

Este exemplo consolida trechos já apresentados em `01`, `02`, `04`, `07`, `10`, `11`, `16` e `19`, mostrando como um itemtype de plugin real combina todas as peças.

### Estrutura de arquivos

```
plugins/meuplugin/
├── setup.php
├── hook.php
├── composer.json
├── src/
│   ├── Coisa.php
│   ├── Profile.php
│   └── NotificationTargetCoisa.php
├── front/
│   ├── coisa.php
│   └── coisa.form.php
└── templates/
    └── coisa.form.html.twig
```

### setup.php (registro completo)

```php
<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Meuplugin\Coisa;
use GlpiPlugin\Meuplugin\Profile;

define('MEUPLUGIN_VERSION', '1.0.0');

function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['meuplugin'] = true;
    $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['meuplugin'] = true;

    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

    if (Session::haveRight(Coisa::$rightname, READ)) {
        $PLUGIN_HOOKS['menu_toadd']['meuplugin'] = ['assets' => Coisa::class];
    }
}

function plugin_version_meuplugin(): array
{
    return [
        'name'         => __('Meu Plugin', 'meuplugin'),
        'version'      => MEUPLUGIN_VERSION,
        'requirements' => ['glpi' => ['min' => '10.0.0', 'max' => '10.0.99']],
    ];
}
```

### src/Coisa.php (itemtype com right customizado, Search e Massive Action)

Ver os trechos completos em:
- `04-Rights.md` (right customizado `APPROVE` + `getRights()`)
- `07-CommonDBTM.md` (`prepareInputForAdd`, `post_addItem`, `showForm` via Twig)
- `10-Search.md` (`getSearchOptions`)
- `11-MassiveActions.md` (`getSpecificMassiveActions`, `showMassiveActionsSubForm`, `processMassiveActionsForOneItemtype`)

A classe final combina todos esses métodos numa única definição de `Coisa extends CommonDBTM` — nenhum é opcional para um itemtype "completo" de negócio, mas cada um pode ser adicionado incrementalmente conforme a funcionalidade evolui.

### Fluxo de ponta a ponta

```
1. Usuário acessa front/coisa.php → Session::checkRight → Search::show(Coisa::class)
   (usa getSearchOptions() para montar colunas)

2. Usuário abre front/coisa.form.php?id=5 → Coisa::showForm()
   → TemplateRenderer renderiza @meuplugin/coisa.form.html.twig
   → template estende generic_show_form.html.twig

3. Usuário seleciona várias Coisas na lista → escolhe "Aprovar selecionados"
   → Coisa::showMassiveActionsSubForm() (modal)
   → Coisa::processMassiveActionsForOneItemtype()
       → para cada id: update(status=aprovado)
       → NotificationEvent::raiseEvent(EVENTO_APROVADA, $coisa, [...])
           → NotificationTargetCoisa resolve destinatários e tags
           → e-mail enfileirado conforme Notification configurada pelo admin

4. Administrador acessa Perfis > [Perfil] > aba "Meu Plugin"
   → Profile::showFormMeuplugin() desenha matriz de rights
   → inclui o nível customizado APPROVE
```

## Exemplo completo: dropdown auxiliar + FK + coluna extra na lista do core

Combina `09-CommonDropdown.md` (dicionário `CategoriaCoisa`) + `10-Search.md` (exposição de coluna em `Computer` via `getAddSearchOptionsNew`) — útil como padrão de "meu plugin adiciona um dado próprio a um itemtype nativo, visível na lista dele".

## Boas práticas ao compor exemplos

- Um itemtype de negócio real raramente usa só UM documento desta KB — a composição típica é: `CommonDBTM` (07) + `Rights` (04) + `Search` (10) + opcionalmente `MassiveActions` (11), `Notifications` (16), `Profiles` (19).
- Ao adicionar uma feature nova a um itemtype existente, releia o documento correspondente ANTES de escrever — a composição correta depende de cada peça seguir seu próprio contrato (ex.: `getSpecificMassiveActions` chamando `parent::`).

## Referências

- Cada trecho deste documento é uma composição dos exemplos completos já apresentados em `01`, `02`, `04`, `07`, `09`, `10`, `11`, `16`, `19` — consulte o documento de origem para o código completo comentado linha a linha.
- Ver também `Templates/PluginSkeleton/` para um esqueleto de projeto pronto para copiar.
