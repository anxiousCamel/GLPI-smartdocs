<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Instalação do banco de dados do plugin.
 *
 * Cria todas as tabelas na ordem que respeita as dependências e insere
 * as configurações padrão. Idempotente: usa CREATE TABLE IF NOT EXISTS
 * e INSERT ... ON DUPLICATE KEY UPDATE, podendo ser reexecutada sem
 * efeitos colaterais.
 * ---------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Executa a instalação completa (tabelas + configurações + permissões).
 */
function plugin_smartdocs_run_install(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $migration = new Migration(PLUGIN_SMARTDOCS_VERSION);

    plugin_smartdocs_create_tables($DB);
    plugin_smartdocs_seed_default_configs($DB);

    // Permissões padrão: perfil Super-Admin recebe todos os direitos.
    if (class_exists(PermissionManager::class)) {
        PermissionManager::installDefaultRights();
    }

    $migration->executeMigration();

    return true;
}

/**
 * Cria as tabelas do plugin (ordem do Apêndice 14 do PROJETO.md).
 */
function plugin_smartdocs_create_tables(DBmysql $DB): void
{
    $queries = plugin_smartdocs_table_definitions();

    foreach ($queries as $table => $query) {
        if (!$DB->query($query)) {
            throw new RuntimeException(
                sprintf('Falha ao criar a tabela %s: %s', $table, $DB->error())
            );
        }
    }
}

/**
 * Definições SQL das tabelas, na ordem de criação.
 *
 * @return array<string, string> mapa tabela => CREATE TABLE
 */
