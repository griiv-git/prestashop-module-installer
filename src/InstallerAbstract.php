<?php
/**
 * This file is part of the Symfony package.
 *
 * (c) Arnaud Scoté <arnaud@griiv.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 **/

namespace Griiv\Prestashop\Module\Installer;

use PrestaShopBundle\Entity\Repository\TabRepository;
use Symfony\Component\Filesystem\Filesystem;

abstract class InstallerAbstract implements InstallerInterface
{
    protected Filesystem $filesystem;

    protected \Module $module;

    protected TabRepository $tabRepository;

    public function __construct(\Module $module)
    {
        $this->filesystem = new Filesystem();
        $this->module = $module;
        $this->tabRepository = $this->module->get('prestashop.core.admin.tab.repository');
    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        return $this->installDatabase() && $this->registerHooks($this->module->getHooks()) && $this->installTabs($this->module->getTabs());
    }

    /**
     * @return bool
     */
    public function uninstall(): bool
    {
        return $this->uninstallDatabase() && $this->unregisterHooks($this->module->getHooks()) && $this->uninstallTabs($this->module->getTabs());
    }

    /**
     * @return bool
     */
    abstract protected function installDatabase(): bool;

    /**
     * @return bool
     */
    abstract protected function uninstallDatabase(): bool;

    /**
     * @param  array $hooks
     * @return bool
     */
    abstract protected function registerHooks(array $hooks): bool;

    /**
     * @param  array $hooks
     * @return bool
     */
    abstract protected function unregisterHooks(array $hooks): bool;

    /**
     * @param  array $tabs
     * @return bool
     */
    abstract protected function installTabs(array $tabs): bool;

    /**
     * @param  array $tab
     * @return bool
     */
    abstract protected function uninstallTabs(array $tab): bool;


    /**
     * A helper that executes multiple database queries.
     *
     * @return bool
     */
    abstract protected function executeQueries(array $queries): bool;

    /**
     * @param  string $query
     * @return bool
     */
    abstract protected function executeQuery(string $query): bool;

    /**
     * @param  array $tabInfo [
     *                        'class_name' => 'string => Class called when the user will click on your link. This is the class name without the Controller part. Ex : AdminGamification',
     *                        'route_name' => 'string => Symfony route name, if your controller is Symfony-based. Ex: gamification_configuration',
     *                        'name' => 'string|string[] => Label displayed in the menu. If not provided, the class name is shown instead. Ex: Merchant Expertise',
     *                        'parent_class_name' => 'string => The parent menu, if you want to display it in a subcategory. Go farther in this document to see available values.',
     *                        'icon' => 'string => Icon name to use, if any. Ex: shopping_basket'
     *                        'visible' => 'boolean => Whether you want to display the tab or not. Hidden tabs are used when you don’t need a menu item but you still need to handle access rights.'
     *                        'wording' => 'string => The translation key to use to translate the menu label. Ex: Merchant Expertise',
     *                        'wording_domain' => 'string => The translation domain to use to translate the menu label. Ex: 'Modules.Gamification.Admin',
     *                        ]
     * @return bool
     */
    public function installModuleTab(array $tabInfo): bool
    {
        if (isset($tabInfo['parent_class_name'])) {
            if ($tabInfo['parent_class_name'] === '-1') {
                $idTabParent = -1;
            } else {
                $idTabParent = $this->tabRepository->findOneIdByClassName($tabInfo['parent_class_name']);
            }
        } else {
            $idTabParent = $this->tabRepository->findOneIdByClassName('DEFAULT');
        }

        $tab = new \Tab();
        $tab->active = true;
        $tab->enabled = true;
        $tab->module = $this->module->name;

        $tab->class_name = isset($tabInfo['class_name']) ?? null;
        $tab->route_name = isset($tabInfo['route_name']) ?? null;
        $tab->name = isset($tabInfo['name']) ?? null;
        $tab->icon = isset($tabInfo['icon']) ?? null;
        $tab->id_parent = $idTabParent;
        $tab->wording = isset($tabInfo['wording']) ?? null;
        $tab->wording_domain = isset($tabInfo['wording_domain']) ?? null;

        if (!$tab->save()) {
            throw new \Exception(sprintf('Failed to install admin tab %s.', $tab->name));
        }

        return true;
    }

    /**
     * @param  string $tabClass
     * @return bool
     */
    public function uninstallModuleTab(string $tabClass): bool
    {
        $idTab = $this->tabRepository->findOneIdByClassName($tabClass);

        if ($idTab) {
            $tab = new \Tab($idTab);
            return $tab->delete();
        }

        return false;
    }
}
