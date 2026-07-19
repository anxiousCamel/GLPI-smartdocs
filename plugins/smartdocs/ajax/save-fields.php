<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Salva os campos posicionados de um template (autosave do editor).
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

header('Content-Type: application/json');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_TEMPLATE_WRITE
);

$input = json_decode(file_get_contents('php://input'), true);

$templateId = $input['template_id'] ?? 0;
$fields     = $input['fields'] ?? [];

if ($templateId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'ID do template não informado.']);
    exit;
}

$template = new GlpiPlugin\SmartDocs\Templates\PdfTemplate();
if (!$template->getFromDB($templateId)) {
    http_response_code(404);
    echo json_encode(['error' => 'NOT_FOUND', 'message' => 'Template não encontrado.']);
    exit;
}

if ($template->fields['status'] === GlpiPlugin\SmartDocs\Templates\PdfTemplate::STATUS_ARCHIVED) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_STATUS', 'message' => 'Template arquivado não pode ser editado.']);
    exit;
}

$repo = new GlpiPlugin\SmartDocs\Templates\TemplateRepository();
$repo->saveFields($templateId, $fields);

echo json_encode(['success' => true, 'saved_at' => date('c')]);
