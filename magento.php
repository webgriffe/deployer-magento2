<?php

namespace Deployer;

require 'recipe/magento2.php';

// Tasks
desc('Deploy assets');
task('magento:deploy:assets', function () {
    run('{{bin/php}} {{release_path}}/bin/magento setup:static-content:deploy {{assets_locales}}');
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
