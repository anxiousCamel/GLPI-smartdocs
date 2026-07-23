<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Wizard de Preenchimento de Documento PDF.
 *
 * Carrega o bundle JS do wizard e injeta os dados do documento,
 * template e campos preenchidos.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
);

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    Html::displayErrorAndDie(__('ID do documento não informado.', 'smartdocs'));
}

$doc = new GlpiPlugin\SmartDocs\Documents\PdfDocument();
if (!$doc->getFromDB($id)) {
    Html::displayErrorAndDie(__('Documento não encontrado.', 'smartdocs'));
}

// Transiciona para IN_PROGRESS ao abrir o wizard
if ($doc->fields['status'] === GlpiPlugin\SmartDocs\Documents\PdfDocument::STATUS_DRAFT) {
    $doc->transitionStatus(GlpiPlugin\SmartDocs\Documents\PdfDocument::STATUS_IN_PROGRESS);
}

$template = new GlpiPlugin\SmartDocs\Templates\PdfTemplate();
if (!$template->getFromDB((int) $doc->fields['pdf_templates_id'])) {
    Html::displayErrorAndDie(__('Template não encontrado.', 'smartdocs'));
}

$templateRepo = new GlpiPlugin\SmartDocs\Templates\TemplateRepository();
$docRepo = new GlpiPlugin\SmartDocs\Documents\DocumentRepository();

$fields = $templateRepo->getFields((int) $template->fields['id']);
$filledFields = $docRepo->getFilledFields($id);

// Indexa campos preenchidos por template_field_id + item_index
$filledMap = [];
foreach ($filledFields as $ff) {
    $key = (int) $ff['pdf_template_fields_id'] . ':' . (int) $ff['item_index'];
    $filledMap[$key] = $ff['value'] ?? '';
}

// Enriquece campos com valores preenchidos e normaliza JSON de config/position
// para objetos nativos (o JS espera field.config.label como objeto, não string).
foreach ($fields as &$field) {
    if (isset($field['config']) && is_string($field['config'])) {
        $decoded = json_decode($field['config'], true);
        $field['config'] = is_array($decoded) ? $decoded : null;
    }
    if (isset($field['position']) && is_string($field['position'])) {
        $decodedPos = json_decode($field['position'], true);
        if (is_array($decodedPos)) {
            $field['position'] = $decodedPos;
        }
    }

    for ($i = 0; $i < (int) $doc->fields['total_items']; $i++) {
        $key = (int) $field['id'] . ':' . $i;
        if (isset($filledMap[$key])) {
            if (!isset($field['filled_values'])) {
                $field['filled_values'] = [];
            }
            $field['filled_values'][$i] = $filledMap[$key];
        }
    }
}
unset($field);

Html::header(
    __('Preencher Documento', 'smartdocs') . ' — ' . $doc->fields['name'],
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'documents'
);

/** @var \DBmysql $DB */
global $DB;

$locations = [];
// glpi_locations não possui coluna is_deleted no schema padrão do GLPI —
// filtrar por ela causava "SQL Error 1054: Unknown column 'is_deleted'".
$locIterator = $DB->request([
    'SELECT' => ['id', 'completename', 'name'],
    'FROM'   => 'glpi_locations',
    'ORDER'  => 'completename ASC',
]);
foreach ($locIterator as $l) {
    $locations[] = [
        'id'   => (int) $l['id'],
        'name' => $l['completename'] ?: $l['name'],
    ];
}

// ------------------------------------------------------------------
// Injeção de dados para o JS
// ------------------------------------------------------------------
$wizardData = [
    'document_id'    => (int) $id,
    'document_name'  => $doc->fields['name'],
    'status'         => $doc->fields['status'],
    'total_items'    => (int) $doc->fields['total_items'],
    'metadata'       => $doc->getMetadata(),
    'template'       => [
        'id'         => (int) $template->fields['id'],
        'name'       => $template->fields['name'],
        'fill_mode'  => $template->fields['fill_mode'],
    ],
    'fields'         => $fields,
    'locations'      => $locations,
    'ajax_url'       => $CFG_GLPI['root_doc'] . '/plugins/smartdocs/ajax/',
    'asset_types'    => ['Computer', 'Peripheral', 'Printer', 'Monitor', 'NetworkEquipment', 'Phone'],
];

$pluginPath = Plugin::getWebDir('smartdocs');
// Cache-busting pelo mtime do bundle para o navegador não servir versão antiga.
$bundleFsPath = __DIR__ . '/../js/wizard.bundle.js';
$bundleVer = file_exists($bundleFsPath) ? filemtime($bundleFsPath) : time();
echo '<script type="module" src="' . htmlescape($pluginPath . '/js/wizard.bundle.js?v=' . $bundleVer) . '"></script>';

echo "<div id='smartdocs-wizard-root' class='smartdocs-wizard'></div>";

echo "<script>
    window.SmartDocsWizard = window.SmartDocsWizard || {};
    window.SmartDocsWizard.data = " . json_encode($wizardData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ";
</script>";

Html::footer();
