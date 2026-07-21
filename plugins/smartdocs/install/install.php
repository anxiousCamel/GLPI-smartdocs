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

    plugin_smartdocs_create_tables($DB, $migration);
    plugin_smartdocs_seed_default_configs($DB);
    plugin_smartdocs_seed_wiki_articles($DB);

    // Permissões padrão: perfil Super-Admin recebe todos os direitos.
    if (class_exists(PermissionManager::class)) {
        PermissionManager::installDefaultRights();
    }

    // Tarefa automática: processa a fila de geração de PDF a cada 2 minutos.
    // O nome precisa bater com o método estático PdfCronTask::cronSmartDocsPdfQueue().
    if (class_exists(\GlpiPlugin\SmartDocs\PdfEngine\PdfCronTask::class)) {
        CronTask::register(
            \GlpiPlugin\SmartDocs\PdfEngine\PdfCronTask::class,
            'SmartDocsPdfQueue',
            2 * MINUTE_TIMESTAMP,
            [
                'comment' => __('Processa jobs pendentes de geração de PDF', 'smartdocs'),
                'mode'    => CronTask::MODE_INTERNAL,
            ]
        );
    }

    $migration->executeMigration();

    // Mensagem de orientação pós-instalação
    \Session::addMessageAfterRedirect(
        __('SmartDocs instalado com sucesso! Próximos passos: 1) Configure permissões em Administração → Perfis, 2) Acesse SmartDocs → Templates PDF para criar seu primeiro template.', 'smartdocs'),
        true,
        INFO
    );

    return true;
}

/**
 * Cria as tabelas do plugin (ordem do Apêndice 14 do PROJETO.md).
 *
 * CREATE TABLE usa $DB->doQuery() com guarda tableExists() — padrão GLPI
 * para DDL inicial. DML (seed) usa query builder exclusivamente.
 */
