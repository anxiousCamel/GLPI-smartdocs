<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Modelo principal de Template PDF.
 *
 * Estende CommonDBTM para herdar CRUD, ACL, logs e histórico
 * automáticos do GLPI.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Templates;

use CommonDBTM;
use Dropdown;
use Html;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

final class PdfTemplate extends CommonDBTM
{
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED  = 'ARCHIVED';

    public static function getTypeName($nb = 0): string
    {
        return _n('Template PDF', 'Templates PDF', $nb, 'smartdocs');
    }

    /**
     * Sobrescreve o nome da tabela pois o GLPI derivaria
     * glpi_plugin_smartdocs_templates_pdftemplates.
     */
    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_smartdocs_pdf_templates';
    }

    /**
     * Sobrescreve a URL do formulário: o GLPI deriva a URL espelhando o
     * namespace completo (Templates\PdfTemplate -> front/templates/
     * pdftemplate.form.php), mas front/ deste plugin é plano.
     */
    public static function getFormURL($full = true): string
    {
        $dir = $full ? \Plugin::getWebDir('smartdocs') : '';

        return $dir . '/front/pdftemplate.form.php';
    }

    /**
     * Define os direitos necessários para cada operação.
     */
    public static function canCreate(): bool
    {
        return PermissionManager::canWriteTemplates();
    }

    public static function canView(): bool
    {
        return PermissionManager::canReadTemplates();
    }

    public static function canUpdate(): bool
    {
        return PermissionManager::canWriteTemplates();
    }

    public static function canDelete(): bool
    {
        return PermissionManager::canWriteTemplates();
    }

    public static function canPurge(): bool
    {
        return PermissionManager::canWriteTemplates();
    }

    /**
     * Publica o template: muda status para PUBLISHED e cria snapshot
     * dos campos em PdfTemplateVersion.
     *
     * @throws \RuntimeException se não houver campos ou já estiver publicado
     */
    public function publish(): void
    {
        if ($this->fields['status'] === self::STATUS_PUBLISHED) {
            throw new \RuntimeException('Template já está publicado.');
        }

        $fields = (new TemplateRepository())->getFields((int) $this->fields['id']);
        if ($fields === []) {
            throw new \RuntimeException('Não é possível publicar um template sem campos.');
        }

        $nextVersion = ((int) $this->fields['version']) + 1;

        $version = new PdfTemplateVersion();
        $version->add([
            'pdf_templates_id' => $this->fields['id'],
            'version'          => $nextVersion,
            'fields_snapshot'  => json_encode($fields),
        ]);

        $this->update([
            'id'      => $this->fields['id'],
            'status'  => self::STATUS_PUBLISHED,
            'version' => $nextVersion,
        ]);
    }

    /**
     * Arquiva o template (não permite mais criação de documentos).
     */
    public function archive(): void
    {
        $this->update([
            'id'     => $this->fields['id'],
            'status' => self::STATUS_ARCHIVED,
        ]);
    }

    /**
     * Duplica o template em estado DRAFT com os mesmos campos.
     */
    public function duplicate(): int
    {
        $input = $this->fields;
        unset($input['id'], $input['date_creation'], $input['date_mod']);

        $input['name']   = sprintf(__('Cópia de %s', 'smartdocs'), $input['name']);
        $input['status'] = self::STATUS_DRAFT;
        $input['version'] = 1;

        $newTemplate = new self();
        $newId = $newTemplate->add($input);

        if ($newId === false) {
            throw new \RuntimeException('Falha ao duplicar template.');
        }

        $repo = new TemplateRepository();
        $repo->duplicateFields((int) $this->fields['id'], $newId);

        return (int) $newId;
    }

    /**
     * Valida e normaliza o input antes da adição no banco.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        $input['status']  = self::STATUS_DRAFT;
        $input['version'] = 1;

        if (empty($input['name'])) {
            \Session::addMessageAfterRedirect(
                __('Nome do template é obrigatório.', 'smartdocs'),
                false,
                ERROR
            );
            return false;
        }

        $input['name'] = trim($input['name']);

        return $input;
    }

    /**
     * Valida e normaliza o input antes da atualização.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForUpdate($input)
    {
        if (isset($input['name'])) {
            $input['name'] = trim($input['name']);
        }

        return $input;
    }

    /**
     * Configuração das abas na tela de detalhes do template.
     */
    public function defineTabs($options = []): array
    {
        $tabs = [];
        $tabs[$this->getType() . '$main'] = self::getTypeName(1);

        return $tabs;
    }

    /**
     * Renderiza o formulário principal do template.
     */
    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Nome', 'smartdocs') . "</td>";
        echo "<td>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '']);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Modo de preenchimento', 'smartdocs') . "</td>";
        echo "<td>";
        echo Dropdown::showFromArray('fill_mode', [
            'single' => __('Único', 'smartdocs'),
            'repeat' => __('Repetição (grade)', 'smartdocs'),
        ], ['value' => $this->fields['fill_mode'] ?? 'single', 'display' => false]);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);

        return true;
    }

    /**
     * Badge colorido para o status.
     */
    public static function getStatusBadge(string $status): string
    {
        $map = [
            self::STATUS_DRAFT     => ['label' => __('Rascunho', 'smartdocs'),     'class' => 'bg-yellow-lt'],
            self::STATUS_PUBLISHED => ['label' => __('Publicado', 'smartdocs'),    'class' => 'bg-green-lt'],
            self::STATUS_ARCHIVED  => ['label' => __('Arquivado', 'smartdocs'),    'class' => 'bg-secondary-lt'],
        ];

        $item = $map[$status] ?? ['label' => $status, 'class' => 'bg-secondary-lt'];

        return sprintf('<span class="badge %s">%s</span>', $item['class'], $item['label']);
    }
}
