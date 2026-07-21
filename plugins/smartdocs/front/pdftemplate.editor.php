<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Editor Visual de Templates PDF.
 *
 * Carrega o bundle JS do editor e injeta as variáveis necessárias
 * (template_id, campos JSON, URL do PDF base).
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_TEMPLATE_WRITE
);

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    Html::displayErrorAndDie(__('ID do template não informado.', 'smartdocs'));
}

$template = new GlpiPlugin\SmartDocs\Templates\PdfTemplate();
if (!$template->getFromDB($id)) {
    Html::displayErrorAndDie(__('Template não encontrado.', 'smartdocs'));
}

$repo   = new GlpiPlugin\SmartDocs\Templates\TemplateRepository();
$fields = $repo->getFields($id);

// URL do PDF base (se houver documento vinculado).
// Document::getDownloadLink() retorna HTML (ícone + link) para exibição
// em página, não uma URL — o PDF.js precisa da URL de download direta.
$pdfUrl = '';
if (!empty($template->fields['pdf_file_documents_id'])) {
    $doc = new Document();
    if ($doc->getFromDB($template->fields['pdf_file_documents_id'])) {
        $pdfUrl = $CFG_GLPI['root_doc'] . '/front/document.send.php?docid=' . $doc->fields['id'];
    }
}

Html::header(
    __('Editor de Template', 'smartdocs') . ' — ' . $template->fields['name'],
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'templates'
);

// ----------------------------------------------------------------------
// Injeção de dados para o JS
// ----------------------------------------------------------------------
$editorData = [
    'template_id' => (int) $id,
    'name'        => $template->fields['name'],
    'status'      => $template->fields['status'],
    'fill_mode'   => $template->fields['fill_mode'],
    'pdf_url'     => $pdfUrl,
    'fields'      => $fields,
    'ajax_url'    => $CFG_GLPI['root_doc'] . '/plugins/smartdocs/ajax/',
];

// Carrega o bundle do editor (cache-busting pelo mtime do arquivo)
$pluginPath  = Plugin::getWebDir('smartdocs');
$bundlePath  = dirname(__FILE__) . '/../js/editor.bundle.js';
$bundleVer   = file_exists($bundlePath) ? filemtime($bundlePath) : PLUGIN_SMARTDOCS_VERSION;
echo '<script type="module" src="' . htmlescape($pluginPath . '/js/editor.bundle.js?v=' . $bundleVer) . '"></script>';

echo "<div id='smartdocs-editor-root' class='smartdocs-editor'></div>";

echo "<script>
    window.SmartDocsEditor = window.SmartDocsEditor || {};
    window.SmartDocsEditor.data = " . json_encode($editorData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) . ";
</script>";

Html::footer();
