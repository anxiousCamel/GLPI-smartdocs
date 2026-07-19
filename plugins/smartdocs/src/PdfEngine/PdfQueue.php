<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Fila de jobs de geração de PDF.
 *
 * Armazena jobs na tabela glpi_plugin_smartdocs_pdf_jobs e os
 * processa via CronTask do GLPI.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\PdfEngine;

use GlpiPlugin\SmartDocs\Documents\DocumentRepository;
use GlpiPlugin\SmartDocs\Documents\PdfDocument;
use GlpiPlugin\SmartDocs\Templates\PdfTemplate;
use GlpiPlugin\SmartDocs\Templates\TemplateRepository;

final class PdfQueue
{
    private const MAX_ATTEMPTS = 3;

    /**
     * Enfileira um novo job de geração de PDF.
     */
    public function enqueue(int $documentId): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $DB->insert('glpi_plugin_smartdocs_pdf_jobs', [
            'pdf_documents_id' => $documentId,
            'status'           => 'PENDING',
            'attempts'         => 0,
            'date_creation'    => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $DB->insertId();
    }

    /**
     * Retorna o status atual de um job.
     */
    public function getStatus(int $jobId): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_smartdocs_pdf_jobs',
            'WHERE' => ['id' => $jobId],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $row) {
            return [
                'status'             => $row['status'],
                'attempts'           => (int) $row['attempts'],
                'error_message'      => $row['error_message'] ?? null,
                'generated_pdf_id'   => null, // preenchido quando DONE
            ];
        }

        return ['status' => 'NOT_FOUND'];
    }

    /**
     * Processa o próximo job PENDENTE da fila.
     */
    public function processNext(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'   => 'glpi_plugin_smartdocs_pdf_jobs',
            'WHERE'  => ['status' => 'PENDING'],
            'ORDER'  => 'date_creation ASC',
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            $this->processJob((int) $row['id'], (int) $row['pdf_documents_id']);
            return;
        }
    }

    /**
     * Processa um job específico.
     */
    private function processJob(int $jobId, int $documentId): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Marca como PROCESSING
        $DB->update('glpi_plugin_smartdocs_pdf_jobs', [
            'status'    => 'PROCESSING',
            'attempts'  => new \QueryExpression('attempts + 1'),
        ], ['id' => $jobId]);

        try {
            $this->doGeneratePdf($documentId);

            $DB->update('glpi_plugin_smartdocs_pdf_jobs', [
                'status'         => 'DONE',
                'date_processed' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ], ['id' => $jobId]);
        } catch (\Throwable $e) {
            $attempts = $this->getJobAttempts($jobId);
            $status = $attempts >= self::MAX_ATTEMPTS ? 'ERROR' : 'PENDING';

            $DB->update('glpi_plugin_smartdocs_pdf_jobs', [
                'status'        => $status,
                'error_message' => substr($e->getMessage(), 0, 500),
                'date_processed' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            ], ['id' => $jobId]);

            // Atualiza o documento para ERROR se esgotou tentativas
            if ($status === 'ERROR') {
                $docRepo = new DocumentRepository();
                $docRepo->updateStatus($documentId, PdfDocument::STATUS_ERROR);
            }
        }
    }

    /**
     * Executa a geração real do PDF.
     */
    private function doGeneratePdf(int $documentId): void
    {
        $docRepo = new DocumentRepository();
        $templateRepo = new TemplateRepository();

        $doc = new PdfDocument();
        if (!$doc->getFromDB($documentId)) {
            throw new \RuntimeException('Documento não encontrado.');
        }

        $template = new PdfTemplate();
        if (!$template->getFromDB((int) $doc->fields['pdf_templates_id'])) {
            throw new \RuntimeException('Template não encontrado.');
        }

        // Obtém campos do template e campos preenchidos
        $baseFields = $templateRepo->getFields((int) $template->fields['id']);
        $filledFields = $docRepo->getFilledFields($documentId);

        // Indexa campos preenchidos por (template_field_id, item_index)
        $filledByKey = [];
        foreach ($filledFields as $ff) {
            $key = (int) $ff['pdf_template_fields_id'] . ':' . (int) $ff['item_index'];
            $filledByKey[$key] = $ff['value'] ?? '';
        }

        // Clona campos para N itens se necessário
        $totalItems = (int) $doc->fields['total_items'];
        $cloner = new FieldCloner();
        $allFields = $cloner->cloneForItems($baseFields, $totalItems);

        // Preenche valores
        $fieldOverlays = [];
        foreach ($allFields as $field) {
            $fieldId = (int) $field['id'];
            $itemIndex = (int) ($field['item_index'] ?? 0);
            $key = $fieldId . ':' . $itemIndex;

            $field['value'] = $filledByKey[$key] ?? '';
            $fieldOverlays[] = $field;
        }

        // Gera o PDF
        $pdfBasePath = $this->resolvePdfBasePath((int) $template->fields['pdf_file_documents_id']);
        $outputDir = GLPI_PLUGIN_DOC_DIR . '/smartdocs';
        $outputName = sprintf('doc_%d_%s.pdf', $documentId, date('Ymd_His'));

        $generator = new PdfGenerator();
        $outputPath = $generator->generate($pdfBasePath, $fieldOverlays, $outputDir, $outputName);

        // Cria Document no GLPI para o PDF gerado
        $document = new \Document();
        $docFileId = $document->add([
            'name'       => $doc->fields['name'] . '.pdf',
            'filename'   => $outputName,
            'filepath'   => 'smartdocs/' . $outputName,
            'mime'       => 'application/pdf',
            'entities_id' => $doc->fields['entities_id'],
            'users_id'   => $_SESSION['glpiID'] ?? 0,
        ]);

        if ($docFileId === false) {
            throw new \RuntimeException('Falha ao registrar o PDF gerado no GLPI.');
        }

        // Atualiza o documento
        $docRepo->updateGeneratedPdf($documentId, (int) $docFileId);
        $docRepo->updateStatus($documentId, PdfDocument::STATUS_GENERATED);
    }

    /**
     * Resolve o caminho físico do PDF base a partir do Document do GLPI.
     */
    private function resolvePdfBasePath(int $documentId): string
    {
        $doc = new \Document();
        if (!$doc->getFromDB($documentId)) {
            throw new \RuntimeException('PDF base não encontrado no GLPI.');
        }

        $filepath = $doc->fields['filepath'] ?? '';
        $fullPath = GLPI_DOC_DIR . '/' . $filepath;

        if (!file_exists($fullPath)) {
            throw new \RuntimeException('Arquivo do PDF base não existe: ' . $fullPath);
        }

        return $fullPath;
    }

    /**
     * Retorna o número de tentativas de um job.
     */
    private function getJobAttempts(int $jobId): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['attempts'],
            'FROM'   => 'glpi_plugin_smartdocs_pdf_jobs',
            'WHERE'  => ['id' => $jobId],
            'LIMIT'  => 1,
        ]);

        foreach ($iterator as $row) {
            return (int) $row['attempts'];
        }

        return 0;
    }
}
