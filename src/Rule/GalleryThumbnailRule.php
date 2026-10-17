<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Validierungsregel für Galerie-Einträge.
 * Erzeugt beim Speichern fehlende Thumbnails; scheitert die Erzeugung,
 * wird das Speichern mit einer verständlichen Meldung abgebrochen.
 */

namespace Nikomat\Module\Pannellum\Site\Rule;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Nikomat\Module\Pannellum\Site\Helper\ThumbnailCleaner;
use Nikomat\Module\Pannellum\Site\Helper\ThumbnailGenerator;

/**
 * Wird am Feld "gallery_item_image" angehängt und läuft einmal je Galerie-Eintrag.
 */
class GalleryThumbnailRule extends FormRule
{
    /** Zeitpunkt des ersten Aufrufs in diesem Request. */
    private static ?float $started = null;

    /** Zeitbudget in Sekunden für alle Thumbnails dieses Requests. */
    private static int $budget = 30;

    /** Anzahl der in diesem Request erzeugten Thumbnails. */
    private static int $created = 0;

    /**
     * @param   \SimpleXMLElement  $element  Feld-Definition
     * @param   mixed              $value    Wert des Bildfelds
     * @param   string|null        $group    Feldgruppe
     * @param   Registry|null      $input    Daten des Galerie-Eintrags
     * @param   Form|null          $form     Formular
     *
     * @return  boolean
     *
     * @throws  \UnexpectedValueException  mit der Fehlermeldung für den Nutzer
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $post   = Factory::getApplication()->getInput()->post->get('jform', [], 'array');
        $params = (isset($post['params']) && \is_array($post['params'])) ? $post['params'] : [];

        // Nur im Galerie-Modus Thumbnails erzeugen
        if (($params['mode'] ?? '') !== 'gallery' || trim((string) $value) === '') {
            return true;
        }

        if (self::$started === null) {
            // Etwa die Hälfte des PHP-Zeitlimits, mindestens 10 Sekunden
            $max            = (int) \ini_get('max_execution_time');
            self::$budget   = $max > 0 ? max(10, (int) ($max / 2)) : 30;
            self::$started  = microtime(true);

            // Nicht mehr verwendete Thumbnails aufräumen (einmal pro Request)
            try {
                ThumbnailCleaner::run($params);
            } catch (\Throwable $e) {
                // Aufräumen ist optional und darf das Speichern nie verhindern
            }
        }

        ThumbnailGenerator::setDebug((bool) ($params['debug_log'] ?? false));

        $input  = $input ?: new Registry();
        $aspect = (string) ($params['gallery_aspect_ratio'] ?? '16_9');
        $title  = trim((string) $input->get('gallery_item_title', ''));

        try {
            $image = ThumbnailGenerator::normalizeImagePath((string) $value);
            $yaw   = (float) $input->get('gallery_item_yaw', 0);
            $pitch = (float) $input->get('gallery_item_pitch', 0);
            $hfov  = (float) $input->get('gallery_item_hfov', 100);

            $target = ThumbnailGenerator::getThumbnailPath($image, $yaw, $pitch, $hfov, $aspect);

            if (!is_file(JPATH_ROOT . '/' . $target)) {
                // Zeitbudget aufgebraucht: kein weiteres Thumbnail beginnen.
                // Fertige Thumbnails bleiben erhalten, der nächste Speichervorgang macht weiter.
                if (microtime(true) - self::$started > self::$budget) {
                    throw new \UnexpectedValueException(Text::sprintf('MOD_PANNELLUM_ERROR_TIME_BUDGET', self::$created));
                }

                (new ThumbnailGenerator())->generate($image, $yaw, $pitch, $hfov, $aspect);
                self::$created++;
            }
        } catch (\UnexpectedValueException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \UnexpectedValueException(Text::sprintf(
                'MOD_PANNELLUM_ERROR_THUMBNAIL_FAILED',
                $title !== '' ? $title : basename((string) $value),
                $e->getMessage()
            ));
        }

        return true;
    }
}
