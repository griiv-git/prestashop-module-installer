# PrestaShop Module Installer

Une bibliothèque PHP réutilisable qui fournit un framework d'installation standardisé pour les modules PrestaShop.

## Table des matières

- [Présentation](#présentation)
- [Installation](#installation)
- [Architecture](#architecture)
- [Utilisation](#utilisation)
- [Fonctionnalités](#fonctionnalités)
- [API Reference](#api-reference)
- [Exemples](#exemples)
- [Prérequis](#prérequis)
- [Licence](#licence)

## Présentation

Ce package abstrait la complexité de l'installation des modules PrestaShop en gérant automatiquement :

- **Base de données** : Création et suppression des schémas SQL avec support transactionnel
- **Hooks** : Enregistrement et désenregistrement des hooks PrestaShop
- **Onglets admin** : Création et suppression des entrées de menu dans le back-office
- **Gestion des erreurs** : Rollback automatique en cas d'échec

### Patterns de conception utilisés

- **Template Method** : `InstallerAbstract` définit le squelette d'installation, `GriivInstaller` implémente les étapes spécifiques
- **Injection de dépendances** : L'instance du module est injectée via le constructeur
- **Interface Segregation** : `InstallerInterface` définit un contrat clair
- **Facade** : Point d'entrée unique (`install()`/`uninstall()`) pour des processus multi-étapes complexes

## Installation

### Via Composer

```bash
composer require griiv/prestashop-module-installer
```

### Manuellement

Clonez le repository et incluez l'autoloader :

```php
require_once 'vendor/autoload.php';
```

## Architecture

### Structure du projet

```
prestashop-module-installer/
├── src/
│   ├── InstallerInterface.php      # Interface définissant le contrat
│   ├── InstallerAbstract.php       # Implémentation de base (template method)
│   └── GriivInstaller.php          # Implémentation concrète
├── composer.json
├── README.md
└── LICENSE
```

### Diagramme des classes

```
┌─────────────────────────┐
│   InstallerInterface    │
├─────────────────────────┤
│ + install()             │
│ + uninstall()           │
│ + installModuleTab()    │
│ + uninstallModuleTab()  │
└───────────┬─────────────┘
            │ implements
            ▼
┌─────────────────────────┐
│   InstallerAbstract     │
├─────────────────────────┤
│ # $module               │
│ # $filesystem           │
│ # $tabRepository        │
├─────────────────────────┤
│ + install()             │
│ + uninstall()           │
│ + installModuleTab()    │
│ + uninstallModuleTab()  │
│ # installDatabase()     │ ← abstract
│ # uninstallDatabase()   │ ← abstract
│ # registerHooks()       │ ← abstract
│ # unregisterHooks()     │ ← abstract
│ # installTabs()         │ ← abstract
│ # uninstallTabs()       │ ← abstract
└───────────┬─────────────┘
            │ extends
            ▼
┌─────────────────────────┐
│     GriivInstaller      │
├─────────────────────────┤
│ + installDatabase()     │
│ + uninstallDatabase()   │
│ + registerHooks()       │
│ + unregisterHooks()     │
│ + installTabs()         │
│ + uninstallTabs()       │
│ + executeQuery()        │
│ + executeQueries()      │
└─────────────────────────┘
```

## Utilisation

### 1. Créer votre installeur de module

Dans votre module PrestaShop, créez une classe qui étend `GriivInstaller` ou utilisez-la directement :

```php
<?php

use Griiv\Prestashop\Module\Installer\GriivInstaller;

class MyModuleInstaller extends GriivInstaller
{
    // Personnalisez si nécessaire
}
```

### 2. Configurer votre module principal

```php
<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MyModule extends Module
{
    private $installer;

    public function __construct()
    {
        $this->name = 'mymodule';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Votre Nom';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Mon Module');
        $this->description = $this->l('Description de mon module');

        // Initialiser l'installeur
        $this->installer = new GriivInstaller($this);
    }

    /**
     * Définir les hooks à enregistrer
     */
    public function getHooks(): array
    {
        return [
            'displayHeader',
            'displayFooter',
            'displayProductPriceBlock',
            'actionProductUpdate',
        ];
    }

    /**
     * Définir les onglets admin à créer
     */
    public function getTabs(): array
    {
        return [
            [
                'class_name' => 'AdminMyModuleSettings',
                'name' => 'Paramètres Mon Module',
                'parent_class_name' => 'AdminParentModulesSf',
                'icon' => 'settings',
                'visible' => true,
            ],
            [
                'class_name' => 'AdminMyModuleStats',
                'route_name' => 'admin_mymodule_stats',
                'name' => 'Statistiques',
                'parent_class_name' => 'AdminMyModuleSettings',
                'icon' => 'assessment',
                'wording' => 'Statistics',
                'wording_domain' => 'Modules.Mymodule.Admin',
            ],
        ];
    }

    public function install(): bool
    {
        return parent::install() && $this->installer->install();
    }

    public function uninstall(): bool
    {
        return $this->installer->uninstall() && parent::uninstall();
    }
}
```

### 3. Créer les fichiers SQL

Créez un dossier `sql` dans votre module avec les fichiers d'installation/désinstallation :

**`sql/install.sql`**

```sql
CREATE TABLE IF NOT EXISTS `{DB_PREFIX}mymodule_data` (
    `id_mymodule` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `value` TEXT,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_mymodule`)
) ENGINE={MYSQL_ENGINE} DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{DB_PREFIX}mymodule_config` (
    `id_config` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(128) NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`id_config`),
    UNIQUE KEY `key` (`key`)
) ENGINE={MYSQL_ENGINE} DEFAULT CHARSET=utf8mb4;
```

**`sql/uninstall.sql`**

```sql
DROP TABLE IF EXISTS `{DB_PREFIX}mymodule_data`;
DROP TABLE IF EXISTS `{DB_PREFIX}mymodule_config`;
```

> **Note** : Les variables `{DB_PREFIX}` et `{MYSQL_ENGINE}` sont automatiquement remplacées par les valeurs de configuration PrestaShop.

## Fonctionnalités

### Gestion de la base de données

Le système de gestion de base de données utilise des transactions pour garantir l'intégrité des données :

```
Installation BD
├─ START TRANSACTION
├─ Chargement de sql/install.sql
├─ Parsing via SqlLoader (remplacement des variables)
├─ Exécution des requêtes
├─ COMMIT (succès) ou ROLLBACK (échec)
```

#### Méthodes utilitaires

```php
// Exécuter une seule requête avec transaction
$this->installer->executeQuery("UPDATE `{DB_PREFIX}table` SET col = 'value'");

// Exécuter plusieurs requêtes atomiquement
$this->installer->executeQueries([
    "INSERT INTO `{DB_PREFIX}table` (col) VALUES ('val1')",
    "INSERT INTO `{DB_PREFIX}table` (col) VALUES ('val2')",
    "UPDATE `{DB_PREFIX}config` SET value = 'new' WHERE key = 'setting'",
]);
```

### Gestion des Hooks

Les hooks sont enregistrés automatiquement lors de l'installation si votre module implémente `getHooks()` :

```php
public function getHooks(): array
{
    return [
        'displayHeader',           // Hook d'affichage dans le header
        'displayFooter',           // Hook d'affichage dans le footer
        'displayProductPriceBlock', // Hook sur la page produit
        'actionCartSave',          // Hook d'action lors de la sauvegarde du panier
        'actionOrderStatusUpdate', // Hook lors du changement de statut commande
    ];
}
```

### Gestion des Onglets Admin

Créez des entrées de menu dans le back-office PrestaShop :

```php
public function getTabs(): array
{
    return [
        [
            // Identifiant unique de la classe du contrôleur
            'class_name' => 'AdminMyModuleMain',

            // Nom affiché dans le menu (peut être un tableau pour multilingue)
            'name' => 'Mon Module',

            // Classe parente pour la hiérarchie du menu
            'parent_class_name' => 'AdminParentModulesSf',

            // Icône Material Icons
            'icon' => 'extension',

            // Visibilité dans le menu
            'visible' => true,
        ],
        [
            'class_name' => 'AdminMyModuleConfig',

            // Route Symfony pour les contrôleurs modernes
            'route_name' => 'admin_mymodule_config',

            'name' => 'Configuration',
            'parent_class_name' => 'AdminMyModuleMain',
            'icon' => 'settings',

            // Clés de traduction i18n
            'wording' => 'Configuration',
            'wording_domain' => 'Modules.Mymodule.Admin',
        ],
    ];
}
```

#### Options disponibles pour les onglets

| Option | Type | Description |
|--------|------|-------------|
| `class_name` | string | **Requis**. Nom de la classe du contrôleur |
| `name` | string\|array | **Requis**. Libellé du menu (multilingue possible) |
| `parent_class_name` | string | Classe parente pour la hiérarchie |
| `route_name` | string | Route Symfony (contrôleurs modernes) |
| `icon` | string | Nom de l'icône Material Icons |
| `visible` | bool | Visibilité du menu (défaut: true) |
| `wording` | string | Clé de traduction |
| `wording_domain` | string | Domaine de traduction |

## API Reference

### InstallerInterface

```php
interface InstallerInterface
{
    public function install(): bool;
    public function uninstall(): bool;
    public function installModuleTab(array $tabInfo): int;
    public function uninstallModuleTab(string $tabClass): bool;
}
```

### InstallerAbstract

| Méthode | Visibilité | Description |
|---------|------------|-------------|
| `install()` | public | Lance l'installation complète |
| `uninstall()` | public | Lance la désinstallation complète |
| `installModuleTab(array $tabInfo)` | public | Installe un onglet admin |
| `uninstallModuleTab(string $tabClass)` | public | Désinstalle un onglet admin |
| `installDatabase()` | protected abstract | Installe le schéma BD |
| `uninstallDatabase()` | protected abstract | Désinstalle le schéma BD |
| `registerHooks(array $hooks)` | protected abstract | Enregistre les hooks |
| `unregisterHooks(array $hooks)` | protected abstract | Désenregistre les hooks |
| `installTabs(array $tabs)` | protected abstract | Installe les onglets |
| `uninstallTabs(array $tabs)` | protected abstract | Désinstalle les onglets |

### GriivInstaller

Hérite de `InstallerAbstract` et ajoute :

| Méthode | Description |
|---------|-------------|
| `executeQuery(string $query)` | Exécute une requête SQL avec transaction |
| `executeQueries(array $queries)` | Exécute plusieurs requêtes atomiquement |

## Exemples

### Exemple complet d'un module

```php
<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Griiv\Prestashop\Module\Installer\GriivInstaller;

class ExampleModule extends Module
{
    private GriivInstaller $installer;

    public function __construct()
    {
        $this->name = 'examplemodule';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Griiv';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.7.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->l('Example Module');
        $this->description = $this->l('Un module exemple utilisant GriivInstaller');

        $this->installer = new GriivInstaller($this);
    }

    public function getHooks(): array
    {
        return [
            'displayHeader',
            'displayFooter',
            'actionFrontControllerSetMedia',
        ];
    }

    public function getTabs(): array
    {
        return [
            [
                'class_name' => 'AdminExampleModule',
                'name' => 'Example Module',
                'parent_class_name' => 'AdminParentModulesSf',
                'icon' => 'star',
            ],
        ];
    }

    public function install(): bool
    {
        return parent::install() && $this->installer->install();
    }

    public function uninstall(): bool
    {
        return $this->installer->uninstall() && parent::uninstall();
    }

    // Implémentation des hooks
    public function hookDisplayHeader($params)
    {
        // Votre code ici
    }

    public function hookDisplayFooter($params)
    {
        // Votre code ici
    }
}
```

### Exécution de requêtes personnalisées

```php
// Dans votre module ou contrôleur
$installer = new GriivInstaller($this->module);

// Requête unique
$success = $installer->executeQuery(
    "INSERT INTO `" . _DB_PREFIX_ . "mymodule_log`
     (action, date_add) VALUES ('user_action', NOW())"
);

// Requêtes multiples (atomique)
$success = $installer->executeQueries([
    "UPDATE `" . _DB_PREFIX_ . "mymodule_config` SET value = '1' WHERE key = 'enabled'",
    "DELETE FROM `" . _DB_PREFIX_ . "mymodule_cache`",
    "INSERT INTO `" . _DB_PREFIX_ . "mymodule_log` (action) VALUES ('config_reset')",
]);
```

## Flux d'installation/désinstallation

### Installation

```
install()
  │
  ├─► installDatabase()
  │     └─ Charge sql/install.sql
  │     └─ Parse avec SqlLoader
  │     └─ Exécute dans une transaction
  │
  ├─► registerHooks()
  │     └─ Appelle module->registerHook() pour chaque hook
  │
  └─► installTabs()
        └─ Crée les entrées de menu admin
        └─ Transaction pour atomicité

Retour: true seulement si TOUTES les étapes réussissent
```

### Désinstallation

```
uninstall()
  │
  ├─► uninstallDatabase()
  │     └─ Charge sql/uninstall.sql
  │     └─ Parse et exécute dans une transaction
  │
  ├─► unregisterHooks()
  │     └─ Appelle module->unregisterHook() pour chaque hook
  │
  └─► uninstallTabs()
        └─ Supprime chaque onglet par class_name

Retour: true seulement si TOUTES les étapes réussissent
```

## Prérequis

- **PHP** : >= 7.2
- **PrestaShop** : 1.7.7.0+
- **Dépendances PrestaShop** :
  - `\Module` - Classe de base des modules
  - `\Tab` - Entité des onglets admin
  - `\Db` - Singleton de base de données
  - `PrestaShopBundle\Install\SqlLoader` - Parser de fichiers SQL
  - `PrestaShopBundle\Entity\Repository\TabRepository` - Repository des onglets
- **Symfony Components** :
  - `Symfony\Component\Filesystem\Filesystem`

## Gestion des erreurs

Le package utilise une stratégie "tout ou rien" :

- **Rollback transactionnel** : En cas d'échec, les transactions BD sont automatiquement annulées
- **Retours booléens** : Toutes les méthodes retournent un booléen indiquant le succès/échec
- **Exceptions** : `installModuleTab()` lance des exceptions pour les échecs critiques
- **Atomicité** : L'installation/désinstallation réussit complètement ou échoue complètement

## Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## Auteur

**Arnaud Scoté** - [arnaud@griiv.fr](mailto:arnaud@griiv.fr)

---

Développé avec ❤️ par [Griiv](https://griiv.fr)
