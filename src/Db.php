<?php declare(strict_types = 1);

namespace Mikk3lRo\atomix\databases;

use PDO;
use PDOException;
use PDOStatement;
use Exception;
use Mikk3lRo\atomix\io\LogTrait;

class Db
{
    use LogTrait;

    /**
     * Whether or not to store queries.
     *
     * @var boolean
     */
    private $logQueries = false;

    /**
     * Keeps track of all executed queries - ONLY if DEBUG_SQL is defined and true!
     *
     * @var array[]
     */
    private $queryLog = array();

    /**
     * The pdo object.
     *
     * @var \PDO
     */
    private $pdo = null;

    /**
     * An ID or slug for the database.
     *
     * @var string
     */
    public $slug = null;

    /**
     * The name of the actual database.
     *
     * @var string
     */
    public $dbName = null;

    /**
     * Username to connect to the database.
     *
     * @var string
     */
    public $username = null;

    /**
     * Password to connect to the database.
     *
     * @var string
     */
    public $password = null;

    /**
     * Hostname to connect to the database server.
     *
     * @var string
     */
    public $hostName = 'localhost';

    /**
     * Port to connect to the database server.
     *
     * @var integer
     */
    public $hostPort = 3306;

    /**
     * Character set for the connection.
     *
     * @var string
     */
    public $charset = 'utf8';


    /**
     * Instantiate a new database - very lightweight as no connection is made.
     *
     * @param string  $slug     An ID / slug of the database - used to get it later.
     * @param string  $dbName   The name of the actual database.
     * @param string  $username The username.
     * @param string  $password The password.
     * @param string  $hostName The hostname of the database server.
     * @param integer $hostPort The port of the database server. Ignored for localhost.
     * @param string  $charset  The character set to use.
     */
    public function __construct(string $slug, string $dbName, string $username, string $password, string $hostName = 'localhost', int $hostPort = 3306, string $charset = 'utf8')
    {
        $this->slug = $slug;
        $this->dbName = $dbName;
        $this->username = $username;
        $this->password = $password;
        $this->hostName = $hostName;
        $this->hostPort = $hostPort;
        $this->charset = $charset;
    }


    /**
     * Find out if logging of queries is currently on.
     *
     * @return boolean
     */
    public function isQueryLogEnabled() : bool
    {
        return $this->logQueries;
    }


    /**
     * Enable logging of queries
     *
     * @return void
     */
    public function enableQueryLog() : void
    {
        $this->logQueries = true;
    }


    /**
     * Disable logging of queries.
     *
     * @return void
     */
    public function disableQueryLog() : void
    {
        $this->logQueries = false;
    }


    /**
     * Reset the query log.
     *
     * @return void
     */
    public function flushQueryLog() : void
    {
        $this->queryLog = array();
    }


    /**
     * Get all queries up until this point as an array.
     *
     * @param boolean $flush   Also reset the query log.
     * @param boolean $disable Also disable logging.
     *
     * @return array[] Returns an array of arrays.
     */
    public function getQueryLogArray(bool $flush = true, bool $disable = true) : array
    {
        $queries = $this->queryLog;

        if ($flush) {
            $this->flushQueryLog();
        }

        if ($disable) {
            $this->disableQueryLog();
        }

        return $queries;
    }


    /**
     * Get all queries up until this point as a string.
     *
     * @param boolean $flush   Also reset the query log.
     * @param boolean $disable Also disable logging.
     *
     * @return string Returns the queries (with parameters replaced).
     */
    public function getQueryLogString(bool $flush = true, bool $disable = true) : string
    {
        $retval = array();
        $queries = $this->getQueryLogArray($flush, $disable);
        $retval[] = sprintf('%d queries on "%s"', count($queries), $this->slug);
        foreach ($queries as $query) {
            $retval[] = '    ' . $this->getEmulatedSql($query['sql'], $query['args']);
        }
        return implode("\n", $retval);
    }


