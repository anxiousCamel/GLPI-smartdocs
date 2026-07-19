<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Endpoint AJAX para vincular documento a chamado.
 *
 * POST JSON:
 *   {
 *     "pdf_document_id": 123,
 *     "ticket_id": 456,
 *     "technician_id": 7,
 *     "assets": [
 *       {"itemtype": "Computer", "items_id": 99}
 *     ]
 *   }
 *
 * Resposta JSON:
 *   { "success": true, "followup_id": 789, "document_id": 321 }
 * ----------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\Services\TicketLinkService;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

include_once dirname(__DIR__) . '/../../../inc/includes.php';

Session::checkLoginUser();
Session::checkRight(PermissionManager::RIGHT_NAME, PermissionManager::SMARTDOCS_DOCUMENT_WRITE);

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new \RuntimeException('Payload JSON inválido.');
    }

    $pdfDocumentId = (int) ($input['pdf_document_id'] ?? 0);
    $ticketId = (int) ($input['ticket_id'] ?? 0);
    $technicianId = isset($input['technician_id']) ? (int) $input['technician_id'] : null;
    $assets = $input['assets'] ?? [];

    if ($pdfDocumentId <= 0 || $ticketId <= 0) {
        throw new \RuntimeException('pdf_document_id e ticket_id são obrigatórios.');
    }

    $service = new TicketLinkService();
    $result = $service->linkDocumentToTicket($pdfDocumentId, $ticketId, $technicianId);

    if (!empty($assets) && is_array($assets)) {
        $service->linkAssetsToTicket($ticketId, $assets);
    }

    echo json_encode($result, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
