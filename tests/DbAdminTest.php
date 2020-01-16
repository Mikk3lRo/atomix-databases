<?php declare(strict_types = 1);

namespace Mikk3lRo\Tests;

use Exception;
use Mikk3lRo\atomix\databases\DbAdmin;
use Mikk3lRo\atomix\logger\OutputLogger;
use Mikk3lRo\Tests\DatabaseHelpers;
use PHPUnit\Framework\TestCase;
use function count;

putenv('isUnitTest=1');

if (!getenv('MYSQLPORT')) {
    putenv('MYSQLPORT=3306');
}
if (!getenv('MYSQLHOST')) {
    putenv('MYSQLHOST=localhost');
}
if (!getenv('MYSQLPASS')) {
    putenv('MYSQLPASS=');
}

/**
 * @covers Mikk3lRo\atomix\databases\DbAdmin
 * @covers Mikk3lRo\atomix\databases\Db
 *
 * TODO: Individual cover tags!
 */
final class DbAdminTest extends TestCase
{
    public static function tearDownAfterClass() : void
    {
        DatabaseHelpers::cleanTestUserAndDb();
    }


    public static function setUpBeforeClass() : void
    {
        DatabaseHelpers::cleanTestUserAndDb();
    }


    public function testCreateDatabase()
    {
        $db = DatabaseHelpers::getRootDb();

        $rowsPre = $db->queryAllRows("SHOW DATABASES LIKE 'phpunittesttestdb'");
        $preCount = count($rowsPre);

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->createDatabase('phpunittesttestdb');

        $rowsPost = $db->queryAllRows("SHOW DATABASES LIKE 'phpunittesttestdb'");
        $postCount = count($rowsPost);

        $this->assertEquals(0, $preCount);
        $this->assertEquals(1, $postCount);
    }


    /**
     * @depends testCreateDatabase
     */
    public function testCreateUser()
    {
        DatabaseHelpers::cleanTestUserAndDb();

        $db = DatabaseHelpers::getRootDb();

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->createUser('phpunittesttestuser', 'phpunittesttestpass');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(0, $preCount);
        $this->assertEquals(2, $postCount);
    }


    /**
     * @depends testCreateUser
     */
    public function testGrantUser()
    {
        DatabaseHelpers::cleanTestUserAndDb();
        $db = DatabaseHelpers::getRootDb();
        $dbAdmin = new DbAdmin($db);
        $dbAdmin->createDatabase('phpunittesttestdb');
        $dbAdmin->createUser('phpunittesttestuser', 'phpunittesttestpass', ['localhost', '%']);

        //Still shouldn't have access to the database
        $exceptionMsgPre = '';
        try {
            $dbPre = DatabaseHelpers::getTestDb();
            $dbPre->setLogger(new OutputLogger);
            ob_start();
            $dbPre->queryOneCell("SHOW TABLES IN `phpunittesttestdb`");
        } catch (Exception $e) {
            $exceptionMsgPre = $e->getMessage();
        }

        $outputPre = ob_get_clean();
        $this->assertStringContainsString('Failed to connect to database "phpunittesttestdb": see log for details', $exceptionMsgPre);
        $this->assertStringContainsString('Access denied for user', $outputPre);


        $db->setLogger(new OutputLogger);

        $this->expectOutputString('');
        $this->assertEquals(1, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $dbAdmin->grantAccessToDbs('phpunittesttestuser', 'phpunittesttestdb');
        $dbAdmin->grantAccessToDbs('phpunittesttestuser', 'phpunittest\_%', array('SELECT', 'INSERT'));

        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));

        //Should now have access to the database
        $exceptionMsgPost = '';
        try {
            $dbPost = DatabaseHelpers::getTestDb();
            $dbPost->setLogger(new OutputLogger);
            ob_start();
            $dbPost->queryOneCell("SHOW TABLES IN `phpunittesttestdb`");
        } catch (Exception $e) {
            $exceptionMsgPost = $e->getMessage();
        }

