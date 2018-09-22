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

final class DbHelpersTest extends TestCase
{
    public function testEscapedTableName() {
        $this->assertEquals('?', DbHelpers::insertPlaceholders(array('value' => 'test')));
        $this->assertEquals('?, ?', DbHelpers::insertPlaceholders(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`', DbHelpers::insertFields(array('value' => 'test')));
        $this->assertEquals('`key`, `value`', DbHelpers::insertFields(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`=?', DbHelpers::updateFieldsAndValues(array('value' => 'test')));
        $this->assertEquals('`key`=?, `value`=?', DbHelpers::updateFieldsAndValues(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`table`', DbHelpers::escapedTableName(array('table')));
        $this->assertEquals('`database`.`table`', DbHelpers::escapedTableName(array('database', 'table')));
        $this->assertEquals('`table`', DbHelpers::escapedTableName('table'));
        $this->assertEquals('`database`.`table`', DbHelpers::escapedTableName('database.table'));
    }

    public function testThrowOnInvalidTableName() {
        $this->expectExceptionMessage('Invalid table name');
        DbHelpers::escapedTableName(array('1', '2', '3'));
    }
}