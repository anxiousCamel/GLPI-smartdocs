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

/**
 * Informações do CronTask do plugin.
 *
 * @return array<int, array<string, mixed>>
 */
function plugin_smartdocs_cronInfo(): array
{
    return [
        'SmartDocsPdfQueue' => [
            'description' => __('Processa jobs pendentes de geração de PDF', 'smartdocs'),
            'parameter'   => __('Número máximo de jobs por execução', 'smartdocs'),
        ],
    ];
}

/**
 * Processa a fila de geração de PDF via CronTask.
 *
 * @param \CronTask $task Instância do CronTask
 *
 * @return int Número de jobs processados
 */
function plugin_smartdocs_cronProcessPdfQueue(\CronTask $task): int
{
    return \GlpiPlugin\SmartDocs\PdfEngine\PdfCronTask::cronProcessPdfQueue($task);
}

/**
 * Hook POST_SHOW_ITEM: injeta botão de scanner OCR nos formulários de ativos.
 *
 * @param array<string, mixed> $params Parâmetros do hook
 */
function plugin_smartdocs_postShowItem(array $params): void
{
    /** @var \CommonDBTM $item */
    $item = $params['item'] ?? null;

    if ($item === null) {
        return;
    }

    $assetTypes = [
        'Computer',
        'Monitor',
        'NetworkEquipment',
        'Peripheral',
        'Printer',
        'Phone',
    ];

    if (!in_array($item::class, $assetTypes, true)) {
        return;
    }

    // Injeta apenas na aba principal (formulário de edição)
    if (($params['options']['tabnum'] ?? 0) !== 0) {
        return;
    }

    if (!\Session::haveRight(\GlpiPlugin\SmartDocs\Permissions\PermissionManager::RIGHT_NAME, \GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_OCR_USE)) {
        return;
    }

    $ajaxUrl = \Plugin::getWebDir('smartdocs', false) . '/ajax/upload-scan.php';

    echo '<div id="smartdocs-scanner-root" ' .
         'data-ajax-url="' . htmlescape($ajaxUrl) . '" ' .
         'data-itemtype="' . htmlescape($item::class) . '" ' .
         'data-items-id="' . (int) ($item->fields['id'] ?? 0) . '">' .
         '</div>';
}
