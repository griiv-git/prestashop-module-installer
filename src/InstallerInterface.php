<?php
/**
 * This file is part of the griiv/prestashop-module-installer package.
 *
 * (c) Arnaud Scoté <arnaud@griiv.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 **/

namespace Griiv\Prestashop\Module\Installer;

interface InstallerInterface
{
    public function install();

    public function uninstall();

    public function installModuleTab(array $tabInfo);

    public function uninstallModuleTab($tabClass);
}