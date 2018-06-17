<?php
namespace sensei\system;

use PDOException;
use sensei\system\process;
use sensei\system\db;
use sensei\settings\db_info;
use sensei\system\server;
use sensei\system\firewall;

class dbs {
    //Holds db objects
    static $_dbs = array();
    
    /**
     * Get a db object from a DB-id, info array or db object
     * 
     * @param string|db|array $db ID of a known database (fx. 'local_root'), info array (user, pass, name required) or db object
     * @return sensei\system\db Returns a db object
     */
    static function get($db) {
        if (is_a($db, 'sensei\system\db')) {
            //AOK, ready to return
            $retval = $db;
        } else if (is_string($db) && isset(self::$_dbs[$db])) {
            //Already instantiated
            $retval = self::$_dbs[$db];
        } else if (is_string($db) && isset(db_info::$$db)) {
            //One of our core DBs defined in config
            $db_info = db_info::$$db;
            $db_info['id'] = $db;
        } else {
            $db_info = $db;
        }
        
        //If we have an array at this point make sure it is complete and
        //instantiate a new db object
        if (isset($db_info) && is_array($db_info)) {
            foreach (array('id', 'user', 'pass', 'name') as $required_index) {
                if (!isset($db_info[$required_index]) || !is_string($db_info[$required_index])) {
                    trigger_error('DB ERROR: must supply db[' . $required_index . ']!', E_USER_ERROR);
                }
            }
            if (!isset($db_info['host']) || !is_string($db_info['host'])) {
                $db_info['host'] = 'localhost';
            }
            $retval = self::$_dbs[$db_info['id']] = new db($db_info['id'], $db_info['name'], $db_info['user'], $db_info['pass'], $db_info['host']);
        }
        
        if (!isset($retval) || !is_a($retval, 'sensei\system\db')) {
            trigger_error('Unrecognized attempt at establishing a DB-connection... what on earth are you passing in...?' . "\n" . var_export($db, true), E_USER_ERROR);
            return false;
        }
            
        return $retval;
    }
    
    /**
     * 
     * @param db $db
     * @param string $file
     * @param boolean $quiet
     */
    static function import_sql($db_conn, $file, $quiet = true) {
        $db = self::get($db_conn);
        
        $cmd = 'mysql -u' . escapeshellarg($db->user) . ' -p' . escapeshellarg($db->pass) . ' -h' . escapeshellarg($db->host) . ' ' . escapeshellarg($db->name) . ' < ' . escapeshellarg($file);
        if ($quiet) {
            `$cmd`;
        } else {
            process::passthru_return($cmd);
        }
    }
    
    
    static function get_allowed_hosts_for_user($username, $db_conn = 'local_root') {
        $db = self::get($db_conn);
        
        $stmt_find = $db->query("SELECT Host, User FROM mysql.user WHERE User=?", array(
            $username
        ));
        $allowed_hosts = array();
        
        if ($stmt_find->rowCount() > 0) {
            foreach ($stmt_find as $user_row) {
                $allowed_hosts[] = $user_row['Host'];
            }
        }
        return $allowed_hosts;
    }
    
    static function create_user($username, $password, $allowed_hosts = array('localhost', '127.0.0.1'), $db_conn = 'local_root') {
        if (!is_array($allowed_hosts) && !is_string($allowed_hosts)) {
            trigger_error('Invalid allowed_hosts supplied: ' . var_export($allowed_hosts, true), E_USER_ERROR);
        } else if (!is_array($allowed_hosts)) {
            $allowed_hosts = array($allowed_hosts);
        }
        
        $db = self::get($db_conn);
        
        foreach (self::get_allowed_hosts_for_user($username, $db) as $allowed_host) {
            $db->query("DROP USER ?@?", array(
                $username,
                $allowed_host
            ));
        }
        
        foreach ($allowed_hosts as $allowed_host) {
            $db->query("CREATE USER ?@? IDENTIFIED BY ?", array(
                $username,
                $allowed_host,
                $password
            ));
        }
    }
    
    static function set_password($username, $password, $db_conn = 'local_root') {
        $db = self::get($db_conn);
        
        foreach (self::get_allowed_hosts_for_user($username, $db) as $allowed_host) {
            $db->query("SET PASSWORD FOR ?@? = PASSWORD(?)", array(
                $username,
                $allowed_host,
                $password
            ));
        }
    }
    static function grant_access_to_dbs($username, $dbname_match, $privileges = 'ALL PRIVILEGES', $db_conn = 'local_root') {
        $db = self::get($db_conn);
        
        if (is_array($privileges)) {
            $privileges = implode(', ', $privileges);
        }
        
        foreach (self::get_allowed_hosts_for_user($username, $db) as $allowed_host) {
            $db->query("GRANT " . $privileges . " ON $dbname_match.* TO ?@?", array(
                $username,
                $allowed_host
            ));
        }
    }
    static function grant_superuser_access($username, $db_conn = 'local_root') {
        $db = self::get($db_conn);
        
        foreach (self::get_allowed_hosts_for_user($username, $db) as $allowed_host) {
            $db->query("GRANT ALL PRIVILEGES ON *.* TO ?@? WITH GRANT OPTION", array(
                $username,
                $allowed_host
            ));
        }
    }
    
    static function drop_database($name, $db_conn = 'local_root') {
        $db = self::get($db_conn);
        
        $db->query('DROP DATABASE IF EXISTS `' . $name . '`');
    }
    
    static function create_database($name, $db_conn = 'local_root') {
        $db = self::get($db_conn);
        
        $db->query('CREATE DATABASE IF NOT EXISTS `' . $name . '`');
    }
    
    public static function get_queries_debug() {
        $retval = array();
        foreach (self::$_dbs as $db_id => $db) {
            $queries = $db->get_queries_debug();
            $retval[] = count($queries) . ' queries on ' . $db_id;
            foreach ($queries as $query) {
                //$retval[] = '    ' . self::get_sql($query['sql'], $query['args']);
            }
            //$retval[] = count($queries) . ' queries on ' . $db_id;
        }
        return implode("\n", $retval);
    }
    
    public static function get_sql($sql, $args = array()) {
        if (count($args) > 0) {
            foreach ($args as $arg) {
                if (is_string($arg)) {
                    $arg = "'" . str_replace("'", "\\'", $arg) . "'";
                }
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $sql = substr_replace($sql, $arg, $pos, 1);
                }
            }
        }
        return rtrim($sql, ';') . ';';
    }
}