<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Controller da página inicial do plugin.
 *
 * Exibe o painel de status do SmartDocs e os atalhos para os módulos
 * disponíveis conforme as permissões do usuário corrente.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Controllers;

use GlpiPlugin\SmartDocs\GlpiCompat\GlpiVersion;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;

final class DashboardController
{
    /**
     * Renderiza o painel inicial do plugin.
     */
    public function show(): void
    {
        echo "<div class='container-fluid py-3'>";

        $this->renderHeader();
        $this->renderStatusCards();
        $this->renderModuleCards();

        echo "</div>";
    }

    private function renderHeader(): void
    {
        echo "<div class='row mb-4'>";
        echo "<div class='col'>";
        echo "<h2 class='d-flex align-items-center gap-2'>";
        echo "<i class='ti ti-file-description'></i> SmartDocs";
        echo "<span class='badge bg-blue-lt ms-2'>v" . htmlescape(PLUGIN_SMARTDOCS_VERSION) . "</span>";
        echo "</h2>";
        echo "<p class='text-muted mb-0'>"
            . htmlescape(__('Documentação técnica, digitalização inteligente e gestão de preventivas dentro do GLPI.', 'smartdocs'))
            . "</p>";
        echo "</div>";
        echo "</div>";
    }

    private function renderStatusCards(): void
    {
        $status_items = [
            [
                'label' => __('Versão do GLPI', 'smartdocs'),
                'value' => GlpiVersion::current(),
                'ok'    => GlpiVersion::isSupported(),
            ],
            [
                'label' => __('Versão do PHP', 'smartdocs'),
                'value' => PHP_VERSION,
                'ok'    => version_compare(PHP_VERSION, '8.2', '>='),
            ],
        ];

        echo "<div class='row row-deck mb-4'>";
        foreach ($status_items as $item) {
            $badge_class = $item['ok'] ? 'bg-green-lt' : 'bg-red-lt';
            $badge_text  = $item['ok'] ? __('OK', 'smartdocs') : __('Incompatível', 'smartdocs');

            echo "<div class='col-sm-6 col-lg-3'>";
            echo "<div class='card'><div class='card-body'>";
            echo "<div class='subheader'>" . htmlescape($item['label']) . "</div>";
            echo "<div class='h3 mb-1'>" . htmlescape($item['value']) . "</div>";
            echo "<span class='badge {$badge_class}'>" . htmlescape($badge_text) . "</span>";
            echo "</div></div>";
            echo "</div>";
        }
        echo "</div>";
    }

    private function renderModuleCards(): void
    {
        $modules = $this->availableModules();

        if ($modules === []) {
            return;
        }

        echo "<div class='row row-deck'>";
        foreach ($modules as $module) {
            echo "<div class='col-sm-6 col-lg-4'>";
            echo "<a class='card card-link' href='" . htmlescape($module['url']) . "'>";
            echo "<div class='card-body'>";
            echo "<div class='d-flex align-items-center gap-3'>";
            echo "<span class='avatar bg-primary-lt'><i class='" . htmlescape($module['icon']) . "'></i></span>";
            echo "<div>";
            echo "<div class='fw-bold'>" . htmlescape($module['title']) . "</div>";
            echo "<div class='text-muted small'>" . htmlescape($module['description']) . "</div>";
            echo "</div>";
            echo "</div>";
            echo "</div></a>";
            echo "</div>";
        }
        echo "</div>";
    }

    /**
     * Módulos visíveis para o usuário corrente.
     *
     * @return array<int, array{title: string, description: string, url: string, icon: string}>
     */
    private function availableModules(): array
    {
        $modules = [];

        if (PermissionManager::canReadTemplates()) {
            $modules[] = [
                'title'       => __('Templates PDF', 'smartdocs'),
                'description' => __('Editor visual de templates de documentos', 'smartdocs'),
                'url'         => '/plugins/smartdocs/front/pdftemplate.php',
                'icon'        => 'ti ti-layout-collage',
            ];
        }

        if (PermissionManager::canReadDocuments()) {
            $modules[] = [
                'title'       => __('Documentos', 'smartdocs'),
                'description' => __('Preenchimento e geração de documentos PDF', 'smartdocs'),
                'url'         => '/plugins/smartdocs/front/pdfdocument.php',
                'icon'        => 'ti ti-files',
            ];
        }

        if (PermissionManager::canAdmin()) {
            $modules[] = [
                'title'       => __('Configurações', 'smartdocs'),
                'description' => __('Provedor de OCR, limites e integrações', 'smartdocs'),
                'url'         => '/plugins/smartdocs/front/config.form.php',
                'icon'        => 'ti ti-settings',
            ];
        }

        return $modules;
    }
}