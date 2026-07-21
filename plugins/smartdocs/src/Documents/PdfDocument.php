<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Modelo principal de Documento PDF gerado a partir de um template.
 *
 * Estende CommonDBTM para herdar CRUD, ACL, logs e histórico
 * automáticos do GLPI.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Documents;

use CommonDBTM;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

final class PdfDocument extends CommonDBTM
{
    public const STATUS_DRAFT       = 'DRAFT';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_GENERATING  = 'GENERATING';
    public const STATUS_GENERATED   = 'GENERATED';
    public const STATUS_ERROR       = 'ERROR';

    public static function getTypeName($nb = 0): string
    {
        return _n('Documento PDF', 'Documentos PDF', $nb, 'smartdocs');
    }

    /**
     * Sobrescreve o nome da tabela para corresponder ao schema definido.
     */
    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_pdf_documents';
    }

    /**
     * Sobrescreve a URL do formulário: o GLPI deriva a URL espelhando o
     * namespace completo (Documents\PdfDocument -> front/documents/
     * pdfdocument.form.php), mas front/ deste plugin é plano.
     */
    public static function getFormURL($full = true): string
    {
        $dir = $full ? \Plugin::getWebDir('smartdocs') : '';

        return $dir . '/front/pdfdocument.form.php';
    }

    /**
     * Mesma razão do getFormURL(): sem isso, Search::show() gera o link
     * "Limpar"/reset e o botão de novo item apontando para
     * front/documents/pdfdocument.php (derivado do namespace), que não
     * existe.
     */
    public static function getSearchURL($full = true): string
    {
        $dir = $full ? \Plugin::getWebDir('smartdocs') : '';

        return $dir . '/front/pdfdocument.php';
    }

    public static function canCreate(): bool
    {
        return PermissionManager::canWriteDocuments();
    }

    public static function canView(): bool
    {
        return PermissionManager::canReadDocuments();
    }

    public static function canUpdate(): bool
    {
        return PermissionManager::canWriteDocuments();
    }

    public static function canDelete(): bool
    {
        return PermissionManager::canWriteDocuments();
    }

    public static function canPurge(): bool
    {
        return PermissionManager::canWriteDocuments();
    }

    /**
     * Transições de status válidas.
     */
    public static function getValidStatusTransitions(): array
    {
        return [
            self::STATUS_DRAFT       => [self::STATUS_IN_PROGRESS],
            self::STATUS_IN_PROGRESS => [self::STATUS_GENERATING],
            self::STATUS_GENERATING  => [self::STATUS_GENERATED, self::STATUS_ERROR],
            self::STATUS_ERROR       => [self::STATUS_GENERATING],
        ];
    }

    /**
     * Verifica se uma transição de status é válida.
     */
    public static function isValidStatusTransition(string $from, string $to): bool
    {
        $transitions = self::getValidStatusTransitions();

        return isset($transitions[$from]) && in_array($to, $transitions[$from], true);
    }

    /**
     * Decodifica o campo metadata JSON.
     */
    public function getMetadata(): array
    {
        $raw = $this->fields['metadata'] ?? '{}';
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Define o metadata do documento.
     */
    public function setMetadata(array $metadata): void
    {
        $this->fields['metadata'] = json_encode($metadata);
    }

    /**
     * Atualiza o status do documento com validação de transição.
     *
     * @throws \RuntimeException se a transição for inválida
     */
    public function transitionStatus(string $newStatus): void
    {
        $currentStatus = $this->fields['status'];

        if ($currentStatus === $newStatus) {
            return;
        }

        if (!self::isValidStatusTransition($currentStatus, $newStatus)) {
            throw new \RuntimeException(
                sprintf(
                    'Transição de status inválida: %s → %s',
                    $currentStatus,
                    $newStatus
                )
            );
        }

        $this->update([
            'id'     => $this->fields['id'],
            'status' => $newStatus,
        ]);
    }

    /**
     * Valida o input antes da adição.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        if (empty($input['name'])) {
            \Session::addMessageAfterRedirect(
                __('Nome do documento é obrigatório.', 'smartdocs'),
                false,
                ERROR
            );
            return false;
        }

        $input['name'] = trim($input['name']);
        $input['status'] = self::STATUS_DRAFT;

        if (!isset($input['total_items']) || (int) $input['total_items'] < 1) {
            $input['total_items'] = 1;
        }

        $input['metadata'] = json_encode([
            'assignments'       => [],
            'assignmentHistory' => [],
            'nextItemIndex'     => 0,
            'populateFilter'    => [],
        ]);

        return $input;
    }

    /**
     * Configuração das abas na tela de detalhes.
     */
    public function defineTabs($options = []): array
    {
        $tabs = [];
        $tabs[$this->getType() . '$main'] = self::getTypeName(1);

        return $tabs;
    }

    /**
     * Badge colorido para o status.
     */
    public static function getStatusBadge(string $status): string
    {
        $map = [
            self::STATUS_DRAFT       => ['label' => __('Rascunho', 'smartdocs'),       'class' => 'bg-yellow-lt'],
            self::STATUS_IN_PROGRESS => ['label' => __('Em andamento', 'smartdocs'),   'class' => 'bg-blue-lt'],
            self::STATUS_GENERATING  => ['label' => __('Gerando PDF...', 'smartdocs'), 'class' => 'bg-orange-lt'],
            self::STATUS_GENERATED   => ['label' => __('Gerado', 'smartdocs'),         'class' => 'bg-green-lt'],
            self::STATUS_ERROR       => ['label' => __('Erro', 'smartdocs'),           'class' => 'bg-red-lt'],
        ];

        $item = $map[$status] ?? ['label' => $status, 'class' => 'bg-secondary-lt'];

        return sprintf('<span class="badge %s">%s</span>', $item['class'], $item['label']);
    }
}
