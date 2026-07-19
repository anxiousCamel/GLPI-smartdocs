<?php

/**
 * -----------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * Formulário de criação/edição de Documento PDF.
 *
 * Para documentos novos: seleção de template e quantidade de itens.
 * Para documentos existentes: redireciona para o wizard de preenchimento.
 * -----------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

$doc = new GlpiPlugin\SmartDocs\Documents\PdfDocument();

// ------------------------------------------------------------------
// POST: salvar documento novo ou atualizar existente
// ------------------------------------------------------------------
if (isset($_POST['add'])) {
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
        GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
    );
    $doc->check(-1, CREATE, $_POST);
    $newId = $doc->add($_POST);
    Html::redirect($doc->getFormURLWithID($newId));
} elseif (isset($_POST['update'])) {
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
        GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
    );
    $doc->check($_POST['id'], UPDATE);
    $doc->update($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    GlpiPlugin\SmartDocs\Permissions\PermissionManager::checkRight(
        GlpiPlugin\SmartDocs\Permissions\PermissionManager::SMARTDOCS_DOCUMENT_WRITE
    );
    $doc->check($_GET['id'], DELETE);
    $doc->delete(['id' => $_GET['id']]);
    Html::redirect(GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdfdocument.php'));
}

// ------------------------------------------------------------------
// GET: exibir formulário
// ------------------------------------------------------------------
$id = $_GET['id'] ?? 0;

// Documento existente → redireciona para o wizard
if ($id > 0 && $doc->getFromDB($id)) {
    Html::redirect(
        GlpiPlugin\SmartDocs\GlpiCompat\MenuHelper::frontUrl('pdfdocument.fill.php?id=' . $id)
    );
}

// Novo documento → formulário de seleção de template
Html::header(
    __('Novo Documento PDF', 'smartdocs'),
    $_SERVER['PHP_SELF'],
    'plugins',
    'smartdocs',
    'documents'
);

$templateRepo = new GlpiPlugin\SmartDocs\Templates\TemplateRepository();
$templates = $templateRepo->findPublished($_SESSION['glpiactive_entity'] ?? 0);

$templateOptions = [];
foreach ($templates as $t) {
    $templateOptions[$t['id']] = $t['name'];
}

echo "<div class='container-fluid py-3'>";
echo "<h2 class='mb-4'><i class='ti ti-files'></i> " . __('Novo Documento PDF', 'smartdocs') . "</h2>";

if ($templateOptions === []) {
    echo "<div class='alert alert-info'>";
    echo __('Nenhum template publicado disponível. Publique um template antes de criar documentos.', 'smartdocs');
    echo "</div>";
} else {
    $doc->showForm(0);
}

echo "</div>";

Html::footer();
