<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Hinweis zur Thumbnail-Erzeugung beim Speichern (Galerie-Modus)
 */

namespace Nikomat\Module\Pannellum\Site\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

/**
 * Zeigt einen Hinweis, dass beim Speichern Vorschaubilder erzeugt werden, und lädt das
 * Skript, das beim Speichern eine Fortschrittsanzeige einblendet.
 */
class GalleryNoticeField extends FormField
{
    protected $type = 'GalleryNotice';

    protected function getLabel(): string
    {
        return '';
    }

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        if ($wa->assetExists('script', 'core')) {
            $wa->useScript('core');
        }

        $wa->registerAndUseScript(
            'mod_pannellum.gallery.admin',
            'media/mod_pannellum/mod_pannellum_gallery_admin.js',
            ['version' => 'auto'],
            ['defer' => true]
        );

        Text::script('MOD_PANNELLUM_JS_SAVING_TITLE');
        Text::script('MOD_PANNELLUM_JS_SAVING_TEXT');

        return '<div class="alert alert-info" role="note">'
            . '<strong>' . htmlspecialchars(Text::_('MOD_PANNELLUM_GALLERY_SAVE_NOTICE_TITLE'), ENT_QUOTES, 'UTF-8') . '</strong><br>'
            . htmlspecialchars(Text::_('MOD_PANNELLUM_GALLERY_SAVE_NOTICE_TEXT'), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }
}
