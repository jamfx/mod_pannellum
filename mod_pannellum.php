<?php
/**
 * @package     mod_pannellum
 * @version     1.0.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Frontend Bootstrap (Joomla 5/6)
 * Uses Pannellum library by Matthew Petroff (MIT License)
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Uri\Uri;

// WebAssets fürs Frontend laden
$app = Factory::getApplication();
$doc = $app->getDocument();
$wa  = $doc->getWebAssetManager();

$wa->registerAndUseStyle('mod_pannellum.pannellum', 'media/mod_pannellum/pannellum.css', ['version' => 'auto']);
$wa->registerAndUseScript('mod_pannellum.pannellum', 'media/mod_pannellum/pannellum.js', ['version' => 'auto'], ['defer' => true]);
$wa->registerAndUseStyle('mod_pannellum.frontend', 'media/mod_pannellum/mod_pannellum_admin.css', ['version' => 'auto']);
$wa->registerAndUseStyle('mod_pannellum.info_hotspots', 'media/mod_pannellum/mod_pannellum_info_hotspots.css', ['version' => 'auto']);
$wa->registerAndUseScript('mod_pannellum.init', 'media/mod_pannellum/mod_pannellum_init.js', ['version' => 'auto'], ['defer' => true], ['mod_pannellum.pannellum']);

// Font Awesome für Icons laden (Joomla Standard)
if ($wa->assetExists('style', 'fontawesome')) {
    $wa->useStyle('fontawesome');
}

/** Safe-get: liest aus Array/Objekt/Registry */
$get = static function ($item, string $key, $default = null) {
    if ($item instanceof \Joomla\Registry\Registry) {
        $item = $item->toArray();
    }
    if (is_array($item) && array_key_exists($key, $item)) {
        return $item[$key];
    }
    if (is_object($item) && isset($item->{$key})) {
        return $item->{$key};
    }
    return $default;
};

/** Deep → Array */
$toArray = static function ($val) {
    if ($val instanceof \Joomla\Registry\Registry) {
        $val = $val->toArray();
    }
    if (is_object($val)) {
        $val = json_decode(json_encode($val), true);
    }
    return is_array($val) ? $val : [];
};

/**
 * Bild-URL aus Media-Feld für das Frontend bereinigen
 */
$resolveImg = static function (?string $raw) : string {
    $val = trim((string) ($raw ?? ''));
    if ($val === '') return '';

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

    $val = ltrim($val, '/');
    $root = rtrim(Uri::root(), '/');
    return $root . '/' . $val;
};

/**
 * Verarbeitet Hotspots (Info oder Scene) mit Icon-Support + Farbe & Größe
 */
$processHotspots = static function(array $hotspotsRaw) use ($get, $toArray) {
    $result = [];

    foreach ($hotspotsRaw as $hsEntry) {
        // Entpacke ggf. aus 'hotspot' wrapper (alte Struktur)
        $hs = $get($hsEntry, 'hotspot', $hsEntry);
        $hs = $toArray($hs);

        // Leere Zeile überspringen
        if ($hs === [] || ($get($hs, 'pitch', '') === '' && $get($hs, 'yaw', '') === '')) {
            continue;
        }

        $pitch     = (float) $get($hs, 'pitch', 0);
        $yaw       = (float) $get($hs, 'yaw', 0);
        $type      = (string) $get($hs, 'type', 'scene');
        $target    = (string) $get($hs, 'target', '');
        $text      = (string) $get($hs, 'text', '');
        $icon      = (string) $get($hs, 'icon', '');
        $iconColor = (string) $get($hs, 'icon_color', '#0066cc');
        $iconSize  = (int) $get($hs, 'icon_size', 20);
        $boxWidth  = (int) $get($hs, 'box_width', 40);
        $boxHeight = (int) $get($hs, 'box_height', 40);

        $hsConf = [
            'pitch' => $pitch,
            'yaw'   => $yaw,
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
            $hsConf['boxWidth'] = max(20, min(80, $boxWidth));
            $hsConf['boxHeight'] = max(20, min(80, $boxHeight));
        }

        // Icon als CSS-Klasse + Farbe & Größe
        if ($icon !== '') {
            $hsConf['cssClass'] = 'custom-hotspot-icon';
            $hsConf['iconClass'] = $icon;
            $hsConf['iconColor'] = $iconColor;
            $hsConf['iconSize'] = max(12, min(60, $iconSize));
        }

        $result[] = $hsConf;
    }

    return $result;
};

$mode = (string) $params->get('mode', 'single');

// === GLOBALE ANZEIGEOPTIONEN (für beide Modi) ===
$displayOptions = [
    // Höhe
    'heightUnit'                => (string) $params->get('height_unit', 'px'),
    'heightValue'               => (int) $params->get('height_value', 500),
    
    // Shadow
    'shadowEnabled'             => (bool) $params->get('shadow_enabled', 0),
    'shadowHorizontal'          => (int) $params->get('shadow_horizontal', 0),
    'shadowVertical'            => (int) $params->get('shadow_vertical', 4),
    'shadowBlur'                => (int) $params->get('shadow_blur', 8),
    'shadowSpread'              => (int) $params->get('shadow_spread', 0),
    'shadowColor'               => (string) $params->get('shadow_color', '#000000'),
    'shadowOpacity'             => (float) $params->get('shadow_opacity', 0.3),
    
    // Kompass, Gyro, Auto-Rotation
    'showCompass'               => (bool) $params->get('show_compass', 0),
    'showGyro'                  => (bool) $params->get('show_gyro', 1),
    'autoRotate'                => (bool) $params->get('auto_rotate', 0),
    'autoRotateSpeed'           => (float) $params->get('auto_rotate_speed', 2),
    'autoRotateDelay'           => (int) $params->get('auto_rotate_delay', 3000),
];

