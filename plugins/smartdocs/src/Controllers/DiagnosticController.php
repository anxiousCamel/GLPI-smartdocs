<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Controller da página de diagnóstico do plugin.
 * Exibe versões, status do cron, contadores e permissões.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Controllers;

use GlpiPlugin\SmartDocs\GlpiCompat\GlpiVersion;
use GlpiPlugin\SmartDocs\Permissions\PermissionManager;
use GlpiPlugin\SmartDocs\Services\SetupChecklistService;
use Plugin;
use CronTask;
use ProfileRight;

final class DiagnosticController
{
    public function show(): void
    {
        if (!PermissionManager::canAdmin()) {
            \Session::addMessageAfterRedirect(
                __('Acesso negado. Apenas administradores.', 'smartdocs'),
                false,
                ERROR
            );
            return;
        }

        $checklist = (new SetupChecklistService())->runAll();

        $data = [
            'plugin_version'    => PLUGIN_SMARTDOCS_VERSION,
            'glpi_version'      => GlpiVersion::current(),
            'glpi_supported'    => GlpiVersion::isSupported(),
            'php_version'       => PHP_VERSION,
            'php_ok'            => version_compare(PHP_VERSION, '8.2', '>='),
            'plugin_dir'        => Plugin::getPhpDir('smartdocs'),
            'composer_ok'       => file_exists(Plugin::getPhpDir('smartdocs') . '/vendor/autoload.php'),
            'checklist'         => $checklist,
            'counters'          => $this->getCounters(),
            'cron_info'         => $this->getCronInfo(),
            'profile_summary'   => $this->getProfileSummary(),
            'exts'              => $this->getExtensionsStatus(),
            'webdir'            => Plugin::getWebDir('smartdocs'),
        ];

        \Glpi\Application\View\TemplateRenderer::getInstance()->display(
            '@smartdocs/diagnostic.html.twig',
            $data
        );
    }

    private function getCounters(): array
    {
        /** @var \DBmysql $DB */
        global $DB;
        $counters = [];

        foreach ([
            'templates'    => 'glpi_plugin_smartdocs_pdf_templates',
            'documents'    => 'glpi_plugin_smartdocs_pdf_documents',
            'wiki'         => 'glpi_plugin_smartdocs_wiki_documents',
            'library'      => 'glpi_plugin_smartdocs_technical_files',
            'ocr_results'  => 'glpi_plugin_smartdocs_ocr_results',
            'pdf_jobs'     => 'glpi_plugin_smartdocs_pdf_jobs',
        ] as $label => $table) {
            $cnt = 0;
            if ($DB->tableExists($table)) {
                $cnt = (int) ($DB->request(['COUNT' => 'cnt', 'FROM' => $table])->current()['cnt'] ?? 0);
            }
            $counters[$label] = $cnt;
        }

        $pendingJobs = 0;
        if ($DB->tableExists('glpi_plugin_smartdocs_pdf_jobs')) {
            $pendingJobs = (int) ($DB->request([
                'COUNT' => 'cnt',
                'FROM'  => 'glpi_plugin_smartdocs_pdf_jobs',
                'WHERE' => ['status' => ['PENDING', 'PROCESSING']],
            ])->current()['cnt'] ?? 0);
        }
        $counters['pending_jobs'] = $pendingJobs;

        return $counters;
    }

    private function getCronInfo(): array
    {
        /** @var \DBmysql $DB */
        global $DB;
        $row = $DB->request([
            'FROM'  => CronTask::getTable(),
            'WHERE' => ['name' => 'SmartDocsPdfQueue'],
        ])->current();

        if (!$row) {
            return ['exists' => false];
        }

        return [
            'exists'      => true,
            'state'       => (int) $row['state'],
            'lastrun'     => $row['lastrun'],
            'frequency'   => (int) $row['frequency'],
            'mode'        => (int) $row['mode'],
            'itemtype'    => $row['itemtype'],
        ];
    }

    private function getProfileSummary(): array
    {
        /** @var \DBmysql $DB */
        global $DB;
        $rows = $DB->request([
            'SELECT' => ['profiles_id', 'rights'],
            'FROM'   => ProfileRight::getTable(),
            'WHERE'  => ['name' => PermissionManager::RIGHT_NAME],
        ]);

        $summary = [];
        foreach ($rows as $row) {
            $profile = new \Profile();
            if ($profile->getFromDB($row['profiles_id'])) {
                $summary[] = [
                    'profile_name' => $profile->fields['name'],
                    'rights'       => (int) $row['rights'],
                ];
            }
        }
        return $summary;
    }

    private function getExtensionsStatus(): array
    {
        $exts = ['gd', 'mbstring', 'curl', 'json', 'zip'];
        $status = [];
        foreach ($exts as $ext) {
            $status[$ext] = extension_loaded($ext);
        }
        return $status;
    }
}