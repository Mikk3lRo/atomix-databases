<?php
chdir(__DIR__);
if (isset($argv[1]) && $argv[1] === 'coverage') {
    `vendor/bin/phpunit --coverage-html=/var/www/html/ --whitelist src`;
    exit();
}

//We can't test atomix_daemon, because systemd doesn't run in our docker test container :(
if (!in_array(getenv('BITBUCKET_REPO_SLUG'), array('atomix_daemon'))) {
    echo "******** UNIT TESTS ********\n";
    succeed_or_die('chmod +x vendor/bin/phpunit vendor/phpunit/phpunit/phpunit');

    if (isset($argv[1])) {
        if (file_exists('tests/' . $argv[1] . 'Test.php')) {
            succeed_or_die('vendor/bin/phpunit tests/' . $argv[1] . 'Test.php');
        }
    } else {
        succeed_or_die('vendor/bin/phpunit');
    }
}

echo "******** CODE SNIFF ********\n";
succeed_or_die('chmod +x vendor/bin/phpcs vendor/squizlabs/php_codesniffer/bin/phpcs');
succeed_or_die('vendor/bin/phpcs -s');

echo "******** ALL TESTS SUCCEEDED ********\n";
exit(0);

function succeed_or_die($cmd) {
    passthru($cmd, $retval);
    if ($retval !== 0) {
        echo "Command failed:\n    $cmd\n";
        exit(1);
    }
}