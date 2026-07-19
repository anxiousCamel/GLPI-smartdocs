<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Funções de hook do GLPI. Este arquivo é incluído automaticamente
 * pelo GLPI (Plugin::includeHook) e pelo plugin_smartdocs_init().
 *
 * Regra de arquitetura: nenhuma lógica de negócio aqui — os hooks
 * apenas delegam para as camadas do plugin (src/).
 * ---------------------------------------------------------------------
 */

/**
 * Instalação: cria as tabelas do plugin e as configurações padrão.
 */
function plugin_smartdocs_install(): bool
{
    include_once dirname(__FILE__) . '/install/install.php';

    return plugin_smartdocs_run_install();
}

/**
 * Desinstalação: remove as tabelas e dados de integração do plugin.
 */
function plugin_smartdocs_uninstall(): bool
{
    include_once dirname(__FILE__) . '/install/uninstall.php';

    return plugin_smartdocs_run_uninstall();
}

/**
 * Conteúdo do menu lateral do GLPI.
 *
 * No GLPI 10/11 o menu é resolvido a partir das classes registradas em
 * $PLUGIN_HOOKS['menu_toadd'] (ver setup.php), que devem expor o método
 * estático getMenuContent(). Esta função é mantida como fachada de
 * compatibilidade e delega para o MenuHelper.
 *
 * @return array<string, mixed>
 */
function plugin_smartdocs_getMenuContent(): array
{
    /** @var array<string, mixed> $menu */
    $menu = \GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::getMenuContent();

    return $menu;
}