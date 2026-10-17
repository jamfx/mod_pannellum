<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Frontend Helper (Joomla 5/6)
 * Uses Pannellum library by Matthew Petroff (MIT License)
 */

namespace Nikomat\Module\Pannellum\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Registry\Registry;

/**
 * Helper for mod_pannellum
 */
class PannellumHelper
{
    /**
     * Registriert und lädt die Frontend-Assets.
     *
     * @param   WebAssetManager  $wa  WebAssetManager
     *
     * @return  void
     */
    public function registerAssets(WebAssetManager $wa, string $mode = 'single'): void
    {
        $wa->registerAndUseStyle('mod_pannellum.pannellum', 'media/mod_pannellum/pannellum.css', ['version' => 'auto']);
        $wa->registerAndUseScript('mod_pannellum.pannellum', 'media/mod_pannellum/pannellum.js', ['version' => 'auto'], ['defer' => true]);

        if ($mode === 'gallery') {
            $wa->registerAndUseStyle('mod_pannellum.gallery', 'media/mod_pannellum/mod_pannellum_gallery.css', ['version' => 'auto']);
            $wa->registerAndUseScript('mod_pannellum.gallery', 'media/mod_pannellum/mod_pannellum_gallery.js', ['version' => 'auto'], ['defer' => true], ['mod_pannellum.pannellum']);

            return;
        }

        // Texte für mod_pannellum_init.js (Joomla.Text)
        $wa->useScript('core');
        Text::script('MOD_PANNELLUM_JS_CLOSE');
        Text::script('MOD_PANNELLUM_JS_INFO_TITLE');
        Text::script('MOD_PANNELLUM_JS_NO_INFO');

        $wa->registerAndUseStyle('mod_pannellum.frontend', 'media/mod_pannellum/mod_pannellum_admin.css', ['version' => 'auto']);
        $wa->registerAndUseStyle('mod_pannellum.info_hotspots', 'media/mod_pannellum/mod_pannellum_info_hotspots.css', ['version' => 'auto']);
        $wa->registerAndUseScript('mod_pannellum.init', 'media/mod_pannellum/mod_pannellum_init.js', ['version' => 'auto'], ['defer' => true], ['mod_pannellum.pannellum']);

        // Font Awesome für Icons laden (Joomla Standard)
        if ($wa->assetExists('style', 'fontawesome')) {
            $wa->useStyle('fontawesome');
        }
    }

    /**
     * Baut die Konfiguration für das Layout auf.
     *
     * @param   Registry  $params  Modulparameter
     *
     * @return  array
     */
    public function getConfig(Registry $params): array
    {
        $mode = (string) $params->get('mode', 'single');

        $config = [
            'type'     => 'single',
            'autoLoad' => (bool) $params->get('autoload', $params->get('single_autoload', 1)),
            'display'  => $this->getDisplayOptions($params),
            'strings'  => [
                'loadButtonLabel' => Text::_('MOD_PANNELLUM_LOAD_BUTTON_LABEL'),
                'loadingLabel'    => Text::_('MOD_PANNELLUM_LOADING_LABEL'),
            ],
        ];

        if ($mode === 'single') {
            return $this->buildSingle($params, $config);
        }

        if ($mode === 'gallery') {
            return $this->buildGallery($params, $config);
        }

        return $this->buildMulti($params, $config);
    }

    /**
     * Globale Anzeigeoptionen.
     *
     * @param   Registry  $params  Modulparameter
     *
     * @return  array
     */
    protected function getDisplayOptions(Registry $params): array
    {
        return [
            'heightUnit'       => (string) $params->get('height_unit', 'px'),
            'heightValue'      => (int) $params->get('height_value', 500),
            'shadowEnabled'    => (bool) $params->get('shadow_enabled', 0),
            'shadowHorizontal' => (int) $params->get('shadow_horizontal', 0),
            'shadowVertical'   => (int) $params->get('shadow_vertical', 4),
            'shadowBlur'       => (int) $params->get('shadow_blur', 8),
            'shadowSpread'     => (int) $params->get('shadow_spread', 0),
            'shadowColor'      => (string) $params->get('shadow_color', '#000000'),
            'shadowOpacity'    => (float) $params->get('shadow_opacity', 0.3),
            'showCompass'      => (bool) $params->get('show_compass', 0),
            'showGyro'         => (bool) $params->get('show_gyro', 1),
            'autoRotate'       => (bool) $params->get('auto_rotate', 0),
            'autoRotateSpeed'  => (float) $params->get('auto_rotate_speed', 2),
            'autoRotateDelay'  => (int) $params->get('auto_rotate_delay', 3000),
        ];
    }

