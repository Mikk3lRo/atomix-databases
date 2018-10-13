<?php
namespace Mikk3lRo\atomix\databases;

use Exception;
use Mikk3lRo\atomix\databases\Db;

/**
 * Use of this class is discouraged!
 *
 * It is extremely convenient in some cases, therefore it is kept...
 *
 * BUT the static nature of it goes against:
 *
 * - Global state (AVOID IT!)
 * - Dependency injection (USE IT!)
 *
 * ...so if possible avoid this completely.
 *
 * Inject instantiated Db classes everywhere they are needed instead!
 */
class Dbs
{
    /**
     * Array of registered databases
     *
     * @var Db[]
     */
    private static $dbs = array();

    /**
     * If true query logging will be enabled when databases are defined.
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
        if (static::$debug) {
            $db->enableQueryLog();
        }
        if (isset(static::$dbs[$db->slug])) {
            throw new Exception(
                sprintf(
                    'Already have a database defined for the slug "%s"',
                    $db->slug
                )
            );
        }
        static::$dbs[$db->slug] = $db;
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
        if (is_string($slug) && isset(static::$dbs[$slug])) {
            return static::$dbs[$slug];
        }

        throw new Exception(
            sprintf(
                'DB was not registered: %s',
                $slug
            )
        );
    }


    /**
     * Enable logging of queries on all (currently registered) databases.
     *
     * @return void
     */
    public static function enableQueryLog() : void
    {
        foreach (static::$dbs as $db) {
            $db->enableQueryLog();
        }
        static::$debug = true;
    }


    /**
     * Disable logging of queries on all (currently registered) databases.
     *
     * @return void
     */
    public static function disableQueryLog() : void
    {
        foreach (static::$dbs as $db) {
            $db->disableQueryLog();
        }
        static::$debug = false;
    }


    /**
     * Get some very verbose database debug.
     *
     * @return string All logged queries on all databases as a single string.
     */
    public static function getQueryDebug() : string
    {
        $retval = array();
        foreach (static::$dbs as $db) {
            $retval[] = $db->getQueryLogString();
        }
        return implode("\n", $retval);
    }
}
