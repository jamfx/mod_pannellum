<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Galerie-Layout
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;

/** @var array $config Konfiguration aus dem Dispatcher (PannellumHelper::getConfig) */
$gallery = $config['gallery'];
$display = $config['display'];
$items   = $gallery['items'];

if ($items === []) {
    return;
}

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$galleryId = 'mod-pannellum-gallery-' . (int) $module->id;

// Seitenverhältnis als CSS-Wert: 16_9 → 16/9
$aspect = str_replace('_', '/', $gallery['aspectRatio']);

// Schatten (aus den Anzeigeoptionen) auf den Kacheln
$shadow = '';

if (!empty($display['shadowEnabled'])) {
    $hex = ltrim((string) $display['shadowColor'], '#');
    $hex = strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex;
    $rgb = preg_match('/^[0-9a-f]{6}$/i', $hex)
        ? hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2))
        : '0, 0, 0';

    $shadow = (int) $display['shadowHorizontal'] . 'px ' . (int) $display['shadowVertical'] . 'px '
        . (int) $display['shadowBlur'] . 'px ' . (int) $display['shadowSpread'] . 'px '
        . 'rgba(' . $rgb . ', ' . (float) $display['shadowOpacity'] . ')';
}

$style = [
    '--gallery-columns: ' . (int) $gallery['columns'],
    '--gallery-gap: ' . (int) $gallery['gap'] . 'px',
    '--gallery-aspect-ratio: ' . $aspect,
];

if ($gallery['divider']) {
    $style[] = '--gallery-divider-color: ' . (preg_match('/^#[0-9a-f]{3,8}$/i', $gallery['dividerColor']) ? $gallery['dividerColor'] : '#cccccc');
    $style[] = '--gallery-divider-width: ' . (int) $gallery['dividerWidth'] . 'px';
}

if ($shadow !== '') {
    $style[] = '--gallery-shadow: ' . $shadow;
}

$classes = ['mod-pannellum-gallery'];

if ($gallery['hoverEffect']) {
    $classes[] = 'mod-pannellum-gallery--hover';
}

if ($gallery['divider']) {
    $classes[] = 'mod-pannellum-gallery--divider';
}

if ($gallery['cssClass'] !== '') {
    $classes[] = $gallery['cssClass'];
}

$modalHeight = (int) $display['heightValue'] . ($display['heightUnit'] === 'vh' ? 'vh' : 'px');
?>
<div class="<?php echo $e(implode(' ', $classes)); ?>"
     id="<?php echo $e($galleryId); ?>"
     style="<?php echo $e(implode('; ', $style)); ?>"
     data-columns="<?php echo (int) $gallery['columns']; ?>"
     data-viewer-mode="<?php echo $e($gallery['viewerMode']); ?>"
     data-gap="<?php echo (int) $gallery['gap']; ?>"
     data-modal-height="<?php echo $e($modalHeight); ?>"
     data-show-compass="<?php echo !empty($display['showCompass']) ? '1' : '0'; ?>"
     data-auto-rotate-speed="<?php echo $e($display['autoRotateSpeed']); ?>"
     data-auto-rotate-delay="<?php echo (int) $display['autoRotateDelay']; ?>"
     data-label-close="<?php echo $e(Text::_('MOD_PANNELLUM_GALLERY_CLOSE')); ?>">

    <?php if ($gallery['title'] !== '') : ?>
        <h3 class="mod-pannellum-gallery__title"><?php echo $e($gallery['title']); ?></h3>
    <?php endif; ?>

    <div class="mod-pannellum-gallery__grid">
        <?php foreach ($items as $item) : ?>
            <?php $label = $item['title'] !== '' ? $item['title'] : $item['alt']; ?>
            <div class="mod-pannellum-gallery__item"
                 data-image="<?php echo $e($item['image']); ?>"
                 data-title="<?php echo $e($item['title']); ?>"
                 data-yaw="<?php echo $e($item['yaw']); ?>"
                 data-pitch="<?php echo $e($item['pitch']); ?>"
                 data-hfov="<?php echo $e($item['hfov']); ?>"
                 data-autorotate="<?php echo $item['autorotate'] ? '1' : '0'; ?>"
                 data-fullscreen="<?php echo $item['fullscreen'] ? '1' : '0'; ?>"
                 role="button"
                 tabindex="0"
                 aria-label="<?php echo $e($label); ?>">

                <div class="mod-pannellum-gallery__media">
                    <img src="<?php echo $e($item['thumbnail']); ?>"
                         alt="<?php echo $e($item['alt']); ?>"
                         class="mod-pannellum-gallery__thumbnail"
                         loading="lazy">

                    <?php if ($item['title'] !== '') : ?>
                        <div class="mod-pannellum-gallery__overlay">
                            <span class="mod-pannellum-gallery__item-title"><?php echo $e($item['title']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
