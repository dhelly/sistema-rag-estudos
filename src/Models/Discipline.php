<?php
/**
 * QUESTION.PHP - Model de Questão
 * 
 * Salvar como: src/Models/Question.php
 */

namespace App\Models;

class Question extends Model {
    protected $table = 'questions';
    
    protected $fillable = [
        'session_id',
        'user_id',
        'statement',
        'correct_answer',
        'topic_id',
        'explanation',
        'key_concept',
        'difficulty',
        'user_answer',
        'answered_at',
        'response_time_seconds'
    ];
    
    protected $casts = [
        'session_id' => 'int',
        'user_id' => 'int',
        'topic_id' => 'int',
        'correct_answer' => 'bool',
        'user_answer' => 'bool',
        'difficulty' => 'int',
        'response_time_seconds' => 'int'
    ];
    
    protected $dates = [
        'answered_at',
        'created_at'
    ];
    
    /**
     * Verifica se foi respondida corretamente
     * 
     * @return bool
     */
    public function isCorrect() {
        return $this->user_answer !== null && $this->user_answer === $this->correct_answer;
    }
    
    /**
     * Registra resposta do usuário
     * 
     * @param bool $answer
     * @param int $responseTime
     * @return bool
     */
    public function answer($answer, $responseTime = null) {
        $this->user_answer = $answer;
        $this->answered_at = date('Y-m-d H:i:s');
        $this->response_time_seconds = $responseTime;
        
        return $this->save();
    }
    
    /**
     * Obtém sessão da questão
     * 
     * @return StudySession|null
     */
    public function session() {
        return StudySession::find($this->session_id);
    }
    
    /**
     * Obtém usuário que respondeu
     * 
     * @return User|null
     */
    public function user() {
        return User::find($this->user_id);
    }
    
    /**
     * Obtém questionamentos (challenges)
     * 
     * @return array
     */
    public function challenges() {
        $sql = "SELECT c.*, u.name as user_name, u.email as user_email
                FROM question_challenges c
                JOIN users u ON c.user_id = u.id
                WHERE c.question_id = ?
                ORDER BY c.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        $data = $stmt->fetchAll();
        
        // Decodifica web_sources
        foreach ($data as &$challenge) {
            if (!empty($challenge['web_sources'])) {
                $challenge['web_sources'] = json_decode($challenge['web_sources'], true);
            }
        }
        
        return $data;
    }
    
    /**
     * Conta questionamentos
     * 
     * @return int
     */
    public function countChallenges() {
        $sql = "SELECT COUNT(*) as count FROM question_challenges WHERE question_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        $result = $stmt->fetch();
        return (int) $result['count'];
    }
    
    /**
     * Atualiza gabarito após questionamento aceito
     * 
     * @param bool $newAnswer
     * @param string $newExplanation
     * @return bool
     */
    public function updateAfterChallenge($newAnswer, $newExplanation) {
        $this->correct_answer = $newAnswer;
        $this->explanation = $newExplanation;
        
        return $this->save();
    }
}

// =====================================
// DISCIPLINE.PHP - Model de Disciplina
// 
// Salvar como: src/Models/Discipline.php
// =====================================

namespace App\Models;

class Discipline extends Model {
    protected $table = 'disciplines';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'is_active',
        'created_by'
    ];
    
    protected $casts = [
        'is_active' => 'bool',
        'created_by' => 'int'
    ];
    
    protected $dates = [
        'created_at',
        'updated_at'
    ];
    
    /**
     * Busca disciplina por slug
     * 
     * @param string $slug
     * @return Discipline|null
     */
    public static function findBySlug($slug) {
        return static::firstWhere('slug', $slug);
    }
    
    /**
     * Obtém apenas disciplinas ativas
     * 
     * @return array
     */
    public static function getActive() {
        return static::where('is_active', 1);
    }
    
    /**
     * Obtém prompt de um agente
     * 
     * @param string $agentType (analyzer, generator, challenger)
     * @return array|null
     */
    public function getAgentPrompt($agentType) {
        $sql = "SELECT * FROM agent_prompts 
                WHERE discipline_id = ? AND agent_type = ? AND is_active = ? 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id, $agentType, 1]);
        
        $prompt = $stmt->fetch();
        
        // Se não encontrar, busca da disciplina geral
        if (!$prompt) {
            $general = static::findBySlug('geral');
            if ($general && $general->id !== $this->id) {
                return $general->getAgentPrompt($agentType);
            }
        }
        
        return $prompt;
    }
    
    /**
     * Salva ou atualiza prompt de agente
     * 
     * @param string $agentType
     * @param string $promptContent
     * @param string $systemInstructions
     * @param string $examples
     * @param int $createdBy
     * @return bool
     */
    public function saveAgentPrompt($agentType, $promptContent, $systemInstructions = null, $examples = null, $createdBy = null) {
        // Verifica se já existe
        $existing = $this->getAgentPrompt($agentType);
        
        if ($existing) {
            // Atualiza
            $sql = "UPDATE agent_prompts 
                    SET prompt_content = ?, 
                        system_instructions = ?, 
                        examples = ?,
                        version = version + 1,
                        updated_at = ?
                    WHERE discipline_id = ? AND agent_type = ?";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $promptContent,
                $systemInstructions,
                $examples,
                date('Y-m-d H:i:s'),
                $this->id,
                $agentType
            ]);
        } else {
            // Cria novo
            $sql = "INSERT INTO agent_prompts 
                    (discipline_id, agent_type, prompt_content, system_instructions, examples, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $this->id,
                $agentType,
                $promptContent,
                $systemInstructions,
                $examples,
                $createdBy
            ]);
        }
    }
    
    /**
     * Obtém todos os prompts da disciplina
     * 
     * @return array
     */
    public function getAllPrompts() {
        $sql = "SELECT * FROM agent_prompts WHERE discipline_id = ? ORDER BY agent_type ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obtém usuário que criou a disciplina
     * 
     * @return User|null
     */
    public function creator() {
        if (!$this->created_by) {
            return null;
        }
        
        return User::find($this->created_by);
    }
    
    /**
     * Conta sessões que usam esta disciplina
     * 
     * @return int
     */
    public function countSessions() {
        $sql = "SELECT COUNT(*) as count FROM study_sessions WHERE discipline_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        
        $result = $stmt->fetch();
        return (int) $result['count'];
    }
    
    /**
     * Valida dados da disciplina
     * 
     * @param array $data
     * @return array Erros
     */
    public static function validate($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = "Nome é obrigatório";
        }
        
        if (empty($data['slug'])) {
            $errors[] = "Slug é obrigatório";
        } elseif (static::findBySlug($data['slug'])) {
            $errors[] = "Já existe uma disciplina com este slug";
        }
        
        return $errors;
    }
}