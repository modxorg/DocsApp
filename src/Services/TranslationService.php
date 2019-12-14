<?php

namespace MODXDocs\Services;

use MODXDocs\Model\PageRequest;
use Slim\Router;

class TranslationService
{
    /**
     * @var \PDO
     */
    private $db;

    public function __construct(\PDO $db, Router $router)
    {
        $this->db = $db;
    }

    public function getAvailableTranslations(PageRequest $request): array
    {
        $language = $request->getLanguage();
        $q = 'SELECT * FROM Translations WHERE ' . $this->db->quote($language) . ' = :uri';
        $stmt = $this->db->prepare($q);
        $stmt->bindValue(':uri', $request->getActualContextUrl() . $request->getPath());

        if ($stmt->execute() && $row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stmt->closeCursor();
            foreach ($row as $lang => $uri) {
                if (empty($uri)) {
                    unset($row[$lang]);
                }
            }
            return $row;
        }

        $stmt->closeCursor();
        return [];
    }
}
