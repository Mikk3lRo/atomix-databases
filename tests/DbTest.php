<?php
declare(strict_types=1);

namespace Mikk3lRo\atomix\Tests;

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\io\OutputLogger;

putenv('isUnitTest=1');

final class DbTest extends TestCase
{
    public function testCanConnectAndQuery()
    {
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }


    public function testQueryHelpers()
    {
        $db = new Db('', '', '', '');
        $this->assertEquals('?', $db->insertPlaceholders(array('value' => 'test')));
        $this->assertEquals('?, ?', $db->insertPlaceholders(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`', $db->insertFields(array('value' => 'test')));
        $this->assertEquals('`key`, `value`', $db->insertFields(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`=?', $db->updateFieldsAndValues(array('value' => 'test')));
        $this->assertEquals('`key`=?, `value`=?', $db->updateFieldsAndValues(array('key' => 1, 'value' => 'test')));
    }


    public function testQueryOneRow()
    {
        $db = new Db('mysql', 'mysql', 'root', '');

        $result = $db->queryOneRow("SELECT '123' as `abc`");

        $this->assertEquals(array('abc' => '123'), $result);
    }


    public function testQueryOneCell()
    {
        $db = new Db('mysql', 'mysql', 'root', '');

        $result = $db->queryOneCell("SELECT '123' as `abc`");

        $this->assertEquals('123', $result);
    }


    public function testCanConnectOnPort()
    {
        $db = new Db('mysql', 'mysql', 'root', '', '127.0.0.1', 3306);
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }


    public function testFailUser()
    {
        $db = new Db('mysql', 'mysql', 'invaliduser', 'invalidpass');
        $db->setLogger(new OutputLogger);
        $this->expectOutputRegex('#Access denied#');
        $this->expectExceptionMessage('Failed to connect to database "mysql": see log for details');
        $db->connect();
    }


    public function testFailConnect()
    {
        $db = new Db('mysql', 'mysql', 'invaliduser', 'invalidpass', 'not.a.domain.that.is.valid');
        $db->setLogger(new OutputLogger);
        $this->expectOutputRegex('#Unknown MySQL server host#');
        $this->expectExceptionMessage('Failed to connect to database "mysql": see log for details');
        $db->connect();
    }


    public function testCanUseArgsArray()
    {
        $db = new Db('mysql', 'mysql', 'root', '');

        foreach ($db->query("SELECT ? as `a`, ? as `b`", array(1, 'two')) as $row) {
            $result = $row;
        }

        $this->assertEquals(array('a' => '1', 'b' => 'two'), $result);
    }


    public function testCanUseArgsString()
    {
        $db = new Db('mysql', 'mysql', 'root', '');

        foreach ($db->query("SELECT ? as `a`", 'three') as $row) {
            $result = $row;
        }

        $this->assertEquals(array('a' => 'three'), $result);
    }


    public function testQueryLog()
    {
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->enableQueryLog();

        $this->assertEquals(array(), $db->getQueryLogArray(false, false));
        $db->query("SELECT ? as `a`", 'three');
        $queryLog = $db->getQueryLogArray();
        $this->assertEquals(1, count($queryLog));
        $this->assertEquals("SELECT 'three' as `a`;", $db->getEmulatedSql($queryLog[0]['sql'], $queryLog[0]['args']));
    }


    public function testInvalidSql()
    {
        $db = new Db('mysql', 'mysql', 'root', '');
        $this->expectExceptionMessage('You have an error in your SQL syntax');
        $db->query("this is not SQL", 'three');
    }


    public function testLostConnection()
    {
        if (getenv('BITBUCKET_REPO_SLUG')) {
            //Can't stop service in docker container :/
            $this->assertEquals(1, 1);
            return;
        }

        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger(new OutputLogger);

        $db->connect();

        `systemctl stop mysql`;

        try {
            $this->expectOutputRegex("#Connection lost on .*will attempt to reconnect.*Failed to connect#s");
            $db->query("SELECT ? as `a`", 'three');
        } catch (\Exception $e) {
            $this->assertRegExp('#Failed to connect#', $e->getMessage());
        }
        `systemctl start mysql`;
    }


    private function createTestDb()
    {
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->query("CREATE DATABASE IF NOT EXISTS `phpunittesttestdb`");
        $db->query("CREATE TABLE IF NOT EXISTS `phpunittesttestdb`.`phpunittesttesttable`
                      (
                        `key` INT NOT NULL AUTO_INCREMENT ,
                        `value` VARCHAR(1024) NOT NULL ,
                        PRIMARY KEY (`key`),
                        INDEX (`value`)
                      )
                    ENGINE = InnoDB;");

        foreach (array('localhost', '127.0.0.1') as $allowedHost) {
            $db->query("CREATE USER IF NOT EXISTS ?@? IDENTIFIED BY ?", array(
                'phpunittesttestuser',
                $allowedHost,
                'phpunittesttestpass'
            ));
            $db->query("GRANT ALL PRIVILEGES ON phpunittesttestdb.* TO ?@?", array(
                'phpunittesttestuser',
                $allowedHost
            ));
        }

        $insertRow = array(
            'value' => 'First value'
        );
        $sqlInsert = sprintf(
            "INSERT INTO `phpunittesttestdb`.`phpunittesttesttable` (%s) VALUES (%s)",
            $db->insertFields($insertRow),
            $db->insertPlaceholders($insertRow)
        );
        $db->query($sqlInsert, $insertRow);
    }


    private function deleteTestDb()
    {
        $db = new Db('mysql', 'mysql', 'root', '');
        foreach (array('localhost', '127.0.0.1') as $allowedHost) {
            $db->query("DROP USER ?@?", array(
                'phpunittesttestuser',
                $allowedHost
            ));
        }
        $db->query("DROP DATABASE IF EXISTS `phpunittesttestdb`");
    }


    public function testGetInsertId()
    {
        $this->createTestDb();
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $insertRow = array(
            'value' => 'Second value'
        );
        $sqlInsert = sprintf(
            "INSERT INTO `phpunittesttesttable` (%s) VALUES (%s)",
            $db->insertFields($insertRow),
            $db->insertPlaceholders($insertRow)
        );
        $db->query($sqlInsert, $insertRow);

        $insertedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");
        $insertedKey = $db->getInsertId();

        $this->assertEquals(1, $preCount);
        $this->assertEquals(2, $insertedCount);
        $this->assertEquals(2, $insertedKey);

        $this->deleteTestDb();
    }


    public function testExport()
    {
        $this->createTestDb();
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');

        if (file_exists('/tmp/phpunittestexport.sql')) {
            unlink('/tmp/phpunittestexport.sql');
        }

        $db->export('/tmp/phpunittestexport.sql');

        $this->assertContains('CREATE TABLE `phpunittesttesttable`', file_get_contents('/tmp/phpunittestexport.sql'));
        $this->deleteTestDb();
    }


    public function testExportThrowsOnFailure()
    {
        $this->createTestDb();
        $db = new Db('phpunittesttestdbfail', 'phpunittesttestdbfail', 'phpunittesttestuserfail', 'phpunittesttestpassfail');

        if (file_exists('/tmp/phpunittestexportfail.sql')) {
            unlink('/tmp/phpunittestexportfail.sql');
        }

        $this->expectExceptionMessage('Export to "/tmp/phpunittestexportfail.sql" failed, file does not exist or is empty!');

        $db->export('/tmp/phpunittestexportfail.sql');

        $this->deleteTestDb();
    }


    public function testExportOnPort()
    {
        $this->createTestDb();
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass', '127.0.0.1');

        if (file_exists('/tmp/phpunittestexportfromport.sql')) {
            unlink('/tmp/phpunittestexportfromport.sql');
        }

        $db->export('/tmp/phpunittestexportfromport.sql');

        $this->assertContains('CREATE TABLE `phpunittesttesttable`', file_get_contents('/tmp/phpunittestexport.sql'));

        if (file_exists('/tmp/phpunittestexportfromport.sql')) {
            unlink('/tmp/phpunittestexportfromport.sql');
        }
        $this->deleteTestDb();
    }


    /**
     * @depends testExport
     */
    public function testImport()
    {
        $this->createTestDb();
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');

        $db->query('DELETE FROM `phpunittesttesttable`');

        $clearedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $db->import('/tmp/phpunittestexport.sql');

        $importedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $this->assertEquals(0, $clearedCount);
        $this->assertEquals(1, $importedCount);
        $this->deleteTestDb();
    }


    public function testThrowsOnImportInvalidFile()
    {
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');

        $this->expectExceptionMessage('file does not exist or is empty');
        $db->import('/tmp/nonexistingfilename.sql');
    }
}
