<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Configurações do plugin (provedor de OCR, etc). Persiste na tabela
 * glpi_plugin_smartdocs_configs via PluginConfigService.
 * ---------------------------------------------------------------------
 */

use GlpiPlugin\SmartDocs\Permissions\PermissionManager;
use GlpiPlugin\SmartDocs\Services\PluginConfigService;

include('../../../inc/includes.php');

Session::checkLoginUser();
PermissionManager::checkRight(PermissionManager::SMARTDOCS_ADMIN);

if (isset($_POST['update'])) {
    $provider = (string) ($_POST['ocr_provider'] ?? 'browser');
    if (!in_array($provider, ['browser', 'tesseract_local', 'external_api'], true)) {
        $provider = 'browser';
    }

    PluginConfigService::set('ocr_provider', $provider);
    PluginConfigService::set('ocr_api_url', (string) ($_POST['ocr_api_url'] ?? ''));
    PluginConfigService::set('ocr_api_key', (string) ($_POST['ocr_api_key'] ?? ''));

    Session::addMessageAfterRedirect(__('Configurações salvas.', 'smartdocs'), true, INFO);
    Html::back();
}

Html::header(
    __('Configurações', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'config'
);

$provider = PluginConfigService::get('ocr_provider', 'browser');
$apiUrl   = PluginConfigService::get('ocr_api_url', '');
$apiKey   = PluginConfigService::get('ocr_api_key', '');

echo "<div class='container-fluid py-3'>";
echo "<h2 class='mb-4'><i class='ti ti-settings me-2'></i>" . __('Configurações do SmartDocs', 'smartdocs') . "</h2>";

echo "<form method='post' action='" . htmlescape($_SERVER['PHP_SELF']) . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div class='card mb-4'>";
echo "<div class='card-header'><h3 class='card-title'>" . __('OCR — Leitura de etiquetas', 'smartdocs') . "</h3></div>";
echo "<table class='table'>";

echo "<tr class='tab_bg_1'><td>" . __('Provedor', 'smartdocs') . "</td><td>";
Dropdown::showFromArray('ocr_provider', [
    'browser'         => __('Navegador (WebAssembly, padrão, sem dependências no servidor)', 'smartdocs'),
    'tesseract_local' => __('Tesseract local (requer tesseract-ocr instalado no servidor)', 'smartdocs'),
    'external_api'    => __('API externa', 'smartdocs'),
], ['value' => $provider]);
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('URL da API (apenas para provedor externo)', 'smartdocs') . "</td><td>";
echo Html::input('ocr_api_url', ['value' => $apiUrl, 'size' => 50]);
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Chave da API (apenas para provedor externo)', 'smartdocs') . "</td><td>";
echo Html::input('ocr_api_key', ['value' => $apiKey, 'size' => 50, 'type' => 'password']);
echo "</td></tr>";

echo "</table>";
echo "</div>";

echo "<div class='mb-4'>";
echo Html::submit(__('Salvar', 'smartdocs'), ['name' => 'update', 'class' => 'btn btn-primary']);
echo "</div>";

echo "</form>";
echo "</div>";

Html::footer();
