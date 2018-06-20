<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\databases\Dbs;

putenv('isUnitTest=1');

$outputLogger = new Mikk3lRo\atomix\io\Logger();
$outputLogger->enableOutput();

final class DbsTest extends TestCase
{
    public function testCanConnect() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }
}