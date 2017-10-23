<?php

namespace Deployer;

use Deployer\Task\Context;
use Symfony\Component\Console\Input\InputOption;

require 'recipe/magento2.php';

// Tasks
desc('Deploy assets');
task('magento:deploy:assets', function () {
    run('{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy {{assets_locales}}');
});

desc('Clear OPCache cache');
task('deploy:resetOPCache', function() {
    $resetScriptFilename = "resetOPCache.php";
    $moveToReleaseFolder = "cd {{release_path}}";
    $resetScriptContent = '<?php echo opcache_reset() ? "Successfully reset opcache" : "Something went wrong trying to reset opcache"; ?>';
    $createResetScript = "echo '$resetScriptContent' > $resetScriptFilename";
    $executeResetScript= "curl {{base_url}}/$resetScriptFilename";
    $removeResetScript = "rm $resetScriptFilename";

    run("$moveToReleaseFolder && $createResetScript && $executeResetScript && $removeResetScript");
});

desc('Magento2 deployment operations');
task('deploy:magento', [
    'magento:enable',
    'magento:compile',
    'magento:deploy:assets',
    'magento:upgrade:db',
    'magento:cache:flush',
]);

desc('Magento2 deployment operations');
task('deploy:magento-maintenance', [
    'magento:enable',
    'magento:compile',
    'magento:deploy:assets',
    'magento:maintenance:enable',
    'magento:upgrade:db',
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
    download($localDump, $remoteDump);
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
    $serverConfig = Context::get()->getServer()->getConfiguration();
    $sshOptions = [
        '-A',
        '-o UserKnownHostsFile=/dev/null',
        '-o StrictHostKeyChecking=no'
    ];


    $username = $serverConfig->getUser() ? $serverConfig->getUser() : null;
    if (!empty($username)) {
        $username .= '@';
    }
    $hostname = $serverConfig->getHost();

    if ($serverConfig->getConfigFile()) {
        $sshOptions[] = '-F ' . escapeshellarg($serverConfig->getConfigFile());
    }

    if ($serverConfig->getPort()) {
        $sshOptions[] = '-p ' . escapeshellarg($serverConfig->getPort());
    }

    if ($serverConfig->getPrivateKey()) {
        $sshOptions[] = '-i ' . escapeshellarg($serverConfig->getPrivateKey());
    } elseif ($serverConfig->getPemFile()) {
        $sshOptions[] = '-i ' . escapeshellarg($serverConfig->getPemFile());
    }

    if ($serverConfig->getPty()) {
        $sshOptions[] = '-t';
    }

    $sshCommand = 'ssh ' . implode(' ', $sshOptions);
    $remotePath = '{{current_path}}/pub/media/';

    $excludeDirs = array_map(function($dir) {
        return '--exclude '.$dir;
    }, get('media_pull_exclude_dirs'));
    $excludeDirsParameter = implode(' ', $excludeDirs);

    $timeout = 300;
    if (input()->hasOption('media-pull-timeout')) {
        $timeout = input()->getOption('media-pull-timeout');
    }

    runLocally(
        'rsync -arvuzi '.$excludeDirsParameter.' -e "'.$sshCommand.'" '.$username . $hostname.':'.$remotePath.' pub/media/',
        $timeout
    );
});
