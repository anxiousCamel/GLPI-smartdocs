<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Lista de Templates PDF.
 *
 * Thin: delega ao sistema de Search do GLPI para exibição paginada.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_TEMPLATE_READ
);

Html::header(
    __('Templates PDF', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'templates'
);

// Banner de ajuda para primeiro template (visível enquanto não houver templates publicados)
if (GlpiPlugin\SmartDocs\Permissions\PermissionManager::canWriteTemplates()) {
    /** @var \DBmysql $DB */
    global $DB;
    $hasTemplates = (int) ($DB->request([
        'COUNT' => 'cnt',
        'FROM'  => 'glpi_plugin_smartdocs_pdf_templates',
    ])->current()['cnt'] ?? 0);

    if ($hasTemplates === 0) {
        echo "<div class='container-fluid mt-3'>";
        echo "<div class='alert alert-info d-flex align-items-start gap-3'>";
        echo "<i class='ti ti-info-circle flex-shrink-0 mt-1'></i>";
        echo "<div class='flex-fill'>";
        echo "<strong>" . __('Primeiro passo: crie um template', 'smartdocs') . "</strong><br>";
        echo __('Um template define o layout de um documento PDF. Faça upload de um PDF base e posicione os campos no editor visual.', 'smartdocs');
        echo "<div class='mt-2'>";
        echo "<a href='" . GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdftemplate.form.php') . "' class='btn btn-sm btn-primary'>";
        echo "<i class='ti ti-plus me-1'></i>" . __('Criar template', 'smartdocs');
        echo "</a>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
}

Search::show('GlpiPlugin\\SmartDocs\\Templates\\PdfTemplate');

Html::footer();
