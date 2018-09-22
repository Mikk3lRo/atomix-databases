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

final class DbAdminTest extends TestCase
{
    public function testCreateDatabase() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $db->query('DROP DATABASE IF EXISTS `phpunittesttestdb`');

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

    public function testCreateUser() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $db->query("DROP USER IF EXISTS 'phpunittesttestuser'@'localhost'");
        $db->query("DROP USER IF EXISTS 'phpunittesttestuser'@'127.0.0.1'");

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
    public function testGrantUser() {
        global $outputLogger;
        //Still shouldn't have access to the database
        $exceptionMsgPre = '';
        try {
            $dbPre = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
            $dbPre->setLogger($outputLogger);
            ob_start();
            $dbPre->queryOneCell("SHOW TABLES IN `phpunittesttestdb`");
        } catch (Exception $e) {
            $exceptionMsgPre = $e->getMessage();
        }

        $outputPre = ob_get_clean();
        $this->assertContains('Access denied for user', $exceptionMsgPre);
        $this->assertContains('Access denied for user', $outputPre);


        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $this->expectOutputString('');
        $this->assertEquals(1, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $dbAdmin = new DbAdmin($db);
        $dbAdmin->grantAccessToDbs('phpunittesttestuser', 'phpunittesttestdb');
        $dbAdmin->grantAccessToDbs('phpunittesttestuser', 'phpunittest\_%', array('SELECT', 'INSERT'));

        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));

        //Should now have access to the database
        $exceptionMsgPost = '';
        try {
            $dbPost = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
            $dbPost->setLogger($outputLogger);
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
    public function testSetPassword() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $prePassword = $db->queryOneCell("SELECT Password FROM `user` WHERE `User`='phpunittesttestuser' LIMIT 1");

        $dbPre = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
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

        $dbPost = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass2');
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
    public function testRemoveAllowedHost() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->removeAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(2, $preCount);
        $this->assertEquals(1, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));
    }

    /**
     * @depends testRemoveAllowedHost
     */
    public function testRemoveAllowedHostThatIsNotAllowed() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->removeAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(1, $preCount);
        $this->assertEquals(1, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));
    }

    /**
     * @depends testRemoveAllowedHostThatIsNotAllowed
     */
    public function testAddAllowedHost() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->addAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(1, $preCount);
        $this->assertEquals(2, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));
    }

    /**
     * @depends testAddAllowedHost
     */
    public function testAddAllowedHostThatExists() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $preCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->addAllowedHostForUser('phpunittesttestuser', 'localhost');

        $postCount = $db->queryOneCell("SELECT COUNT(*) FROM `user` WHERE `User`='phpunittesttestuser'");

        $this->assertEquals(2, $preCount);
        $this->assertEquals(2, $postCount);
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));
    }


    /**
     * @depends testAddAllowedHost
     */
    public function testGrantSuperuserAccess() {
        global $outputLogger;
        //Shouldn't be able to create a database
        $exceptionMsgPre = '';
        try {
            $dbPre = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
            $dbPre->setLogger($outputLogger);
            ob_start();
            $dbPre->query("CREATE DATABASE IF NOT EXISTS `phpunittesttestdb2`");
        } catch (Exception $e) {
            $exceptionMsgPre = $e->getMessage();
        }

        $outputPre = ob_get_clean();
        $this->assertContains('Access denied for user', $exceptionMsgPre);
        $this->assertEquals('', $outputPre);


        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);
        $dbAdmin = new DbAdmin($db);
        
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));
        $dbAdmin->grantSuperuserAccess('phpunittesttestuser');
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'localhost'")));
        $this->assertEquals(3, count($db->queryAllRows("SHOW GRANTS FOR 'phpunittesttestuser'@'127.0.0.1'")));

        //Should now have access to create a database
        $exceptionMsgPost = '';
        try {
            $dbPost = new Db('phpunittesttestdb', 'phpunittesttestdb', 'phpunittesttestuser', 'phpunittesttestpass');
            $dbPost->setLogger($outputLogger);
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
    public function testDropUser() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

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
    public function testDropDatabase() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $rowsPre = $db->queryAllRows("SHOW DATABASES LIKE 'phpunittesttestdb'");
        $preCount = count($rowsPre);

        $dbAdmin = new DbAdmin($db);
        $dbAdmin->dropDatabase('phpunittesttestdb');

        $rowsPost = $db->queryAllRows("SHOW DATABASES LIKE 'phpunittesttestdb'");
        $postCount = count($rowsPost);

        $this->assertEquals(1, $preCount);
        $this->assertEquals(0, $postCount);
    }


    public function testGetCreateThrowsOnNonexistingUser() {
        global $outputLogger;
        $db = new Db('mysql', 'mysql', 'root', '');
        $db->setLogger($outputLogger);

        $dbAdmin = new DbAdmin($db);
        $this->expectExceptionMessage('Could not get CREATE USER');
        $dbAdmin->getCreateStatementForUser('phpunittestnonexistentuser');
    }

}