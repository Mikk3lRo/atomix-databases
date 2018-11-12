<?php declare(strict_types = 1);

namespace Mikk3lRo\atomix\databases;

use Exception;
use Mikk3lRo\atomix\databases\Db;

class DbAdmin
{
    /**
     * The database - normally a root-user connection to `mysql` on localhost.
     *
     * @var Db
     */
    private $db = null;


    /**
     * Instantiate.
     *
     * @param Db $db The database - normally a root-user connection to `mysql` on localhost.
     */
    public function __construct(Db $db)
    {
        $this->db = $db;
    }


    /**
     * Create a new user.
     *
     * @param string   $username     The username.
     * @param string   $password     The password.
     * @param string[] $allowedHosts An array of hostnames and / or IPs allowed to connect.
     *
     * @return void
     */
    public function createUser(string $username, string $password, array $allowedHosts = array('localhost', '127.0.0.1')) : void
    {
        $this->dropUser($username);

        foreach ($allowedHosts as $allowedHost) {
            $this->db->query("CREATE USER ?@? IDENTIFIED BY ?", array(
                $username,
                $allowedHost,
                $password
            ));
        }
    }


    /**
     * Remove a user.
     *
     * @param string $username The username.
     *
     * @return void
     */
    public function dropUser(string $username) : void
    {
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            $this->db->query("DROP USER ?@?", array(
                $username,
                $allowedHost
            ));
        }
    }


    /**
     * Update the password of a user.
     *
     * @param string $username The username.
     * @param string $password The new password.
     *
     * @return void
     */
    public function setPassword(string $username, string $password) : void
    {
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            $this->db->query("SET PASSWORD FOR ?@? = PASSWORD(?)", array(
                $username,
                $allowedHost,
                $password
            ));
        }
    }


    /**
     * Get the hosts / IPs a user is allowed to connect from.
     *
     * @param string $username The username.
     *
     * @return string[] An array of hostnames and / or IPs.
     */
    public function getAllowedHostsForUser(string $username) : array
    {
        $allowedHosts = array();

        foreach ($this->db->query("SELECT Host, User FROM mysql.user WHERE User=?", $username) as $userRow) {
            $allowedHosts[] = $userRow['Host'];
        }

        return $allowedHosts;
    }


    /**
     * Add a hosts / IP a user is allowed to connect from.
     *
     * @param string $username       The username.
     * @param string $newAllowedHost The hostname or IP allowed to connect.
     *
     * @return void
     */
    public function addAllowedHostForUser(string $username, string $newAllowedHost) : void
    {
        $oldAllowedHosts = $this->getAllowedHostsForUser($username);

        if (in_array($newAllowedHost, $oldAllowedHosts)) {
            //Already allowed
            return;
        }

        $grants = $this->getGrantsForUser($username);
        $createSql = $this->getCreateStatementForUser($username);

        //Create the new user
        $this->db->query($createSql, array(
            $username,
            $newAllowedHost
        ));

        //Give identical privileges
        foreach ($grants as $grant) {
            $this->db->query($grant, array(
                $username,
                $newAllowedHost
            ));
        }
    }


    /**
     * Remove a host / IP a user has previously been allowed to connect from.
     *
     * @param string $username       The username.
     * @param string $disallowedHost The hostname or IP no longer allowed to connect.
     *
     * @return void
     */
    public function removeAllowedHostForUser(string $username, string $disallowedHost) : void
    {
        $oldAllowedHosts = $this->getAllowedHostsForUser($username);

        if (!in_array($disallowedHost, $oldAllowedHosts)) {
            //Already not allowed
            return;
        }

        //Create the new user
        $this->db->query("DROP USER ?@?", array(
            $username,
            $disallowedHost
        ));
    }


    /**
     * Get GRANTs for a user - fx. required to add new allowed host(s) with the same privileges.
     *
     * @param string $username The username.
     *
     * @return string[] An array of statements required to grant identical privileges.
     */
    public function getGrantsForUser(string $username) : array
    {
        //Just look at the first user - they should all be identical!
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            break;
        }

        $grants = array();
        foreach ($this->db->query("SHOW GRANTS FOR ?@?", array($username, $allowedHost)) as $grantRow) {
            $grants[] = reset($grantRow);
        }

        $retval = array();

        foreach ($grants as $grant) {
            if (preg_match("#^(GRANT .* TO )'$username'@'$allowedHost'(.*)$#", $grant, $matches)) {
                $retval[] = $matches[1] . '?@?' . $matches[2];
            }
        }

        return $retval;
    }


    /**
     * Get a CREATE USER statement for an existing user - required to add
     * allowed hosts with same password without knowing it.
     *
     * @param string $username The username.
     *
     * @return string An SQL statement with placeholders for username and host.
     *
     * @throws Exception If the statement cannot be determined fx. if the user does not exist.
     */
    public function getCreateStatementForUser(string $username) : string
    {
        //Just look at the first user - they should all be identical!
        foreach ($this->getAllowedHostsForUser($username) as $allowedHost) {
            break;
        }

        if (isset($allowedHost)) {
            foreach ($this->db->query("SHOW CREATE USER ?@?", array($username, $allowedHost)) as $createUserRow) {
                $oldCreateUserSql = reset($createUserRow);
                if (preg_match("#^(CREATE USER )'$username'@'$allowedHost'(.*)$#", $oldCreateUserSql, $matches)) {
                    $createUserSql = $matches[1] . '?@?' . $matches[2];
                }
            }
        }

        if (!isset($createUserSql)) {
            throw new Exception(
                sprintf(
                    'Could not get CREATE USER statement to add allowed host. Perhaps the user "%s" does not exist?!',
                    $username
                )
            );
        }

        return $createUserSql;
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
            $this->db->query("GRANT " . $privileges . " ON `$dbnameMatch`.* TO ?@?", array(
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
            $this->db->query("GRANT ALL PRIVILEGES ON *.* TO ?@? WITH GRANT OPTION", array(
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
        $this->db->query('DROP DATABASE IF EXISTS `' . $name . '`');
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
        $this->db->query('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
}
