<?php declare(strict_types = 1);

namespace Mikk3lRo\atomix\Tests;

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\databases\Dbs;
use Mikk3lRo\atomix\io\OutputLogger;

putenv('isUnitTest=1');

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
        $dbIn = new Db('mysql', 'mysql', 'root', '');
        Dbs::define($dbIn);
        $dbOut = Dbs::get('mysql');
        $this->assertTrue($dbIn === $dbOut);
    }


    public function testTurnsQuerylogOnWhenRegisteringDatabase()
    {
        Dbs::enableQueryLog();

        $db2 = new Db('mysql2', 'mysql', 'root', '');
        $this->assertFalse($db2->isQueryLogEnabled());
        Dbs::define($db2);
        $this->assertTrue($db2->isQueryLogEnabled());
        $this->assertTrue(Dbs::get('mysql2')->isQueryLogEnabled());

        Dbs::disableQueryLog();
        $this->assertFalse($db2->isQueryLogEnabled());

        $db3 = new Db('mysql3', 'mysql', 'root', '');
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
        $db3 = new Db('mysql3', 'mysql', 'root', '');
        Dbs::define($db3);
    }


    public function testEmulatedSql()
    {
        $this->assertEquals(Dbs::get('mysql')->getEmulatedSql('SELECT * FROM `test` WHERE ?', 'string'), "SELECT * FROM `test` WHERE 'string';");
        $this->assertEquals(Dbs::get('mysql')->getEmulatedSql('SELECT * FROM `test` WHERE a=? AND b=?', array(1, 'string in array')), "SELECT * FROM `test` WHERE a=1 AND b='string in array';");
    }


    public function testQueryDebug()
    {
        Dbs::enableQueryLog();
        $this->assertEquals('123', Dbs::get('mysql')->queryOneCell('SELECT 123'));
        $debug = Dbs::getQueryDebug();

        $this->assertRegExp('#1 queries on "mysql"\s+SELECT 123#s', $debug);
    }
}
