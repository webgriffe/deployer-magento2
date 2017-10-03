Magento 2 Deployer Recipe
===========================

Deployer recipe for Magento 2 project.
This adds some useful tasks for db and media operations and it overrides some 

Install
-------

Install it using Composer:

	$ composer require --dev webgriffe/deployer-magento2 dev-master
	
Usage
-----

Require the recipe in your `deploy.php`:

```php

namespace Deployer;

require __DIR__ . '/vendor/webgriffe/deployer-magento2/magento.php';

// ... usual Deployer configuration
```


This recipe overrides some tasks of the original Deployer Magento2 recipe:

* The tasks `deploy:magento` and `deploy` now do not enable and disable the maintenance page. Instead, if you want to do that you must use one of two new tasks `deploy:magento-maintenance` and `deploy-maintenance`.
* The task `magento:deploy:assets` now uses the `assets_locales` environment variable that you can define in your deploy.php file like this:
    ```php
    set('assets_locales', 'en_GB en_US it_IT'); 
    ```
It also adds the `magento:first-deploy` task which is useful when depoying a project for the first time (when Magento is not installed).

Magento useful tasks
--------------------

This recipe provides some Magento's related tasks:

* `magento:db-dump`: creates a gzipped database dump on the remote stage in the deploy user's home directory
* `magento:db-pull`: pulls database from the remote stage to local environment
* `magento:media-pull`: pulls Magento media from the remote stage to local environment
  * With the `media_pull_exclude_dirs` environment variable it's possible to specify which sub-directories of the media dir you want to exclude. Usage example:
    
    ```php
    add('media_pull_exclude_dirs', ['wysiwyg']);
    ```
  * You can specify the execution timeout for this task by using the `media-pull-timeout` argument while running the command. This is needed because the default execution's time for the tasks on deployer is of 300s and when you first run this command it could take a while if the media directory is big (or maybe after a huge import). Usage example:
    
    ```
    dep magento:media-pull --media-pull-timeout 900
    ```


License
-------

This library is under the MIT license. See the complete license in the LICENSE file.

Credits
-------

Developed by [Webgriffe®](http://www.webgriffe.com/). Please, report to us any bug or suggestion by GitHub issues.