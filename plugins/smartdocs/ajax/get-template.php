<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Retorna dados de um template (campos + URL do PDF) em JSON.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

header('Content-Type: application/json');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_TEMPLATE_READ
);

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_STATUS', 'message' => 'ID do template não informado.']);
    exit;
}

$template = new GlpiPlugin\SmartDocs\Templates\PdfTemplate();
if (!$template->getFromDB($id)) {
    http_response_code(404);
    echo json_encode(['error' => 'NOT_FOUND', 'message' => 'Template não encontrado.']);
    exit;
}

$repo   = new GlpiPlugin\SmartDocs\Templates\TemplateRepository();
$fields = $repo->getFields($id);

$pdfUrl = '';
if (!empty($template->fields['pdf_file_documents_id'])) {
    $doc = new Document();
    if ($doc->getFromDB($template->fields['pdf_file_documents_id'])) {
        $pdfUrl = $doc->getDownloadLink();
    }
}

echo json_encode([
    'success' => true,
    'data'    => [
        'id'        => (int) $template->fields['id'],
        'name'      => $template->fields['name'],
        'status'    => $template->fields['status'],
        'version'   => (int) $template->fields['version'],
        'fill_mode' => $template->fields['fill_mode'],
        'pdf_url'   => $pdfUrl,
        'fields'    => $fields,
    ],
]);
