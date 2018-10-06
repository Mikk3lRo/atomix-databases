<?php
namespace Mikk3lRo\atomix\databases;

use Exception;
use Mikk3lRo\atomix\databases\Db;
use Mikk3lRo\atomix\io\Formatters;

class Dbs
{
    /**
     * Array of registered databases
     *
     * @var Db[]
     */
    private static $dbs = array();

    /**
     * If true querylogging will be enabled when databases are registered.
     *
     * @var boolean
     */
    private static $debug = false;


    /**
     * Register a new database for easy access using its slug.
     *
     * @param Db $db An instantiated Db - ie. register(new Db([...]));.
     *
     * @return void
     *
     * @throws Exception If there is already a database registered with the same slug.
     */
    public static function define(Db $db) : void
    {
        //Force query logging on when a new db is registered if debugging is
        //enabled... but don't force it off if not!
        if (self::$debug) {
            $db->enableQueryLog();
        }
        if (isset(self::$dbs[$db->slug])) {
            throw new Exception(sprintf('Already have a database registered for the slug "%s"', $db->slug));
        }
        self::$dbs[$db->slug] = $db;
    }


    /**
     * Get a Db object from its slug.
     *
     * @param string $slug Slug of an already registered database.
     *
     * @return Db Returns a Db object.
     *
     * @throws Exception If the slug is unknown.
     */
    public static function get(string $slug) : Db
    {
        if (is_string($slug) && isset(self::$dbs[$slug])) {
            return self::$dbs[$slug];
        }

        throw new Exception(Formatters::replaceTags('DB was not registered: {db}', array(
            'db' => $slug
        )));
    }


    /**
     * Enable logging of queries on all (currently registered) databases.
     *
     * @return void
     */
    public function enableQueryLog() : void
    {
        foreach (self::$dbs as $db) {
            $db->enableQueryLog();
        }
        self::$debug = true;
    }


    /**
     * Disable logging of queries on all (currently registered) databases.
     *
     * @return void
     */
    public function disableQueryLog() : void
    {
        foreach (self::$dbs as $db) {
            $db->disableQueryLog();
        }
        self::$debug = false;
    }


    /**
     * Get some very verbose database debug.
     *
     * @return string All logged queries on all databases as a single string.
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
     * correct value.
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
            }
            $pos = strpos($sql, '?');
            if ($pos !== false) {
                $sql = substr_replace($sql, $arg, $pos, 1);
            }
        }
        return rtrim($sql, ';') . ';';
    }
}
