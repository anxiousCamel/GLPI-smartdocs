<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Controller da página inicial do plugin.
 *
 * Exibe o painel de status do SmartDocs com checklist de setup,
 * banner de boas-vindas e atalhos para os módulos disponíveis.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Controllers;

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\SmartDocs\GlpiCompat\GlpiVersion;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;
use GlpiPlugin\SmartDocs\Services\SetupChecklistService;
use Plugin;

final class DashboardController
{
    public function show(): void
    {
        $checklist = (new SetupChecklistService())->runAll();

        TemplateRenderer::getInstance()->display('@smartdocs/dashboard.html.twig', [
            'plugin_version'       => PLUGIN_SMARTDOCS_VERSION,
            'glpi_version'         => GlpiVersion::current(),
            'glpi_supported'       => GlpiVersion::isSupported(),
            'php_version'          => PHP_VERSION,
            'php_ok'               => version_compare(PHP_VERSION, '8.2', '>='),
            'checklist'            => $checklist,
            'show_welcome_banner'  => $this->shouldShowWelcomeBanner(),
            'modules'              => $this->availableModules(),
            'webdir'               => Plugin::getWebDir('smartdocs', false),
        ]);
    }

    /**
     * Verifica se o banner de boas-vindas deve ser exibido.
     * Persiste a escolha do usuário via display preference genérica.
     */
    private function shouldShowWelcomeBanner(): bool
    {
        // Simplificado: sempre mostra para novos usuários até que
        // o checklist esteja 100% resolvido. Em produção pode usar
        // Config::getConfigurationValues ou UserPreference.
        $checklist = (new SetupChecklistService())->runAll();
        return !$checklist['all_ok'];
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
                'url'         => Plugin::getWebDir('smartdocs', false) . '/front/pdftemplate.php',
                'icon'        => 'ti ti-layout-collage',
            ];
        }

        if (PermissionManager::canReadDocuments()) {
            $modules[] = [
                'title'       => __('Documentos', 'smartdocs'),
                'description' => __('Preenchimento e geração de documentos PDF', 'smartdocs'),
                'url'         => Plugin::getWebDir('smartdocs', false) . '/front/pdfdocument.php',
                'icon'        => 'ti ti-files',
            ];
        }

        if (PermissionManager::canAdmin()) {
            $modules[] = [
                'title'       => __('Configurações', 'smartdocs'),
                'description' => __('Provedor de OCR, limites e integrações', 'smartdocs'),
                'url'         => Plugin::getWebDir('smartdocs', false) . '/front/config.form.php',
                'icon'        => 'ti ti-settings',
            ];
        }

        return $modules;
    }
}