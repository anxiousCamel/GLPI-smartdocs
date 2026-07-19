<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Publica um template: valida campos, cria versão snapshot e muda
 * o status para PUBLISHED.
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

try {
    $template->publish();
    echo json_encode([
        'success' => true,
        'version' => (int) $template->fields['version'],
        'status'  => $template->fields['status'],
    ]);
} catch (\RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_STATUS', 'message' => $e->getMessage()]);
}
