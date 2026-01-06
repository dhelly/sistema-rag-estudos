<?php
/**
 * CONNECTION.PHP - Gerenciador de Conexão PDO
 * 
 * Substitui a lógica de conexão duplicada em database.php
 * Implementa Singleton pattern para reutilizar conexão
 */

namespace App\Database;

use PDO;
use PDOException;

class Connection {
    private static $instance = null;
    private $pdo = null;
    private $dbType = null;
    
    /**
     * Construtor privado (Singleton)
     */
    private function __construct() {
        $this->dbType = config('database.type');
        $this->connect();
    }
    
    /**
     * Obtém instância única da conexão
     * 
     * @return Connection
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Obtém objeto PDO
     * 
     * @return PDO
     */
    public function getPDO() {
        return $this->pdo;
    }
    
    /**
     * Obtém tipo do banco de dados
     * 
     * @return string
     */
    public function getType() {
        return $this->dbType;
    }
    
    /**
     * Estabelece conexão com o banco
     * 
     * @throws PDOException
     */
    private function connect() {
        try {
            if ($this->dbType === 'mysql') {
                $this->connectMySQL();
            } elseif ($this->dbType === 'sqlite') {
                $this->connectSQLite();
            } else {
                throw new PDOException("Tipo de banco de dados não suportado: {$this->dbType}");
            }
            
            // Configurações comuns do PDO
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            log_message("Conexão com banco {$this->dbType} estabelecida com sucesso", 'info', 'app');
            
        } catch (PDOException $e) {
            log_message("Erro ao conectar com banco: " . $e->getMessage(), 'error', 'errors');
            throw new PDOException("Erro ao conectar com o banco de dados: " . $e->getMessage());
        }
    }
    
    /**
     * Conexão MySQL
     * 
     * @throws PDOException
     */
    private function connectMySQL() {
        $host = config('database.mysql.host');
        $port = config('database.mysql.port');
        $dbname = config('database.mysql.database');
        $username = config('database.mysql.username');
        $password = config('database.mysql.password');
        $charset = config('database.mysql.charset');
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        ]);
    }
    
    /**
     * Conexão SQLite
     * 
     * @throws PDOException
     */
    private function connectSQLite() {
        $database = config('database.sqlite.database');
        
        // Cria diretório se não existir
        $dir = dirname($database);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $dsn = "sqlite:{$database}";
        $this->pdo = new PDO($dsn);
    }
    
    /**
     * Testa conexão
     * 
     * @return bool
     */
    public function testConnection() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            log_message("Teste de conexão falhou: " . $e->getMessage(), 'error', 'errors');
            return false;
        }
    }
    
    /**
     * Inicia transação
     * 
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit da transação
     * 
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback da transação
     * 
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Verifica se está em transação
     * 
     * @return bool
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Obtém último ID inserido
     * 
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Prepara statement
     * 
     * @param string $sql
     * @return \PDOStatement
     */
    public function prepare($sql) {
        if (config('logging.level') === 'debug') {
            log_message("Query: $sql", 'debug', 'queries');
        }
        
        return $this->pdo->prepare($sql);
    }
    
    /**
     * Executa query diretamente
     * 
     * @param string $sql
     * @return \PDOStatement|bool
     */
    public function query($sql) {
        if (config('logging.level') === 'debug') {
            log_message("Query: $sql", 'debug', 'queries');
        }
        
        return $this->pdo->query($sql);
    }
    
    /**
     * Executa statement
     * 
     * @param string $sql
     * @return int Número de linhas afetadas
     */
    public function exec($sql) {
        if (config('logging.level') === 'debug') {
            log_message("Exec: $sql", 'debug', 'queries');
        }
        
        return $this->pdo->exec($sql);
    }
    
    /**
     * Quote para prevenir SQL injection (quando prepared statements não são viáveis)
     * 
     * @param string $value
     * @param int $type
     * @return string
     */
    public function quote($value, $type = PDO::PARAM_STR) {
        return $this->pdo->quote($value, $type);
    }
    
    /**
     * Previne clonagem (Singleton)
     */
    private function __clone() {}
    
    /**
     * Previne unserialize (Singleton)
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}

/**
 * Helper global para obter conexão
 * 
 * @return Connection
 */
function db() {
    return Connection::getInstance();
}

/**
 * Helper global para obter PDO diretamente
 * 
 * @return PDO
 */
function pdo() {
    return Connection::getInstance()->getPDO();
}