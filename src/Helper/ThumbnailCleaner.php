<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Aufräumen nicht mehr benötigter Thumbnails
 */

namespace Nikomat\Module\Pannellum\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Löscht Thumbnails, die von keinem Galerie-Eintrag mehr verwendet werden.
 *
 * Sicherheitsprinzip: Als "in Gebrauch" gilt alles, was in der Datenbank (alle Module,
 * auch im Papierkorb) oder im gerade gesendeten Formular referenziert wird. So gehen
 * Thumbnails nicht verloren, wenn das Speichern nach der Prüfung noch scheitert.
 * Ein entfernter Eintrag wird deshalb spätestens beim nächsten Speichern bereinigt.
 * Frische Dateien (jünger als 10 Minuten) bleiben immer stehen, damit gleichzeitige
 * Bearbeitungen anderer Module nicht gestört werden.
 */
class ThumbnailCleaner
{
    /** Mindestalter einer Datei in Sekunden, bevor sie gelöscht werden darf. */
    private const MIN_AGE = 600;

    /**
     * @param   array  $postedParams  Gesendete Modulparameter (jform[params])
     *
     * @return  int  Anzahl gelöschter Dateien
     */
    public static function run(array $postedParams): int
    {
        $dir = JPATH_ROOT . '/' . ThumbnailGenerator::THUMBNAIL_DIR;

        if (!is_dir($dir)) {
            return 0;
        }

        $keep = self::collectReferenced($postedParams);

        // Bei Datenbankproblemen lieber nichts löschen
        if ($keep === null) {
            return 0;
        }

        $deleted = 0;

        foreach (glob($dir . '/thumb_*.jpg') ?: [] as $file) {
            $relative = ThumbnailGenerator::THUMBNAIL_DIR . '/' . basename($file);

            if (isset($keep[$relative]) || (time() - (int) filemtime($file)) < self::MIN_AGE) {
                continue;
            }

            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return  array|null  Menge der verwendeten Thumbnail-Pfade (Pfad => true) oder null bei Fehler
     */
    private static function collectReferenced(array $postedParams): ?array
    {
        $keep = [];

        self::addFromParams($postedParams, $keep);

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_pannellum'));

            foreach ($db->setQuery($query)->loadColumn() ?: [] as $json) {
                $params = (new Registry((string) $json))->toArray();

                if (($params['mode'] ?? '') === 'gallery' || !empty($params['gallery_items'])) {
                    self::addFromParams($params, $keep);
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return $keep;
    }

    /**
     * Ermittelt die Thumbnail-Pfade aller Galerie-Einträge eines Parameter-Satzes.
     */
    private static function addFromParams(array $params, array &$keep): void
    {
        $aspect = (string) ($params['gallery_aspect_ratio'] ?? '16_9');
        $items  = $params['gallery_items'] ?? [];

        if (\is_string($items)) {
            $items = json_decode($items, true);
        }

        foreach (\is_array($items) ? $items : [] as $item) {
            if (!\is_array($item) || trim((string) ($item['gallery_item_image'] ?? '')) === '') {
                continue;
            }

            try {
                $image = ThumbnailGenerator::normalizeImagePath((string) $item['gallery_item_image']);
                $path  = ThumbnailGenerator::getThumbnailPath(
                    $image,
                    (float) ($item['gallery_item_yaw'] ?? 0),
                    (float) ($item['gallery_item_pitch'] ?? 0),
                    (float) ($item['gallery_item_hfov'] ?? 100),
                    $aspect
                );

                $keep[$path] = true;
            } catch (\RuntimeException $e) {
                continue;
            }
        }
    }
}
