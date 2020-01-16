<?php
chdir(__DIR__);

echo "******** UNIT TESTS ********\n";
succeed_or_die(PHP_BINARY . ' vendor/phpunit/phpunit/phpunit');

echo "******** CODE SNIFF (src) ********\n";
succeed_or_die(PHP_BINARY . ' vendor/squizlabs/php_codesniffer/bin/phpcs -s');

echo "******** CODE SNIFF (tests) ********\n";
succeed_or_die(PHP_BINARY . ' vendor/squizlabs/php_codesniffer/bin/phpcs -s --standard=phpcsTests.xml');

echo "******** ALL TESTS SUCCEEDED ********\n";
exit(0);

function succeed_or_die($cmd) {
    passthru($cmd, $retval);
    if ($retval !== 0) {
        echo "Command failed:\n    $cmd\n";
        exit(1);
    }
}