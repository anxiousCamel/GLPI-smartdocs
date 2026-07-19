<?php

/**
 * ---------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Formulário de criação/edição de Template PDF.
 *
 * Para templates novos: exibe formulário de upload do PDF base.
 * Para templates existentes: redireciona para o editor visual.
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();
GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_TEMPLATE_WRITE
);

$template = new GlpiPlugin\SmartDocs\Templates\PdfTemplate();

// ----------------------------------------------------------------------
// POST: salvar template novo ou atualizar existente
// ----------------------------------------------------------------------
if (isset($_POST['add'])) {
    $template->check(-1, CREATE, $_POST);
    $newId = $template->add($_POST);
    Html::redirect($template->getFormURLWithID($newId));
} elseif (isset($_POST['update'])) {
    $template->check($_POST['id'], UPDATE);
    $template->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    $template->check($_GET['id'], DELETE);
    $template->delete(['id' => $_GET['id']]);
    Html::redirect(GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdftemplate.php'));
} elseif (isset($_POST['purge'])) {
    $template->check($_GET['id'], PURGE);
    $template->delete(['id' => $_GET['id']], 1);
    Html::redirect(GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdftemplate.php'));
}

// ----------------------------------------------------------------------
// GET: exibir formulário
// ----------------------------------------------------------------------
$id = $_GET['id'] ?? 0;

// Template existente → redireciona para o editor visual
if ($id > 0 && $template->getFromDB($id)) {
    Html::redirect(
        GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdftemplate.editor.php?id=' . $id)
    );
}

// Novo template → formulário de upload
Html::header(
    __('Novo Template PDF', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'templates'
);

echo "<div class='container-fluid py-3'>";
echo "<h2 class='mb-4'><i class='ti ti-layout-collage'></i> " . __('Novo Template PDF', 'smartdocs') . "</h2>";

$template->showForm(0);

echo "</div>";

Html::footer();
