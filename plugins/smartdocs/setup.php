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

use Glpi\Plugin\Hooks;
use GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

// Polyfills de funções globais ausentes no GLPI 10.x (ex: htmlescape()).
// Precisa carregar antes de qualquer código do plugin rodar.
require_once dirname(__FILE__) . '/src/GlpiCompat/functions.php';

define('PLUGIN_SMARTDOCS_VERSION', '1.0.0');
define('PLUGIN_SMARTDOCS_MIN_GLPI', '10.0.0');
define('PLUGIN_SMARTDOCS_MAX_GLPI', '11.99.99');

// CSRF compliance: declarar com global para garantir escopo correto quando
// setup.php é incluído dentro de um método de classe pelo GLPI.
global $PLUGIN_HOOKS;
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
 * Verifica se o plugin está pronto para funcionar.
 * Retorna false quando as dependências do Composer não estão instaladas,
 * fazendo o GLPI marcar o plugin como "Precisa de configuração".
 */
function plugin_smartdocs_check_config(bool $verbose = false): bool
{
    if (!file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
        if ($verbose) {
            $plugin_dir = dirname(__FILE__);
            $help_url   = '/plugins/smartdocs/front/config.setup.php';

            echo '<div style="max-width: 640px; margin: 1rem 0;">';
            echo '<h4 style="color: #dc3545;"><i class="fas fa-exclamation-triangle me-2"></i>'
                . __('SmartDocs — Dependências não instaladas', 'smartdocs')
                . '</h4>';
            echo '<p>'
                . __('O plugin SmartDocs precisa das dependências do Composer para funcionar. Execute o comando abaixo no terminal do servidor:', 'smartdocs')
                . '</p>';
            echo '<pre style="background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.375rem; font-family: monospace; margin: 0.75rem 0;">';
            echo 'cd ' . htmlescape($plugin_dir) . "\n";
            echo 'composer install --no-dev --optimize-autoloader';
            echo '</pre>';
            echo '<p>'
                . __('Depois execute o comando, recarregue o GLPI e clique em Ativar novamente.', 'smartdocs')
                . '</p>';
            echo '<p style="margin-top: 1rem;">';
            echo '<a href="' . htmlescape($help_url) . '" class="btn btn-sm btn-primary" style="margin-right: 0.5rem;">';
            echo '<i class="fas fa-book me-1"></i>' . __('Ver guia completo de instalação', 'smartdocs');
            echo '</a>';
            echo '<a href="' . htmlescape($help_url) . '" class="btn btn-sm btn-outline-secondary">';
            echo '<i class="fas fa-stethoscope me-1"></i>' . __('Página de diagnóstico', 'smartdocs');
            echo '</a>';
            echo '</p>';
            echo '</div>';
        }
        return false;
    }
    return true;
}

/**
 * Inicialização executada em toda requisição quando o plugin está ativo.
 * Registra autoload do Composer, hooks e integrações com o GLPI.
 */
function plugin_init_smartdocs(): void
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

    // Aviso no dashboard quando deps do Composer estão ausentes.
    // Registrado ANTES do early return para que o admin veja o banner
    // mesmo quando as classes PSR-4 ainda não estão carregáveis.
    if (!file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
        $PLUGIN_HOOKS['config_page']['smartdocs'] = 'front/config.setup.php';
        $PLUGIN_HOOKS[Hooks::DISPLAY_CENTRAL]['smartdocs'] = 'plugin_smartdocs_warn_missing_deps';
        return;
    }

    // Menu lateral do GLPI (padrão GLPI 10/11: classe com getMenuContent()).
    $PLUGIN_HOOKS['menu_toadd']['smartdocs'] = [
        'plugins' => MenuHelper::class,
    ];

    // Registra o mapeamento tabela <-> itemtype para as classes que
    // sobrescrevem getTable() com nomes customizados (evitando os nomes
    // longos que o GLPI derivaria do namespace completo). Sem isso,
    // DbUtils::getItemTypeForTable() não consegue resolver a tabela de
    // volta para a classe (cai em "UNKNOWN"), quebrando Search::show()
    // usado pelas listagens (front/pdftemplate.php, front/pdfdocument.php).
    plugin_smartdocs_register_table_mappings();

    // Aba de permissões na tela de perfis do GLPI.
    Plugin::registerClass(PermissionManager::class, ['addtabon' => 'Profile']);

    // Scanner OCR: injeta botão de câmera nas telas de ativos
    $PLUGIN_HOOKS['post_show_item']['smartdocs'] = 'plugin_smartdocs_postShowItem';

    // Módulos JS do plugin (formato ES module)
    $PLUGIN_HOOKS['add_javascript']['smartdocs'] = [
        'js/scanner.bundle.js',
    ];
}

/**
 * Pré-registra o mapeamento tabela <-> itemtype no cache do GLPI
 * ($CFG_GLPI['glpitablesitemtype'] / ['glpiitemtypetables']) para as
 * classes do plugin que sobrescrevem getTable(). Ver comentário em
 * plugin_init_smartdocs().
 */
function plugin_smartdocs_register_table_mappings(): void
{
    global $CFG_GLPI;

    $mappings = [
        \GlpiPlugin\SmartDocs\Templates\PdfTemplate::class        => 'glpi_plugin_smartdocs_pdf_templates',
        \GlpiPlugin\SmartDocs\Templates\PdfTemplateVersion::class => 'glpi_plugin_smartdocs_pdf_template_versions',
        \GlpiPlugin\SmartDocs\Templates\TemplateField::class      => 'glpi_plugin_smartdocs_pdf_template_fields',
        \GlpiPlugin\SmartDocs\Documents\PdfDocument::class        => 'glpi_plugin_smartdocs_pdf_documents',
        \GlpiPlugin\SmartDocs\Documents\FilledField::class        => 'glpi_plugin_smartdocs_pdf_filled_fields',
        \GlpiPlugin\SmartDocs\Library\TechnicalFile::class        => 'glpi_plugin_smartdocs_technical_files',
        \GlpiPlugin\SmartDocs\Wiki\WikiCategory::class             => 'glpi_plugin_smartdocs_wiki_categories',
        \GlpiPlugin\SmartDocs\Wiki\WikiVersion::class              => 'glpi_plugin_smartdocs_wiki_versions',
    ];

    foreach ($mappings as $itemtype => $table) {
        $CFG_GLPI['glpitablesitemtype'][$itemtype] = $table;
        $CFG_GLPI['glpiitemtypetables'][$table]     = $itemtype;
    }
}