        $outputPost = ob_get_clean();
        $this->assertEquals('', $exceptionMsgPost);
        $this->assertEquals('', $outputPost);
    }


    /**
     * @depends testGrantUser
     */
    public function testSetPassword()
    {
        $db = DatabaseHelpers::getRootDb();

        $prePassword = $db->queryOneCell("SELECT Password FROM `user` WHERE `User`='phpunittesttestuser' LIMIT 1");

        $dbPre = DatabaseHelpers::getTestDb();
        $preSuccess = true;
        try {
            $dbPre->connect();
        } catch (Exception $e) {
            $preSuccess = false;
        }

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->setPassword('phpunittesttestuser', 'phpunittesttestpass2');

        $postPassword = $db->queryOneCell("SELECT Password FROM `user` WHERE `User`='phpunittesttestuser' LIMIT 1");

        $this->assertNotEquals($prePassword, $postPassword);

        $dbPost = DatabaseHelpers::getTestDb(null, null, null, 'phpunittesttestpass2');
        $postSuccess = true;
        try {
            $dbPost->connect();
        } catch (Exception $e) {
            $postSuccess = false;
        }

        $this->assertTrue($preSuccess);
        $this->assertTrue($postSuccess);

        //Set it back for other tests!
        $dbAdmin->setPassword('phpunittesttestuser', 'phpunittesttestpass');
    }


    /**
     * @depends testSetPassword
     */
    public function testRemoveAllowedHost()
    {
        $db = DatabaseHelpers::getRootDb();

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->removeAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(2, $preCount);
        $this->assertEquals(1, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));
    }


    /**
     * @depends testRemoveAllowedHost
     */
    public function testRemoveAllowedHostThatIsNotAllowed()
    {
        $db = DatabaseHelpers::getRootDb();

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->removeAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(1, $preCount);
        $this->assertEquals(1, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));
    }


    /**
     * @depends testRemoveAllowedHostThatIsNotAllowed
     */
    public function testAddAllowedHost()
    {
        $db = DatabaseHelpers::getRootDb();

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->addAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(1, $preCount);
        $this->assertEquals(2, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));
    }


    /**
     * @depends testAddAllowedHost
     */
    public function testAddAllowedHostThatExists()
    {
        $db = DatabaseHelpers::getRootDb();

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->addAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(2, $preCount);
        $this->assertEquals(2, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));
    }


    /**
     * @depends testAddAllowedHost
     */
    public function testGrantSuperuserAccess()
    {
        //Shouldn't be able to create a database
        $exceptionMsgPre = '';
        try {
            $dbPre = DatabaseHelpers::getTestDb();
            $dbPre->setLogger(new OutputLogger);
            ob_start();
            $dbPre->query("CREATE DATABASE IF NOT EXISTS `phpunittesttestdb2`");
        } catch (Exception $e) {
            $exceptionMsgPre = $e->getMessage();
        }

        $outputPre = ob_get_clean();
        $this->assertStringContainsString('Access denied for user', $exceptionMsgPre);
        $this->assertEquals('', $outputPre);


        $db = DatabaseHelpers::getRootDb();
        $db->setLogger(new OutputLogger);
        $dbAdmin = new DbAdmin($db);

        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));
        $dbAdmin->grantSuperuserAccess('phpunittesttestuser');
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'%'")));

        //Should now have access to create a database
        $exceptionMsgPost = '';
        try {
            $dbPost = DatabaseHelpers::getTestDb();
            $dbPost->setLogger(new OutputLogger);
            ob_start();
            $dbPost->query("CREATE DATABASE IF NOT EXISTS `phpunittesttestdb2`");
        } catch (Exception $e) {
            $exceptionMsgPost = $e->getMessage();
        }

        $outputPost = ob_get_clean();
        $this->assertEquals('', $exceptionMsgPost);
        $this->assertEquals('', $outputPost);

        $dbPost->query("DROP DATABASE IF EXISTS `phpunittesttestdb2`");
    }


    /**
     * @depends testAddAllowedHostThatExists
     */
    public function testDropUser()
    {
        $db = DatabaseHelpers::getRootDb();

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->dropUser('phpunittesttestuser');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(2, $preCount);
        $this->assertEquals(0, $postCount);
    }


    /**
     * @depends testDropUser
     */
    public function testDropDatabase()
    {
        $db = DatabaseHelpers::getRootDb();

        $rowsPre = $db->queryAllRows("SHOW DATABASES LIKE 'phpunittesttestdb'");
        $preCount = count($rowsPre);

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->dropDatabase('phpunittesttestdb');

        $rowsPost = $db->queryAllRows("SHOW DATABASES LIKE 'phpunittesttestdb'");
        $postCount = count($rowsPost);

        $this->assertEquals(1, $preCount);
        $this->assertEquals(0, $postCount);
    }


    public function testGetCreateThrowsOnNonexistingUser()
    {
        $db = DatabaseHelpers::getRootDb();

        $dbAdmin = new DbAdmin($db);
        $this->expectExceptionMessage('Could not get CREATE USER');
        $dbAdmin->getCreateStatementForUser('phpunittestnonexistentuser');
    }
}
