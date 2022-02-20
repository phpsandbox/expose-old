<?php

namespace Deployer;

// Include the Laravel & rsync recipes
require 'vendor/deployer/deployer/recipe/laravel.php';
require 'contrib/rsync.php';

define("SOURCE_ROOT", dirname(__DIR__, 3));

set('application', 'Expose');
set('ssh_multiplexing', true); // Speed up deployment

set('rsync_src', function () {
    return SOURCE_ROOT; // If your project isn't in the root, you'll need to change this.
});

// Configuring the rsync exclusions.
// You'll want to exclude anything that you don't want on the production server.
add('rsync', [
    'exclude' => [
        '.git',
        '/.env',
        '/vendor/',
        '/node_modules/',
        '.github',
        'deploy.php',
    ],
]);

host('ciroue.com') // Name of the server
->setHostname('100.25.14.85') // Hostname or IP address
->setRemoteUser('ubuntu') // SSH user
//->stage('production') // Deployment stage (production, staging, etc)
->setDeployPath('/var/www/expose');

set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader');

after('deploy:failed', 'deploy:unlock'); // Unlock after failed deploy

task('composer:update', function (): void {
    $output = run('sudo composer self-update --2');
    writeln($output);
});

desc('Deploy the application');

// Set up a deployer task to copy secrets to the server.
// Grabs the dotenv file from the github secret
task('deploy:secrets', function () {
    file_put_contents(SOURCE_ROOT . '/.env', getenv('DOT_ENV'));
    upload(SOURCE_ROOT . '/.env', get('deploy_path') . '/shared');
});

task('deploy', [
    'deploy:info',
    'deploy:lock',
    'deploy:release',
    'rsync', // Deploy code & built assets
    'deploy:secrets', // Deploy secrets
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
]);

$completeRelease = function (): void {
    run('sudo supervisorctl restart all');

    within('{{release_path}}', function (): void {
        run("php expose --version");
    });
};

task('deploy:done:production', $completeRelease);
after('deploy', 'deploy:done:production');
