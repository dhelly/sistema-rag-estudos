<?php
/**
 * QUESTIONREPOSITORY.PHP - Repository de Questões
 * 
 * Salvar como: src/Repositories/QuestionRepository.php
 */

namespace App\Repositories;

use App\Models\Question;

class QuestionRepository extends Repository {
    protected $model = Question::class;
    
    /**
     * Salva nova questão
     * 
     * @param int $sessionId
     * @param int $userId
     * @param array $questionData
     * @param int $difficulty
     * @return Question
     */
    public function saveQuestion($sessionId, $userId, $questionData, $difficulty) {
        $question = new Question();
        $question->session_id = $sessionId;
        $question->user_id = $userId;
        $question->statement = $questionData['statement'];
        $question->correct_answer = $questionData['correctAnswer'];
        $question->topic_id = $questionData['topicId'];
        $question->explanation = $questionData['explanation'];
        $question->key_concept = $questionData['keyConceptTested'];
        $question->difficulty = $difficulty;
        
        $question->save();
        
        return $question;
    }
    
    /**
     * Registra resposta do usuário
     * 
     * @param int $questionId
     * @param bool $userAnswer
     * @param int|null $responseTime
     * @return bool
     */
    public function answerQuestion($questionId, $userAnswer, $responseTime = null) {
        $question = $this->find($questionId);
        
        if (!$question) {
            return false;
        }
        
        return $question->answer($userAnswer, $responseTime);
    }
    
    /**
     * Obtém questões de uma sessão
     * 
     * @param int $sessionId
     * @param bool $onlyAnswered
     * @return array
     */
    public function getBySession($sessionId, $onlyAnswered = false) {
        $sql = "SELECT * FROM questions WHERE session_id = ?";
        
        if ($onlyAnswered) {
            $sql .= " AND user_answer IS NOT NULL";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $data = $this->fetchAll($sql, [$sessionId]);
        
        return array_map(function($row) {
            return Question::hydrate($row);
        }, $data);
    }
    
    /**
     * Obtém questões do usuário
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getByUser($userId, $limit = 50) {
        $sql = "SELECT * FROM questions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $data = $this->fetchAll($sql, [$userId, $limit]);
        
        return array_map(function($row) {
            return Question::hydrate($row);
        }, $data);
    }
    
    /**
     * Conta questões respondidas corretamente
     * 
     * @param int $userId
     * @param int|null $sessionId
     * @return int
     */
    public function countCorrect($userId, $sessionId = null) {
        $sql = "SELECT COUNT(*) as count 
                FROM questions 
                WHERE user_id = ? 
                AND user_answer = correct_answer 
                AND user_answer IS NOT NULL";
        
        $params = [$userId];
        
        if ($sessionId) {
            $sql .= " AND session_id = ?";
            $params[] = $sessionId;
        }
        
        $result = $this->fetchOne($sql, $params);
        return (int) $result['count'];
    }
    
    /**
     * Obtém estatísticas de questões
     * 
     * @param int $userId
     * @param int|null $sessionId
     * @return array
     */
    public function getStatistics($userId, $sessionId = null) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(user_answer) as answered,
                    SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct,
                    AVG(difficulty) as avg_difficulty,
                    AVG(response_time_seconds) as avg_response_time
                FROM questions 
                WHERE user_id = ?";
        
        $params = [$userId];
        
        if ($sessionId) {
            $sql .= " AND session_id = ?";
            $params[] = $sessionId;
        }
        
        return $this->fetchOne($sql, $params);
    }
    
    /**
     * Obtém usuários afetados por uma questão (para recalcular progresso)
     * 
     * @param int $questionId
     * @return array
     */
    public function getUsersAffectedByQuestion($questionId) {
        $sql = "SELECT DISTINCT user_id, session_id 
                FROM questions 
                WHERE id = ?";
        
        return $this->fetchAll($sql, [$questionId]);
    }
    
    /**
     * Atualiza questão após challenge aceito
     * 
     * @param int $questionId
     * @param bool $newAnswer
     * @param string $newExplanation
     * @return bool
     */
    public function updateAfterChallenge($questionId, $newAnswer, $newExplanation) {
        $question = $this->find($questionId);
        
        if (!$question) {
            return false;
        }
        
        return $question->updateAfterChallenge($newAnswer, $newExplanation);
    }
}
