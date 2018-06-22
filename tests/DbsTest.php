<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\databases\Dbs;
use Mikk3lRo\atomix\databases\DbAdmin;
use Mikk3lRo\atomix\databases\DbHelpers;

putenv('isUnitTest=1');

$outputLogger = new Mikk3lRo\atomix\io\Logger();
$outputLogger->enableOutput();

final class DbsTest extends TestCase
{
    public function testRegisterDatabase() {
        $dbIn = new Db('mysql', 'mysql', 'root', '');
        Dbs::register($dbIn);
        $dbOut = Dbs::get('mysql');
        $this->assertTrue($dbIn === $dbOut);
    }
    public function testTurnsQuerylogOnWhenRegisteringDatabase() {
        Dbs::enableQueryLog();

        $db2 = new Db('mysql2', 'mysql', 'root', '');
        $this->assertFalse($db2->isQueryLogEnabled());
        Dbs::register($db2);
        $this->assertTrue($db2->isQueryLogEnabled());
        $this->assertTrue(Dbs::get('mysql2')->isQueryLogEnabled());

        Dbs::disableQueryLog();
        $this->assertFalse($db2->isQueryLogEnabled());

        $db3 = new Db('mysql3', 'mysql', 'root', '');
        $this->assertFalse($db3->isQueryLogEnabled());
        Dbs::register($db3);
        $this->assertFalse($db3->isQueryLogEnabled());
        $this->assertFalse(Dbs::get('mysql3')->isQueryLogEnabled());
    }
    public function testThrowsOnUnregisteredDatabase() {
        $this->expectExceptionMessage('was not registered');
        Dbs::get('This slug does not exist');
    }

    public function testEmulatedSql() {
        $this->assertEquals(Dbs::getEmulatedSql('SELECT * FROM `test` WHERE ?', 'string'), "SELECT * FROM `test` WHERE 'string';");
        $this->assertEquals(Dbs::getEmulatedSql('SELECT * FROM `test` WHERE a=? AND b=?', array(1, 'string in array')), "SELECT * FROM `test` WHERE a=1 AND b='string in array';");
    }

    public function testQueryDebug() {
        Dbs::enableQueryLog();
        $this->assertEquals('123', Dbs::get('mysql')->queryOneCell('SELECT 123'));
        $debug = Dbs::getQueryDebug();

        $this->assertRegExp("#1 queries on mysql\s+SELECT 123#s", $debug);
    }
}