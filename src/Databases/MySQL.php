<?php

namespace InfinityBrackets\Exceptional\Databases;

use PDO;
use InfinityBrackets\Core\Application;

class MySQL {
    public \PDO $pdo;
    protected $driver = NULL;
    protected $host = NULL;
    protected $database = NULL;
    protected $username = NULL;
    protected $password = NULL;

    public function Init($config = NULL) {
        $default = NULL;

        if(is_string($config)) {
            if($config == "ils") {
                switch(Application::$app->config->env->APP_ENV) {
                    default:
                    case "local":
                        $default = Application::$app->config->database->ils->local;
                        break;
                    case "sandbox":
                    case "live":
                        $default = Application::$app->config->database->ils->live;
                        break;
                }
            } else if($config == "cc") {
                switch(Application::$app->config->env->APP_ENV) {
                    default:
                    case "local":
                        $default = Application::$app->config->database->cc->local;
                        break;
                    case "sandbox":
                    case "live":
                        $default = Application::$app->config->database->cc->live;
                        break;
                }
            }
        }
        if(is_array($config)) {
            $default = $config;
        }
        if(is_null($default)) {
            $default = Application::$app->config->env;
        }

        $this->driver = $config['driver'] ?? $default->DB_DRIVER;
        $this->host = $config['host'] ?? $default->DB_HOST;
        $this->database = $config['database'] ?? $default->DB_DATABASE;
        $this->username = $config['username'] ?? $default->DB_USERNAME;
        $this->password = $config['password'] ?? $default->DB_PASSWORD;

        $this->Connect();
    }

