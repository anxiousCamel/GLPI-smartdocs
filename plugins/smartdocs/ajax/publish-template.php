<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Publica um template: valida campos, cria versão snapshot e muda
 * o status para PUBLISHED.
 * ---------------------------------------------------------------------
 */

ob_start();

include('../../../inc/includes.php');

if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=UTF-8');

if (!Session::getLoginUserID()) {
    http_response_code(401);
    echo json_encode(['error' => 'UNAUTHORIZED', 'message' => 'Sessão expirada. Faça login novamente no GLPI.']);
    exit;
}

if (!GlpiPlugin\SmartDocs\Permissions\PermissionManager::canWriteTemplates()) {
    http_response_code(403);
    echo json_encode(['error' => 'FORBIDDEN', 'message' => 'Sem permissão para publicar templates.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$templateId = (int) ($input['template_id'] ?? 0);

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
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode([
        'success' => true,
        'version' => (int) $template->fields['version'],
        'status'  => $template->fields['status'],
    ]);
} catch (\Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(400);
    echo json_encode(['error' => 'PUBLISH_FAILED', 'message' => $e->getMessage()]);
}
