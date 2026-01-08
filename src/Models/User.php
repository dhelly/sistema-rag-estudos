<?php
/**
 * USER.PHP - Model de Usuário
 * 
 * Representa usuário do sistema
 * Substitui lógica espalhada em auth.php e database.php
 */

namespace App\Models;

class User extends Model {
    protected $table = 'users';
    
    protected $fillable = [
        'email',
        'password',
        'name',
        'is_admin',
        'active',
        'last_login',
        'activated_at',
        'activated_by'
    ];
    
    protected $hidden = [
        'password'
    ];
    
    protected $casts = [
        'is_admin' => 'bool',
        'active' => 'bool',
        'activated_by' => 'int'
    ];
    
    protected $dates = [
        'created_at',
        'last_login',
        'activated_at'
    ];
    
    // =====================================
    // AUTENTICAÇÃO
    // =====================================
    
    /**
     * Busca usuário por email
     * 
     * @param string $email
     * @return User|null
     */
    public static function findByEmail($email) {
        return static::firstWhere('email', $email);
    }
    
    /**
     * Verifica senha
     * 
     * @param string $password
     * @return bool
     */
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }
    
    /**
     * Define nova senha (com hash)
     * 
     * @param string $password
     */
    public function setPassword($password) {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }
    
    /**
     * Atualiza último login
     */
    public function updateLastLogin() {
        $this->last_login = date('Y-m-d H:i:s');
        $this->save();
    }
    
    // =====================================
    // ATIVAÇÃO
    // =====================================
    
    /**
     * Verifica se usuário está ativo
     * 
     * @return bool
     */
    public function isActive() {
        return $this->active === true;
    }
    
    /**
     * Verifica se é admin
     * 
     * @return bool
     */
    public function isAdmin() {
        return $this->is_admin === true;
    }
    
    /**
     * Ativa usuário
     * 
     * @param int $activatedBy ID do admin que ativou
     * @return bool
     */
    public function activate($activatedBy) {
        $this->active = true;
        $this->activated_at = date('Y-m-d H:i:s');
        $this->activated_by = $activatedBy;
        
        return $this->save();
    }
    
    /**
     * Desativa usuário
     * 
     * @return bool
     */
    public function deactivate() {
        $this->active = false;
        $this->activated_at = null;
        $this->activated_by = null;
        
        return $this->save();
    }
    
    /**
     * Torna usuário admin
     * 
     * @return bool
     */
    public function makeAdmin() {
        $this->is_admin = true;
        return $this->save();
    }
    
    /**
     * Remove privilégios de admin
     * 
     * @return bool
     */
    public function removeAdmin() {
        $this->is_admin = false;
        return $this->save();
    }
    
    // =====================================
    // QUERIES ESPECIALIZADAS
    // =====================================
    
    /**
     * Obtém usuários pendentes de ativação
     * 
     * @return array
     */
    public static function getPending() {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table} WHERE active = ? ORDER BY created_at DESC";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([0]);
        
        $data = $stmt->fetchAll();
        
        return array_map(function($row) {
            return static::hydrate($row);
        }, $data);
    }
    
    /**
     * Conta usuários pendentes
     * 
     * @return int
     */
    public static function countPending() {
        $instance = new static();
        
        $sql = "SELECT COUNT(*) as count FROM {$instance->table} WHERE active = ?";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([0]);
        
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }
    
    /**
     * Obtém todos os admins
     * 
     * @return array
     */
    public static function getAdmins() {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table} WHERE is_admin = ? AND active = ?";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([1, 1]);
        
        $data = $stmt->fetchAll();
        
        return array_map(function($row) {
            return static::hydrate($row);
        }, $data);
    }
    
    /**
     * Busca usuários com ordenação e filtros
     * 
     * @param string $orderBy
     * @param string $order
     * @param array $filters
     * @return array
     */
    public static function getAll($orderBy = 'created_at', $order = 'DESC', $filters = []) {
        $instance = new static();
        
        $sql = "SELECT * FROM {$instance->table}";
        
        $where = [];
        $params = [];
        
        if (!empty($filters['active'])) {
            $where[] = "active = ?";
            $params[] = $filters['active'];
        }
        
        if (!empty($filters['is_admin'])) {
            $where[] = "is_admin = ?";
            $params[] = $filters['is_admin'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY $orderBy $order";
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute($params);
        
        $data = $stmt->fetchAll();
        
        return array_map(function($row) {
            return static::hydrate($row);
        }, $data);
    }
    
    // =====================================
    // RELACIONAMENTOS
    // =====================================
    
    /**
     * Obtém sessões do usuário
     * 
     * @param int $limit
     * @return array
     */
    public function sessions($limit = null) {
        $sql = "SELECT * FROM study_sessions WHERE user_id = ? ORDER BY updated_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        $data = $stmt->fetchAll();
        
        return array_map(function($row) {
            return StudySession::hydrate($row);
        }, $data);
    }
    
    /**
     * Obtém estatísticas do usuário
     * 
     * @return array
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(DISTINCT s.id) as total_sessions,
                    COUNT(q.id) as total_questions,
                    SUM(CASE WHEN q.user_answer = q.correct_answer THEN 1 ELSE 0 END) as total_correct,
                    SUM(COALESCE(up.study_time_seconds, 0)) as total_study_time,
                    AVG(up.difficulty_level) as avg_difficulty
                FROM users u
                LEFT JOIN study_sessions s ON s.user_id = u.id
                LEFT JOIN questions q ON q.user_id = u.id
                LEFT JOIN user_progress up ON up.user_id = u.id
                WHERE u.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return $stmt->fetch();
    }
    
    /**
     * Obtém progresso nos últimos N dias
     * 
     * @param int $days
     * @return array
     */
    public function getProgressHistory($days = 30) {
        $dbType = $this->db->getType();
        
        if ($dbType === 'mysql') {
            $sql = "SELECT 
                        DATE(answered_at) as date,
                        COUNT(*) as questions,
                        SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
                    FROM questions
                    WHERE user_id = ? AND answered_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY DATE(answered_at)
                    ORDER BY date ASC";
        } else {
            $sql = "SELECT 
                        DATE(answered_at) as date,
                        COUNT(*) as questions,
                        SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
                    FROM questions
                    WHERE user_id = ? AND answered_at >= datetime('now', '-' || ? || ' days')
                    GROUP BY DATE(answered_at)
                    ORDER BY date ASC";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $days]);
        
        return $stmt->fetchAll();
    }
    
    // =====================================
    // VALIDAÇÃO
    // =====================================
    
    /**
     * Valida dados do usuário
     * 
     * @param array $data
     * @return array Erros de validação
     */
    public static function validate($data) {
        $errors = [];
        
        // Email
        if (empty($data['email'])) {
            $errors[] = "Email é obrigatório";
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email inválido";
        } elseif (static::findByEmail($data['email'])) {
            $errors[] = "Este email já está cadastrado";
        }
        
        // Nome
        if (empty($data['name'])) {
            $errors[] = "Nome é obrigatório";
        } elseif (strlen($data['name']) < config('auth.name_min_length', 3)) {
            $errors[] = "Nome deve ter no mínimo " . config('auth.name_min_length', 3) . " caracteres";
        }
        
        // Senha (apenas na criação)
        if (isset($data['password'])) {
            if (empty($data['password'])) {
                $errors[] = "Senha é obrigatória";
            } elseif (strlen($data['password']) < config('auth.password_min_length', 6)) {
                $errors[] = "Senha deve ter no mínimo " . config('auth.password_min_length', 6) . " caracteres";
            }
            
            // Confirmar senha
            if (isset($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
                $errors[] = "Senhas não coincidem";
            }
        }
        
        return $errors;
    }
}