<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Página exibida quando as dependências do Composer não estão instaladas.
 * Acessada via Configurar → Plugins → SmartDocs → Configurar.
 * Também útil como página de ajuda de instalação independente.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', READ);

Html::header('SmartDocs — Configuração', $_SERVER['PHP_SELF'], 'config', 'plugin');

$plugin_dir  = Plugin::getPhpDir('smartdocs');
$web_dir     = Plugin::getWebDir('smartdocs', false);
$composer_ok = file_exists($plugin_dir . '/vendor/autoload.php');
$php_ok      = version_compare(PHP_VERSION, '8.2', '>=');

$required_exts = ['gd', 'mbstring', 'curl', 'json', 'zip'];
$missing_exts  = [];
foreach ($required_exts as $ext) {
    if (!extension_loaded($ext)) {
        $missing_exts[] = $ext;
    }
}

echo '<div class="container-fluid mt-4">';

// Header
echo '<div class="page-header mb-4">';
echo '<h1 class="page-title">';
echo '<i class="ti ti-help-circle me-2"></i>';
echo __('SmartDocs — Ajuda de Instalação', 'smartdocs');
echo '</h1>';
echo '</div>';

// Composer status card
if (!$composer_ok) {
    echo '<div class="alert alert-danger mb-4">';
    echo '<h4 class="alert-title"><i class="ti ti-alert-triangle me-2"></i>' . __('Dependências PHP não instaladas', 'smartdocs') . '</h4>';
    echo '<p>' . __('O plugin SmartDocs requer as dependências do Composer para funcionar. Sem elas, o menu SmartDocs não aparece no GLPI.', 'smartdocs') . '</p>';
    echo '<h5 class="mt-3">' . __('Como instalar:', 'smartdocs') . '</h5>';
    echo '<ol>';
    echo '<li>' . __('Abra um terminal no servidor', 'smartdocs') . '</li>';
    echo '<li>' . __('Navegue até a pasta do plugin:', 'smartdocs') . '</li>';
    echo '</ol>';
    echo '<div class="bg-dark text-light p-3 rounded mb-3">';
    echo '<code class="d-block">cd ' . htmlescape($plugin_dir) . '</code>';
    echo '<code class="d-block">composer install --no-dev --optimize-autoloader</code>';
    echo '</div>';
    echo '<ol start="3">';
    echo '<li>' . __('Recarregue o GLPI no navegador', 'smartdocs') . '</li>';
    echo '<li>' . __('Acesse Configuração → Plugins e verifique se o SmartDocs aparece como Ativo', 'smartdocs') . '</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<div class="alert alert-success mb-4">';
    echo '<i class="ti ti-check me-2"></i>';
    echo __('Dependências do Composer instaladas corretamente.', 'smartdocs');
    echo '</div>';
}

// Prerequisites table
echo '<div class="card mb-4">';
echo '<div class="card-header"><h3 class="card-title">' . __('Pré-requisitos', 'smartdocs') . '</h3></div>';
echo '<div class="card-body">';
echo '<table class="table table-bordered table-sm w-auto">';
echo '<thead><tr><th>' . __('Requisito', 'smartdocs') . '</th><th>' . __('Mínimo', 'smartdocs') . '</th><th>' . __('Status', 'smartdocs') . '</th></tr></thead>';
echo '<tbody>';

echo '<tr>';
echo '<td>PHP</td><td>8.2</td>';
echo '<td><span class="badge ' . ($php_ok ? 'bg-green-lt' : 'bg-red-lt') . '">' . ($php_ok ? htmlescape(PHP_VERSION) : PHP_VERSION) . '</span></td>';
echo '</tr>';

foreach ($required_exts as $ext) {
    $loaded = extension_loaded($ext);
    echo '<tr>';
    echo '<td>ext-' . htmlescape($ext) . '</td><td>—</td>';
    echo '<td><span class="badge ' . ($loaded ? 'bg-green-lt' : 'bg-red-lt') . '">' . ($loaded ? __('OK', 'smartdocs') : __('Ausente', 'smartdocs')) . '</span></td>';
    echo '</tr>';
}

