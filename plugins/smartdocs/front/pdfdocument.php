<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Lista de Documentos PDF.
 *
 * Thin: delega ao sistema de Search do GLPI.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_READ
);

Html::header(
    __('Documentos PDF', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'documents'
);

// Banner quando não há templates publicados
if (GlpiPlugin\SmartDocs\Permissions\PermissionManager::canWriteDocuments()) {
    /** @var \DBmysql $DB */
    global $DB;
    $hasPublished = (int) ($DB->request([
        'COUNT' => 'cnt',
        'FROM'  => 'glpi_plugin_smartdocs_pdf_templates',
        'WHERE' => ['status' => 'PUBLISHED'],
    ])->current()['cnt'] ?? 0);

    if ($hasPublished === 0) {
        echo "<div class='container-fluid mt-3'>";
        echo "<div class='alert alert-warning d-flex align-items-start gap-3'>";
        echo "<i class='ti ti-alert-circle flex-shrink-0 mt-1'></i>";
        echo "<div class='flex-fill'>";
        echo "<strong>" . __('Nenhum template publicado', 'smartdocs') . "</strong><br>";
        echo __('Você precisa publicar um template PDF antes de criar documentos. Vá em Templates PDF e publique um template.', 'smartdocs');
        echo "<div class='mt-2'>";
        echo "<a href='" . GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdftemplate.php') . "' class='btn btn-sm btn-primary'>";
        echo "<i class='ti ti-layout-collage me-1'></i>" . __('Ver templates', 'smartdocs');
        echo "</a>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    } else {
        // Search::show() não gera botão de "novo item" para itemtypes de
        // plugin — sem isso não haveria como criar um documento.
        echo "<div class='container-fluid mt-3 text-end'>";
        echo "<a href='" . GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdfdocument.form.php') . "' class='btn btn-sm btn-primary'>";
        echo "<i class='ti ti-plus me-1'></i>" . __('Novo documento', 'smartdocs');
        echo "</a>";
        echo "</div>";
    }
}

Search::show('GlpiPlugin\\SmartDocs\\Documents\\PdfDocument');

Html::footer();