    /**
     * Make the actual connection.
     *
     * @return void
     *
     * @throws Exception If the connection fails.
     */
    public function connect() : void
    {
        if ($this->pdo === null) {
            try {
                $port = $this->hostName == 'localhost' ? '' : ';port=' . $this->hostPort;
                $pdo = new PDO(
                    'mysql:host=' . $this->hostName . $port . ';dbname=' . $this->dbName . ';charset=' . $this->charset,
                    $this->username,
                    $this->password,
                    array(
                        PDO::ATTR_PERSISTENT => false
                    )
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo = $pdo;
            } catch (PDOException $e) {
                //Log full details...
                $this->log()->critical(
                    sprintf(
                        'Failed to connect to database "%s": %s',
                        $this->dbName,
                        $e->getMessage()
                    ),
                    array(
                        'exception' => $e
                    )
                );
                //...but do not disclose too much information in the exception that might end up being displayed!
                throw new Exception(
                    sprintf(
                        'Failed to connect to database "%s": %s',
                        $this->dbName,
                        'see log for details'
                    )
                );
            }
        }
    }


    /**
     * Make a minimal effort to clean up :p
     */
    public function __destruct()
    {
        $this->close();
    }


    /**
     * Try to avoid leaks by removing the reference to the PDO object.
     *
     * @return void
     */
    public function close() : void
    {
        $this->pdo = null;
    }


    /**
     * Ensure that we have a connection and get the "raw" PDO object.
     *
     * @return PDO
     */
    public function getPdo() : PDO
    {
        $this->connect();
        return $this->pdo;
    }


    /**
     * Run a query against the database.
     *
     * @param string       $sql           The query.
     * @param string|array $args          Values for the placeholders in the query - if there is only one placeholder the value can be passed directly.
     * @param integer      $maxReconnects Maximum reconnect attempts if connection was lost.
     *
     * @return PDOStatement Returns the PDOStatement
     *
     * @throws PDOException If exceptions other than autohandled connection failures are thrown by PDO.
     */
    public function query(string $sql, $args = array(), int $maxReconnects = 1) : PDOStatement
    {
        $this->connect();

        if (!is_array($args)) {
            $args = array($args);
        } else {
            //Make sure we have a numerically indexed array - if we want named
            //parameters in the future that will have to be handled elsewhere...
            $args = array_values($args);
        }

        if ($this->logQueries) {
            $this->queryLog[] = array(
                'sql' => $sql,
                'args' => $args
            );

            $this->log()->debug(
                sprintf(
                    'SQL query on "%s": %s',
                    $this->slug,
                    $this->getEmulatedSql($sql, $args)
                )
            );
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($args);
        } catch (PDOException $e) {
            //If connection was lost try to reconnect...
            $isDisconnect = in_array(
                $e->errorInfo[1],
                array(
                    2001,
                    2002,
                    2003,
                    2004,
                    2006
                )
            );

            if ($isDisconnect && $maxReconnects > 0) {
                $this->log()->warning(
                    sprintf(
                        'Connection lost on "%s" (will attempt to reconnect): %s',
                        $this->dbName,
                        $e->getMessage()
                    ),
                    array(
                        'exception' => $e
                    )
                );
                $this->close();
                return $this->query($sql, $args, $maxReconnects - 1);
            }
            //Otherwise just pass the exception up
            throw $e;
        }

        return $stmt;
    }


    /**
     * Get all matching rows. Returns an empty array if no rows are returned.
     *
     * Careful with huge data sets - memory may run out when the whole result
     * set needs to be read into memory at once!
     *
     * Use query() directly if the query may result in large data sets!
     *
     * @param string       $sql           The query.
     * @param string|array $args          Values for the placeholders in the query - if there is only one placeholder the value can be passed directly.
     * @param integer      $maxReconnects Maximum reconnect attempts if connection was lost.
     *
     * @return array|null
     */
    public function queryAllRows(string $sql, $args = array(), int $maxReconnects = 1) : array
    {
        $stmt = $this->query($sql, $args, $maxReconnects);
        return $stmt->fetchAll();
    }


    /**
     * Get a single row. Returns null if no rows are returned.
     *
     * @param string       $sql           The query.
     * @param string|array $args          Values for the placeholders in the query - if there is only one placeholder the value can be passed directly.
     * @param integer      $maxReconnects Maximum reconnect attempts if connection was lost.
     *
     * @return array|null
     */
    public function queryOneRow(string $sql, $args = array(), int $maxReconnects = 1) : ?array
    {
        $retval = null;
        $stmt = $this->query($sql, $args, $maxReconnects);
        foreach ($stmt as $row) {
            $retval = $row;
        }
        $stmt->closeCursor();
        return $retval;
    }


    /**
     * Get a single cell. Returns null if no rows are returned.
     *
     * @param string       $sql           The query.
     * @param string|array $args          Values for the placeholders in the query - if there is only one placeholder the value can be passed directly.
     * @param integer      $maxReconnects Maximum reconnect attempts if connection was lost.
     *
     * @return null|string
     */
    public function queryOneCell(string $sql, $args = array(), int $maxReconnects = 1) : ?string
    {
        $retval = null;
        $stmt = $this->query($sql, $args, $maxReconnects);
        foreach ($stmt as $row) {
            $retval = reset($row);
        }
        $stmt->closeCursor();
        return $retval;
    }


    /**
     * Get the latest insert ID
     *
     * @return integer The last insert ID
     */
    public function getInsertId() : int
    {
        return intval($this->pdo->lastInsertId());
    }


    /**
     * Get the arguments required for mysql / mysqldump on the shell.
     *
     * @return string The arguments as a string.
     */
    private function shellArgs() : string
    {
        $args = array();
        $args[] = '-u' . escapeshellarg($this->username);
        if ($this->password !== '') {
            $args[] = '-p' . escapeshellarg($this->password);
        }
        $args[] = '-h' . escapeshellarg($this->hostName);
        if ($this->hostName !== 'localhost') {
            $args[] = '-P' . escapeshellarg((string)$this->hostPort);
        }
        $args[] = escapeshellarg($this->dbName);
        return implode(' ', $args);
    }


    /**
     * Import a database dump.
     *
     * @param string $file The dump filename.
     *
     * @return void
     *
     * @throws Exception If the file does not exist or is empty.
     */
    public function import(string $file) : void
    {
        if (!file_exists($file) || filesize($file) == 0) {
            throw new Exception(
                sprintf(
                    'Import of "%s" failed, file does not exist or is empty!',
                    $file
                )
            );
        }
        $cmd = 'mysql ' . $this->shellArgs() . ' < ' . escapeshellarg($file);
        `$cmd`;
    }


    /**
     * Export a database dump.
     *
     * @param string $file The dump filename.
     *
     * @return void
     *
     * @throws Exception If the file does not exist or is empty after exporting.
     */
    public function export(string $file) : void
    {
        $dumpArgs = '--routines --add-drop-table'; //TODO: optimal parameters here!!!
        $cmd = 'mysqldump ' . $this->shellArgs() . ' ' . $dumpArgs . '> ' . escapeshellarg($file);
        `$cmd 2>/dev/null`;
        if (!file_exists($file) || filesize($file) == 0) {
            throw new Exception(
                sprintf(
                    'Export to "%s" failed, file does not exist or is empty!',
                    $file
                )
            );
        }
    }


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
    public function insertPlaceholders(array $fieldsAndValues) : string
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
    public function insertFields(array $fieldsAndValues) : string
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
    public function updateFieldsAndValues(array $fieldsAndValues) : string
    {
        $escapedFieldsAndPlaceholders = array();
        foreach (array_keys($fieldsAndValues) as $field) {
            $escapedFieldsAndPlaceholders[] = '`' . $field . '`=?';
        }
        return implode(', ', $escapedFieldsAndPlaceholders);
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
    public function getEmulatedSql(string $sql, $args = array()) : string
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
