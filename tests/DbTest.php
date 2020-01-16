<?php declare(strict_types = 1);

namespace Mikk3lRo\Tests;

use Exception;
use Mikk3lRo\atomix\databases\DbHelpers;
use Mikk3lRo\atomix\logger\OutputLogger;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use function count;

require_once __DIR__ . '/DatabaseHelpers.php';

/**
 * @covers Mikk3lRo\atomix\databases\Db
 *
 * TODO: Individual cover tags!
 */
final class DbTest extends TestCase
{
    public static function tearDownAfterClass() : void
    {
        DatabaseHelpers::cleanTestUserAndDb();
    }


    public static function setUpBeforeClass() : void
    {
        DatabaseHelpers::cleanTestUserAndDb();
    }


    public function testCanConnectAndQuery()
    {
        $db = DataBaseHelpers::getRootDb();
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }


    public function testGetPdo()
    {
        $db = DataBaseHelpers::getRootDb();

        $result = $db->getPdo();

        $this->assertInstanceOf(PDO::class, $result);
    }


    public function testQueryOneRow()
    {
        $db = DataBaseHelpers::getRootDb();

        $result = $db->queryOneRow("SELECT '123' as `abc`");

        $this->assertEquals(array('abc' => '123'), $result);
    }


    public function testQueryOneCell()
    {
        $db = DataBaseHelpers::getRootDb();

        $result = $db->queryOneCell("SELECT '123' as `abc`");

        $this->assertEquals('123', $result);
    }


    public function testCanConnectOnPort()
    {
        $db = DataBaseHelpers::getRootDb(null, null, null, null, '127.0.0.1');
        $db->connect();

        foreach ($db->query("SELECT '123' as `abc`") as $row) {
            $result = $row;
        }

        $this->assertEquals(array('abc' => '123'), $result);
    }


    public function testFailUser()
    {
        $db = DataBaseHelpers::getRootDb(null, null, 'incorrect');
        $db->setLogger(new OutputLogger);
        $this->expectOutputRegex('#Access denied#');
        $this->expectExceptionMessage('Failed to connect to database "mysql": see log for details');
        $db->connect();
    }


    public function testFailConnect()
    {
        $db = DataBaseHelpers::getRootDb(null, null, null, null, 'invalid.hostname', 1234);
        $db->setLogger(new OutputLogger);
        $this->expectOutputRegex('#Failed to connect to database#');
        $this->expectExceptionMessage('Failed to connect to database "mysql": see log for details');
        $db->connect();
    }


    public function testCanUseArgsArray()
    {
        $db = DataBaseHelpers::getRootDb();

        foreach ($db->query("SELECT ? as `a`, ? as `b`", array(1, 'two')) as $row) {
            $result = $row;
        }

        $this->assertEquals(array('a' => '1', 'b' => 'two'), $result);
    }


    public function testCanUseArgsString()
    {
        $db = DataBaseHelpers::getRootDb();

        foreach ($db->query("SELECT ? as `a`", 'three') as $row) {
            $result = $row;
        }

        $this->assertEquals(array('a' => 'three'), $result);
    }


    public function testQueryLog()
    {
        $db = DataBaseHelpers::getRootDb();
        $db->enableQueryLog();

        $this->assertEquals(array(), $db->getQueryLogArray(false, false));
        $db->query("SELECT ? as `a`", 'three');
        $queryLog = $db->getQueryLogArray();
        $this->assertEquals(1, count($queryLog));
        $this->assertEquals("SELECT 'three' as `a`;", DbHelpers::getEmulatedSql($queryLog[0]['sql'], $queryLog[0]['args']));
    }


    public function testCanLogQuery()
    {
        $db = DataBaseHelpers::getRootDb();
        $db->enableQueryLog();
        $logger = new OutputLogger();
        $logger->setMaxLogLevel(LogLevel::DEBUG);
        $db->setLogger($logger);

        $this->expectOutputRegex('#SELECT \'three\' as `a`#');
        $db->query("SELECT ? as `a`", 'three');
    }


