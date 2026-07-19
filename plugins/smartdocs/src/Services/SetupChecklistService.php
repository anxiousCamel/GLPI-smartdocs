<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Verificações de saúde do plugin para onboarding de novos usuários.
 * Detecta o que falta configurar e retorna itens acionáveis.
 * ---------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Services;

use GlpiPlugin\SmartDocs\Permissions\PermissionManager;
use Plugin;
use ProfileRight;
use CronTask;

final class SetupChecklistService
{
    /**
     * Executa todas as verificações e retorna array estruturado.
     *
     * @return array<string, mixed>
     */
    public function runAll(): array
    {
        $items = [
            $this->checkComposerDeps(),
            $this->checkPhpExtensions(),
            $this->checkDatabaseTables(),
            $this->checkProfileRights(),
            $this->checkCronTask(),
            $this->checkPublishedTemplates(),
            $this->checkOcrConfig(),
        ];

        $pending = array_filter($items, static fn(array $i): bool => !$i['ok']);
        $ok      = array_filter($items, static fn(array $i): bool => $i['ok']);

        return [
            'all_ok'     => $pending === [],
            'pending'    => array_values($pending),
            'ok'         => array_values($ok),
            'total'      => count($items),
            'pending_count' => count($pending),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkComposerDeps(): array
    {
        $ok = file_exists(Plugin::getPhpDir('smartdocs') . '/vendor/autoload.php');
        return [
            'key'          => 'composer_deps',
            'label'        => __('Dependências Composer', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? __('Todas as dependências PHP estão instaladas.', 'smartdocs')
                : __('As dependências do Composer não foram instaladas. O menu SmartDocs não aparece até isso ser feito.', 'smartdocs'),
            'action_url'   => $ok ? null : Plugin::getWebDir('smartdocs', false) . '/front/config.setup.php',
            'action_label' => $ok ? null : __('Ver como instalar', 'smartdocs'),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkPhpExtensions(): array
    {
        $required = ['gd', 'mbstring', 'curl', 'json', 'zip'];
        $missing  = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        $ok = $missing === [];
        return [
            'key'          => 'php_extensions',
            'label'        => __('Extensões PHP', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? __('Todas as extensões obrigatórias estão habilitadas.', 'smartdocs')
                : sprintf(__('Extensões ausentes: %s. Contate o administrador do servidor.', 'smartdocs'), implode(', ', $missing)),
            'action_url'   => null,
            'action_label' => null,
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkDatabaseTables(): array
    {
        /** @var \DBmysql $DB */
        global $DB;
        $ok = $DB->tableExists('glpi_plugin_smartdocs_configs');
        return [
            'key'          => 'database_tables',
            'label'        => __('Tabelas do banco', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? __('Tabelas do SmartDocs criadas corretamente.', 'smartdocs')
                : __('As tabelas do plugin não foram criadas. Reinstale o plugin em Configuração → Plugins.', 'smartdocs'),
            'action_url'   => $ok ? null : '/front/plugin.php',
            'action_label' => $ok ? null : __('Ir para Plugins', 'smartdocs'),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkProfileRights(): array
    {
        $ok = false;
        $count = 0;

        /** @var \DBmysql $DB */
        global $DB;
        $result = $DB->request([
            'COUNT' => 'cnt',
            'FROM'  => ProfileRight::getTable(),
            'WHERE' => ['name' => PermissionManager::RIGHT_NAME],
        ]);
        $count = (int) ($result->current()['cnt'] ?? 0);
        $ok = $count > 0;

        return [
            'key'          => 'profile_rights',
            'label'        => __('Permissões por perfil', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? sprintf(__('Direitos SmartDocs configurados em %d perfil(is).', 'smartdocs'), $count)
                : __('Nenhum perfil possui direitos do SmartDocs. Os usuários não conseguem acessar o plugin.', 'smartdocs'),
            'action_url'   => $ok ? null : '/front/profile.php',
            'action_label' => $ok ? null : __('Configurar perfis', 'smartdocs'),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkCronTask(): array
    {
        $ok = false;
        $lastRun = null;

        /** @var \DBmysql $DB */
        global $DB;
        $result = $DB->request([
            'FROM'  => CronTask::getTable(),
            'WHERE' => ['name' => 'SmartDocsPdfQueue'],
        ]);
        if ($row = $result->current()) {
            $ok = !empty($row['lastrun']) && strtotime((string) $row['lastrun']) > (time() - 600);
            $lastRun = $row['lastrun'];
        }

        return [
            'key'          => 'cron_task',
            'label'        => __('Tarefa automática (Cron)', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? sprintf(__('Cron ativo. Última execução: %s.', 'smartdocs'), $lastRun)
                : __('A tarefa SmartDocsPdfQueue não foi executada nos últimos 10 minutos. A geração de PDF ficará pendente.', 'smartdocs'),
            'action_url'   => $ok ? null : '/front/crontask.php',
            'action_label' => $ok ? null : __('Ver tarefas automáticas', 'smartdocs'),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkPublishedTemplates(): array
    {
        $ok = false;
        $count = 0;

        /** @var \DBmysql $DB */
        global $DB;
        $result = $DB->request([
            'COUNT' => 'cnt',
            'FROM'  => 'glpi_plugin_smartdocs_pdf_templates',
            'WHERE' => ['status' => 'PUBLISHED'],
        ]);
        $count = (int) ($result->current()['cnt'] ?? 0);
        $ok = $count > 0;

        return [
            'key'          => 'published_templates',
            'label'        => __('Templates publicados', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? sprintf(__('%d template(s) publicado(s) disponível(is) para uso.', 'smartdocs'), $count)
                : __('Nenhum template publicado ainda. Sem templates, não é possível gerar documentos.', 'smartdocs'),
            'action_url'   => $ok ? null : Plugin::getWebDir('smartdocs', false) . '/front/pdftemplate.form.php',
            'action_label' => $ok ? null : __('Criar primeiro template', 'smartdocs'),
        ];
    }

    /**
     * @return array{key: string, label: string, ok: bool, message: string, action_url?: string, action_label?: string}
     */
    public function checkOcrConfig(): array
    {
        /** @var \DBmysql $DB */
        global $DB;
        $provider = 'browser';
        $row = $DB->request([
            'FROM'  => 'glpi_plugin_smartdocs_configs',
            'WHERE' => ['name' => 'ocr_provider'],
        ])->current();
        if ($row) {
            $provider = (string) $row['value'];
        }
        $ok = in_array($provider, ['browser', 'tesseract_local', 'external_api'], true);

        return [
            'key'          => 'ocr_config',
            'label'        => __('Configuração do OCR', 'smartdocs'),
            'ok'           => $ok,
            'message'      => $ok
                ? sprintf(__('Provedor OCR configurado: %s.', 'smartdocs'), $provider)
                : __('O provedor de OCR não está configurado. O scanner de etiquetas pode não funcionar.', 'smartdocs'),
            'action_url'   => $ok ? null : Plugin::getWebDir('smartdocs', false) . '/front/config.form.php',
            'action_label' => $ok ? null : __('Configurar OCR', 'smartdocs'),
        ];
    }
}