<?php
/**
 * USERREPOSITORY.PHP - Repository de Usuários
 * 
 * Centraliza toda lógica de acesso a dados de usuários
 * Substitui métodos espalhados em database.php e auth.php
 */

namespace App\Repositories;

use App\Models\User;

class UserRepository extends Repository {
    protected $model = User::class;
    
    /**
     * Busca usuário por email
     * 
     * @param string $email
     * @return User|null
     */
    public function findByEmail($email) {
        return User::findByEmail($email);
    }
    
    /**
     * Verifica credenciais de login
     * 
     * @param string $email
     * @param string $password
     * @return User|null
     */
    public function verifyCredentials($email, $password) {
        $user = $this->findByEmail($email);
        
        if (!$user) {
            return null;
        }
        
        if (!$user->verifyPassword($password)) {
            return null;
        }
        
        return $user;
    }
    
    /**
     * Cria novo usuário
     * 
     * @param string $email
     * @param string $password
     * @param string $name
     * @param bool $isAdmin
     * @param bool $active
     * @return User
     */
    public function createUser($email, $password, $name, $isAdmin = false, $active = false) {
        $user = new User();
        $user->email = $email;
        $user->setPassword($password);
        $user->name = $name;
        $user->is_admin = $isAdmin;
        $user->active = $active;
        
        $user->save();
        
        return $user;
    }
    
    /**
     * Obtém todos os usuários com ordenação
     * 
     * @param string $orderBy
     * @param string $order
     * @return array
     */
    public function getAllUsers($orderBy = 'created_at', $order = 'DESC') {
        return User::getAll($orderBy, $order);
    }
    
    /**
     * Obtém usuários pendentes de ativação
     * 
     * @return array
     */
    public function getPending() {
        return User::getPending();
    }
    
    /**
     * Conta usuários pendentes
     * 
     * @return int
     */
    public function countPending() {
        return User::countPending();
    }
    
    /**
     * Ativa usuário
     * 
     * @param int $userId
     * @param int $activatedBy
     * @return bool
     */
    public function activate($userId, $activatedBy) {
        $user = $this->find($userId);
        
        if (!$user) {
            return false;
        }
        
        return $user->activate($activatedBy);
    }
    
    /**
     * Desativa usuário
     * 
     * @param int $userId
     * @return bool
     */
    public function deactivate($userId) {
        $user = $this->find($userId);
        
        if (!$user) {
            return false;
        }
        
        return $user->deactivate();
    }
    
    /**
     * Verifica se usuário está ativo
     * 
     * @param int $userId
     * @return bool
     */
    public function isActive($userId) {
        $user = $this->find($userId);
        
        if (!$user) {
            return false;
        }
        
        return $user->isActive();
    }
    
    /**
     * Alterna privilégio de admin
     * 
     * @param int $userId
     * @param bool $isAdmin
     * @return bool
     */
    public function toggleAdmin($userId, $isAdmin) {
        $user = $this->find($userId);
        
        if (!$user) {
            return false;
        }
        
        if ($isAdmin) {
            return $user->makeAdmin();
        } else {
            return $user->removeAdmin();
        }
    }
    
    /**
     * Atualiza último login
     * 
     * @param int $userId
     * @return bool
     */
    public function updateLastLogin($userId) {
        $user = $this->find($userId);
        
        if (!$user) {
            return false;
        }
        
        $user->updateLastLogin();
        return true;
    }
    
    /**
     * Obtém estatísticas do usuário
     * 
     * @param int $userId
     * @return array|null
     */
    public function getStatistics($userId) {
        $user = $this->find($userId);
        
        if (!$user) {
            return null;
        }
        
        return $user->getStatistics();
    }
    
    /**
     * Obtém histórico de progresso
     * 
     * @param int $userId
     * @param int $days
     * @return array
     */
    public function getProgressHistory($userId, $days = 30) {
        $user = $this->find($userId);
        
        if (!$user) {
            return [];
        }
        
        return $user->getProgressHistory($days);
    }
    
    /**
     * Obtém desempenho por tópico
     * 
     * @param int $userId
     * @param int|null $sessionId
     * @return array
     */
    public function getTopicPerformance($userId, $sessionId = null) {
        $sql = "SELECT 
                    key_concept,
                    COUNT(*) as total,
                    SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct,
                    ROUND(100.0 * SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) / COUNT(*), 2) as percentage
                FROM questions
                WHERE user_id = ?";
        
        $params = [$userId];
        
        if ($sessionId) {
            $sql .= " AND session_id = ?";
            $params[] = $sessionId;
        }
        
        $sql .= " GROUP BY key_concept ORDER BY percentage DESC";
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Obtém administradores
     * 
     * @return array
     */
    public function getAdmins() {
        return User::getAdmins();
    }
    
    /**
     * Valida dados de novo usuário
     * 
     * @param array $data
     * @return array Erros de validação
     */
    public function validate($data) {
        return User::validate($data);
    }
    
    /**
     * Busca usuários por critérios
     * 
     * @param array $criteria ['active' => true, 'is_admin' => false, ...]
     * @param string $orderBy
     * @param string $order
     * @return array
     */
    public function search($criteria = [], $orderBy = 'created_at', $order = 'DESC') {
        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        if (isset($criteria['active'])) {
            $sql .= " AND active = ?";
            $params[] = $criteria['active'] ? 1 : 0;
        }
        
        if (isset($criteria['is_admin'])) {
            $sql .= " AND is_admin = ?";
            $params[] = $criteria['is_admin'] ? 1 : 0;
        }
        
        if (isset($criteria['email'])) {
            $sql .= " AND email LIKE ?";
            $params[] = '%' . $criteria['email'] . '%';
        }
        
        if (isset($criteria['name'])) {
            $sql .= " AND name LIKE ?";
            $params[] = '%' . $criteria['name'] . '%';
        }
        
        $sql .= " ORDER BY $orderBy $order";
        
        $data = $this->fetchAll($sql, $params);
        
        // Converte para Models
        return array_map(function($row) {
            return User::hydrate($row);
        }, $data);
    }
    
    /**
     * Conta usuários por status
     * 
     * @return array ['total', 'active', 'inactive', 'admins']
     */
    public function countByStatus() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END) as admins
                FROM users";
        
        return $this->fetchOne($sql);
    }
}