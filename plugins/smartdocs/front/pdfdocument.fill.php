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

// Enriquece campos com valores preenchidos
foreach ($fields as &$field) {
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

// ------------------------------------------------------------------
// Injeção de dados para o JS
// ------------------------------------------------------------------
$wizardData = [
    'document_id'    => (int) $id,
    'document_name'  => $doc->fields['name'],
    'status'         => $doc->fields['status'],
    'total_items'    => (int) $doc->fields['total_items'],
    'template'       => [
        'id'         => (int) $template->fields['id'],
        'name'       => $template->fields['name'],
        'fill_mode'  => $template->fields['fill_mode'],
    ],
    'fields'         => $fields,
    'ajax_url'       => $CFG_GLPI['root_doc'] . '/plugins/smartdocs/ajax/',
    'asset_types'    => ['Computer', 'Printer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone'],
];

$pluginPath = Plugin::getWebDir('smartdocs');
echo '<script type="module" src="' . htmlescape($pluginPath . '/js/wizard.bundle.js') . '"></script>';

echo "<div id='smartdocs-wizard-root' class='smartdocs-wizard'></div>";

echo "<script>
    window.SmartDocsWizard = window.SmartDocsWizard || {};
    window.SmartDocsWizard.data = " . json_encode($wizardData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ";
</script>";

Html::footer();
