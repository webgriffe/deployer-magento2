<?php

namespace Deployer;

use Deployer\Task\Context;
use Symfony\Component\Console\Input\InputOption;

// TODO Add deployer version check (now it works only with Deployer >= 5.0)

const DEPLOY_ASSETS_TIMEOUT_OPTION_NAME = 'deploy-assets-timeout';

require 'recipe/common.php';

// Configuration
set('shared_files', [
    'app/etc/env.php',
    'var/.maintenance.ip',
]);
set('shared_dirs', [
    'var/log',
    'var/report',
    'var/session',
    'var/backups',
    'pub/media',
]);
set('writable_dirs', [
    'var',
    'pub/static',
    'pub/media',
]);
set('clear_paths', [
    'CHANGELOG.md',
    'COPYING.txt',
    'LICENSE.txt',
    'LICENSE_AFL.txt',
    'LICENSE_EE.txt',
    'README_EE.md',
]);
set('db_pull_strip_tables', ['@stripped']);
set('deploy_mode', 'production');
set('media_pull_exclude_dirs', []); // Magento media pull exclude dirs (paths must be relative to the media dir)
set('magerun_remote', 'n98-magerun2.phar');
set('magerun_local', getenv('DEPLOYER_MAGERUN_LOCAL') ?: 'n98-magerun2.phar');
set('local_magento_path', getcwd());
set('assets_locales', []);

function is_magento_installed() {
    return test('[ -f {{release_path}}/app/etc/env.php ]') &&
        run('cat {{release_path}}/app/etc/env.php | grep "\'date\'"; true');
}

// Tasks
desc('Compile magento di');
task('magento:compile', function () {
    if (is_magento_installed()) {
        run("{{bin/php}} {{release_path}}/bin/magento setup:di:compile");
        run('cd {{release_path}} && {{bin/composer}} dump-autoload -o');
    }
});
desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    run("if [ -d $(echo {{current_path}}) ]; then {{bin/php}} {{current_path}}/bin/magento maintenance:enable; fi");
});
desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    run("if [ -d $(echo {{current_path}}) ]; then {{bin/php}} {{current_path}}/bin/magento maintenance:disable; fi");
});
desc('Upgrade magento database');
task('magento:upgrade:db', function () {
    if (is_magento_installed()) {
        run("{{bin/php}} {{release_path}}/bin/magento setup:upgrade --keep-generated");
    }
});
desc('Flush Magento Cache');
task('magento:cache:flush', function () {
    if (is_magento_installed()) {
        run("{{bin/php}} {{release_path}}/bin/magento cache:flush");
    }
});
desc('Set Magento deploy mode');
task('magento:mode:set', function () {
    if (is_magento_installed()) {
        run("{{bin/php}} {{release_path}}/bin/magento deploy:mode:set -s {{deploy_mode}}");
    }
});
option(
    DEPLOY_ASSETS_TIMEOUT_OPTION_NAME,
    null,
    InputOption::VALUE_OPTIONAL,
    'Timeout for static-content:deploy task in seconds (default is 300s)'
);
desc('Deploy assets');
task('magento:deploy:assets', function () {
    if (!is_magento_installed()) {
        return;
    }
    $timeout = 300;
    if (input()->hasOption(DEPLOY_ASSETS_TIMEOUT_OPTION_NAME)) {
        $timeout = input()->getOption(DEPLOY_ASSETS_TIMEOUT_OPTION_NAME);
    }
    $locales = implode(' ', get('assets_locales'));
    $themes = implode(
        ' ',
        array_map(
            function ($theme) {
                return '--theme=' . $theme;
            },
            get('assets_themes')
        )
    );
    run(
        "{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy $themes $locales",
        ['timeout' => $timeout]
    );
});
desc('Create Magento database dump');
task('magento:db-dump', function () {
    run('cd {{current_path}} && {{magerun_remote}} db:dump -n -c gz ~');
});
desc('Pull Magento database to local');
task('magento:db-pull', function () {
    $fileName = uniqid('dbdump_');
    $stripTables = implode(' ', get('db_pull_strip_tables'));
    $remoteDump = "/tmp/{$fileName}.sql.gz";

    write('➤ Dumping... ');

    run('cd {{current_path}} && {{magerun_remote}} db:dump -n --strip="'. $stripTables .'"  -c gz ' . $remoteDump);

    write('Done!' . PHP_EOL);
    write('➤ Downloading... ');

    $localDump =  tempnam(sys_get_temp_dir(), 'deployer_') . '.sql.gz';
    download($remoteDump, $localDump);
    run('rm ' . $remoteDump);

    write('Done!' . PHP_EOL);
    write('➤ Importing... ');

    runLocally('cd {{local_magento_path}} && {{magerun_local}} db:import -n --drop-tables -c gz ' . $localDump);
    runLocally('rm ' . $localDump);

    write('Done!' . PHP_EOL);
    write('➤ Running setup:upgrade...');

    runLocally('cd {{local_magento_path}} && {{magerun_local}} setup:upgrade');

    write('Done!' . PHP_EOL);
});
option(
    'media-pull-timeout',
    null,
    InputOption::VALUE_OPTIONAL,
    'Timeout for media-pull task in seconds (default is 300s)'
);
desc('Pull Magento media to local');
task('magento:media-pull', function () {
    $remotePath = '{{current_path}}/pub/media/';
    $localPath = rtrim(get('local_magento_path'), '/');
    $localPath = $localPath . '/pub/media/';

    $excludeDirs = array_map(function($dir) {
        return '--exclude '.$dir;
    }, get('media_pull_exclude_dirs'));

    $timeout = 300;
    if (input()->hasOption('media-pull-timeout')) {
        $timeout = input()->getOption('media-pull-timeout');
    }
    $config = [
        'options' => $excludeDirs,
        'timeout' => $timeout
    ];

    download($remotePath, $localPath, $config);
});

desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'magento:mode:set',
    'magento:upgrade:db',
    'magento:compile',
    'magento:deploy:assets',
    'magento:cache:flush',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);






