<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\databases\Dbs;
use Mikk3lRo\atomix\databases\DbAdmin;
use Mikk3lRo\atomix\databases\DbHelpers;
use Mikk3lRo\atomix\io\Formatters;

putenv('isUnitTest=1');

$outputLogger = new Mikk3lRo\atomix\io\Logger();
$outputLogger->enableOutput();

final class DbTest extends TestCase
{
    public function testCanConnectAndQuery() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }
    public function testQueryOneRow() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $result = $db->queryOneRow("SELECT '123' as `abc`");

        $this->assertEquals(array('abc' => '123'), $result);
    }
    public function testQueryOneCell() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $result = $db->queryOneCell("SELECT '123' as `abc`");

        $this->assertEquals('123', $result);
    }
    public function testCanConnectOnPort() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '', '127.0.0.1', 3306);
        $db->setLogger($outputLogger);
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }

    public function testFailUser() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'invaliduser', 'invalidpass');
        $db->setLogger($outputLogger);
        $this->expectExceptionMessage('Access denied');
        $db->connect();
    }

    public function testFailConnect() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'invaliduser', 'invalidpass', 'not.a.domain.that.is.valid');
        $db->setLogger($outputLogger);
        $this->expectExceptionMessage('server host');
        $db->connect();
    }

    public function testCanUseArgsArray() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        foreach ($db->query("SELECT ? as `a`, ? as `b`", array(1, 'two')) as $row) {
            $result = $row;
        }

        $this->assertEquals(array('a' => '1', 'b' => 'two'), $result);
    }

    public function testCanUseArgsString() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        foreach ($db->query("SELECT ? as `a`", 'three') as $row) {
            $result = $row;
        }

        $this->assertEquals(array('a' => 'three'), $result);
    }

    public function testQueryLog() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);
        $db->enableQueryLog();

        $this->assertEquals(array(), $db->getQueryLog(false, false));
        $db->query("SELECT ? as `a`", 'three');
        $queryLog = $db->getQueryLog();
        $this->assertEquals(1, count($queryLog));
        $this->assertEquals("SELECT 'three' as `a`;", Dbs::getEmulatedSql($queryLog[0]['sql'], $queryLog[0]['args']));
   }

    public function testInvalidSQL() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);
        $this->expectExceptionMessage('You have an error in your SQL syntax');
        $db->query("this is not SQL", 'three');
    }

    public function testLostConnection() {
        if (getenv('BITBUCKET_REPO_SLUG')) {
            //Can't stop service in docker container :/
            $this->assertEquals(1, 1);
            return;
        }

        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $db->connect();

        `systemctl stop mysql`;

        try {
            $this->expectOutputRegex("#Connection lost on mysql.*Auto-connect.*Failed to connect#s");
            $db->query("SELECT ? as `a`", 'three');
        } catch (\Exception $e) {
            $this->assertRegExp('#Failed to connect#', $e->getMessage());
        }
        `systemctl start mysql`;
    }

    private function createTestDb() {
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->query("CREATE DATABASE IF NOT EXISTS `phpunittesttestdb`");
        $db->query("CREATE TABLE IF NOT EXISTS " . DbHelpers::escapedTableName('phpunittesttestdb.phpunittesttesttable') . "
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
        $sqlInsert = Formatters::replaceTags("INSERT INTO {table} ({fields}) VALUES ({placeholders})", array(
            'table' => DbHelpers::escapedTableName('phpunittesttestdb.phpunittesttesttable'),
            'fields' => DbHelpers::insertFields($insertRow),
            'placeholders' => DbHelpers::insertPlaceholders($insertRow)
        ));
        $db->query($sqlInsert, $insertRow);
    }

    private function deleteTestDb() {
        $db = new Db('mysql', 'mysql', 'root', '');
        foreach (array('localhost', '127.0.0.1') as $allowedHost) {
            $db->query("DROP USER ?@?", array(
                'phpunittesttestuser',
                $allowedHost
            ));
        }
        $db->query("DROP DATABASE IF EXISTS `phpunittesttestdb`");
    }

    public function testGetInsertId() {
        $this->createTestDb();
        global $outputLogger;
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
        $db->setLogger($outputLogger);

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $insertRow = array(
            'value' => 'Second value'
        );
        $sqlInsert = Formatters::replaceTags("INSERT INTO {table} ({fields}) VALUES ({placeholders})", array(
            'table' => DbHelpers::escapedTableName('phpunittesttesttable'),
            'fields' => DbHelpers::insertFields($insertRow),
            'placeholders' => DbHelpers::insertPlaceholders($insertRow)
        ));
        $db->query($sqlInsert, $insertRow);

        $insertedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");
        $insertedCount2 = count($db->queryAllRows("SELECT * FROM `phpunittesttesttable`"));
        $insertedKey = $db->getInsertId();

        $this->assertEquals(1, $preCount);
        $this->assertEquals(2, $insertedCount);
        $this->assertEquals(2, $insertedKey);

        $this->deleteTestDb();
    }

    public function testExport() {
        $this->createTestDb();
        global $outputLogger;
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
        $db->setLogger($outputLogger);

        if (file_exists('/tmp/phpunittestexport.sql')) {
            unlink('/tmp/phpunittestexport.sql');
        }

        $db->export('/tmp/phpunittestexport.sql');

        $this->assertContains('CREATE TABLE `phpunittesttesttable`', file_get_contents('/tmp/phpunittestexport.sql'));
        $this->deleteTestDb();
    }

    public function testExportOnPort() {
        $this->createTestDb();
        global $outputLogger;
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass', '127.0.0.1');
        $db->setLogger($outputLogger);

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
    public function testImport() {
        $this->createTestDb();
        global $outputLogger;
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
        $db->setLogger($outputLogger);

        $db->query('DELETE FROM `phpunittesttesttable`');

        $clearedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $db->import('/tmp/phpunittestexport.sql');

        $importedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $this->assertEquals(0, $clearedCount);
        $this->assertEquals(1, $importedCount);
        $this->deleteTestDb();
    }

    /**
     * @depends testExport
     */
    public function testThrowsOnImportInvalidFile() {
        global $outputLogger;
        $db = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
        $db->setLogger($outputLogger);

        $this->expectExceptionMessage('file does not exist or is empty');
        $db->import('/tmp/nonexistingfilename.sql');
    }
}