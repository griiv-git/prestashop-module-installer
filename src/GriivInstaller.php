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

use PrestaShop\PrestaShop\Core\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;

class GriivInstaller extends InstallerAbstract
{

    /**
     * @return bool
     */
    protected function installDatabase(): bool
    {
        $file = new File(sprintf('%s/%s/sql/install.sql', _PS_MODULE_DIR_, $this->module->name));

        if ($this->filesystem->exists($file) && $file->isReadable()) {
            $fileContent = file_get_contents($file->getRealPath());
            $fileContent = str_replace(['DB_PREFIX', 'MYSQL_ENGINE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $fileContent);

            if ($file->getSize() > 0) {
                return $this->executeQuery($fileContent);
            } else {
                return true;
            }
        }

        throw new FileNotFoundException(sprintf('File %s not exist or not readable', $file->getRealPath()));
    }

    /**
     * @return bool
     */
    protected function uninstallDatabase(): bool
    {
        $file = new File(sprintf('%s/%s/sql/uninstall.sql', _PS_MODULE_DIR_, $this->module->name));

        if ($this->filesystem->exists($file) && $file->isReadable()) {
            $fileContent = file_get_contents($file->getRealPath());
            $fileContent = str_replace(['DB_PREFIX', 'MYSQL_ENGINE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $fileContent);

            if ($file->getSize() > 0) {
                return $this->executeQuery($fileContent);
            } else {
                return true;
            }

        }

        throw new FileNotFoundException(sprintf('File %s not exist or not readable', $file->getRealPath()));
    }

    /**
     * @param  array $tabs
     * @return bool
     * @throws \Exception
     */
    protected function installTabs(array $tabs): bool
    {
        $ret = true;

        $dbi = \Db::getInstance();
        $dbi->execute('START TRANSACTION;');

        foreach ($tabs as $tab) {
            $ret = $ret && $this->installModuleTab($tab);
        }

        if (!$ret) {
            $dbi->execute('ROLLBACK;');
            return false;
        }

        $dbi->execute('COMMIT');
        return true;
    }

    /**
     * @param  array $tabs
     * @return bool
     */
    public function uninstallTabs(array $tabs): bool
    {
        $ret = true;

        foreach ($tabs as $tab) {
            $ret = $ret && $this->uninstallModuleTab($tab['class_name']);
        }

        return $ret;
    }

    /**
     * @param  array $hooks
     * @return bool
     */
    public function registerHooks(array $hooks): bool
    {
        return $this->module->registerHook($hooks);
    }

    /**
     * @param  array $hooks
     * @return bool
     */
    public function unregisterHooks(array $hooks): bool
    {
        $ret = true;
        foreach ($hooks as $hookName) {
            $ret = $ret && $this->module->unregisterHook($hookName);
        }

        return $ret;
    }

    /**
     * @param  string $query
     * @return bool
     */
    public function executeQuery(string $query): bool
    {
        $dbi = \Db::getInstance();
        $dbi->execute('START TRANSACTION;');

        if (!$dbi->execute($query)) {
            $dbi->execute('ROLLBACK;');
            return false;
        }

        $dbi->execute('COMMIT;');
        return true;
    }

    /**
     * A helper that executes multiple database queries.
     *
     * @return bool
     */
    protected function executeQueries(array $queries): bool
    {
        $dbi = \Db::getInstance();
        $dbi->execute('START TRANSACTION;');

        foreach ($queries as $query) {
            if (!$dbi->execute($query)) {
                $dbi->execute('ROLLBACK;');
                return false;
            }
        }

        $dbi->execute('COMMIT;');
        return true;
    }
}
