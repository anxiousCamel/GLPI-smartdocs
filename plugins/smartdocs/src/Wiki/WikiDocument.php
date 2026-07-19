<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * WikiDocument: artigo da wiki técnica com editor WYSIWYG.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Wiki;

use CommonDBTM;

final class WikiDocument extends CommonDBTM
{
    public static $rightname = 'plugin_smartdocs_wiki';

    public static function getTypeName($nb = 0): string
    {
        return _n('Documento Wiki', 'Documentos Wiki', $nb, 'smartdocs');
    }

    public function prepareInputForAdd($input): array
    {
        $input = parent::prepareInputForAdd($input);
        $input['version'] = 1;
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        return $input;
    }

    public function post_addItem(): void
    {
        parent::post_addItem();

        // Cria primeira versão
        $version = new WikiVersion();
        $version->add([
            'wiki_documents_id' => $this->fields['id'],
            'version'           => 1,
            'content'           => $this->fields['content'] ?? '',
            'users_id'          => \Session::getLoginUserID(),
        ]);
    }

    public function post_updateItem($history = 1): void
    {
        parent::post_updateItem($history);

        // Incrementa versão e salva snapshot
        $newVersion = ((int) $this->fields['version']) + 1;
        $this->update(['id' => $this->fields['id'], 'version' => $newVersion]);

        $version = new WikiVersion();
        $version->add([
            'wiki_documents_id' => $this->fields['id'],
            'version'           => $newVersion,
            'content'           => $this->fields['content'] ?? '',
            'users_id'          => \Session::getLoginUserID(),
        ]);
    }
}
