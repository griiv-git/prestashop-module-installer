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

use PrestaShopBundle\Install\SqlLoader;

class GriivInstaller extends InstallerAbstract
{

    /**
     * @return bool
     */
    protected function installDatabase()
    {
        if (!$this->filesystem->exists(sprintf('%s%s/sql/install.sql', _PS_MODULE_DIR_, $this->module->name))) {
            return true;
        }

        $db = \Db::getInstance();
        $sqlLoader = new SqlLoader();
        $sqlLoader->setMetaData([
            'DB_PREFIX' => _DB_PREFIX_,
            'MYSQL_ENGINE' => _MYSQL_ENGINE_,
        ]);
        $db->execute('START TRANSACTION');
        $installSql = $sqlLoader->parse_file($this->module->getLocalPath() . 'sql/install.sql');
        if (!$installSql) {
            $db->execute('ROLLBACK');
            return false;
        }

        $db->execute('COMMIT');

        return true;
    }

    /**
     * @return bool
     */
    protected function uninstallDatabase()
    {
        if (!$this->filesystem->exists(sprintf('%s%s/sql/uninstall.sql', _PS_MODULE_DIR_, $this->module->name))) {
            return true;
        }

        $db = \Db::getInstance();
        $sqlLoader = new SqlLoader();
        $sqlLoader->setMetaData([
            'DB_PREFIX' => _DB_PREFIX_,
            'MYSQL_ENGINE' => _MYSQL_ENGINE_,
        ]);
        $db->execute('START TRANSACTION');
        $installSql = $sqlLoader->parse_file($this->module->getLocalPath() . 'sql/uninstall.sql');
        if (!$installSql) {
            $db->execute('ROLLBACK');
            return false;
        }

        $db->execute('COMMIT');

        return true;
    }

    /**
     * @param  array $tabs
     * @return bool
     * @throws \Exception
     */
    protected function installTabs(array $tabs)
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
    protected function uninstallTabs(array $tabs)
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
    protected function registerHooks(array $hooks)
    {
        return $this->module->registerHook($hooks);
    }

    /**
     * @param  array $hooks
     * @return bool
     */
    protected function unregisterHooks(array $hooks)
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
    protected function executeQuery($query)
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
     * @param  array $queries
     * @return bool
     */
    protected function executeQueries(array $queries)
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