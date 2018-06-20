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
     * Get all queries up until this point - only useful during debugging!
     *
     * @return array[]
     *
     * @throws Exception If debugging is not enabled.
     */
    public function getQueryLog() : array
    {
        if (!Dbs::isDebugging()) {
            throw new Exception('Tried to get query log, but debugging is not enabled!');
        }
        return $this->queryLog;
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
                $hostAndPort = $this->hostName . (($this->hostName == 'localhost') ? '' : ':' . $this->hostPort);
                $pdo = new PDO('mysql:host=' . $hostAndPort . ';dbname=' . $this->dbName, $this->username, $this->password, array(PDO::ATTR_PERSISTENT => false));
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
     * @return PDOstatement Returns the PDOstatement
     *
     * @throws PDOException If maximum reconnect attempts is reached - or if another exception is thrown from PDO.
     */
    public function query(string $sql, $args = array(), int $maxReconnects = 1) : PDOstatement
    {
        $this->connect();

        if (!is_array($args)) {
            $args = array($args);
        }

        if (Dbs::isDebugging()) {
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
                    'message' => $e->getMessage()
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
        $args[] = '-p' . escapeshellarg($this->password);
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
            throw new Exception(Formatters::replaceTags("Import of {file} failed, file does not exist or is empty!"));
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
        $cmd = 'mysqldump ' . $this->shellArgs() . ' > ' . escapeshellarg($file);
        `$cmd`;
        if (!file_exists($file) || filesize($file) == 0) {
            throw new Exception(Formatters::replaceTags("Export to {file} failed, file does not exist or is empty!"));
        }
    }


    /**
     * Get the hosts / IPs a user is allowed to connect from.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string $username The username.
     *
     * @return string[] An array of hostnames and / or IPs.
     */
    public function getAllowedHostsForUser(string $username) : array
    {
        $stmtFind = $this->query("SELECT Host, User FROM mysql.user WHERE User=?", $username);
        $allowedHosts = array();

        if ($stmtFind->rowCount() > 0) {
            foreach ($stmtFind as $userRow) {
                $allowedHosts[] = $userRow['Host'];
            }
        }
        return $allowedHosts;
    }


    /**
     * Create a new mysql user.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string   $username     The username.
     * @param string   $password     The password.
     * @param string[] $allowedHosts An array of hostnames and / or IPs allowed to connect.
     *
     * @return void
     */
    public function createUser(string $username, string $password, array $allowedHosts = array('localhost', '127.0.0.1')) : void
    {
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            $this->query("DROP USER ?@?", array(
                $username,
                $allowedHost
            ));
        }

        foreach ($allowedHosts as $allowedHost) {
            $this->query("CREATE USER ?@? IDENTIFIED BY ?", array(
                $username,
                $allowedHost,
                $password
            ));
        }
    }


    /**
     * Update the password of a mysql user.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string $username The username.
     * @param string $password The new password.
     *
     * @return void
     */
    public function setPassword(string $username, string $password) : void
    {
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            $this->query("SET PASSWORD FOR ?@? = PASSWORD(?)", array(
                $username,
                $allowedHost,
                $password
            ));
        }
    }


    /**
     * Grant a mysql user access to one or more databases using a pattern.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string          $username    The username.
     * @param string          $dbnameMatch The database name pattern to match - fx.: "username_%".
     * @param string|string[] $privileges  The privileges to grant in array or string form.
     *
     * @return void
     */
    public function grantAccessToDbs(string $username, string $dbnameMatch, $privileges = 'ALL PRIVILEGES') : void
    {
        if (is_array($privileges)) {
            $privileges = implode(', ', $privileges);
        }

        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            $this->query("GRANT " . $privileges . " ON $dbnameMatch.* TO ?@?", array(
                $username,
                $allowedHost
            ));
        }
    }


    /**
     * Grant a mysql user access to all databases - including access to create
     * and remove databases, users etc.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string $username The username.
     *
     * @return void
     */
    public function grantSuperuserAccess(string $username) : void
    {
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            $this->query("GRANT ALL PRIVILEGES ON *.* TO ?@? WITH GRANT OPTION", array(
                $username,
                $allowedHost
            ));
        }
    }


    /**
     * Remove a database.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string $name Name of the database.
     *
     * @return void
     */
    public function dropDatabase(string $name) : void
    {
        $this->query('DROP DATABASE IF EXISTS `' . $name . '`');
    }


    /**
     * Create a new database.
     *
     * ONLY WORKS IF THE CONNECTION HAS ADMIN PRIVILEGES!
     *
     * @param string $name Name of the database.
     *
     * @return void
     */
    public function createDatabase(string $name) : void
    {
        $this->query('CREATE DATABASE IF NOT EXISTS `' . $name . '`');
    }
}
