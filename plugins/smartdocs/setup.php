<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Documentação técnica, digitalização inteligente (OCR/QR Code) e
 * gestão de preventivas diretamente dentro do GLPI.
 *
 * Ponto de entrada do plugin: carregado automaticamente pelo GLPI
 * ao detectar a pasta em plugins/.
 * ---------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

define('PLUGIN_SMARTDOCS_VERSION', '1.0.0');
define('PLUGIN_SMARTDOCS_MIN_GLPI', '10.0.0');
define('PLUGIN_SMARTDOCS_MAX_GLPI', '11.99.99');

// CSRF compliance deve estar no escopo global para ativação via CLI.
$PLUGIN_HOOKS['csrf_compliant']['smartdocs'] = true;

/**
 * Metadados do plugin exigidos pelo GLPI.
 *
 * @return array<string, mixed>
 */
function plugin_version_smartdocs(): array
{
    return [
        'name'         => 'SmartDocs',
        'version'      => PLUGIN_SMARTDOCS_VERSION,
        'author'       => 'Luiz Belmonte',
        'license'      => 'GPL-3.0-or-later',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SMARTDOCS_MIN_GLPI,
                'max' => PLUGIN_SMARTDOCS_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Valida pré-requisitos antes da instalação.
 */
function plugin_smartdocs_check_prerequisites(): bool
{
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        echo 'SmartDocs requer PHP 8.2 ou superior. Versão atual: ' . PHP_VERSION;
        return false;
    }

    foreach (['gd', 'mbstring', 'curl', 'json', 'zip'] as $extension) {
        if (!extension_loaded($extension)) {
            echo sprintf('SmartDocs requer a extensão PHP "%s".', $extension);
            return false;
        }
    }

    return true;
}

/**
 * A configuração do plugin é feita no painel após a instalação,
 * portanto não há pré-condição de configuração de arquivos.
 */
function plugin_smartdocs_check_config(bool $verbose = false): bool
{
    return true;
}

/**
 * Inicialização executada em toda requisição quando o plugin está ativo.
 * Registra autoload do Composer, hooks e integrações com o GLPI.
 */
function plugin_smartdocs_init(): void
{
    /** @var array<string, array<string, mixed>> $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['smartdocs'] = true;

    // Autoload PSR-4 via Composer (namespace GlpiPlugin\SmartDocs → src/).
    $composer_autoload = dirname(__FILE__) . '/vendor/autoload.php';
    if (file_exists($composer_autoload)) {
        include_once $composer_autoload;
    }

    // Funções de hook do GLPI (instalação, desinstalação, cron, itens).
    include_once dirname(__FILE__) . '/hook.php';

    if (!class_exists(PermissionManager::class) || !class_exists(MenuHelper::class)) {
        // Dependências ainda não instaladas (composer install pendente).
        return;
    }

    // Menu lateral do GLPI (padrão GLPI 10/11: classe com getMenuContent()).
    $PLUGIN_HOOKS['menu_toadd']['smartdocs'] = [
        'smartdocs' => MenuHelper::class,
    ];

    // Aba de permissões na tela de perfis do GLPI.
    Plugin::registerClass(PermissionManager::class, ['addtabon' => 'Profile']);

    // Scanner OCR: injeta botão de câmera nas telas de ativos
    $PLUGIN_HOOKS['post_show_item']['smartdocs'] = 'plugin_smartdocs_postShowItem';

    // Módulos JS do plugin (formato ES module)
    $PLUGIN_HOOKS['add_javascript']['smartdocs'] = [
        'js/scanner.bundle.js',
    ];
}
