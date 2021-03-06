<?php declare(strict_types = 1);

namespace Mikk3lRo\Tests;

use Mikk3lRo\atomix\databases\Dbs;
use Mikk3lRo\Tests\DatabaseHelpers;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/DatabaseHelpers.php';


/**
 * @covers Mikk3lRo\atomix\databases\Dbs
 * @covers Mikk3lRo\atomix\databases\Db
 *
 * TODO: Individual cover tags!
 */
final class DbsTest extends TestCase
{
    public function testRegisterDatabase()
    {
        $dbIn = DatabaseHelpers::getRootDb();
        Dbs::define($dbIn);
        $dbOut = Dbs::get('mysql');
        $this->assertTrue($dbIn === $dbOut);
    }


    public function testTurnsQuerylogOnWhenRegisteringDatabase()
    {
        Dbs::enableQueryLog();

        $db2 = DatabaseHelpers::getRootDb('mysql2');
        $this->assertFalse($db2->isQueryLogEnabled());
        Dbs::define($db2);
        $this->assertTrue($db2->isQueryLogEnabled());
        $this->assertTrue(Dbs::get('mysql2')->isQueryLogEnabled());

        Dbs::disableQueryLog();
        $this->assertFalse($db2->isQueryLogEnabled());

        $db3 = DatabaseHelpers::getRootDb('mysql3');
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
        $db3 = DatabaseHelpers::getRootDb('mysql3');
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
