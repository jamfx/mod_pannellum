<?php
/**
 * Custom field: hotspoteditorbutton (Joomla 5/6)
 * Rendert den Button direkt und lädt die benötigten Assets aus /media/mod_pannellum/.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class JFormFieldHotspoteditorbutton extends FormField
{
    protected $type = 'hotspoteditorbutton';

    protected function getInput()
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

        // Label/Hinweis
        $label = Text::_('MOD_PANNELLUM_HOTSPOT_EDITOR_OPEN') ?: 'Hotspot-Editor';
        $hint  = Text::_('MOD_PANNELLUM_HOTSPOT_EDITOR_HINT') ?: 'Laden Sie zuerst ein Panoramabild hoch, klicken Sie dann auf "Hotspot-Editor öffnen" und klicken Sie im Panorama auf die gewünschte Position. Die Koordinaten werden automatisch übernommen.';

        // WICHTIG: Button wird hier direkt gerendert
        // KORREKTUR: $hint wird NICHT durch htmlspecialchars gejagt, damit der Text normal angezeigt wird
        $html  = [];
        $html[] = '<div class="control-group hotspot-editor-field-container">';
        $html[] = '  <div class="control-label"><label>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label></div>';
        $html[] = '  <div class="controls hotspot-editor-trigger">';
        $html[] = '    <div class="alert alert-info" style="margin-bottom:10px;">' . $hint . '</div>';
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
