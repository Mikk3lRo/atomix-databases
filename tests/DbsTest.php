<?php declare(strict_types = 1);

namespace Mikk3lRo\atomix\Tests;

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\databases\Dbs;
use Mikk3lRo\atomix\logger\OutputLogger;

putenv('isUnitTest=1');

/**
 * @covers Mikk3lRo\atomix\databases\Dbs
 * @covers Mikk3lRo\atomix\databases\Db
 *
 * TODO: Individual cover tags!
 */
final class DbsTest extends TestCase
{
    private function getRootDb($id = 'mysql') : Db
    {
        if (getenv('GITHUB_MYSQLPORT')) {
            $db = new Db($id, 'mysql', 'root', getenv('GITHUB_MYSQLPASS'), '127.0.0.1', intval(getenv('GITHUB_MYSQLPORT')));
        } else {
            $db = new Db($id, 'mysql', 'root', '');
        }
        return $db;
    }


    public function testRegisterDatabase()
    {
        $dbIn = $this->getRootDb();
        Dbs::define($dbIn);
        $dbOut = Dbs::get('mysql');
        $this->assertTrue($dbIn === $dbOut);
    }


    public function testTurnsQuerylogOnWhenRegisteringDatabase()
    {
        Dbs::enableQueryLog();

        $db2 = $this->getRootDb('mysql2');
        $this->assertFalse($db2->isQueryLogEnabled());
        Dbs::define($db2);
        $this->assertTrue($db2->isQueryLogEnabled());
        $this->assertTrue(Dbs::get('mysql2')->isQueryLogEnabled());

        Dbs::disableQueryLog();
        $this->assertFalse($db2->isQueryLogEnabled());

        $db3 = $this->getRootDb('mysql3');
        $this->assertFalse($db3->isQueryLogEnabled());
        Dbs::define($db3);
        $this->assertFalse($db3->isQueryLogEnabled());
        $this->assertFalse(Dbs::get('mysql3')->isQueryLogEnabled());
    }


    public function testThrowsOnUnregisteredDatabase()
    {
        $this->expectExceptionMessage('was not registered');
        Dbs::get('This slug does not exist');
    }


    public function testThrowsOnReregisterDatabase()
    {
        $this->expectExceptionMessage('Already have a database defined for the slug');
        $db3 = $this->getRootDb('mysql3');
        Dbs::define($db3);
    }


    public function testQueryDebug()
    {
        Dbs::enableQueryLog();
        $this->assertEquals('123', Dbs::get('mysql')->queryOneCell('SELECT 123'));
        $debug = Dbs::getQueryDebug();

        $this->assertRegExp('#1 queries on "mysql"\s+SELECT 123#s', $debug);
    }
}
