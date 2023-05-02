<?php

namespace InfinityBrackets\Exceptional;

use InfinityBrackets\Exceptional\Databases\MySQL;
use InfinityBrackets\Core\Application;
use InfinityBrackets\Core\Pagination;

class Model extends MySQL {
    protected string $db;
    protected string $tbl;
    protected string $query;
    protected $results;

    protected $fields = [];

    public function __construct() {
        $this->db = $this->application;
        $this->tbl = $this->table;
        $this->Connect();
    }
    
    public function All($active = NULL) {
        $condition = "";
        $params = [];
        if(!is_null($active) && ($active == 1 || $active == 0)) {
            $condition = "WHERE `active` = :in_active";
            $params['in_active'] = $active;
        }
        $this->results = $this->Select("SELECT * FROM `" . $this->tbl . "` " . $condition, $params)->Get();
        return $this;
    }

    public function Exists($id) {
        if($this->CountTable($this->tbl, "WHERE `id` = :in_id", ['in_id' => $id])) {
            return TRUE;
        }
        return FALSE;
    }
    
    public function Find($id) {
        $this->results = $this->SelectOne("SELECT * FROM `" . $this->tbl . "` WHERE `id` = :in_id", ['in_id' => $id])->Get();
        return $this;
    }
    
    public function FindId($options = []) {
        if(!$options) {
            // no value
        }
        $column = array_keys($options)[0] ?? 'id';
        $columnPlaceHolder = "in_" . $column;
        return $this->SelectOne("SELECT `id` FROM `" . $this->tbl . "` WHERE `" . $column . "` = :" . $columnPlaceHolder, [$columnPlaceHolder => $options[$column]])->Get()['id'];
    }

    public function Replace($data) {
        foreach($data as $values) {
            $customModel = $values[2];
            $tempResults = $customModel->All()->Get();
            $i = 0;
            foreach($this->results as $result) {
                if(array_key_exists($values[0], $result)) {
                    // Get id
                    $id = $result[$values[0]];
                    $index = array_search($id, array_column($tempResults, 'id'));
                    $this->results[$i][$values[1]] = $tempResults[$index]['name'];
                    unset($this->results[$i][$values[0]]);
                }
                $i++;
            }
        }
        return $this;
    }
    
    public function Where($column, $value) {
        $this->results = $this->Select("SELECT * FROM `" . $this->tbl . "` WHERE `" . $column . "` = :in_" . $column, ['in_' . $column => $value])->Get();
        return $this;
    }
    
    /**
     * FindCustomColumn('column', 'value')
     */
    public function WhereFirst($column, $value) {
        $this->results = $this->SelectOne("SELECT * FROM `" . $this->tbl . "` WHERE `" . $column . "` = :in_" . $column, ['in_' . $column => $value])->Get();
        return $this;
    }
    
    /**
     * FindCustomColumn('column', 'value')
     */
    public function Except($column, $value) {
        $this->results = $this->Select("SELECT * FROM `" . $this->tbl . "` WHERE `" . $column . "` <> :in_" . $column, ['in_' . $column => $value])->Get();
        return $this;
    }
    
    public function HasInsert($options = []) {
        if(!$options) {
            // no value
        }
        $column = array_keys($options)[0] ?? 'id';
        $columnPlaceHolder = "in_" . $column;
        $count = $this->CountTable($this->tbl, "WHERE `" . $column . "` = :" . $columnPlaceHolder, [$columnPlaceHolder => $options[$column]]);
        if($count > 0) {
            $id = $this->SelectOne("SELECT `id` FROM `" . $this->tbl . "` WHERE `" . $column . "` = :" . $columnPlaceHolder, [$columnPlaceHolder => $options[$column]])->Get()['id'];
        } else {
            $id = $this->Create([$column => $options[$column]])->Get();
        }
        return $id;
    }

    public function CountAll() {
        return $this->CountTable($this->tbl);
    }

    public function CountWhere($column, $value = NULL, $custom = FALSE) {
        if($custom) {
            return $this->CountTable($this->tbl, $column, $value);
        } else {
            if(is_string($column)) {
                return $this->CountTable($this->tbl, "WHERE `" . $column . "` = :in_" . $column, ['in_' . $column => $value]);
            }
            if(is_array($column)) {
                $condition = "WHERE ";
                $params = [];
                $i = 0;
                foreach($column as $key => $value) {
                    $condition .= "`" . $key . "` = :in_" . $key;
                    $params['in_' . $key] = $value;
                    if($i < count($column) - 1) {
                        $condition .= " AND ";
                    }
                    $i++;
                }
                return $this->CountTable($this->tbl, $condition, $params);
            }
        }
    }