    public function Connect() {
        try {
            $this->pdo = new \PDO($this->driver . ":host=" . $this->host . ";dbname=" . $this->db . ";", $this->username, $this->password);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());   
        }
    }

    public function Begin() {
        $this->pdo->beginTransaction();
    }

    public function Commit() {
        $this->pdo->commit();
    }

    public function Rollback() {
        $this->pdo->rollback();
    }

    public function Query($statement = "", $parameters = []) {
        try {
            $stmt = $this->ExecuteStatement($statement, $parameters);
            $this->results = $stmt->fetchAll();
            return $this;
        } catch(Exception $e) {
            throw new Exception($e->getMessage());   
        }
    }

    public function Select($table = "", $condition = "", $parameters = [], $fields = []) {
        try {
            $fieldString = "";
            if($fields) {
                for($i = 0; $i < count($fields); $i++) {
                    $fieldString .= '`' . $fields[$i] . '`';
                    if($i < count($fields) - 1) {
                        $fieldString .= ", ";
                    }
                }
            } else {
                $fieldString = "*";
            }
            $statement = "SELECT " . $fieldString . " FROM `" . $table . "` " . $condition;
            $stmt = $this->ExecuteStatement($statement, $parameters);
            $this->results = $stmt->fetchAll();
            return $this;
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function CountTable($table = "", $condition = "", $parameters = []) {
        try {
            $statement = "SELECT * FROM `" . $table . "` " . $condition;
            $stmt = $this->ExecuteStatement($statement, $parameters);
            $this->results = $stmt->fetchAll();
            return $this->Count();
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function Compare($tables = [], $columns = [], $condition = "", $parameters = []) {
        try {
            $statement1 = "SELECT `" . $columns[0] . "` FROM `" . $tables[0] . "` " . $condition;
            $statement2 = "SELECT `" . $columns[1] . "` FROM `" . $tables[1] . "` ORDER BY `" . $columns[1] . "` DESC LIMIT 1";

            $stmt1 = $this->ExecuteStatement($statement1, $parameters);
            $stmt2 = $this->ExecuteStatement($statement2, []);
            $results1 = $stmt1->fetch();
            $results2 = $stmt2->fetch();
            if($results1 && $results2) {
                if($results1[$columns[0]] == $results2[$columns[1]]) {
                    return TRUE;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function SelectOne($table = "", $condition = "", $parameters = [], $fields = []) {
        try {
            $fieldString = "";
            if($fields) {
                for($i = 0; $i < count($fields); $i++) {
                    $fieldString .= '`' . $fields[$i] . '`';
                    if($i < count($fields) - 1) {
                        $fieldString .= ", ";
                    }
                }
            } else {
                $fieldString = "*";
            }
            $statement = "SELECT " . $fieldString . " FROM `" . $table . "` " . $condition;
            $stmt = $this->ExecuteStatement($statement, $parameters);
            $this->results = $stmt->fetch();
            return $this;
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get average of a column
     *
     * @param any
     * @param any
     * @param array
     * @return int
     */
    public function Average($column, $condition = "", $parameters = []) {
        try {
            $statement = "SELECT IFNULL(AVG($column), 0) `average` FROM `" . $this->tbl . "` " . $condition;
            $stmt = $this->ExecuteStatement($statement, $parameters);
            return $stmt->fetch()['average'];
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get sum of a column
     *
     * @param any
     * @param any
     * @param array
     * @return int
     */
    public function Sum($column, $condition = "", $parameters = []) {
        try {
            $statement = "SELECT IFNULL(SUM($column), 0) `sum` FROM `" . $this->tbl . "` " . $condition;
            $stmt = $this->ExecuteStatement($statement, $parameters);
            return $stmt->fetch()['sum'];
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function FindNextSequence($table = "", $sequenceColumn = "", $parameters = []) {
        try {
            $columnKey = array_keys($parameters)[0];
            $statement = "SELECT `" . $sequenceColumn . "` FROM `" . $table . "` WHERE `" . $columnKey . "` = " . $parameters[$columnKey] . " ORDER BY `" . $sequenceColumn . "` DESC LIMIT 1";
            $stmt = $this->ExecuteStatement($statement, $parameters);
            $this->results = $stmt->fetch();
            if($this->results) {
                return $this->Get()[$sequenceColumn] + 1;
            } else {
                return 1;
            }
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    public function Insert($statement = "", $parameters = []) {
        try{
            $this->executeStatement($statement, $parameters);
            return $this->pdo->lastInsertId();
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }		
    }
    
    public function InsertOne($table = "", $fields = [], $parameters = []) {
        try{
            $statement = "INSERT INTO `" . $table . "` (";
            for($i = 0; $i < count($fields); $i++) {
                $statement .= "`" . $fields[$i] . "`";
                if($i < count($fields) - 1) {
                    $statement .= ", ";
                }
            }
            $statement .= ") VALUES (";
            for($i = 0; $i < count($parameters); $i++) {
                $statement .= array_keys($parameters)[$i];
                if($i < count($parameters) - 1) {
                    $statement .= ", ";
                }
            }
            $statement .= ")";
            $this->ExecuteStatement($statement, $parameters);
            return $this->pdo->lastInsertId();
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
        }		
    }
    
    public function Update($table = "", $fields = [], $condition = "", $parameters = []) {
        try {
            $statement = "UPDATE `" . $table . "` SET ";
            for($i = 0; $i < count($fields); $i++) {
                $statement .= "`" . array_keys($fields)[$i] . "` = " . $fields[array_keys($fields)[$i]];
                if($i < count($fields) - 1) {
                    $statement .= ", ";
                }
            }
            $statement .= " " . $condition;
            $this->ExecuteStatement($statement, $parameters);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());   
        }		
    }		
    
    public function Remove($table = "", $field = "", $parameters = []) {
        try{
            $statement = "DELETE FROM `" . $table . "` WHERE `" . $field . "` = :in_" . $field;
            $this->ExecuteStatement($statement, $parameters);
        }catch(Exception $e){
            throw new Exception($e->getMessage());   
        }		
    }
    
    public function ExecuteStatement($statement = "", $parameters = []) {
        try{
            $stmt = $this->pdo->prepare($statement);
            $stmt->execute($parameters);
            return $stmt;
        } catch(Exception $e){
            throw new Exception($e->getMessage());   
        }		
    }

    public function Take($index) {
        if(isset($this->results[$index])) {
            return $this->results[$index];
        } else {
            return false;
        }
    }

    public function Count() {
        return count($this->results);
    }

    public function Get() {
        return $this->results;
    }

    public function First() {
        return current($this->results);
    }

    public function Last() {
        return end($this->results);
    }

    // public function applyMigrations()
    // {
    //     $this->createMigrationsTable();
    //     $appliedMigrations = $this->getAppliedMigrations();

    //     $newMigrations = [];
    //     $files = scandir(Application::$ROOT_DIR . '/migrations');
    //     $toApplyMigrations = array_diff($files, $appliedMigrations);
    //     foreach ($toApplyMigrations as $migration) {
    //         if ($migration === '.' || $migration === '..') {
    //             continue;
    //         }

    //         require_once Application::$ROOT_DIR . '/migrations/' . $migration;
    //         $className = pathinfo($migration, PATHINFO_FILENAME);
    //         $instance = new $className();
    //         $this->log("Applying migration $migration");
    //         $instance->up();
    //         $this->log("Applied migration $migration");
    //         $newMigrations[] = $migration;
    //     }

    //     if (!empty($newMigrations)) {
    //         $this->saveMigrations($newMigrations);
    //     } else {
    //         $this->log("There are no migrations to apply");
    //     }
    // }

    // protected function createMigrationsTable()
    // {
    //     $this->pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    //         id INT AUTO_INCREMENT PRIMARY KEY,
    //         migration VARCHAR(255),
    //         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    //     )  ENGINE=INNODB;");
    // }

    // protected function getAppliedMigrations()
    // {
    //     $statement = $this->pdo->prepare("SELECT migration FROM migrations");
    //     $statement->execute();

    //     return $statement->fetchAll(\PDO::FETCH_COLUMN);
    // }

    // protected function saveMigrations(array $newMigrations)
    // {
    //     $str = implode(',', array_map(fn($m) => "('$m')", $newMigrations));
    //     $statement = $this->pdo->prepare("INSERT INTO migrations (migration) VALUES 
    //         $str
    //     ");
    //     $statement->execute();
    // }
}