function plugin_smartdocs_table_definitions(): array
{
    $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        // 1. Configuração
        'glpi_plugin_smartdocs_configs' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_configs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `value` TEXT NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_name` (`name`)
            ) {$engine}
        ",

        // 2. Templates PDF
        'glpi_plugin_smartdocs_pdf_templates' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_pdf_templates` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `status` ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `fill_mode` ENUM('single','repeat') NOT NULL DEFAULT 'single',
                `pdf_file_documents_id` INT UNSIGNED NULL,
                `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_recursive` TINYINT NOT NULL DEFAULT 0,
                `users_id_creator` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`),
                KEY `idx_entities` (`entities_id`)
            ) {$engine}
        ",

        // 3. Versões de templates (snapshot no momento da publicação)
        'glpi_plugin_smartdocs_pdf_template_versions' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_pdf_template_versions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pdf_templates_id` INT UNSIGNED NOT NULL,
                `version` INT UNSIGNED NOT NULL,
                `fields_snapshot` JSON NOT NULL,
                `date_creation` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_template_version` (`pdf_templates_id`, `version`)
            ) {$engine}
        ",

        // 4. Campos posicionados dos templates
        'glpi_plugin_smartdocs_pdf_template_fields' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_pdf_template_fields` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pdf_templates_id` INT UNSIGNED NOT NULL,
                `type` ENUM('text','image','signature','checkbox') NOT NULL,
                `page_index` INT UNSIGNED NOT NULL DEFAULT 0,
                `position` JSON NOT NULL,
                `config` JSON NULL,
                `scope` ENUM('global','item') NOT NULL DEFAULT 'global',
                `slot_index` INT NULL,
                `binding_key` VARCHAR(100) NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_template` (`pdf_templates_id`)
            ) {$engine}
        ",

        // 5. Documentos gerados a partir de templates
        'glpi_plugin_smartdocs_pdf_documents' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_pdf_documents` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `status` ENUM('DRAFT','IN_PROGRESS','GENERATING','GENERATED','ERROR')
                    NOT NULL DEFAULT 'DRAFT',
                `total_items` INT UNSIGNED NOT NULL DEFAULT 1,
                `pdf_templates_id` INT UNSIGNED NOT NULL,
                `template_version` INT UNSIGNED NOT NULL,
                `generated_pdf_documents_id` INT UNSIGNED NULL,
                `metadata` JSON NULL,
                `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `users_id_creator` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`),
                KEY `idx_template` (`pdf_templates_id`),
                KEY `idx_entities` (`entities_id`)
            ) {$engine}
        ",

        // 6. Campos preenchidos dos documentos
        'glpi_plugin_smartdocs_pdf_filled_fields' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_pdf_filled_fields` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pdf_documents_id` INT UNSIGNED NOT NULL,
                `pdf_template_fields_id` INT UNSIGNED NOT NULL,
                `item_index` INT UNSIGNED NOT NULL DEFAULT 0,
                `value` TEXT NULL,
                `file_documents_id` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_doc_field_item`
                    (`pdf_documents_id`, `pdf_template_fields_id`, `item_index`),
                KEY `idx_document` (`pdf_documents_id`)
            ) {$engine}
        ",

        // 7. Fila de geração de PDF
        'glpi_plugin_smartdocs_pdf_jobs' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_pdf_jobs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pdf_documents_id` INT UNSIGNED NOT NULL,
                `status` ENUM('PENDING','PROCESSING','DONE','ERROR')
                    NOT NULL DEFAULT 'PENDING',
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `error_message` TEXT NULL,
                `date_creation` DATETIME NULL,
                `date_processed` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`),
                KEY `idx_document` (`pdf_documents_id`)
            ) {$engine}
        ",

        // 8. Vínculos genéricos com qualquer objeto GLPI
        'glpi_plugin_smartdocs_links' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_links` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `smartdocs_type` ENUM('pdf_document','wiki_document','library_file') NOT NULL,
                `smartdocs_id` INT UNSIGNED NOT NULL,
                `itemtype` VARCHAR(100) NOT NULL,
                `items_id` INT UNSIGNED NOT NULL,
                `date_creation` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_item` (`itemtype`, `items_id`),
                KEY `idx_smartdocs` (`smartdocs_type`, `smartdocs_id`)
            ) {$engine}
        ",

        // 9. Histórico de resultados de OCR
        'glpi_plugin_smartdocs_ocr_results' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_ocr_results` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `source_type` ENUM('upload','camera') NOT NULL,
                `file_hash` VARCHAR(64) NULL,
                `raw_text` TEXT NULL,
                `candidates` JSON NULL,
                `used_candidate` JSON NULL,
                `itemtype` VARCHAR(100) NULL,
                `items_id` INT UNSIGNED NULL,
                `users_id` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_hash` (`file_hash`),
                KEY `idx_item` (`itemtype`, `items_id`)
            ) {$engine}
        ",

        // 10. Categorias da Wiki
        'glpi_plugin_smartdocs_wiki_categories' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_wiki_categories` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `wiki_categories_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_recursive` TINYINT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_parent` (`wiki_categories_id`),
                KEY `idx_entities` (`entities_id`)
            ) {$engine}
        ",

        // 11. Documentos da Wiki
        'glpi_plugin_smartdocs_wiki_documents' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_wiki_documents` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `content` LONGTEXT NULL,
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `wiki_categories_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_recursive` TINYINT NOT NULL DEFAULT 0,
                `users_id_creator` INT UNSIGNED NULL,
                `users_id_lastupdater` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                FULLTEXT KEY `ft_content` (`name`, `content`),
                KEY `idx_category` (`wiki_categories_id`),
                KEY `idx_entities` (`entities_id`)
            ) {$engine}
        ",

        // 12. Versões dos documentos da Wiki
        'glpi_plugin_smartdocs_wiki_versions' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_wiki_versions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `wiki_documents_id` INT UNSIGNED NOT NULL,
                `version` INT UNSIGNED NOT NULL,
                `content` LONGTEXT NULL,
                `users_id` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_doc_version` (`wiki_documents_id`, `version`)
            ) {$engine}
        ",
    ];
}

/**
 * Insere as configurações padrão (seção 6.5 do PROJETO.md).
 */
function plugin_smartdocs_seed_default_configs(DBmysql $DB): void
{
    $defaults = [
        'ocr_provider'          => 'browser',
        'ocr_api_url'           => '',
        'ocr_api_key'           => '',
        'pdf_max_file_size_mb'  => '20',
        'cron_interval_minutes' => '2',
        'scanner_languages'     => 'eng+por',
    ];

    $stmt = $DB->prepare(
        "INSERT INTO `glpi_plugin_smartdocs_configs` (`name`, `value`, `date_mod`)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)"
    );

    foreach ($defaults as $name => $value) {
        $stmt->bind_param('ss', $name, $value);
        $stmt->execute();
    }

    $stmt->close();
}