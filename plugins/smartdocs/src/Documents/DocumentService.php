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
use GlpiPlugin\SmartDocs\Templates\TemplatePaginator;
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
     * Popula automaticamente um documento com ativos do GLPI filtrados
     * por tipo e localização. Força quebra de página a cada troca de
     * localização preenchendo slots vazios.
     *
     * @param int   $docId  ID do PdfDocument
     * @param array $filter ['itemtype' => string, 'locations_id' => int|null]
     *
     * @return array{total_items:int,total_pages:int,assignments:array}
     */
    public function populate(int $docId, array $filter): array
    {
        $doc = new PdfDocument();
        if (!$doc->getFromDB($docId)) {
            throw new \RuntimeException('Documento não encontrado.');
        }

        $fields = $this->templateRepo->getFields((int) $doc->fields['pdf_templates_id']);
        $itemsPerPage = TemplatePaginator::itemsPerPage($fields);

        if ($itemsPerPage === 0) {
            throw new \RuntimeException(
                __('Template sem slots de equipamento configurados.', 'smartdocs')
            );
        }

        $itemtype = $filter['itemtype'] ?? '';
        $locationsId = isset($filter['locations_id']) && $filter['locations_id'] !== ''
            ? (int) $filter['locations_id']
            : null;

        if (empty($itemtype) || !class_exists($itemtype)) {
            throw new \RuntimeException(
                __('Tipo de ativo inválido.', 'smartdocs')
            );
        }

        $queryService = new EquipmentQueryService();
        $equipments = $queryService->query(
            $itemtype,
            (int) $doc->fields['entities_id'],
            $locationsId
        );

        if ($equipments === []) {
            throw new \RuntimeException(
                __('Nenhum equipamento encontrado com os filtros selecionados.', 'smartdocs')
            );
        }

        // Pad: força nova página a cada troca de localização
        $padded = $this->padByLocation($equipments, $itemsPerPage);
        $totalItems = count($padded);

        $paginator = TemplatePaginator::compute($fields, $totalItems);
        $assignments = [];

        foreach ($paginator['assignments'] as $assignment) {
            $itemIndex = $assignment['itemIndex'];
            $equipment = $padded[$itemIndex];

            // Slot vazio (padding) → não preenche campos de item, mas conta na paginação
            if ($equipment === null) {
                continue;
            }

            $assignments[$itemIndex] = [
                'itemtype'     => $itemtype,
                'items_id'     => $equipment['id'],
                'locationName' => $equipment['locationName'],
                'name'         => $equipment['name'],
            ];

            foreach ($fields as $field) {
                if (empty($field['binding_key'])) {
                    continue;
                }

                $scope = $field['scope'] ?? 'global';

                // Campos globais: só preenchemos uma vez (itemIndex = 0)
                if ($scope === 'global') {
                    if ($itemIndex !== 0) {
                        continue;
                    }
                    $value = BindingKeyResolver::resolve(
                        $field['binding_key'],
                        $itemtype,
                        $equipment['id']
                    );
                    if ($value !== null) {
                        $this->repo->saveFilledField($docId, (int) $field['id'], 0, $value);
                    }
                    continue;
                }

                // Campos de item: preenchemos para cada assignment
                $value = BindingKeyResolver::resolve(
                    $field['binding_key'],
                    $itemtype,
                    $equipment['id']
                );
                if ($value !== null) {
                    $this->repo->saveFilledField($docId, (int) $field['id'], $itemIndex, $value);
                }
            }
        }

        // Atualiza documento
        $metadata = $doc->getMetadata();
        $metadata['assignments'] = array_values($assignments);
        $metadata['itemsPerPage'] = $itemsPerPage;
        $metadata['totalPages'] = $paginator['totalPages'];
        $metadata['populateFilter'] = [
            'itemtype'     => $itemtype,
            'locations_id' => $locationsId,
        ];

        $doc->setMetadata($metadata);
        $doc->update([
            'id'          => $docId,
            'total_items' => $totalItems,
            'metadata'    => $doc->fields['metadata'],
            'status'      => PdfDocument::STATUS_IN_PROGRESS,
        ]);

        return [
            'total_items' => $totalItems,
            'total_pages' => $paginator['totalPages'],
            'assignments' => array_values($assignments),
        ];
    }

    /**
     * Força quebra de página a cada troca de localização preenchendo
     * slots vazios até o próximo múltiplo de itemsPerPage.
     *
     * @param array $equipments   Equipamentos ordenados por locationName
     * @param int   $itemsPerPage Slots por página
     *
     * @return array<int, array|null> Equipamentos com nulls de padding
     */
    private function padByLocation(array $equipments, int $itemsPerPage): array
    {
        $result = [];
        $currentLoc = null;
        $countInLoc = 0;

        foreach ($equipments as $eq) {
            $loc = $eq['locationName'] ?? '';
            if ($loc !== $currentLoc) {
                // Preenche slots vazios da localização anterior
                if ($currentLoc !== null && $countInLoc > 0) {
                    $remainder = $countInLoc % $itemsPerPage;
                    if ($remainder !== 0) {
                        for ($i = 0; $i < $itemsPerPage - $remainder; $i++) {
                            $result[] = null;
                        }
                    }
                }
                $currentLoc = $loc;
                $countInLoc = 0;
            }
            $result[] = $eq;
            $countInLoc++;
        }

        return $result;
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
