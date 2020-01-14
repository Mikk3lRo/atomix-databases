<?php
chdir(__DIR__);
succeed_or_die('chmod +x vendor/bin/phpunit vendor/phpunit/phpunit/phpunit');
succeed_or_die('chmod +x vendor/bin/phpcs vendor/squizlabs/php_codesniffer/bin/phpcs vendor/bin/phpcbf vendor/squizlabs/php_codesniffer/bin/phpcbf');

echo "******** UNIT TESTS ********\n";
succeed_or_die('vendor/bin/phpunit');

echo "******** CODE SNIFF (src) ********\n";
succeed_or_die('vendor/bin/phpcs -s');

echo "******** CODE SNIFF (tests) ********\n";
succeed_or_die('vendor/bin/phpcs -s --standard=phpcsTests.xml');

echo "******** ALL TESTS SUCCEEDED ********\n";
exit(0);

function succeed_or_die($cmd) {
    passthru($cmd, $retval);
    if ($retval !== 0) {
        echo "Command failed:\n    $cmd\n";
        exit(1);
    }
}