function plugin_smartdocs_create_tables(DBmysql $DB, Migration $migration): void
{
    $queries = plugin_smartdocs_table_definitions();

    foreach ($queries as $table => $query) {
        if ($DB->tableExists($table)) {
            continue;
        }

        $migration->displayMessage("Criando tabela {$table}");

        if (!$DB->doQuery($query)) {
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

        // 13. Vínculos de equipamentos a documentos (preventivas)
        'glpi_plugin_smartdocs_equipment_assignments' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_equipment_assignments` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pdf_documents_id` INT UNSIGNED NOT NULL,
                `itemtype` VARCHAR(100) NOT NULL,
                `items_id` INT UNSIGNED NOT NULL,
                `item_index` INT UNSIGNED NOT NULL,
                `non_binding_data` JSON NULL,
                `removed_at` DATETIME NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_doc_item` (`pdf_documents_id`, `itemtype`, `items_id`, `removed_at`),
                KEY `idx_document` (`pdf_documents_id`)
            ) {$engine}
        ",

        // 14. Arquivos técnicos (biblioteca)
        'glpi_plugin_smartdocs_technical_files' => "
            CREATE TABLE IF NOT EXISTS `glpi_plugin_smartdocs_technical_files` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `file_documents_id` INT UNSIGNED NULL,
                `type` ENUM('manual','pop','contract','warranty','other') NOT NULL DEFAULT 'other',
                `linked_itemtype` VARCHAR(100) NULL,
                `linked_items_id` INT UNSIGNED NULL,
                `entities_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_recursive` TINYINT NOT NULL DEFAULT 0,
                `users_id_creator` INT UNSIGNED NULL,
                `date_creation` DATETIME NULL,
                `date_mod` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_linked_item` (`linked_itemtype`, `linked_items_id`),
                KEY `idx_entities` (`entities_id`)
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

    foreach ($defaults as $name => $value) {
        $exists = $DB->request([
            'COUNT' => 'cnt',
            'FROM'  => 'glpi_plugin_smartdocs_configs',
            'WHERE' => ['name' => $name],
        ])->current()['cnt'] > 0;

        if (!$exists) {
            $DB->insert('glpi_plugin_smartdocs_configs', [
                'name'     => $name,
                'value'    => $value,
                'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ]);
        }
    }
}

/**
 * Semeia artigos iniciais da Wiki para orientar o administrador.
 * Idempotente: só insere se a categoria "SmartDocs — Ajuda" ainda não existe.
 */
function plugin_smartdocs_seed_wiki_articles(DBmysql $DB): void
{
    $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

    $exists = $DB->request([
        'COUNT' => 'cnt',
        'FROM'  => 'glpi_plugin_smartdocs_wiki_categories',
        'WHERE' => ['name' => 'SmartDocs — Ajuda'],
    ])->current()['cnt'] > 0;

    if ($exists) {
        return;
    }

    $DB->insert('glpi_plugin_smartdocs_wiki_categories', [
        'name'                => 'SmartDocs — Ajuda',
        'wiki_categories_id'  => 0,
        'entities_id'         => 0,
        'is_recursive'        => 1,
    ]);
    $category_id = $DB->insertId();

    $articles = [
        [
            'name'    => 'Primeiros Passos',
            'content' => <<<'MD'
## Bem-vindo ao SmartDocs

O SmartDocs adiciona ao GLPI quatro módulos:

- **Templates PDF** — crie layouts visuais posicionando campos sobre um PDF base
- **Documentos** — gere PDFs preenchidos vinculados a equipamentos e chamados
- **Scanner OCR** — leia QR Code, código de barras ou etiquetas pela câmera
- **Wiki** — base de conhecimento interna (este módulo)
- **Biblioteca Técnica** — manuais, POPs e contratos vinculados a qualquer objeto

### Por onde começar

1. Vá em **SmartDocs → Templates PDF** e crie seu primeiro template
2. Faça upload de um PDF base e posicione os campos no editor visual
3. Publique o template
4. Em **SmartDocs → Documentos**, crie um documento a partir do template
5. Preencha os campos e gere o PDF
MD,
        ],
        [
            'name'    => 'Templates PDF — Como usar',
            'content' => <<<'MD'
## Templates PDF

Um template define o layout de um documento: qual PDF usar como base e onde ficam os campos preenchíveis.

### Criando um template

1. **SmartDocs → Templates PDF → Adicionar**
2. Dê um nome e faça upload do PDF base (máx. 20 MB por padrão)
3. No editor visual, arraste campos para as posições desejadas
4. Tipos de campo: `texto`, `imagem`, `assinatura`, `checkbox`
5. Para campos repetidos por equipamento, defina o escopo como `item`
6. Clique **Publicar** — somente templates publicados geram documentos

### Binding keys

Campos com `binding_key` são preenchidos automaticamente a partir dos dados do equipamento no GLPI.

Exemplos: `computer.name`, `computer.serial`, `computer.location`.
MD,
        ],
        [
            'name'    => 'Scanner OCR — Como usar',
            'content' => <<<'MD'
## Scanner OCR

O scanner aparece nos formulários de **Computadores, Monitores, Impressoras, Periféricos, Equipamentos de Rede e Telefones**.

### Como ativar

O usuário precisa da permissão **Uso de OCR** no perfil SmartDocs (**Administração → Perfis → SmartDocs**).

### Usando o scanner

1. Abra o formulário de qualquer ativo compatível
2. Clique no botão de câmera que aparece no formulário
3. Aponte para QR Code, código de barras ou etiqueta com texto
4. O sistema identifica o equipamento e pré-preenche os campos

### Configuração do OCR

Em **SmartDocs → Configurações**:

| Opção | Descrição |
|-------|-----------|
| `browser` | OCR via WebAssembly no navegador (padrão, sem dependências no servidor) |
| `tesseract` | Tesseract instalado no servidor (mais preciso para textos longos) |
| `api` | Serviço externo via URL + chave de API |
MD,
        ],
        [
            'name'    => 'Fila de PDF — Geração assíncrona',
            'content' => <<<'MD'
## Geração de PDF

PDFs são gerados em fila assíncrona para não bloquear a interface.

### Status de um documento

| Status | Significado |
|--------|-------------|
| `DRAFT` | Em preenchimento |
| `IN_PROGRESS` | Aguardando envio para fila |
| `GENERATING` | Sendo processado pelo cron |
| `GENERATED` | PDF disponível para download |
| `ERROR` | Falha na geração — veja a mensagem de erro |

### Configurar o cron

O cron do GLPI precisa estar ativo. Configure em **Configuração → Tarefas automáticas → SmartDocsPdfQueue**.

Intervalo recomendado: **2 minutos**.

Para forçar execução manual: acesse a tarefa e clique **Executar agora**.
MD,
        ],
    ];

    foreach ($articles as $article) {
        $DB->insert('glpi_plugin_smartdocs_wiki_documents', [
            'name'                   => $article['name'],
            'content'                => $article['content'],
            'version'                => 1,
            'wiki_categories_id'     => $category_id,
            'entities_id'            => 0,
            'is_recursive'           => 1,
            'users_id_creator'       => 0,
            'users_id_lastupdater'   => 0,
            'date_creation'          => $now,
            'date_mod'               => $now,
        ]);
    }
}