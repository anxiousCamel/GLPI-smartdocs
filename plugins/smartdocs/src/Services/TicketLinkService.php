<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Vincula documentos gerados a chamados (Tickets) do GLPI.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Services;

use Document;
use Document_Item;
use ITILFollowup;
use Item_Ticket;
use Ticket;
use Ticket_User;

final class TicketLinkService
{
    /**
     * Vincula um documento PDF a um chamado existente.
     *
     * @param int $pdfDocumentId ID do PdfDocument no plugin
     * @param int $ticketId ID do Ticket GLPI
     * @param int|null $technicianId ID do técnico a atribuir (opcional)
     *
     * @return array{success: bool, followup_id: int, document_id: int, message: string}
     */
    public function linkDocumentToTicket(int $pdfDocumentId, int $ticketId, ?int $technicianId = null): array
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) {
            throw new \RuntimeException('Chamado não encontrado: ' . $ticketId);
        }

        // 1. Atribui técnico ao chamado (se informado)
        if ($technicianId !== null && $technicianId > 0) {
            $ticketUser = new Ticket_User();
            $ticketUser->add([
                'tickets_id' => $ticketId,
                'users_id'   => $technicianId,
                'type'       => 2, // Atribuído
            ]);
        }

        // 2. Obtém caminho do PDF gerado (deve estar em Document ou caminho direto)
        $docPath = $this->resolvePdfPath($pdfDocumentId);

        // 3. Cria Document no GLPI com o PDF
        $document = new Document();
        $docInput = [
            'name'             => 'SmartDocs - ' . basename($docPath),
            'filename'         => basename($docPath),
            'filepath'         => $docPath,
            'mime'             => 'application/pdf',
            'entities_id'      => $ticket->fields['entities_id'] ?? 0,
            'is_recursive'     => 0,
            'users_id'         => \Session::getLoginUserID(),
        ];
        $documentId = $document->add($docInput);

        if (!$documentId) {
            throw new \RuntimeException('Falha ao criar Document no GLPI.');
        }

        // 4. Vincula Document ao Ticket
        $docItem = new Document_Item();
        $docItem->add([
            'documents_id' => $documentId,
            'itemtype'     => 'Ticket',
            'items_id'     => $ticketId,
        ]);

        // 5. Cria followup com referência ao documento
        $followup = new ITILFollowup();
        $followupId = $followup->add([
            'tickets_id'  => $ticketId,
            'content'     => sprintf(
                'Documento SmartDocs vinculado automaticamente.\n'
                . 'Ver documento: [Documento #%d]',
                $documentId
            ),
            'users_id'    => \Session::getLoginUserID(),
            'is_private'  => 0,
        ]);

        return [
            'success'      => true,
            'followup_id'  => $followupId ?: 0,
            'document_id'  => $documentId,
            'message'      => 'Documento vinculado ao chamado com sucesso.',
        ];
    }

    /**
     * Vincula cada ativo do documento ao chamado via Item_Ticket.
     *
     * @param int $ticketId
     * @param array<int, array{itemtype: string, items_id: int}> $assets
     */
    public function linkAssetsToTicket(int $ticketId, array $assets): void
    {
        foreach ($assets as $asset) {
            $itemTicket = new Item_Ticket();
            $itemTicket->add([
                'tickets_id' => $ticketId,
                'itemtype'   => $asset['itemtype'],
                'items_id'   => $asset['items_id'],
            ]);
        }
    }

    /**
     * Resolve o caminho físico do PDF gerado.
     */
    private function resolvePdfPath(int $pdfDocumentId): string
    {
        $pdfDoc = new \GlpiPlugin\SmartDocs\Documents\PdfDocument();
        if (!$pdfDoc->getFromDB($pdfDocumentId)) {
            throw new \RuntimeException('PdfDocument não encontrado: ' . $pdfDocumentId);
        }

        $filePath = $pdfDoc->fields['file_path'] ?? '';
        if ($filePath === '' || !file_exists($filePath)) {
            throw new \RuntimeException('Arquivo PDF não encontrado para o documento: ' . $pdfDocumentId);
        }

        return $filePath;
    }
}
