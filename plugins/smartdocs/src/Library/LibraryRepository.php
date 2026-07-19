<?php

/**
 * ----------------------------------------------------------------------
 * SmartDocs — Plugin GLPI
 *
 * LibraryRepository: acesso a dados para arquivos técnicos e wiki.
 * ----------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\SmartDocs\Library;

final class LibraryRepository
{
    /**
     * Lista arquivos técnicos filtrados por itemtype/items_id.
     *
     * @param string $linkedItemType Tipo do objeto GLPI (ex: Computer)
     * @param int $linkedItemId ID do objeto GLPI
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByLinkedItem(string $linkedItemType, int $linkedItemId): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['*'],
            'FROM'   => TechnicalFile::getTable(),
            'WHERE'  => [
                'linked_itemtype' => $linkedItemType,
                'linked_items_id' => $linkedItemId,
            ],
            'ORDER'  => 'date_creation DESC',
        ]);

        $results = [];
        foreach ($iterator as $row) {
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Lista documentos wiki por categoria.
     *
     * @param int|null $categoryId
     *
     * @return array<int, array<string, mixed>>
     */
    public function findWikiByCategory(?int $categoryId = null): array
    {
        global $DB;

        $criteria = [
            'SELECT' => ['*'],
            'FROM'   => \GlpiPlugin\SmartDocs\Wiki\WikiDocument::getTable(),
            'ORDER'  => 'date_mod DESC',
        ];

        if ($categoryId !== null) {
            $criteria['WHERE'] = ['wiki_categories_id' => $categoryId];
        }

        $iterator = $DB->request($criteria);
        $results = [];
        foreach ($iterator as $row) {
            $results[] = $row;
        }

        return $results;
    }
}
