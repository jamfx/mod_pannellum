<?php
defined('_JEXEC') or die;

$instanceId = 'pannellum-' . (int) $module->id;
$wrapperId = 'pannellum-wrapper-' . (int) $module->id;

// $config kommt aus mod_pannellum.php
$json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Höhe aus Display-Optionen
$heightUnit = isset($config['display']['heightUnit']) ? $config['display']['heightUnit'] : 'px';
$heightValue = isset($config['display']['heightValue']) ? $config['display']['heightValue'] : 500;
$height = $heightValue . $heightUnit;

// Shadow Optionen
$shadowEnabled = isset($config['display']['shadowEnabled']) ? (bool)$config['display']['shadowEnabled'] : false;
$shadowHorizontal = isset($config['display']['shadowHorizontal']) ? $config['display']['shadowHorizontal'] : 0;
$shadowVertical = isset($config['display']['shadowVertical']) ? $config['display']['shadowVertical'] : 4;
$shadowBlur = isset($config['display']['shadowBlur']) ? $config['display']['shadowBlur'] : 8;
$shadowSpread = isset($config['display']['shadowSpread']) ? $config['display']['shadowSpread'] : 0;
$shadowColor = isset($config['display']['shadowColor']) ? $config['display']['shadowColor'] : '#000000';
$shadowOpacity = isset($config['display']['shadowOpacity']) ? $config['display']['shadowOpacity'] : 0.3;

// Konvertiere Hex zu RGBA
function hexToRgba($hex, $alpha = 1.0) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r, $g, $b, $alpha)";
}

// Shadow-Styles zusammenbauen
$shadowStyles = '';
if ($shadowEnabled) {
    $shadowColorRgba = hexToRgba($shadowColor, $shadowOpacity);
    $shadowStyles = 'box-shadow: ' . 
                    htmlspecialchars($shadowHorizontal, ENT_QUOTES, 'UTF-8') . 'px ' . 
                    htmlspecialchars($shadowVertical, ENT_QUOTES, 'UTF-8') . 'px ' . 
                    htmlspecialchars($shadowBlur, ENT_QUOTES, 'UTF-8') . 'px ' . 
                    htmlspecialchars($shadowSpread, ENT_QUOTES, 'UTF-8') . 'px ' . 
                    htmlspecialchars($shadowColorRgba, ENT_QUOTES, 'UTF-8') . ';';
}

// Bildbeschreibung ermitteln (Single-Mode oder erste Szene im Multi-Mode)
$descriptionData = null;
if ($config['type'] === 'single' && isset($config['description'])) {
    $descriptionData = $config['description'];
} elseif ($config['type'] === 'multi' && isset($config['scenes'])) {
    // Hole die erste Szene
    $firstSceneId = isset($config['default']['firstScene']) ? $config['default']['firstScene'] : null;
    if ($firstSceneId && isset($config['scenes'][$firstSceneId]['description'])) {
        $descriptionData = $config['scenes'][$firstSceneId]['description'];
    }
}

$showDescription = false;
$descriptionText = '';
$descriptionPosition = 'bottom';
$descriptionTextAlign = 'center';
$descriptionFontSize = '18px';
$descriptionTextColor = '#ffffff';
$descriptionBgColorRgba = 'rgba(0, 0, 0, 0.7)';

if ($descriptionData) {
    $showDescription = isset($descriptionData['showDescription']) ? (bool)$descriptionData['showDescription'] : false;
    $descriptionText = isset($descriptionData['descriptionText']) ? $descriptionData['descriptionText'] : '';
    $descriptionPosition = isset($descriptionData['descriptionPosition']) ? $descriptionData['descriptionPosition'] : 'bottom';
    $descriptionTextAlign = isset($descriptionData['descriptionTextAlign']) ? $descriptionData['descriptionTextAlign'] : 'center';
    
    $descriptionFontSizeValue = isset($descriptionData['descriptionFontSizeValue']) ? $descriptionData['descriptionFontSizeValue'] : 18;
    $descriptionFontSizeUnit = isset($descriptionData['descriptionFontSizeUnit']) ? $descriptionData['descriptionFontSizeUnit'] : 'px';
    $descriptionFontSize = $descriptionFontSizeValue . $descriptionFontSizeUnit;
    
    $descriptionTextColor = isset($descriptionData['descriptionTextColor']) ? $descriptionData['descriptionTextColor'] : '#ffffff';
    $descriptionBgColor = isset($descriptionData['descriptionBgColor']) ? $descriptionData['descriptionBgColor'] : '#000000';
    $descriptionBgOpacity = isset($descriptionData['descriptionBgOpacity']) ? $descriptionData['descriptionBgOpacity'] : 0.7;
    $descriptionBgColorRgba = hexToRgba($descriptionBgColor, $descriptionBgOpacity);
}
?>

<div id="<?php echo $wrapperId; ?>" class="pannellum-wrapper" style="position: relative; width: 100%; <?php echo $shadowStyles; ?>">
    <?php if ($showDescription && !empty($descriptionText) && $descriptionPosition === 'top'): ?>
    <div class="pannellum-description-overlay pannellum-description-top" style="
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        padding: 15px 20px;
        background: <?php echo htmlspecialchars($descriptionBgColorRgba, ENT_QUOTES, 'UTF-8'); ?>;
        color: <?php echo htmlspecialchars($descriptionTextColor, ENT_QUOTES, 'UTF-8'); ?>;
        font-size: <?php echo htmlspecialchars($descriptionFontSize, ENT_QUOTES, 'UTF-8'); ?>;
        font-weight: 600;
        text-align: <?php echo htmlspecialchars($descriptionTextAlign, ENT_QUOTES, 'UTF-8'); ?>;
        z-index: 10;
        pointer-events: none;
    ">
        <?php echo htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <div
      id="<?php echo $instanceId; ?>"
      class="pannellum-module"
      style="width:100%; height:<?php echo htmlspecialchars($height, ENT_QUOTES, 'UTF-8'); ?>;"
    ></div>

    <?php if ($showDescription && !empty($descriptionText) && $descriptionPosition === 'bottom'): ?>
    <div class="pannellum-description-overlay pannellum-description-bottom" style="
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 15px 20px;
        background: <?php echo htmlspecialchars($descriptionBgColorRgba, ENT_QUOTES, 'UTF-8'); ?>;
        color: <?php echo htmlspecialchars($descriptionTextColor, ENT_QUOTES, 'UTF-8'); ?>;
        font-size: <?php echo htmlspecialchars($descriptionFontSize, ENT_QUOTES, 'UTF-8'); ?>;
        font-weight: 600;
        text-align: <?php echo htmlspecialchars($descriptionTextAlign, ENT_QUOTES, 'UTF-8'); ?>;
        z-index: 10;
        pointer-events: none;
    ">
        <?php echo htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
</div>

<script type="application/json" id="<?php echo $instanceId; ?>-config"><?php echo $json; ?></script>
