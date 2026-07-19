<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Desinstalação do plugin: remove todas as tabelas (ordem inversa da
 * criação) e limpa os dados de integração registrados no GLPI
 * (preferências de exibição, direitos de perfil e tarefas agendadas).
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Executa a desinstalação completa.
 */
function plugin_smartdocs_run_uninstall(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    plugin_smartdocs_drop_tables($DB);
    plugin_smartdocs_cleanup_integration($DB);

    return true;
}

/**
 * Remove as tabelas do plugin na ordem inversa da criação.
 */
function plugin_smartdocs_drop_tables(DBmysql $DB): void
{
    $tables = [
        'glpi_plugin_smartdocs_wiki_versions',
        'glpi_plugin_smartdocs_wiki_documents',
        'glpi_plugin_smartdocs_wiki_categories',
        'glpi_plugin_smartdocs_ocr_results',
        'glpi_plugin_smartdocs_links',
        'glpi_plugin_smartdocs_pdf_jobs',
        'glpi_plugin_smartdocs_pdf_filled_fields',
        'glpi_plugin_smartdocs_pdf_documents',
        'glpi_plugin_smartdocs_pdf_template_fields',
        'glpi_plugin_smartdocs_pdf_template_versions',
        'glpi_plugin_smartdocs_pdf_templates',
        'glpi_plugin_smartdocs_configs',
    ];

    foreach ($tables as $table) {
        $DB->queryOrDie(
            sprintf('DROP TABLE IF EXISTS `%s`', $table),
            sprintf('Erro ao remover a tabela %s', $table)
        );
    }
}

/**
 * Remove registros do GLPI associados ao plugin.
 */
function plugin_smartdocs_cleanup_integration(DBmysql $DB): void
{
    // Preferências de exibição das telas de listagem do plugin.
    $DB->delete('glpi_displaypreferences', [
        'itemtype' => ['LIKE', 'GlpiPlugin\\\\SmartDocs%'],
    ]);

    // Direitos do plugin em perfis.
    $DB->delete('glpi_profilerights', [
        'name' => ['LIKE', 'plugin\\_smartdocs%'],
    ]);

    // Tarefas agendadas do plugin.
    $DB->delete('glpi_crontasks', [
        'itemtype' => ['LIKE', 'GlpiPlugin\\\\SmartDocs%'],
    ]);
}