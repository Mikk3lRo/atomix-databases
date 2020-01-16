<?php declare(strict_types = 1);

namespace Mikk3lRo\Tests;

use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\databases\DbHelpers;

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

class DatabaseHelpers
{
    public function getRootDb($id = null, $name = null, $user = null, $pass = null, $host = null, $port = null) : Db
    {
        return new Db(
            $id === null ? 'mysql' : $id,
            $name === null ? 'mysql' : $name,
            $user === null ? 'root' : $user,
            $pass === null ? getenv('MYSQLPASS') : $pass,
            $host === null ? getenv('MYSQLHOST') : $host,
            $port === null ? intval(getenv('MYSQLPORT')) : intval($port)
        );
    }


    public function getTestDb($id = null, $name = null, $user = null, $pass = null, $host = null, $port = null) : Db
    {
        return new Db(
            $id === null ? 'phpunittesttestdb' : $id,
            $name === null ? 'phpunittesttestdb' : $name,
            $user === null ? 'phpunittesttestuser' : $user,
            $pass === null ? 'phpunittesttestpass' : $pass,
            $host === null ? getenv('MYSQLHOST') : $host,
            $port === null ? intval(getenv('MYSQLPORT')) : intval($port)
        );
    }


    public static function cleanTestUserAndDb()
    {
        $db = self::getRootDb();

        $db->query("DROP USER IF EXISTS 'phpunittesttestuser'@'localhost'");
        $db->query("DROP USER IF EXISTS 'phpunittesttestuser'@'127.0.0.1'");
        $db->query("DROP USER IF EXISTS 'phpunittesttestuser'@'%'");

        $db->query('DROP DATABASE IF EXISTS `phpunittesttestdb`');
    }


    public static function createTestDb()
    {
        $db = self::getRootDb();
        $db->query("CREATE DATABASE IF NOT EXISTS `phpunittesttestdb`");
        $db->query("CREATE TABLE IF NOT EXISTS `phpunittesttestdb`.`phpunittesttesttable`
                      (
                        `key` INT NOT NULL AUTO_INCREMENT ,
                        `value` VARCHAR(1024) NOT NULL ,
                        PRIMARY KEY (`key`),
                        INDEX (`value`)
                      )
                    ENGINE = InnoDB;");

        $db->query("CREATE USER IF NOT EXISTS 'phpunittesttestuser'@'localhost' IDENTIFIED BY 'phpunittesttestpass'");
        $db->query("GRANT ALL PRIVILEGES ON phpunittesttestdb.* TO 'phpunittesttestuser'@'localhost'");
        $db->query("CREATE USER IF NOT EXISTS 'phpunittesttestuser'@'%' IDENTIFIED BY 'phpunittesttestpass'");
        $db->query("GRANT ALL PRIVILEGES ON phpunittesttestdb.* TO 'phpunittesttestuser'@'%'");

        $insertRow = array(
            'value' => 'First value'
        );
        $sqlInsert = sprintf(
            "INSERT INTO `phpunittesttestdb`.`phpunittesttesttable` (%s) VALUES (%s)",
            DbHelpers::insertFields($insertRow),
            DbHelpers::insertPlaceholders($insertRow)
        );
        $db->query($sqlInsert, $insertRow);
    }
}
