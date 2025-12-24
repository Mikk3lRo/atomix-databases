<?php declare(strict_types = 1);

namespace Mikk3lRo\atomix\databases;

class DbHelpers
{
    /**
     * Creates a string with one placeholder per element in the input array
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


    /**
     * Creates a string with fields and values ready to use in an "...ON DUPLICATE KEY" statement.
     *
     * Fx. "`field1`=VALUES(`field1`), `field2`=VALUES(`field2`)"
     *
     * @param array       $fieldsAndValues The values that will be used.
     * @param string|null $excludeField    Field to skip - so we don't have to pass a separate array without the primary key for example (optional).
     *
     * @return string The escaped keys.
     */
    public static function onDuplicateUpdateFields(array $fieldsAndValues, ?string $excludeField = null) : string
    {
        $updateFieldsArr = array();
        foreach (array_keys($fieldsAndValues) as $field) {
            if ($field !== $excludeField) {
                $updateFieldsArr[] = "`$field`=VALUES(`$field`)";
            }
        }
        return implode(', ', $updateFieldsArr);
    }


    /**
     * Replace each placeholder in stored statements with the correct value.
     *
     * This is ONLY FOR DEBUGGING PURPOSES!!!
     *
     * It should NEVER be used to actually run anything!!!
     *
     * @param string       $sql  The statement with placeholders.
     * @param string|array $args The values for the placeholders.
     *
     * @return string The statement with placeholders replaced by values.
     */
    public static function getEmulatedSql(string $sql, $args = array()) : string
    {
        if (!is_array($args)) {
            $args = array($args);
        }
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $arg = "'" . str_replace("'", "\\'", $arg) . "'";
            } else if ($arg === null) {
                $arg = 'NULL';
            } else if ($arg === false) {
                $arg = 'FALSE';
            } else if ($arg === true) {
                $arg = 'TRUE';
            } else if (is_int($arg) || is_float($arg)) {
                $arg = (string)$arg;
            }
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                $sql = substr_replace($sql, $arg, $pos, 1);
            }
        }
        return rtrim($sql, ';') . ';';
    }
}
