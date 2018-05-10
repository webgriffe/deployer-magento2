<?php

namespace Deployer;

use Deployer\Task\Context;
use Symfony\Component\Console\Input\InputOption;

// TODO Add deployer version check (now it works only with Deployer >= 5.0)

require 'recipe/magento2.php';

// Rewriting shared_dirs of parent recipe to make the make var/log and var/session shared, too
set('shared_dirs', [
    'var/log',
    'var/session',
    'var/backup',
    'pub/media',
]);

// Tasks
set('deploy_mode', 'production');
desc('Set Magento deploy mode');
task('magento:mode:set', function () {
    run('{{bin/php}} {{release_path}}/bin/magento deploy:mode:set -s {{deploy_mode}}');
});

desc('Deploy assets');
task('magento:deploy:assets', function () {
    if (get('deploy_mode') === 'production') {
        run('{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy {{assets_locales}}');
    }
});

desc('Clear OPCache cache');
task('deploy:resetOPCache', function() {
    $resetScriptFilename = "resetOPCache.php";
    $moveToReleaseFolder = "cd {{release_path}}";
    $resetScriptContent = '<?php echo opcache_reset() ? "Successfully reset opcache" : "Something went wrong trying to reset opcache"; ?>';
    $createResetScript = "echo '$resetScriptContent' > $resetScriptFilename";
    $executeResetScript= "curl -k {{base_url}}/$resetScriptFilename";
    $removeResetScript = "rm $resetScriptFilename";

    run("$moveToReleaseFolder && $createResetScript && $executeResetScript && $removeResetScript");
});

desc('Magento2 deployment operations');
task('deploy:magento', [
    'magento:enable',
    'magento:module:disable',
    'magento:mode:set',
    'magento:compile',
    'magento:upgrade:db',
    'magento:deploy:assets',
    'magento:cache:flush',
]);

desc('Magento2 deployment operations');
task('deploy:magento-maintenance', [
    'magento:enable',
    'magento:module:disable',
    'magento:mode:set',
    'magento:compile',
    'magento:maintenance:enable',
    'magento:upgrade:db',
    'magento:deploy:assets',
    'magento:cache:flush',
    'magento:maintenance:disable'
]);

desc('Deploy your project');
task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:magento',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:resetOPCache',
    'cleanup',
    'success'
]);

desc('Deploy your project with maintenance');
task('deploy:maintenance', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:magento-maintenance',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:resetOPCache',
    'cleanup',
    'success'
]);

desc('Deploy your project');
task('deploy-first', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');



desc('Create Magento database dump');
task('magento:db-dump', function () {
    run('cd {{current_path}} && n98-magerun2.phar db:dump -n -c gz ~');
});

desc('Pull Magento database to local');
task('magento:db-pull', function () {
    $fileName = uniqid('dbdump_');
    $remoteDump = "/tmp/{$fileName}.sql.gz";
    run('cd {{current_path}} && n98-magerun2.phar db:dump -n -c gz ' . $remoteDump);
    $localDump =  tempnam(sys_get_temp_dir(), 'deployer_') . '.sql.gz';
    download($remoteDump, $localDump);
    runLocally('n98-magerun2.phar db:import -n -c gz ' . $localDump);
    runLocally('n98-magerun2.phar cache:disable layout block_html full_page');
});

// Magento media pull exclude dirs (paths must be relative to the media dir)
set('media_pull_exclude_dirs', []);

option(
    'media-pull-timeout',
    null,
    InputOption::VALUE_OPTIONAL,
    'Timeout for media-pull task in seconds (default is 300s)'
);
desc('Pull Magento media to local');
task('magento:media-pull', function () {
    $remotePath = '{{current_path}}/pub/media/';
    $localPath = 'pub/media/';

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

set('modules_to_disable', []);
desc('Disable modules');
task('magento:module:disable', function () {
    $modulesToDisable = get('modules_to_disable');
    if (empty($modulesToDisable)) {
        return;
    }
    run('{{bin/php}} {{release_path}}/bin/magento module:disable ' . implode(' ', $modulesToDisable));
});