    /**
     * Konfiguration für den Einzelpanorama-Modus.
     *
     * @param   Registry  $params  Modulparameter
     * @param   array     $config  Basiskonfiguration
     *
     * @return  array
     */
    protected function buildSingle(Registry $params, array $config): array
    {
        $config['type']     = 'single';
        $config['panorama'] = $this->resolveImage((string) $params->get('single_image', ''));
        $config['yaw']      = (float) $params->get('single_yaw', 0);
        $config['pitch']    = (float) $params->get('single_pitch', 0);
        $config['hfov']     = (float) $params->get('single_hfov', 100);

        $config['description'] = [
            'showDescription'          => (bool) $params->get('single_show_description', 0),
            'descriptionText'          => (string) $params->get('single_description_text', ''),
            'descriptionPosition'      => (string) $params->get('single_description_position', 'bottom'),
            'descriptionTextAlign'     => (string) $params->get('single_description_text_align', 'center'),
            'descriptionFontSizeValue' => (int) $params->get('single_description_font_size_value', 18),
            'descriptionFontSizeUnit'  => (string) $params->get('single_description_font_size_unit', 'px'),
            'descriptionTextColor'     => (string) $params->get('single_description_text_color', '#ffffff'),
            'descriptionBgColor'       => (string) $params->get('single_description_bg_color', '#000000'),
            'descriptionBgOpacity'     => (float) $params->get('single_description_bg_opacity', 0.7),
        ];

        $config['hotSpots'] = $this->processHotspots($this->decodeList($params->get('single_hotspots', [])));

        return $config;
    }

    /**
     * Konfiguration für den Galerie-Modus.
     *
     * Einträge ohne Bild oder ohne vorhandenes Thumbnail werden übersprungen.
     *
     * @param   Registry  $params  Modulparameter
     * @param   array     $config  Basiskonfiguration
     *
     * @return  array
     */
    protected function buildGallery(Registry $params, array $config): array
    {
        $aspect = (string) $params->get('gallery_aspect_ratio', '16_9');
        $aspect = \in_array($aspect, ['16_9', '4_3', '1_1'], true) ? $aspect : '16_9';

        $columns = (int) $params->get('gallery_columns', 3);
        $columns = \in_array($columns, [2, 3, 4], true) ? $columns : 3;

        $config['type']    = 'gallery';
        $config['gallery'] = [
            'title'        => trim((string) $params->get('gallery_title', '')),
            'columns'      => $columns,
            'gap'          => max(0, min(50, (int) $params->get('gallery_gap', 10))),
            'aspectRatio'  => $aspect,
            'viewerMode'   => ((string) $params->get('gallery_viewer_mode', 'inline')) === 'modal' ? 'modal' : 'inline',
            'divider'      => (bool) $params->get('gallery_divider', 0),
            'dividerColor' => (string) $params->get('gallery_divider_color', '#cccccc'),
            'dividerWidth' => max(1, min(5, (int) $params->get('gallery_divider_width', 1))),
            'cssClass'     => trim((string) $params->get('gallery_css_class', '')),
            'hoverEffect'  => (bool) $params->get('gallery_hover_effect', 1),
        ];

        $items = [];

        foreach ($this->decodeList($params->get('gallery_items', [])) as $entry) {
            $item = $this->toArray($this->get($entry, 'gallery_item', $entry));
            $raw  = (string) $this->get($item, 'gallery_item_image', '');

            if (trim($raw) === '') {
                continue;
            }

            $yaw   = (float) $this->get($item, 'gallery_item_yaw', 0);
            $pitch = (float) $this->get($item, 'gallery_item_pitch', 0);
            $hfov  = (float) $this->get($item, 'gallery_item_hfov', 100);

            try {
                $local = ThumbnailGenerator::normalizeImagePath($raw);
                $thumb = ThumbnailGenerator::getThumbnailPath($local, $yaw, $pitch, $hfov, $aspect);
            } catch (\RuntimeException $e) {
                continue;
            }

            // Ohne generiertes Thumbnail keine Kachel
            if (!is_file(JPATH_ROOT . '/' . $thumb)) {
                continue;
            }

            $title = trim((string) $this->get($item, 'gallery_item_title', ''));
            $alt   = trim((string) $this->get($item, 'gallery_item_alt_text', ''));

            $items[] = [
                'image'       => $this->resolveImage($raw),
                'thumbnail'   => rtrim(Uri::root(), '/') . '/' . $thumb,
                'title'       => $title,
                'alt'         => $alt !== '' ? $alt : $title,
                'description' => trim((string) $this->get($item, 'gallery_item_description', '')),
                'yaw'         => $yaw,
                'pitch'       => $pitch,
                'hfov'        => $hfov,
                'autorotate'  => (bool) $this->get($item, 'gallery_item_autorotate', 0),
                'fullscreen'  => (bool) $this->get($item, 'gallery_item_fullscreen', 1),
                'ordering'    => (int) $this->get($item, 'gallery_item_ordering', 0),
            ];
        }

        // Stabil nach Sortierung ordnen (bei gleichem Wert bleibt die Reihenfolge der Eingabe)
        $indexed = array_map(null, array_keys($items), $items);
        usort($indexed, static function (array $a, array $b): int {
            return [$a[1]['ordering'], $a[0]] <=> [$b[1]['ordering'], $b[0]];
        });

        $config['gallery']['items'] = array_column($indexed, 1);

        return $config;
    }