    public function Create($data = [], $ignoreCreatedBy = FALSE) {
        if(!$data) {
            // no data found
        }

        $keys = array_keys($data);
        $parameters = [];
        foreach($data as $key => $value) {
            $parameters[':in_' . $key] = $value;
        }

        if(!isset($keys['created_by']) && !$ignoreCreatedBy) {
            array_push($keys, 'created_by');
            $parameters[':in_created_by'] = Application::$app->user->user->id;
        }
        
        $this->results = $this->InsertOne($this->tbl, $keys, $parameters);
        return $this;
    }

    public function Order($key = NULL, $direction = 'ASC') {
        if(!is_null($key)) {
            if($this->KeyExists($this->results, $key)) {
                switch($direction) {
                    default:
                    case 'ASC':
                        usort($this->results, fn($a, $b) => $a[$key] <=> $b[$key]);
                        break;
                    case 'DESC':
                        usort($this->results, fn($a, $b) => $b[$key] <=> $a[$key]);
                        break;
                }
            }
        }
        return $this;
    }

    public function Limit($limit = NULL, $offset = NULL, $data = NULL) {
        if(is_null($data)) {
            $this->results = $this->Select("SELECT * FROM `" . $this->tbl . "` LIMIT " . $limit . (!is_null($offset) ? ' OFFSET ' . $offset : ''))->Get();
            return $this;
        }
        $temp = [];
        for($i = $offset ?? 0; $i < (count($data) <= $limit ? count($data) : ($offset ?? 0) + $limit); $i++) {
            if($i > count($data) - 1) {
                break;
            }
            $temp[] = $data[$i];
        }
        $this->results = $temp;
        return $this;
    }

    //TODO Move this function to relative class later
    private function KeyExists(array $array, $key) {
        // is in base array?
        if (array_key_exists($key, $array)) {
            return true;
        }
        // check arrays contained in this array
        foreach ($array as $element) {
            if (is_array($element)) {
                if ($this->KeyExists($element, $key)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function Patch($data = [], $identifier = []) {
        if(!$data) {
            // no data found
        }
        $fields = [];
        $parameters = [];
        foreach($data as $key => $value) {
            $fields[$key] = ':in_' . $key;
            $parameters['in_' . $key] = $value;
        }
        $parameters['in_' . array_keys($identifier)[0]] = $identifier[array_keys($identifier)[0]];
        
        $this->results = $this->Update($this->tbl, $fields, "WHERE " . array_keys($identifier)[0] . " = :in_" . array_keys($identifier)[0], $parameters);
        return $this;
    }

    public function BatchPatch($data = []) {
        if(!$data) {
            // no data found
        }
        $fields = [];
        $parameters = [];
        $condition = "";
        foreach($data as $batches => $batch) {
            if($batch['columns'] && $batch['identifiers']) {
                $fields = [];
                $parameters = [];
                foreach($batch['columns'] as $key => $value) {
                    $fields[$key] = ':in_' . $key;
                    $parameters['in_' . $key] = $value;
                }
                if(count($batch['identifiers']) > 1) {
                    $condition = "WHERE ";
                    $i = 0;
                    foreach($batch['identifiers'] as $key => $value) {
                        $condition .= $key . " = :in_" . $key;
                        $parameters['in_' . $key] = $value;
                        $i++;
                        if(count($batch['identifiers']) > $i) {
                            $condition .= " AND ";
                        }
                    }
                } else {
                    $condition = "WHERE " . array_keys($batch['identifiers'])[0] . " = :in_" . array_keys($batch['identifiers'])[0];
                    $parameters['in_' . array_keys($batch['identifiers'])[0]] = $batch['identifiers'][array_keys($batch['identifiers'])[0]];
                }
                $this->Update($this->tbl, $fields, $condition, $parameters);
            }
        }
    }
    
    public function Disable($id = 0) {
        if($id == 0) {
            return FALSE;
        }

        $parameters = [];
        $parameters['in_active'] = 0;
        $parameters['in_id'] = $id;
        
        $this->results = $this->Update($this->tbl, ['active' => ':in_active'], "WHERE `id` = :in_id", $parameters);
        return $this;
    }

    public function ToObject() {
        return json_decode(json_encode($this->results));
    }

    /**
     * PAGINATION 
     */
    public function Paginate($total, $limit, $options = []) {
        $pagination = new Pagination();
        return $pagination->Paginate($total, $limit, $options);
    }
}