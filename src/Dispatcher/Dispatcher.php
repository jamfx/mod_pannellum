<?php
/**
 * @package     mod_pannellum
 * @version     1.1.0
 * @author      Niko Winckel
 * @copyright   (C) 2025 Niko Winckel. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 * @link        https://nik-o-mat.de
 *
 * Pannellum 360° Viewer – Dispatcher
 */

namespace Nikomat\Module\Pannellum\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;

/**
 * Dispatcher class for mod_pannellum
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        /** @var \Nikomat\Module\Pannellum\Site\Helper\PannellumHelper $helper */
        $helper = $this->getHelperFactory()->getHelper('PannellumHelper');

        $helper->registerAssets(
            $this->getApplication()->getDocument()->getWebAssetManager(),
            (string) $data['params']->get('mode', 'single')
        );

        $data['config'] = $helper->getConfig($data['params']);

        return $data;
    }
}