    /**
     * Konfiguration für den Multi-Szenen-Modus.
     *
     * @param   Registry  $params  Modulparameter
     * @param   array     $config  Basiskonfiguration
     *
     * @return  array
     */
    protected function buildMulti(Registry $params, array $config): array
    {
        $config['type']    = 'multi';
        $config['default'] = [
            'firstScene' => null,
            'autoLoad'   => (bool) $params->get('autoload', $params->get('single_autoload', 1)),
        ];
        $config['scenes'] = [];

        $seq           = 1;
        $firstSceneKey = null;

        foreach ($this->decodeList($params->get('scenes', [])) as $entry) {
            $scene = $this->toArray($this->get($entry, 'scene', $entry));

            // Leere Einträge überspringen
            $img = $this->resolveImage((string) $this->get($scene, 'scene_image', ''));
            if ($img === '') {
                continue;
            }

            $idRaw   = trim((string) $this->get($scene, 'scene_id', ''));
            $sceneId = ($idRaw !== '') ? $idRaw : ('scene' . $seq);

            $sceneConf = [
                'type'        => 'equirectangular',
                'panorama'    => $img,
                'yaw'         => (float) $this->get($scene, 'yaw', 0),
                'pitch'       => (float) $this->get($scene, 'pitch', 0),
                'hfov'        => (float) $this->get($scene, 'hfov', 100),
                'northOffset' => (float) $this->get($scene, 'northOffset', 0),
            ];

            // Titel nur, wenn aktiviert
            if ((bool) $this->get($scene, 'show_title', 0)) {
                $titleText = trim((string) $this->get($scene, 'title', ''));
                if ($titleText !== '') {
                    $sceneConf['title'] = $titleText;
                }
            }

            $sceneConf['description'] = [
                'showDescription'          => (bool) $this->get($scene, 'show_description', 0),
                'descriptionText'          => (string) $this->get($scene, 'description_text', ''),
                'descriptionPosition'      => (string) $this->get($scene, 'description_position', 'bottom'),
                'descriptionTextAlign'     => (string) $this->get($scene, 'description_text_align', 'center'),
                'descriptionFontSizeValue' => (int) $this->get($scene, 'description_font_size_value', 18),
                'descriptionFontSizeUnit'  => (string) $this->get($scene, 'description_font_size_unit', 'px'),
                'descriptionTextColor'     => (string) $this->get($scene, 'description_text_color', '#ffffff'),
                'descriptionBgColor'       => (string) $this->get($scene, 'description_bg_color', '#000000'),
                'descriptionBgOpacity'     => (float) $this->get($scene, 'description_bg_opacity', 0.7),
            ];

            $sceneConf['hotSpots'] = $this->processHotspots($this->toArray($this->get($scene, 'hotspots', [])));

            $config['scenes'][$sceneId] = $sceneConf;

            if ($firstSceneKey === null) {
                $firstSceneKey = $sceneId;
            }

            $seq++;
        }

        if ($firstSceneKey !== null) {
            $config['default']['firstScene'] = $firstSceneKey;
        }

        return $config;
    }