echo '<tr>';
echo '<td>Composer</td><td>2.x</td>';
echo '<td><span class="badge ' . ($composer_ok ? 'bg-green-lt' : 'bg-red-lt') . '">' . ($composer_ok ? __('OK', 'smartdocs') : __('Faltando', 'smartdocs')) . '</span></td>';
echo '</tr>';

echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</div>';

// Quick install steps
echo '<div class="card mb-4">';
echo '<div class="card-header"><h3 class="card-title">' . __('Passo a passo rápido', 'smartdocs') . '</h3></div>';
echo '<div class="card-body">';
echo '<div class="steps">';
echo '<div class="step-item">';
echo '<div class="h4">' . __('1. Instalar dependências PHP', 'smartdocs') . '</div>';
echo '<p class="text-muted">' . __('Execute composer install na pasta do plugin.', 'smartdocs') . '</p>';
echo '</div>';
echo '<div class="step-item">';
echo '<div class="h4">' . __('2. Ativar no GLPI', 'smartdocs') . '</div>';
echo '<p class="text-muted">' . __('Vá em Configuração → Plugins → SmartDocs → Instalar → Ativar.', 'smartdocs') . '</p>';
echo '</div>';
echo '<div class="step-item">';
echo '<div class="h4">' . __('3. Configurar permissões', 'smartdocs') . '</div>';
echo '<p class="text-muted">' . __('Administração → Perfis → [perfil] → aba SmartDocs.', 'smartdocs') . '</p>';
echo '</div>';
echo '<div class="step-item">';
echo '<div class="h4">' . __('4. Configurar OCR (opcional)', 'smartdocs') . '</div>';
echo '<p class="text-muted">' . __('SmartDocs → Configurações → escolha o provedor de OCR.', 'smartdocs') . '</p>';
echo '</div>';
echo '<div class="step-item">';
echo '<div class="h4">' . __('5. Criar primeiro template', 'smartdocs') . '</div>';
echo '<p class="text-muted">' . __('SmartDocs → Templates PDF → Adicionar → upload de PDF base → posicione campos → Publicar.', 'smartdocs') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Troubleshooting
echo '<div class="card mb-4">';
echo '<div class="card-header"><h3 class="card-title">' . __('Problemas comuns', 'smartdocs') . '</h3></div>';
echo '<div class="card-body">';
echo '<dl>';
echo '<dt>' . __('Menu SmartDocs não aparece', 'smartdocs') . '</dt>';
echo '<dd>' . __('Execute composer install na pasta do plugin. Sem vendor/autoload.php os hooks não são registrados.', 'smartdocs') . '</dd>';
echo '<dt>' . __('Erro na instalação / tabelas não criadas', 'smartdocs') . '</dt>';
echo '<dd>' . __('Verifique se o usuário do banco tem permissão CREATE TABLE. Confirme que PHP 8.2+ está ativo.', 'smartdocs') . '</dd>';
echo '<dt>' . __('OCR não funciona', 'smartdocs') . '</dt>';
echo '<dd>' . __('Por padrão o OCR usa o navegador (WebAssembly). Para Tesseract local, instale tesseract-ocr no servidor.', 'smartdocs') . '</dd>';
echo '<dt>' . __('PDF não é gerado', 'smartdocs') . '</dt>';
echo '<dd>' . __('A geração é assíncrona via CronTask. Configure o cron do GLPI ou execute manualmente em Configuração → Tarefas automáticas.', 'smartdocs') . '</dd>';
echo '</dl>';
echo '</div>';
echo '</div>';

// Links
echo '<div class="mt-3">';
if ($composer_ok) {
    echo '<a href="' . $web_dir . '/front/smartdocs.php" class="btn btn-primary">';
    echo '<i class="ti ti-arrow-left me-1"></i>' . __('Voltar ao SmartDocs', 'smartdocs');
    echo '</a>';
}
echo '</div>';

echo '</div>';

Html::footer();