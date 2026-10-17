<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Hotspot Editor Button Field
 */

namespace Nikomat\Module\Pannellum\Site\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class HotspotEditorButtonField extends FormField
{
    protected $type = 'HotspotEditorButton';

    protected function getInput(): string
    {
        $app = Factory::getApplication();
        $doc = $app->getDocument();
        $wa  = $doc->getWebAssetManager();

        // Core (falls registriert)
        if ($wa->assetExists('script', 'core')) {
            $wa->useScript('core');
        }

        // Styles (Backend)
        $wa->registerAndUseStyle('mod_pannellum.pannellum',      'media/mod_pannellum/pannellum.css',             ['version' => 'auto']);
        $wa->registerAndUseStyle('mod_pannellum.admin',          'media/mod_pannellum/mod_pannellum_admin.css',   ['version' => 'auto']);
        $wa->registerAndUseStyle('mod_pannellum.hotspot.editor', 'media/mod_pannellum/hotspot-editor.css',        ['version' => 'auto']);

        // Scripts (Backend)
        // pannellum ohne defer – damit der Viewer im Modal sofort bereit ist
        $wa->registerAndUseScript('mod_pannellum.pannellum',   'media/mod_pannellum/pannellum.js',                ['version' => 'auto'], ['defer' => false]);
        // Validierung optional
        $wa->registerAndUseScript('mod_pannellum.validation',  'media/mod_pannellum/mod_pannellum_validation.js', ['version' => 'auto'], ['defer' => true]);
        // Hotspot-Editor mit Event-Delegation
        $wa->registerAndUseScript('mod_pannellum.hotspot',     'media/mod_pannellum/hotspot-editor.js',           ['version' => 'auto'], ['defer' => true], ['mod_pannellum.pannellum','core']);

        // Optional: FontAwesome
        if ($wa->assetExists('style', 'fontawesome')) {
            $wa->useStyle('fontawesome');
        }

        // Texte für die Backend-JavaScripts (Joomla.Text)
        foreach ([
            'MOD_PANNELLUM_JS_HS_EDITOR_TITLE',
            'MOD_PANNELLUM_JS_HS_ADD',
            'MOD_PANNELLUM_JS_HS_REFRESH',
            'MOD_PANNELLUM_JS_HS_ARMED',
            'MOD_PANNELLUM_JS_HS_ARMED_HINT',
            'MOD_PANNELLUM_JS_HS_TAKEN',
            'MOD_PANNELLUM_JS_HS_ADDED',
            'MOD_PANNELLUM_JS_HS_ADDED_RETRY',
            'MOD_PANNELLUM_JS_HS_ERR_CONTAINER',
            'MOD_PANNELLUM_JS_HS_ERR_ADD_BUTTON',
            'MOD_PANNELLUM_JS_HS_ERR_NEW_ROW',
            'MOD_PANNELLUM_JS_HS_ERR_FIELDS',
            'MOD_PANNELLUM_JS_HS_ERR_FILL',
            'MOD_PANNELLUM_JS_HS_ERR_CONTEXT',
            'MOD_PANNELLUM_JS_HS_ERR_IMAGE_FIELD',
            'MOD_PANNELLUM_JS_HS_ERR_NO_IMAGE',
            'MOD_PANNELLUM_JS_HS_ERR_IMAGE_URL',
            'MOD_PANNELLUM_JS_VAL_TITLE',
            'MOD_PANNELLUM_JS_VAL_MISSING',
            'MOD_PANNELLUM_JS_VAL_AVAILABLE',
            'MOD_PANNELLUM_JS_VAL_FALLBACK',
            'MOD_PANNELLUM_JS_VAL_FOUND',
            'MOD_PANNELLUM_JS_CLOSE',
        ] as $key) {
            Text::script($key);
        }

        // Label/Hinweis (aus den Sprachdateien)
        $label = Text::_('MOD_PANNELLUM_HOTSPOT_EDITOR_OPEN');
        $hint  = Text::_('MOD_PANNELLUM_HOTSPOT_EDITOR_HINT');

        $html  = [];
        $html[] = '<div class="control-group hotspot-editor-field-container">';
        $html[] = '  <div class="control-label"><label>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label></div>';
        $html[] = '  <div class="controls hotspot-editor-trigger">';
        $html[] = '    <div class="alert alert-info" style="margin-bottom:10px;">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    <div class="hotspot-editor-button-container">';
        $html[] = '      <button type="button" class="btn btn-primary hotspot-editor-button">';
        $html[] = '        <i class="fa fa-map-marker-alt" aria-hidden="true"></i> ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $html[] = '      </button>';
        $html[] = '    </div>';
        $html[] = '  </div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}
