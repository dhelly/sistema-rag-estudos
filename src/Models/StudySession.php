<?php
/**
 * STUDYSESSION.PHP - Model de Sessão de Estudo
 * 
 * Representa uma sessão de estudo do usuário
 */

namespace App\Models;

class StudySession extends Model {
    protected $table = 'study_sessions';
    
    protected $fillable = [
        'user_id',
        'discipline_id',
        'pdf_name',
        'pdf_content',
        'core_topics'
    ];
    
    protected $casts = [
        'user_id' => 'int',
        'discipline_id' => 'int',
        'core_topics' => 'json'
    ];
    
    protected $dates = [
        'created_at',
        'updated_at'
    ];
    
    // =====================================
    // RELACIONAMENTOS
    // =====================================
    
    /**
     * Obtém usuário da sessão
     * 
     * @return User|null
     */
    public function user() {
        return User::find($this->user_id);
    }
    
    /**
     * Obtém disciplina da sessão
     * 
     * @return Discipline|null
     */
    public function discipline() {
        if (!$this->discipline_id) {
            return null;
        }
        
        return Discipline::find($this->discipline_id);
    }
    
    /**
     * Obtém progresso da sessão
     * 
     * @return array|null
     */
    public function progress() {
        $sql = "SELECT * FROM user_progress WHERE session_id = ? LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        // Decodifica weak_points JSON
        if (isset($data['weak_points'])) {
            $data['weak_points'] = json_decode($data['weak_points'], true) ?? [];
        }
        
        return $data;
    }
    
    /**
     * Obtém questões da sessão
     * 
     * @param bool $onlyAnswered
     * @return array
     */
    public function questions($onlyAnswered = false) {
        $sql = "SELECT * FROM questions WHERE session_id = ?";
        
        if ($onlyAnswered) {
            $sql .= " AND user_answer IS NOT NULL";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Conta questões da sessão
     * 
     * @return array ['total' => int, 'answered' => int, 'correct' => int]
     */
    public function countQuestions() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(user_answer) as answered,
                    SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
                FROM questions 
                WHERE session_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return $stmt->fetch();
    }
    
    // =====================================
    // PROGRESSO
    // =====================================
    
    /**
     * Cria progresso inicial da sessão
     * 
     * @return bool
     */
    public function createProgress() {
        $sql = "INSERT INTO user_progress (session_id, user_id) VALUES (?, ?)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->id, $this->user_id]);
    }
    
    /**
     * Atualiza progresso da sessão
     * 
     * @param int $correct
     * @param int $total
     * @param int $difficulty
     * @param array $weakPoints
     * @return bool
     */
    public function updateProgress($correct, $total, $difficulty, $weakPoints = []) {
        $weakPointsJson = json_encode($weakPoints);
        
        $sql = "UPDATE user_progress 
                SET correct_answers = ?, 
                    total_answers = ?, 
                    difficulty_level = ?,
                    weak_points = ?
                WHERE session_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$correct, $total, $difficulty, $weakPointsJson, $this->id]);
    }
    
    /**
     * Adiciona tempo de estudo
     * 
     * @param int $seconds
     * @return bool
     */
    public function addStudyTime($seconds) {
        $sql = "UPDATE user_progress 
                SET study_time_seconds = study_time_seconds + ? 
                WHERE session_id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$seconds, $this->id]);
    }
    
    /**
     * Recalcula progresso baseado nas questões
     * 
     * @return bool
     */
    public function recalculateProgress() {
        $stats = $this->countQuestions();
        $progress = $this->progress();
        
        if (!$progress) {
            return false;
        }
        
        return $this->updateProgress(
            $stats['correct'],
            $stats['answered'],
            $progress['difficulty_level'],
            $progress['weak_points'] ?? []
        );
    }
    
    // =====================================
    // QUERIES ESPECIALIZADAS
    // =====================================
    
    /**
     * Busca sessões do usuário com progresso
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getUserSessionsWithProgress($userId, $limit = 20) {
        $instance = new static();
        $dbType = $instance->db->getType();
        
        if ($dbType === 'mysql') {
            $sql = "SELECT 
                        s.id,
                        s.pdf_name,
                        s.created_at,
                        s.updated_at,
                        COALESCE(up.difficulty_level, 1) as difficulty_level,
                        COALESCE(up.correct_answers, 0) as correct_answers,
                        COALESCE(up.total_answers, 0) as total_answers,
                        COALESCE(up.study_time_seconds, 0) as study_time_seconds
                    FROM study_sessions s
                    LEFT JOIN user_progress up ON s.id = up.session_id AND up.user_id = ?
                    WHERE s.user_id = ?
                    ORDER BY difficulty_level ASC, total_answers DESC, s.updated_at DESC
                    LIMIT ?";
        } else {
            $sql = "SELECT 
                        s.id,
                        s.pdf_name,
                        s.created_at,
                        s.updated_at,
                        COALESCE(up.difficulty_level, 1) as difficulty_level,
                        COALESCE(up.correct_answers, 0) as correct_answers,
                        COALESCE(up.total_answers, 0) as total_answers,
                        COALESCE(up.study_time_seconds, 0) as study_time_seconds
                    FROM study_sessions s
                    LEFT JOIN user_progress up ON s.id = up.session_id AND up.user_id = ?
                    WHERE s.user_id = ?
                    ORDER BY difficulty_level ASC, total_answers DESC, s.updated_at DESC
                    LIMIT ?";
        }
        
        $stmt = $instance->db->prepare($sql);
        $stmt->execute([$userId, $userId, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Busca tópico específico
     * 
     * @param int $topicId
     * @return array|null
     */
    public function getTopic($topicId) {
        $topics = $this->core_topics;
        
        if (!is_array($topics)) {
            return null;
        }
        
        foreach ($topics as $topic) {
            if (isset($topic['id']) && $topic['id'] == $topicId) {
                return $topic;
            }
        }
        
        return null;
    }
    
    /**
     * Obtém tópico aleatório (com prioridade para weak points)
     * 
     * @return array|null
     */
    public function getRandomTopic() {
        $topics = $this->core_topics;
        
        if (empty($topics)) {
            return null;
        }
        
        $progress = $this->progress();
        $weakPoints = $progress['weak_points'] ?? [];
        
        // 70% de chance de escolher um weak point
        if (!empty($weakPoints) && rand(0, 100) > 30) {
            $weakTopicId = $weakPoints[array_rand($weakPoints)];
            $topic = $this->getTopic($weakTopicId);
            
            if ($topic) {
                return $topic;
            }
        }
        
        // Senão, escolhe aleatório
        return $topics[array_rand($topics)];
    }
    
    // =====================================
    // VALIDAÇÃO
    // =====================================
    
    /**
     * Valida dados da sessão
     * 
     * @param array $data
     * @return array Erros
     */
    public static function validate($data) {
        $errors = [];
        
        if (empty($data['user_id'])) {
            $errors[] = "ID do usuário é obrigatório";
        }
        
        if (empty($data['pdf_name'])) {
            $errors[] = "Nome do material é obrigatório";
        }
        
        if (empty($data['pdf_content'])) {
            $errors[] = "Conteúdo é obrigatório";
        }
        
        if (empty($data['core_topics'])) {
            $errors[] = "Tópicos essenciais são obrigatórios";
        } elseif (!is_array($data['core_topics'])) {
            $errors[] = "Tópicos devem ser um array";
        }
        
        return $errors;
    }
}