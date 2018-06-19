<?php
namespace Mikk3lRo\atomix\io;

use Mikk3lRo\atomix\io\Db;
use Mikk3lRo\atomix\io\Formatters;

class Dbs {
    /**
     * Array of registered databases
     *
     * @var Db[]
     */
    static $dbs = array();


    /**
     * Register a new database for easy access using its slug.
     *
     * @param Db $db An instantiated Db - ie. register(new Db([...]));.
     *
     * @return Db The new db-object is returned.
     */
    public static function register(Db $db) : void
    {
        self::$dbs[$db->slug] = $db;
    }


    /**
     * Get a Db object from its slug.
     *
     * @param string $slug Slug of an already registered database
     *
     * @return Db Returns a Db object
     */
    static function get(string $slug) : Db
    {
        if (is_string($slug) && isset(self::$dbs[$slug])) {
            return self::$dbs[$slug];
        }

        throw new \Exception(Formatters::replaceTags('DB was not registered: {db}', array(
            'db' => $slug
        )));
    }

    /**
     * Get some very verbose database debug.
     *
     * Only works if DEBUG_SQL is defined and true!
     *
     * @return string All queries on all databases as a string.
     */
    public static function getQueryDebug() : string
    {
        $retval = array();
        foreach (self::$dbs as $dbSlug => $db) {
            $queries = $db->getQueryLog();
            $retval[] = count($queries) . ' queries on ' . $dbSlug;
            foreach ($queries as $query) {
                $retval[] = '    ' . self::getEmulatedSql($query['sql'], $query['args']);
            }
        }
        return implode("\n", $retval);
    }


    /**
     * Helper function to replace each placeholder in stored statements with the
     * correct value. Should NEVER be used to actually run anything.
     *
     * This is ONLY FOR DEBUGGING PURPOSES!!!
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
            }
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                $sql = substr_replace($sql, $arg, $pos, 1);
            }
        }
        return rtrim($sql, ';') . ';';
    }

    /**
     * Helper to check if debugging is enabled - to avoid wasting resources when
     * it is not.
     *
     * @return boolean
     */
    public static function isDebugging() : bool
    {
        return (defined('DEBUG_SQL') && DEBUG_SQL);
    }
}