<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Retorna o status de um job de geração de PDF.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_READ
);

$jobId = (int) ($_GET['job_id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'VALIDATION_ERROR', 'message' => 'ID do job não informado.']);
    exit;
}

$queue = new GlpiPlugin\SmartDocs\PdfEngine\PdfQueue();
$status = $queue->getStatus($jobId);

if ($status['status'] === 'NOT_FOUND') {
    http_response_code(404);
    echo json_encode(['error' => 'NOT_FOUND', 'message' => 'Job não encontrado.']);
    exit;
}

$response = [
    'status' => $status['status'],
];

if ($status['status'] === 'DONE') {
    // Busca o documento para retornar o ID do PDF gerado
    /** @var \DBmysql $DB */
    global $DB;

    $iterator = $DB->request([
        'SELECT' => ['j.pdf_documents_id', 'd.generated_pdf_documents_id'],
        'FROM'   => 'glpi_plugin_smartdocs_pdf_jobs AS j',
        'LEFT JOIN' => [
            'glpi_plugin_smartdocs_pdf_documents AS d' => [
                'ON' => ['j', 'd', 'pdf_documents_id', 'id'],
            ],
        ],
        'WHERE'  => ['j.id' => $jobId],
        'LIMIT'  => 1,
    ]);

    foreach ($iterator as $row) {
        $response['generated_pdf_id'] = (int) ($row['generated_pdf_documents_id'] ?? 0);
    }
} elseif ($status['status'] === 'ERROR') {
    $response['message'] = $status['error_message'];
}

echo json_encode($response);
