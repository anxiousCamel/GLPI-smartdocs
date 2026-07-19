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
 * Exibe aviso no dashboard quando as dependências do Composer não estão instaladas.
 * Chamado via DISPLAY_CENTRAL hook — output HTML direto na página central do GLPI.
 */
function plugin_smartdocs_warn_missing_deps(): void
{
    $plugin_dir = Plugin::getPhpDir('smartdocs');

    echo '<div class="alert alert-important alert-danger d-flex align-items-start mx-3 my-2" role="alert">';
    echo '<i class="fas fa-exclamation-triangle me-2 flex-shrink-0 mt-1"></i>';
    echo '<div class="flex-fill">';
    echo '<strong>SmartDocs</strong> — ' . __('Dependências PHP não instaladas', 'smartdocs') . '<br>';
    echo '<p class="mb-2">';
    echo __('O plugin SmartDocs requer as dependências do Composer para funcionar. Execute no terminal:', 'smartdocs');
    echo '</p>';
    echo '<pre class="bg-dark text-light p-2 rounded mb-2"><code>';
    echo 'cd ' . htmlescape($plugin_dir) . "\n";
    echo 'composer install --no-dev --optimize-autoloader';
    echo '</code></pre>';
    echo '<p class="mb-0">';
    echo sprintf(
        __('Depois recarregue o GLPI e acesse %s para continuar.', 'smartdocs'),
        '<a href="' . Plugin::getWebDir('smartdocs', false) . '/front/config.setup.php" class="alert-link">' . __('Configuração do SmartDocs', 'smartdocs') . '</a>'
    );
    echo '</p>';
    echo '</div>';
    echo '</div>';
}

/**
 * Stub necessário para o hook post_show_tab registrado quando deps estão ausentes.
 *
 * @param array<string, mixed> $params
 */
function plugin_smartdocs_warn_missing_deps_tab(array $params): void
{
    // Sem ação — hook registrado apenas para compatibilidade.
}

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