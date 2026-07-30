<?php

namespace MODXDocs\Services;

use PDO;
use MODXDocs\Helpers\DbValueGuard;
use MODXDocs\Model\PageRequest;

class TranslationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAvailableTranslations(PageRequest $request): array
    {
        /**
         * Index pages don't show up in the translation index - so return them manually.
         */
        if ($request->getPath() === 'index') {
            return [
                'en' => '/' . $request->getVersionBranch() . '/en/index',
                'ru' => '/' . $request->getVersionBranch() . '/ru/index',
                'nl' => '/' . $request->getVersionBranch() . '/nl/index',
                'es' => '/' . $request->getVersionBranch() . '/es/index',
            ];
        }

        $language = $request->getLanguage();
        if (!in_array($language, ['nl', 'ru', 'en', 'es'])) {
            $language = 'en';
        }
        try {
            $q = 'SELECT * FROM Translations WHERE ' . $language . ' = :uri';
            $stmt = $this->db->prepare($q);
            $stmt->bindValue(':uri', DbValueGuard::truncate($request->getActualContextUrl() . $request->getPath(), DbValueGuard::TRANSLATION_URI));

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
        } catch (\Exception $e) {
            return [];
        }
        return [];
    }
}
