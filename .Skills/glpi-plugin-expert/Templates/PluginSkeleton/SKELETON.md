# Template: PluginSkeleton

Esqueleto mínimo e completo de um plugin GLPI 10.x/11.x-ready, seguindo todas as regras de `GLPI10/99-Rules.md`. Copie esta estrutura, substitua `meuplugin`/`Meuplugin`/`MEUPLUGIN` pelo nome real do plugin em todos os arquivos e caminhos.

## Estrutura

```
plugins/meuplugin/
├── setup.php
├── hook.php
├── composer.json
├── README.md
├── src/
│   └── Coisa.php
├── front/
│   ├── coisa.php
│   └── coisa.form.php
├── templates/
│   └── coisa.form.html.twig
├── public/
│   └── css/meuplugin.css
└── locales/
    └── meuplugin.pot
```

## composer.json

```json
{
    "name": "seuvendor/meuplugin",
    "type": "glpi-plugin",
    "license": "GPL-3.0-or-later",
    "require": { "php": ">=8.1" },
    "autoload": {
        "psr-4": { "GlpiPlugin\\Meuplugin\\": "src/" }
    }
}
```

## setup.php

```php
<?php

declare(strict_types=1);

use Glpi\Plugin\Hooks;
use GlpiPlugin\Meuplugin\Coisa;

define('MEUPLUGIN_VERSION', '1.0.0');
define('MEUPLUGIN_MIN_GLPI', '10.0.0');
define('MEUPLUGIN_MAX_GLPI', '10.0.99');

/**
 * Registra hooks e integrações. Roda em toda request — só registro, zero trabalho.
 */
function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['meuplugin'] = true;

    if (!Plugin::isPluginActive('meuplugin')) {
        return;
    }

    if (Session::haveRight(Coisa::$rightname, READ)) {
        $PLUGIN_HOOKS['menu_toadd']['meuplugin'] = ['tools' => Coisa::class];
    }

    $PLUGIN_HOOKS[Hooks::ADD_CSS]['meuplugin'] = 'public/css/meuplugin.css';
}

/**
 * Metadados exibidos na tela de plugins.
 */
function plugin_version_meuplugin(): array
{
    return [
        'name'         => __('Meu Plugin', 'meuplugin'),
        'version'      => MEUPLUGIN_VERSION,
        'author'       => 'Seu Nome',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/seuvendor/meuplugin',
        'requirements' => [
            'glpi' => ['min' => MEUPLUGIN_MIN_GLPI, 'max' => MEUPLUGIN_MAX_GLPI],
            'php'  => ['min' => '8.1'],
        ],
    ];
}

function plugin_meuplugin_check_prerequisites(): bool
{
    return true;
}

function plugin_meuplugin_check_config(bool $verbose = false): bool
{
    return true;
}
```

## hook.php

```php
<?php

declare(strict_types=1);

use GlpiPlugin\Meuplugin\Coisa;

/**
 * Instala/atualiza o plugin. Idempotente — roda também em updates.
 */
function plugin_meuplugin_install(): bool
{
    $migration = new Migration(MEUPLUGIN_VERSION);

    Coisa::install($migration);
    ProfileRight::addProfileRights(['plugin_meuplugin_coisa']);

    $migration->executeMigration();

    return true;
}

/**
 * Remove tudo que o plugin criou.
 */
function plugin_meuplugin_uninstall(): bool
{
    $migration = new Migration(MEUPLUGIN_VERSION);

    Coisa::uninstall($migration);
    ProfileRight::deleteProfileRights(['plugin_meuplugin_coisa']);

    $migration->executeMigration();

    return true;
}
```

## src/Coisa.php

Ver `Templates/CommonDBTM/SKELETON.md` para o itemtype completo — é o mesmo arquivo, colado aqui neste `src/`.

## front/coisa.php (lista)

```php
<?php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

Session::checkRight(Coisa::$rightname, READ);

Html::header(Coisa::getTypeName(2), $_SERVER['PHP_SELF'], 'plugins', Coisa::class);
Search::show(Coisa::class);
Html::footer();
```

## front/coisa.form.php (CRUD)

```php
<?php

use GlpiPlugin\Meuplugin\Coisa;

include('../../../inc/includes.php');

$coisa = new Coisa();

if (isset($_POST['add'])) {
    Session::checkRight(Coisa::$rightname, CREATE);
    $coisa->check(-1, CREATE, $_POST);
    $newId = $coisa->add($_POST);
    Html::redirect($coisa->getFormURLWithID($newId));
} elseif (isset($_POST['update'])) {
    Session::checkRight(Coisa::$rightname, UPDATE);
    $coisa->check($_POST['id'], UPDATE);
    $coisa->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    Session::checkRight(Coisa::$rightname, DELETE);
    $coisa->check($_POST['id'], DELETE);
    $coisa->delete($_POST);
    Html::redirect(Coisa::getSearchURL());
} else {
    Html::header(Coisa::getTypeName(1), $_SERVER['PHP_SELF'], 'plugins', Coisa::class);
    $coisa->display(['id' => (int) ($_GET['id'] ?? 0)]);
    Html::footer();
}
```

## templates/coisa.form.html.twig

```twig
{% extends "generic_show_form.html.twig" %}
{% import "components/form/fields_macros.html.twig" as fields %}

{% block more_fields %}
    {{ fields.textField('name', item.fields['name'], __('Nome', 'meuplugin')) }}
{% endblock %}
```

## Checklist pós-cópia

- [ ] Substituir `meuplugin`/`Meuplugin`/`MEUPLUGIN` em TODOS os arquivos (incluindo nomes de arquivo se aplicável)
- [ ] Ajustar `composer.json` (`name`, autoload namespace)
- [ ] Rodar `composer install` localmente para gerar `vendor/` de dev
- [ ] Ler `GLPI10/99-Rules.md` e `Checklists/PluginChecklist.md` antes de expandir