    /**
     * Verarbeitet Hotspots (Info oder Scene) mit Icon-Support, Farbe und Größe.
     *
     * @param   array  $hotspotsRaw  Rohdaten der Hotspots
     *
     * @return  array
     */
    protected function processHotspots(array $hotspotsRaw): array
    {
        $result = [];

        foreach ($hotspotsRaw as $hsEntry) {
            // Entpacke ggf. aus 'hotspot' wrapper (alte Struktur)
            $hs = $this->toArray($this->get($hsEntry, 'hotspot', $hsEntry));

            // Leere Zeile überspringen
            if ($hs === [] || ($this->get($hs, 'pitch', '') === '' && $this->get($hs, 'yaw', '') === '')) {
                continue;
            }

            $type      = (string) $this->get($hs, 'type', 'scene');
            $target    = (string) $this->get($hs, 'target', '');
            $text      = (string) $this->get($hs, 'text', '');
            $icon      = (string) $this->get($hs, 'icon', '');
            $iconColor = (string) $this->get($hs, 'icon_color', '#0066cc');
            $iconSize  = (int) $this->get($hs, 'icon_size', 20);
            $boxWidth  = (int) $this->get($hs, 'box_width', 40);
            $boxHeight = (int) $this->get($hs, 'box_height', 40);

            $hsConf = [
                'pitch' => (float) $this->get($hs, 'pitch', 0),
                'yaw'   => (float) $this->get($hs, 'yaw', 0),
                'type'  => ($type === 'info') ? 'info' : 'scene',
            ];

            // Bei Scene-Hotspots: Target hinzufügen
            if ($type === 'scene' && $target !== '') {
                $hsConf['sceneId'] = $target;
            }

            // Text/Tooltip (unterstützt HTML für Info-Hotspots)
            if ($text !== '') {
                $hsConf['text'] = $text;
            }

            // Box-Größe für Info-Hotspots
            if ($type === 'info') {
                $hsConf['boxWidth']  = max(20, min(80, $boxWidth));
                $hsConf['boxHeight'] = max(20, min(80, $boxHeight));
            }

            // Icon als CSS-Klasse + Farbe & Größe
            if ($icon !== '') {
                $hsConf['cssClass']  = 'custom-hotspot-icon';
                $hsConf['iconClass'] = $icon;
                $hsConf['iconColor'] = $iconColor;
                $hsConf['iconSize']  = max(12, min(60, $iconSize));
            }

            $result[] = $hsConf;
        }

        return $result;
    }

    /**
     * Bild-URL aus Media-Feld für das Frontend bereinigen.
     *
     * @param   string  $raw  Rohwert des Media-Felds
     *
     * @return  string
     */
    public function resolveImage(string $raw): string
    {
        $val = trim($raw);

        if ($val === '') {
            return '';
        }

        $hashPos = strpos($val, '#joomlaImage://');

        if ($hashPos !== false) {
            $hash = substr($val, $hashPos + 1);

            if (preg_match('#^joomlaImage://(?:local-)?(images/[^?]+)#i', $hash, $m)) {
                $val = $m[1];
            } else {
                $val = substr($val, 0, $hashPos);
            }
        }

        $val = preg_replace('#^/?administrator/#i', '', $val);

        if (preg_match('#^https?://#i', $val)) {
            return $val;
        }

        return rtrim(Uri::root(), '/') . '/' . ltrim($val, '/');
    }

    /**
     * Safe-get: liest aus Array/Objekt/Registry.
     *
     * @param   mixed   $item     Quelle
     * @param   string  $key      Schlüssel
     * @param   mixed   $default  Standardwert
     *
     * @return  mixed
     */
    protected function get($item, string $key, $default = null)
    {
        if ($item instanceof Registry) {
            $item = $item->toArray();
        }

        if (\is_array($item) && \array_key_exists($key, $item)) {
            return $item[$key];
        }

        if (\is_object($item) && isset($item->{$key})) {
            return $item->{$key};
        }

        return $default;
    }

    /**
     * Wandelt Registry/Objekt in ein Array um.
     *
     * @param   mixed  $val  Wert
     *
     * @return  array
     */
    protected function toArray($val): array
    {
        if ($val instanceof Registry) {
            $val = $val->toArray();
        }

        if (\is_object($val)) {
            $val = json_decode(json_encode($val), true);
        }

        return \is_array($val) ? $val : [];
    }

    /**
     * Subform-Rohwert (ggf. JSON-String) in ein Array umwandeln.
     *
     * @param   mixed  $raw  Rohwert
     *
     * @return  array
     */
    protected function decodeList($raw): array
    {
        if (\is_string($raw) && $raw !== '') {
            $tmp = json_decode($raw, true);

            if (\is_array($tmp)) {
                $raw = $tmp;
            }
        }

        return $this->toArray($raw);
    }
}
