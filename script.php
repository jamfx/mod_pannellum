<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Installationsskript
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;

return new class () implements InstallerScriptInterface {
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Entfernt beim Deinstallieren die generierten Thumbnails.
     * Es werden nur Dateien "thumb_*.jpg" in images/mod_pannellum/thumbnails gelöscht;
     * Ordner werden nur entfernt, wenn sie danach leer sind.
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        $base = JPATH_ROOT . '/images/mod_pannellum';
        $dir  = $base . '/thumbnails';

        if (is_dir($dir)) {
            foreach (glob($dir . '/thumb_*.jpg') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);
        }

        if (is_dir($base)) {
            @rmdir($base);
        }

        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Weist nach Installation/Update darauf hin, wenn die GD-Erweiterung fehlt.
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (\in_array($type, ['install', 'update'], true) && !\extension_loaded('gd')) {
            Factory::getApplication()->enqueueMessage(Text::_('MOD_PANNELLUM_INSTALL_GD_WARNING'), 'warning');
        }

        return true;
    }
};