    public function testInvalidSql()
    {
        $db = DataBaseHelpers::getRootDb();
        $this->expectExceptionMessage('You have an error in your SQL syntax');
        $db->query("this is not SQL", 'three');
    }


    public function testLostConnection()
    {
        if (getenv('BITBUCKET_REPO_SLUG') || getenv('IS_GITHUB')) {
            //Can't stop service in docker container :/
            $this->assertEquals(1, 1);
            return;
        }

        $db = DataBaseHelpers::getRootDb();
        $db->setLogger(new OutputLogger);

        $db->connect();

        `systemctl stop mysql`;

        try {
            $this->expectOutputRegex("#Connection lost on .*will attempt to reconnect.*Failed to connect#s");
            $db->query("SELECT ? as `a`", 'three');
        } catch (Exception $e) {
            $this->assertRegExp('#Failed to connect#', $e->getMessage());
        }
        `systemctl start mysql`;
    }


    public function testGetInsertId()
    {
        DatabaseHelpers::createTestDb();
        $db = DataBaseHelpers::getTestDb();
        $db->setLogger(new OutputLogger());

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $insertRow = array(
            'value' => 'Second value'
        );

        $db->insert("INSERT INTO `phpunittesttesttable`", $insertRow);
        $insertedKey = $db->getInsertId();

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $this->assertEquals(1, $preCount);
        $this->assertEquals(2, $postCount);
        $this->assertEquals(2, $insertedKey);

        DatabaseHelpers::cleanTestUserAndDb();
    }


    public function testExport()
    {
        DatabaseHelpers::createTestDb();
        $db = DataBaseHelpers::getTestDb();

        if (file_exists('/tmp/phpunittestexport.sql')) {
            unlink('/tmp/phpunittestexport.sql');
        }

        $db->export('/tmp/phpunittestexport.sql');

        $this->assertStringContainsString('CREATE TABLE `phpunittesttesttable`', file_get_contents('/tmp/phpunittestexport.sql'));
        DatabaseHelpers::cleanTestUserAndDb();
    }


    public function testExportThrowsOnFailure()
    {
        DatabaseHelpers::createTestDb();
        $db = DataBaseHelpers::getTestDb(null, null, 'incorrect');

        if (file_exists('/tmp/phpunittestexportfail.sql')) {
            unlink('/tmp/phpunittestexportfail.sql');
        }

        $this->expectExceptionMessage('Export to "/tmp/phpunittestexportfail.sql" failed, file does not exist or is empty!');

        $db->export('/tmp/phpunittestexportfail.sql');

        DatabaseHelpers::cleanTestUserAndDb();
    }


    public function testExportOnPort()
    {
        DatabaseHelpers::createTestDb();
        $db = DataBaseHelpers::getTestDb(null, null, null, null, '127.0.0.1');

        if (file_exists('/tmp/phpunittestexportfromport.sql')) {
            unlink('/tmp/phpunittestexportfromport.sql');
        }

        $db->export('/tmp/phpunittestexportfromport.sql');

        $this->assertStringContainsString('CREATE TABLE `phpunittesttesttable`', file_get_contents('/tmp/phpunittestexport.sql'));

        if (file_exists('/tmp/phpunittestexportfromport.sql')) {
            unlink('/tmp/phpunittestexportfromport.sql');
        }
        DatabaseHelpers::cleanTestUserAndDb();
    }


    /**
     * @depends testExport
     */
    public function testImport()
    {
        DatabaseHelpers::createTestDb();
        $db = DataBaseHelpers::getTestDb();

        $db->query('DELETE FROM `phpunittesttesttable`');

        $clearedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $db->import('/tmp/phpunittestexport.sql');

        $importedCount = $db->queryOneCell("SELECT COUNT(*) FROM `phpunittesttesttable`");

        $this->assertEquals(0, $clearedCount);
        $this->assertEquals(1, $importedCount);
        DatabaseHelpers::cleanTestUserAndDb();
    }


    public function testThrowsOnImportInvalidFile()
    {
        $db = DataBaseHelpers::getTestDb();

        $this->expectExceptionMessage('file does not exist or is empty');
        $db->import('/tmp/nonexistingfilename.sql');
    }
}
