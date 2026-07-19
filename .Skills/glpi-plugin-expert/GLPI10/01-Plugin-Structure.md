# 01 — Estrutura de um Plugin

## Objetivo

Definir a anatomia completa de um plugin GLPI 10.x profissional: arquivos obrigatórios, layout de pastas, o que vai em `setup.php` vs `hook.php`, e as funções que o core exige.

## Conceitos

- **Diretório = identidade.** O plugin vive em `plugins/<chave>/` (ou `marketplace/<chave>/`). A `<chave>` (minúscula, sem hífen) é usada em TODOS os contratos: `plugin_init_<chave>`, `plugin_version_<chave>`, índices de `$PLUGIN_HOOKS`, nomes de tabela `glpi_plugin_<chave>_*`, rights `plugin_<chave>_*`. Divergência de chave = plugin invisível ou quebrado.
- **Dois arquivos são obrigatórios:** `setup.php` (metadados + registro de hooks; carregado para TODO plugin listado, ativo ou não) e `hook.php` (funções de instalação/desinstalação e callbacks `plugin_<chave>_*`; carregado sob demanda).
- **O resto é convenção**, mas convenção forte: `src/` (classes PSR-4), `front/` (páginas), `ajax/` (endpoints), `templates/` (Twig), `locales/`, `sql/` (opcional), `tools/`, `vendor/`.

## Funcionamento interno

Ciclo de descoberta: a tela *Configurar > Plugins* varre os diretórios, inclui cada `setup.php` e chama `plugin_version_<chave>()` para obter nome/versão/autor/requisitos. Ao ativar, o core valida `plugin_<chave>_check_prerequisites()` e `plugin_<chave>_check_config()`; ao instalar, chama `plugin_<chave>_install()` de `hook.php`. Em toda request subsequente, apenas `setup.php` + `plugin_init_<chave>()` dos plugins **ativos** são executados.

### Layout recomendado

```
plugins/meuplugin/
├── setup.php                  # obrigatório — metadados + init/hooks
├── hook.php                   # obrigatório — install/uninstall + callbacks
├── composer.json              # PSR-4 + deps PHP
├── vendor/                    # gerado (commitar? ver 23-Composer.md)
├── src/                       # classes: GlpiPlugin\Meuplugin\*
│   ├── Coisa.php
│   ├── Config.php
│   └── Profile.php
├── front/
│   ├── coisa.php              # lista (Search)
│   └── coisa.form.php         # form CRUD
├── ajax/
│   └── atualizaCampo.php
├── templates/
│   └── coisa_form.html.twig
├── locales/
│   ├── meuplugin.pot
│   └── pt_BR.po / pt_BR.mo
├── public/                    # assets web (obrigatório no 11; adote já no 10)
│   └── css/meuplugin.css
├── tools/                     # scripts de dev/release (não distribuir)
├── README.md
├── CHANGELOG.md
└── LICENSE
```

## Fluxograma — carga do plugin

```
Tela de Plugins                     Toda request (plugin ativo)
──────────────                      ───────────────────────────
varre plugins/*/setup.php           inc/includes.php
  │                                   │
  ▼                                   ▼
plugin_version_meuplugin()          include setup.php
  │ (metadados, faixa de versão)      │
  ▼                                   ▼
[Instalar] → hook.php:              plugin_init_meuplugin()
  plugin_meuplugin_install()          │ popula $PLUGIN_HOOKS
[Ativar] → check_prerequisites        │ registra classes/abas
           + check_config             ▼
                                    core despacha hooks durante a request
```

## Exemplos corretos

### setup.php mínimo profissional

```php
<?php

/**
 * @file setup.php
 * Metadados e inicialização do plugin Meuplugin.
 * Carregado pelo core em toda request para plugins ativos.
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Meuplugin\Coisa;
use GlpiPlugin\Meuplugin\Profile;

define('MEUPLUGIN_VERSION', '1.0.0');

// Faixa de compatibilidade declarada e testada
define('MEUPLUGIN_MIN_GLPI', '10.0.0');
define('MEUPLUGIN_MAX_GLPI', '10.0.99');

/**
 * Registra hooks e integrações. Roda em TODA request — só registro, zero trabalho.
 */
function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS;

    // Obrigatório: declara que todos os forms POST do plugin enviam token CSRF
    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['meuplugin'] = true;

    if (!Plugin::isPluginActive('meuplugin')) {
        return;
    }

    // Aba do plugin no perfil (rights)
    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

    // Menu apenas para quem tem direito
    if (Session::haveRight(Coisa::$rightname, READ)) {
        $PLUGIN_HOOKS['menu_toadd']['meuplugin'] = [
            'assets' => Coisa::class,
        ];
    }

    // CSS/JS do plugin
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['meuplugin'] = 'public/css/meuplugin.css';
}

/**
 * Metadados exibidos na tela de plugins e usados na validação de versão.
 *
 * @return array{name: string, version: string, author: string, license: string,
 *               homepage: string, requirements: array}
 */
function plugin_version_meuplugin(): array
{
    return [
        'name'         => __('Meu Plugin', 'meuplugin'),
        'version'      => MEUPLUGIN_VERSION,
        'author'       => 'Vini',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => 'https://github.com/anxiousCamel/meuplugin',
        'requirements' => [
            'glpi' => [
                'min' => MEUPLUGIN_MIN_GLPI,
                'max' => MEUPLUGIN_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.1',
            ],
        ],
    ];
}

/**
 * Pré-requisitos adicionais (extensões, plugins dependentes...).
 */
function plugin_meuplugin_check_prerequisites(): bool
{
    return true;
}

/**
 * Valida configuração antes de ativar.
 */
function plugin_meuplugin_check_config(bool $verbose = false): bool
{
    return true;
}
```

