<?php
namespace Mikk3lRo\atomix\databases;

use Exception;
use Mikk3lRo\atomix\io\Formatters;

class DbHelpers
{
    /**
     * Takes a string or array and returns a table name with backticks.
     *
     * @param string|string[] $table A string in the form "table" or "database.table", or an array with one or two elements.
     *
     * @return string The table name as a backticked string, ready for use in a statement.
     *
     * @throws Exception If the input is invalid.
     */
    public static function escapedTableName($table) : string
    {
        if (is_string($table)) {
            $table = explode('.', $table);
        }
        if (is_array($table)) {
            if (count($table) == 1) {
                return '`' . $table[0] . '`';
            } else if (count($table) == 2) {
                return '`' . $table[0] . '`.`' . $table[1] . '`';
            }
        }
        throw new Exception(Formatters::replaceTags('Invalid table name: "{name}"', array('name' => $table)));
    }


    /**
     * Creates a string with on placeholder per element in the input array
     * separated by commas.
     *
     * Fx. "?, ?, ?"
     *
     * @param array $fieldsAndValues The values that will be used.
     *
     * @return string The placeholder string.
     */
    public static function insertPlaceholders(array $fieldsAndValues) : string
    {
        $placeholders = array_fill(0, count($fieldsAndValues), '?');
        return implode(', ', $placeholders);
    }


    /**
     * Creates a string with each field in backticks separated by commas.
     *
     * Fx. "`field1`, `field2`, `field3`"
     *
     * @param array $fieldsAndValues The values that will be used.
     *
     * @return string The escaped field list.
     */
    public static function insertFields(array $fieldsAndValues) : string
    {
        $escapedFields = array();
        foreach (array_keys($fieldsAndValues) as $field) {
            $escapedFields[] = '`' . $field . '`';
        }
        return implode(', ', $escapedFields);
    }


    /**
     * Creates a string with fields and placeholders to be updated ready to use in an update statement.
     *
     * Fx. "`field1`=?, `field2`=?, `field3`=?"
     *
     * @param array $fieldsAndValues The values that will be used.
     *
     * @return string The escaped fields and placeholders.
     */
    public static function updateFieldsAndValues(array $fieldsAndValues) : string
    {
        $escapedFieldsAndPlaceholders = array();
        foreach (array_keys($fieldsAndValues) as $field) {
            $escapedFieldsAndPlaceholders[] = '`' . $field . '`=?';
        }
        return implode(', ', $escapedFieldsAndPlaceholders);
    }
}
