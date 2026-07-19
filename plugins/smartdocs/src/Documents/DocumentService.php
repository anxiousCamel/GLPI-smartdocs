<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Serviço de regras de negócio para Documentos PDF.
 *
 * Orquestra a criação, preenchimento e geração de documentos,
 * resolvendo binding keys e gerenciando a fila de PDF.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

use GlpiPlugin\SmartDocs\PdfEngine\PdfQueue;
use GlpiPlugin\SmartDocs\Templates\BindingKeyResolver;
use GlpiPlugin\SmartDocs\Templates\PdfTemplate;
use GlpiPlugin\SmartDocs\Templates\TemplateRepository;

final class DocumentService
{
    private DocumentRepository $repo;
    private TemplateRepository $templateRepo;

    public function __construct()
    {
        $this->repo = new DocumentRepository();
        $this->templateRepo = new TemplateRepository();
    }

    /**
     * Cria um novo documento a partir de um template publicado.
     *
     * @throws \RuntimeException se o template não estiver publicado
     */
    public function createFromTemplate(int $templateId, string $name, int $totalItems = 1): int
    {
        $template = new PdfTemplate();
        if (!$template->getFromDB($templateId)) {
            throw new \RuntimeException('Template não encontrado.');
        }

        if ($template->fields['status'] !== PdfTemplate::STATUS_PUBLISHED) {
            throw new \RuntimeException('O template deve estar publicado para criar documentos.');
        }

        $doc = new PdfDocument();
        $newId = $doc->add([
            'name'             => $name,
            'total_items'      => max(1, $totalItems),
            'pdf_templates_id' => $templateId,
            'template_version' => (int) $template->fields['version'],
            'entities_id'      => $_SESSION['glpiactive_entity'] ?? 0,
            'users_id_creator' => $_SESSION['glpiID'] ?? 0,
        ]);

        if ($newId === false) {
            throw new \RuntimeException('Falha ao criar documento.');
        }

        return (int) $newId;
    }

    /**
     * Preenche um campo do documento.
     */
    public function fillField(int $docId, int $fieldId, int $itemIndex, ?string $value): void
    {
        $this->repo->saveFilledField($docId, $fieldId, $itemIndex, $value);
    }

    /**
     * Seleciona um ativo GLPI para um item do documento e preenche
     * automaticamente os campos com binding keys.
     *
     * @return array<int, array{field_id: int, value: string|null}> campos preenchidos
     */
    public function selectAsset(int $docId, int $itemIndex, string $itemtype, int $itemsId): array
    {
        $doc = new PdfDocument();
        if (!$doc->getFromDB($docId)) {
            throw new \RuntimeException('Documento não encontrado.');
        }

        $fields = $this->templateRepo->getFields((int) $doc->fields['pdf_templates_id']);
        $filled = [];

        foreach ($fields as $field) {
            if (empty($field['binding_key'])) {
                continue;
            }

            $value = BindingKeyResolver::resolve($field['binding_key'], $itemtype, $itemsId);

            if ($value !== null) {
                $this->repo->saveFilledField($docId, (int) $field['id'], $itemIndex, $value);
                $filled[] = [
                    'field_id' => (int) $field['id'],
                    'value'    => $value,
                ];
            }
        }

        // Atualiza metadata com o ativo vinculado
        $metadata = $doc->getMetadata();
        $metadata['assignments'][$itemIndex] = [
            'itemtype'   => $itemtype,
            'items_id'   => $itemsId,
            'itemIndex'  => $itemIndex,
            'addedAt'    => date('c'),
        ];
        $doc->setMetadata($metadata);
        $doc->update([
            'id'       => $docId,
            'metadata' => $doc->fields['metadata'],
        ]);

        return $filled;
    }

    /**
     * Solicita a geração do PDF, enfileirando um job.
     *
     * @return int ID do job criado
     */
    public function requestGeneration(int $docId): int
    {
        $doc = new PdfDocument();
        if (!$doc->getFromDB($docId)) {
            throw new \RuntimeException('Documento não encontrado.');
        }

        if ($doc->fields['status'] !== PdfDocument::STATUS_IN_PROGRESS
            && $doc->fields['status'] !== PdfDocument::STATUS_ERROR
        ) {
            throw new \RuntimeException(
                sprintf('Status atual (%s) não permite geração de PDF.', $doc->fields['status'])
            );
        }

        $doc->transitionStatus(PdfDocument::STATUS_GENERATING);

        $queue = new PdfQueue();
        return $queue->enqueue($docId);
    }
}
