<?php
/**
 * MODEL.PHP - Classe Base para Todos os Models
 * 
 * Implementa Active Record pattern básico
 * Todos os models herdam desta classe
 */

namespace App\Models;

use App\Database\Connection;
use PDO;

abstract class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    protected $dates = ['created_at', 'updated_at'];
    
    // Dados do model
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    
    public function __construct(array $attributes = []) {
        $this->db = Connection::getInstance();
        $this->fill($attributes);
    }
    
    /**
     * Preenche o model com dados
     * 
     * @param array $attributes
     * @return self
     */
    public function fill(array $attributes) {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        
        return $this;
    }
    
    /**
     * Verifica se atributo é fillable
     * 
     * @param string $key
     * @return bool
     */
    protected function isFillable($key) {
        // Se fillable está vazio, todos são fillable
        if (empty($this->fillable)) {
            return true;
        }
        
        return in_array($key, $this->fillable);
    }
    
    /**
     * Define atributo
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setAttribute($key, $value) {
        // Aplica cast se definido
        if (isset($this->casts[$key])) {
            $value = $this->castAttribute($key, $value);
        }
        
        $this->attributes[$key] = $value;
    }
    
    /**
     * Obtém atributo
     * 
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key) {
        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }
        
        return $this->attributes[$key];
    }
    
    /**
     * Aplica cast em atributo
     * 
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function castAttribute($key, $value) {
        if (is_null($value)) {
            return null;
        }
        
        $cast = $this->casts[$key];
        
        switch ($cast) {
            case 'int':
            case 'integer':
                return (int) $value;
                
            case 'float':
            case 'double':
                return (float) $value;
                
            case 'string':
                return (string) $value;
                
            case 'bool':
            case 'boolean':
                return (bool) $value;
                
            case 'array':
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
                
            case 'object':
                return is_string($value) ? json_decode($value) : $value;
                
            case 'datetime':
                return is_string($value) ? new \DateTime($value) : $value;
                
            default:
                return $value;
        }
    }
    
    /**
     * Magic getter
     */
    public function __get($key) {
        return $this->getAttribute($key);
    }
    
    /**
     * Magic setter
     */
    public function __set($key, $value) {
        $this->setAttribute($key, $value);
    }
    
    /**
     * Magic isset
     */
    public function __isset($key) {
        return isset($this->attributes[$key]);
    }
    
    /**
     * Converte para array
     * 
     * @return array
     */
    public function toArray() {
        $attributes = $this->attributes;
        
        // Remove atributos hidden
        foreach ($this->hidden as $hidden) {
            unset($attributes[$hidden]);
        }
        
        // Converte JSON para array
        foreach ($attributes as $key => $value) {
            if (isset($this->casts[$key]) && in_array($this->casts[$key], ['array', 'json', 'object'])) {
                if (is_string($value)) {
                    $attributes[$key] = json_decode($value, true);
                }
            }
        }
        
        return $attributes;
    }
    
    /**
     * Converte para JSON
     * 
     * @return string
     */
    public function toJson() {
        return json_encode($this->toArray());
    }
    
    /**
     * Salva o model (insert ou update)
     * 
     * @return bool
     */
    public function save() {
        if ($this->exists) {
            return $this->update();
        }
        
        return $this->insert();
    }
    
    /**
     * Insere novo registro
     * 
     * @return bool
     */
    protected function insert() {
        $data = $this->attributes;
        
        // Remove primary key se estiver vazio
        if (isset($data[$this->primaryKey]) && empty($data[$this->primaryKey])) {
            unset($data[$this->primaryKey]);
        }
        
        // Adiciona timestamps se não existirem
        if (in_array('created_at', $this->dates) && !isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', $this->dates) && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // Converte arrays/objects para JSON
        foreach ($data as $key => $value) {
            if (isset($this->casts[$key]) && in_array($this->casts[$key], ['array', 'json', 'object'])) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = json_encode($value);
                }
            }
        }
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute(array_values($data));
        
        if ($result) {
            $this->attributes[$this->primaryKey] = $this->db->lastInsertId();
            $this->exists = true;
            $this->original = $this->attributes;
        }
        
        return $result;
    }
    
    /**
     * Atualiza registro existente
     * 
     * @return bool
     */
    protected function update() {
        $data = $this->attributes;
        $id = $data[$this->primaryKey];
        
        // Remove primary key dos dados
        unset($data[$this->primaryKey]);
        
        // Atualiza timestamp
        if (in_array('updated_at', $this->dates)) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        // Converte arrays/objects para JSON
        foreach ($data as $key => $value) {
            if (isset($this->casts[$key]) && in_array($this->casts[$key], ['array', 'json', 'object'])) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = json_encode($value);
                }
            }
        }
        
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "$column = ?";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        
        if ($result) {
            $this->original = $this->attributes;
        }
        
        return $result;
    }
    
    /**
     * Deleta o model
     * 
     * @return bool
     */
    public function delete() {
        if (!$this->exists) {
            return false;
        }
        
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$this->attributes[$this->primaryKey]]);
        
        if ($result) {
            $this->exists = false;
        }
        
        return $result;
    }
    
    /**
     * Busca registro por ID
     * 
     * @param int $id
     * @return static|null
     */
    public static function find($id) {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = ? LIMIT 1";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([$id]);
        
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return static::hydrate($data);
    }
    
    /**
     * Busca ou lança exceção
     * 
     * @param int $id
     * @return static
     * @throws \Exception
     */
    public static function findOrFail($id) {
        $model = static::find($id);
        
        if (!$model) {
            throw new \Exception(static::class . " não encontrado: ID $id");
        }
        
        return $model;
    }
    
    /**
     * Busca todos os registros
     * 
     * @return array
     */
    public static function all() {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table}";
        
        $stmt = $instance->db->query($sql);
        $data = $stmt->fetchAll();
        
        return array_map(function($row) {
            return static::hydrate($row);
        }, $data);
    }
    
    /**
     * Busca com condição where
     * 
     * @param string $column
     * @param mixed $value
     * @return array
     */
    public static function where($column, $value) {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table} WHERE $column = ?";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([$value]);
        
        $data = $stmt->fetchAll();
        
        return array_map(function($row) {
            return static::hydrate($row);
        }, $data);
    }
    
    /**
     * Busca primeiro registro com condição
     * 
     * @param string $column
     * @param mixed $value
     * @return static|null
     */
    public static function firstWhere($column, $value) {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table} WHERE $column = ? LIMIT 1";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([$value]);
        
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return static::hydrate($data);
    }
    
    /**
     * Cria instância do model a partir de dados
     * 
     * @param array $data
     * @return static
     */
    protected static function hydrate(array $data) {
        $instance = new static();
        $instance->attributes = $data;
        $instance->original = $data;
        $instance->exists = true;
        
        return $instance;
    }
    
    /**
     * Cria e salva novo registro
     * 
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes) {
        $instance = new static($attributes);
        $instance->save();
        
        return $instance;
    }
    
    /**
     * Conta registros
     * 
     * @return int
     */
    public static function count() {
        $instance = new static();
        
        $sql = "SELECT COUNT(*) as count FROM {$instance->table}";
        
        $stmt = $instance->db->query($sql);
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
}