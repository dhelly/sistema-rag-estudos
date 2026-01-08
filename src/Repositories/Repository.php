<?php
/**
 * REPOSITORY.PHP - Classe Base para Repositories
 * 
 * Repositories isolam a lógica de acesso a dados dos Models
 * Facilita testes, manutenção e reutilização de queries complexas
 */

namespace App\Repositories;

use App\Database\Connection;
use PDO;

abstract class Repository {
    protected $db;
    protected $model;
    
    public function __construct() {
        $this->db = Connection::getInstance();
    }
    
    /**
     * Busca registro por ID
     * 
     * @param int $id
     * @return mixed|null
     */
    public function find($id) {
        $modelClass = $this->model;
        return $modelClass::find($id);
    }
    
    /**
     * Busca todos os registros
     * 
     * @return array
     */
    public function all() {
        $modelClass = $this->model;
        return $modelClass::all();
    }
    
    /**
     * Cria novo registro
     * 
     * @param array $data
     * @return mixed
     */
    public function create(array $data) {
        $modelClass = $this->model;
        return $modelClass::create($data);
    }
    
    /**
     * Atualiza registro
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data) {
        $model = $this->find($id);
        
        if (!$model) {
            return false;
        }
        
        $model->fill($data);
        return $model->save();
    }
    
    /**
     * Deleta registro
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $model = $this->find($id);
        
        if (!$model) {
            return false;
        }
        
        return $model->delete();
    }
    
    /**
     * Conta registros
     * 
     * @return int
     */
    public function count() {
        $modelClass = $this->model;
        return $modelClass::count();
    }
    
    /**
     * Executa query customizada
     * 
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    protected function query($sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt;
    }
    
    /**
     * Busca único registro
     * 
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    protected function fetchOne($sql, array $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Busca múltiplos registros
     * 
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function fetchAll($sql, array $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Executa statement
     * 
     * @param string $sql
     * @param array $params
     * @return bool
     */
    protected function execute($sql, array $params = []) {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Obtém tipo do banco
     * 
     * @return string
     */
    protected function getDbType() {
        return $this->db->getType();
    }
    
    /**
     * Inicia transação
     */
    protected function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit
     */
    protected function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback
     */
    protected function rollback() {
        return $this->db->rollback();
    }
}