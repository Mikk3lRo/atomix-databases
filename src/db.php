<?php
namespace sensei\system;

use PDO;
use PDOException;
use sensei\io\log;

class db {
    private $_queries = array();
    private $_pdo = null;
    private $_die_on_failure = true;
    private $_expects_error = false;
    
    public $id = null;
    public $name = null;
    public $user = null;
    public $pass = null;
    public $host = 'localhost';
    
    public function get_queries_debug() {
        return $this->_queries;
    }
    
    public function die_on_failure() {
        $this->_die_on_failure = true;
    }
    public function survive_failure() {
        $this->_die_on_failure = false;
    }
    public function expect_error() {
        $this->_expects_error = true;
    }
    public function __construct($id, $name, $user, $pass, $host = 'localhost') {
        $this->id = $id;
        $this->name = $name;
        $this->user = $user;
        $this->pass = $pass;
        $this->host = $host;
    }
    public function connect($die_on_failure = true) {
        if ($this->_pdo === null) {
            try {
                $pdo = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->name, $this->user, $this->pass, array(PDO::ATTR_PERSISTENT => false));
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                if ($die_on_failure) {
                    trigger_error('DB ERROR-: ' . $e->getMessage(), E_USER_ERROR);
                    die();
                } else {
                    $pdo = null;
                    unset($pdo);
                    return false;
                }
            }

            $this->_pdo = $pdo;
        }
        
        return $this;
    }
    
    public function __destruct() {
        $this->close();
    }
    
    public function close() {
        $this->_pdo = null;
    }
    
    public function verify_connection($retry = 0) {
        $this->connect(false);
        try {
            $this->_pdo->query('SELECT RAND()');
        } catch (PDOException $e) {
            if ($retry <= 3) {
                $this->close();
                sleep($retry + 1);
                return $this->verify_connection($retry + 1);
            }
            trigger_error('UNRECOVERABLE DB ERROR: ' . $e->getMessage(), E_USER_ERROR);
        }
        return $this;
    }
    
    public function query($sql, $args = array(), $return = 'auto') {
        $this->connect();
        
        $this->_queries[] = array(
            'sql' => $sql,
            'args' => $args
        );
        //log::write(dbs::get_sql($sql, $args));
        try {
            $stmt = $this->_pdo->prepare($sql);
            $stmt->execute($args);
            $this->_expects_error = false;
        } catch(PDOException $e) {
            //If connection was lost try to reconnect... Max 3 times.
            if (in_array($e->errorInfo[1], array(2001, 2002, 2003, 2004, 2006))) {
                $reconnects = 3;
                $delay = 0;
                log::write('MySQL: ' . $e->getMessage() . " (but all is not lost, for now we'll just try to reconnect)");
                for ($reconnects = 5; $reconnects > 0; $reconnects--) {
                    $this->_pdo = null;
                    if ($this->connect(false) !== false) {
                        log::write('MySQL: Reconnected - this error should not have any consequences...');
                        break;
                    } else {
                        if ($reconnects === 1) {
                            trigger_error('Giving up, sorry :(', E_USER_ERROR);
                            die();
                        }
                        $delay += 5;
                        log::write('Reconnect failed, trying again in ' . $delay . ' seconds)');
                        sleep($delay);
                    }
                }
                return $this->query($sql, $args, $return, $reconnects);
            }
            
            if ($this->_expects_error) {
                $this->_expects_error = false;
                log::write('Expected error: ' . $e->errorInfo[1] . ': ' . $e->getMessage(), E_USER_NOTICE);
            } else {
                if ($this->_die_on_failure) {
                    trigger_error('DB ERROR: ' . $e->errorInfo[1] . ': ' . $e->getMessage(), E_USER_ERROR);
                } else {
                    trigger_error('DB ERROR: ' . $e->errorInfo[1] . ': ' . $e->getMessage(), E_USER_WARNING);
                }
            }
            return false;
        }
        
        if ($return === 'auto') {
            if (substr($sql, 0, 6) === 'INSERT' || substr($sql, 0, 7) === 'REPLACE') {
                $return = 'insert_id';
            } else if (in_array(substr($sql, 0, 6), array('UPDATE', 'DELETE'))) {
                $return = 'rowcount';
            } else {
                $return = 'stmt';
            }
        }
        
        switch ($return) {
            case 'insert_id':
                return intval($this->_pdo->lastInsertId());
            case 'rowcount':
                return intval($stmt->rowCount());
            case 'stmt':
            default:
                return $stmt;
        }
    }
}