### hook.php mínimo

```php
<?php

/**
 * @file hook.php
 * Instalação, desinstalação e callbacks plugin_meuplugin_*.
 */

use GlpiPlugin\Meuplugin\Coisa;
use GlpiPlugin\Meuplugin\Profile;

/**
 * Instala/atualiza o plugin. Idempotente: decide o que fazer olhando o estado real.
 */
function plugin_meuplugin_install(): bool
{
    $migration = new Migration(MEUPLUGIN_VERSION);

    Coisa::install($migration);    // cada classe cuida da própria tabela
    Profile::install($migration);  // e dos próprios rights

    $migration->executeMigration();

    return true;
}

/**
 * Remove TUDO que o plugin criou: tabelas, rights, crons, notificações,
 * display preferences. Desinstalação limpa é requisito, não cortesia.
 */
function plugin_meuplugin_uninstall(): bool
{
    $migration = new Migration(MEUPLUGIN_VERSION);

    Coisa::uninstall($migration);
    Profile::uninstall($migration);

    $migration->executeMigration();

    return true;
}
```

## Exemplos incorretos

```php
// ERRADO: trabalho pesado no init — roda em toda request do GLPI inteiro.
function plugin_init_meuplugin(): void
{
    global $PLUGIN_HOOKS, $DB;
    $stats = $DB->request(['FROM' => 'glpi_plugin_meuplugin_coisas']); // NÃO
    foreach ($stats as $row) { /* ... */ }
}
```

```php
// ERRADO: chave inconsistente. Diretório "meu-plugin", funções "meuplugin",
// hooks "meu_plugin". O core nunca vai encontrar nada disso.
```

```php
// ERRADO: esquecer csrf_compliant. No GLPI 10, plugin sem essa flag tem
// seus POSTs bloqueados/avisados; forms sem _glpi_csrf_token são rejeitados.
```

## Boas práticas

- `setup.php` só declara; `hook.php` e classes executam.
- Cada itemtype implementa seus próprios `install(Migration $m)`/`uninstall(Migration $m)` estáticos — `hook.php` vira um orquestrador de 10 linhas.
- Versione a faixa GLPI honestamente (`min`/`max` testados). Faixa aberta demais = bug report de versão que você nunca testou.
- Adote `public/` para assets desde já: é opcional no 10 e obrigatório no 11 (URL sem o segmento `/public`).
- `locales/` desde o dia 1; extrair strings depois é retrabalho.

## Anti-patterns

- Lógica de negócio em `front/*.php` (scripts são controllers finos: right → ação em classe → redirect).
- `install()` que executa `.sql` gigante em vez de usar `Migration` (perde idempotência e upgrade incremental — ver `06-Migrations.md`).
- Copiar o plugin `example` como base (a própria equipe do GLPI desaconselha; use o `plugin-template`).
- Distribuir `tools/`, `.git`, testes e afins no pacote de release.

## Checklist

- [ ] Chave do plugin idêntica em: diretório, funções, hooks, tabelas, rights
- [ ] `setup.php`: version info completa + faixa GLPI/PHP + `csrf_compliant`
- [ ] `plugin_init` barato e protegido por `isPluginActive`
- [ ] `hook.php`: install idempotente + uninstall que remove tudo
- [ ] `src/` PSR-4 com namespace `GlpiPlugin\Meuplugin\`
- [ ] Assets em `public/`, strings em `__('...', 'meuplugin')`

## Dicas de performance

- Registre CSS/JS só nas páginas que precisam (hooks aceitam condicionar por contexto) em vez de global.
- `Plugin::isPluginActive()` é barato (cache); use-o para curto-circuitar o init.

## Dicas de segurança

- `csrf_compliant` é obrigatório; sem ele o plugin inteiro é tratado como legado inseguro.
- Menu registrado só após `Session::haveRight` evita revelar features a quem não tem acesso — mas a checagem REAL fica nos entry points (menu escondido não é controle de acesso).

## Referências

- Requirements oficiais: https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/requirements.html
- Template oficial: https://github.com/glpi-project/plugin-template
- Documentos relacionados: `02-Lifecycle.md`, `03-Hooks.md`, `23-Composer.md`, `24-Namespaces.md`, `Templates/PluginSkeleton/`