$config = [
    'type'     => 'single',
    'autoLoad' => (bool) $params->get('single_autoload', 1),
    'display'  => $displayOptions,
];

if ($mode === 'single') {
    // Einzel-Panorama
    $config['type']     = 'single';
    $config['panorama'] = $resolveImg((string) $params->get('single_image', ''));
    $config['yaw']      = (float)  $params->get('single_yaw', 0);
    $config['pitch']    = (float)  $params->get('single_pitch', 0);
    $config['hfov']     = (float)  $params->get('single_hfov', 100);

    // BILDBESCHREIBUNG FÜR SINGLE-MODE
    $config['description'] = [
        'showDescription'           => (bool) $params->get('single_show_description', 0),
        'descriptionText'           => (string) $params->get('single_description_text', ''),
        'descriptionPosition'       => (string) $params->get('single_description_position', 'bottom'),
        'descriptionTextAlign'      => (string) $params->get('single_description_text_align', 'center'),
        'descriptionFontSizeValue'  => (int) $params->get('single_description_font_size_value', 18),
        'descriptionFontSizeUnit'   => (string) $params->get('single_description_font_size_unit', 'px'),
        'descriptionTextColor'      => (string) $params->get('single_description_text_color', '#ffffff'),
        'descriptionBgColor'        => (string) $params->get('single_description_bg_color', '#000000'),
        'descriptionBgOpacity'      => (float) $params->get('single_description_bg_opacity', 0.7),
    ];

    // Hotspots für Single-Mode
    $singleHotspotsRaw = $params->get('single_hotspots', []);
    if (is_string($singleHotspotsRaw) && $singleHotspotsRaw !== '') {
        $tmp = json_decode($singleHotspotsRaw, true);
        if (is_array($tmp)) {
            $singleHotspotsRaw = $tmp;
        }
    }
    $singleHotspotsRaw = $toArray($singleHotspotsRaw);
    $config['hotSpots'] = $processHotspots($singleHotspotsRaw);

} else {
    // Mehrere Szenen
    $config['type']    = 'multi';
    $config['default'] = [
        'firstScene' => null,
        'autoLoad'   => true,
    ];
    $config['scenes']  = [];

    // Rohwert holen
    $scenesRaw = $params->get('scenes', []);

    // JSON-String? -> dekodieren
    if (is_string($scenesRaw) && $scenesRaw !== '') {
        $tmp = json_decode($scenesRaw, true);
        if (is_array($tmp)) {
            $scenesRaw = $tmp;
        }
    }

    $scenesRaw = $toArray($scenesRaw);
    $seq = 1;
    $firstSceneKey = null;

    foreach ($scenesRaw as $entry) {
        $scene = $get($entry, 'scene', $entry);
        $scene = $toArray($scene);

        // Leere Einträge überspringen
        $img = $resolveImg((string) $get($scene, 'scene_image', ''));
        if ($img === '') {
            continue;
        }

        // Scene-ID
        $idRaw   = trim((string) $get($scene, 'scene_id', ''));
        $sceneId = ($idRaw !== '') ? $idRaw : ('scene' . $seq);

        // Grunddaten
        $sceneConf = [
            'type'        => 'equirectangular',
            'panorama'    => $img,
            'yaw'         => (float)  $get($scene, 'yaw', 0),
            'pitch'       => (float)  $get($scene, 'pitch', 0),
            'hfov'        => (float)  $get($scene, 'hfov', 100),
            'northOffset' => (float)  $get($scene, 'northOffset', 0),
        ];

        // TITEL NUR WENN AKTIVIERT
        $showTitle = (bool) $get($scene, 'show_title', 0);
        if ($showTitle) {
            $titleText = trim((string) $get($scene, 'title', ''));
            if ($titleText !== '') {
                $sceneConf['title'] = $titleText;
            }
        }

        // BILDBESCHREIBUNG FÜR DIESE SZENE
        $sceneConf['description'] = [
            'showDescription'           => (bool) $get($scene, 'show_description', 0),
            'descriptionText'           => (string) $get($scene, 'description_text', ''),
            'descriptionPosition'       => (string) $get($scene, 'description_position', 'bottom'),
            'descriptionTextAlign'      => (string) $get($scene, 'description_text_align', 'center'),
            'descriptionFontSizeValue'  => (int) $get($scene, 'description_font_size_value', 18),
            'descriptionFontSizeUnit'   => (string) $get($scene, 'description_font_size_unit', 'px'),
            'descriptionTextColor'      => (string) $get($scene, 'description_text_color', '#ffffff'),
            'descriptionBgColor'        => (string) $get($scene, 'description_bg_color', '#000000'),
            'descriptionBgOpacity'      => (float) $get($scene, 'description_bg_opacity', 0.7),
        ];

        // Hotspots verarbeiten
        $hotspotsRaw = $get($scene, 'hotspots', []);
        $hotspotsRaw = $toArray($hotspotsRaw);
        $sceneConf['hotSpots'] = $processHotspots($hotspotsRaw);

        $config['scenes'][$sceneId] = $sceneConf;

        if ($firstSceneKey === null) {
            $firstSceneKey = $sceneId;
        }
        $seq++;
    }

    if ($firstSceneKey !== null) {
        $config['default']['firstScene'] = $firstSceneKey;
    }
}

// an das Layout geben
require ModuleHelper::getLayoutPath('mod_pannellum', $params->get('layout', 'default'));
