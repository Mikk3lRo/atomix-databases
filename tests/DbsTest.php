<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases;

putenv('isUnitTest=1');

final class DbsTest extends TestCase
{
    public function testJustFail() {
        $this->assertEquals(1, 2);
    }
}