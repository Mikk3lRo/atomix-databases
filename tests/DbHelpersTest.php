<?php declare(strict_types = 1);

namespace Mikk3lRo\atomix\Tests;

use Mikk3lRo\atomix\databases\DbHelpers;
use PHPUnit\Framework\TestCase;

/**
 * @covers Mikk3lRo\atomix\databases\DbHelpers
 *
 * TODO: Individual cover tags!
 */
final class DbHelpersTest extends TestCase
{
    public function testEmulatedSql()
    {
        $this->assertEquals(DbHelpers::getEmulatedSql('SELECT * FROM `test` WHERE ?', 'string'), "SELECT * FROM `test` WHERE 'string';");
        $this->assertEquals(DbHelpers::getEmulatedSql('SELECT * FROM `test` WHERE a=? AND b=?', array(1, 'string in array')), "SELECT * FROM `test` WHERE a=1 AND b='string in array';");
    }


    public function testQueryHelpers()
    {
        $this->assertEquals('?', DbHelpers::insertPlaceholders(array('value' => 'test')));
        $this->assertEquals('?, ?', DbHelpers::insertPlaceholders(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`', DbHelpers::insertFields(array('value' => 'test')));
        $this->assertEquals('`key`, `value`', DbHelpers::insertFields(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`=?', DbHelpers::updateFieldsAndValues(array('value' => 'test')));
        $this->assertEquals('`key`=?, `value`=?', DbHelpers::updateFieldsAndValues(array('key' => 1, 'value' => 'test')));

        $this->assertEquals('`value`=VALUES(`value`)', DbHelpers::onDuplicateUpdateFields(array('value' => 'test')));
        $this->assertEquals('`key`=VALUES(`key`), `value`=VALUES(`value`)', DbHelpers::onDuplicateUpdateFields(array('key' => 1, 'value' => 'test')));
        $this->assertEquals('`value`=VALUES(`value`)', DbHelpers::onDuplicateUpdateFields(array('key' => 1, 'value' => 'test'), 'key'));
    }
}
