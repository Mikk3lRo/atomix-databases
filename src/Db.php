<?php
namespace Mikk3lRo\atomix\databases;

use PDO;
use PDOException;
use PDOStatement;
use Exception;
use Mikk3lRo\atomix\databases\Dbs;
use Mikk3lRo\atomix\io\LogTrait;
use Mikk3lRo\atomix\io\Formatters;

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
     * Instantiate a new database - very lightweight as no connection is made.
     *
     * @param string  $slug     An ID / slug of the database - used to get it later.
     * @param string  $dbName   The name of the actual database.
     * @param string  $username The username.
     * @param string  $password The password.
     * @param string  $hostName The hostname of the database server.
     * @param integer $hostPort The port of the database server. Ignored for localhost.
     */
    public function __construct(string $slug, string $dbName, string $username, string $password, string $hostName = 'localhost', int $hostPort = 3306)
    {
        $this->slug = $slug;
        $this->dbName = $dbName;
        $this->username = $username;
        $this->password = $password;
        $this->hostName = $hostName;
        $this->hostPort = $hostPort;
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
     * Get all queries up until this point - only useful during debugging!
     *
     * @param boolean $flush   Also reset the query log.
     * @param boolean $disable Also disable logging.
     *
     * @return array[] Returns an array of arrays.
     *
     * @throws Exception If debugging is not enabled.
     */
    public function getQueryLog(bool $flush = true, bool $disable = true) : array
    {
        $ql = $this->queryLog;
        if ($flush) {
            $this->flushQueryLog();
        }
        if ($disable) {
            $this->disableQueryLog();
        }
        return $ql;
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
                $pdo = new PDO('mysql:host=' . $this->hostName . $port . ';dbname=' . $this->dbName, $this->username, $this->password, array(PDO::ATTR_PERSISTENT => false));
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo = $pdo;
            } catch (PDOException $e) {
                throw new Exception(Formatters::replaceTags('Failed to connect to database {name}: {message}', array(
                    'name' => $this->dbName,
                    'message' => $e->getMessage(),
                    'exception' => $e
                )));
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
     * Run a query against the database.
     *
     * @param string       $sql           The query.
     * @param string|array $args          Values for the placeholders in the query - if there is only one placeholder the value can be passed directly.
     * @param integer      $maxReconnects Maximum reconnect attempts if connection was lost.
     *
     * @return PDOStatement Returns the PDOStatement
     *
     * @throws PDOException If exceptions other than connection fails are thrown by PDO.
     * @throws Exception If connection fails.
     */
    public function query(string $sql, $args = array(), int $maxReconnects = 1) : PDOStatement
    {
        try {
            $this->connect();
        } catch (Exception $e) {
            $this->log()->error(Formatters::replaceTags('Auto-connect in query() failed on {slug}: {message}', array(
                'slug' => $this->dbName,
                'message' => $e->getMessage(),
                'exception' => $e
            )));
            throw $e;
        }

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

            $this->log()->debug(Formatters::replaceTags("SQL query on {slug}: {sql}", array(
                $this->slug,
                Dbs::getEmulatedSql($sql, $args)
            )));
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($args);
        } catch (PDOException $e) {
            //If connection was lost try to reconnect...
            $isDisconnect = in_array($e->errorInfo[1], array(2001, 2002, 2003, 2004, 2006));
            if ($isDisconnect && $maxReconnects > 0) {
                $this->log()->warning(Formatters::replaceTags('Connection lost on {slug} (will attempt to reconnect): {message}', array(
                    'slug' => $this->dbName,
                    'message' => $e->getMessage(),
                    'exception' => $e
                )));
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
            $args[] = '-P' . escapeshellarg($this->hostPort);
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
            throw new Exception(Formatters::replaceTags("Import of {file} failed, file does not exist or is empty!", array(
                'file' => $file
            )));
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
        `$cmd`;
        // @codeCoverageIgnoreStart
        if (!file_exists($file) || filesize($file) == 0) {
            throw new Exception(Formatters::replaceTags("Export to {file} failed, file does not exist or is empty!", array(
                'file' => $file
            )));
        }
        // @codeCoverageIgnoreEnd
    }
}
