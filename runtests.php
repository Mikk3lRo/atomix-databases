<?php
chdir(__DIR__);
echo "******** UNIT TESTS ********\n";
succeed_or_die('chmod +x vendor/bin/phpunit vendor/phpunit/phpunit/phpunit');
succeed_or_die('vendor/bin/phpunit');

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