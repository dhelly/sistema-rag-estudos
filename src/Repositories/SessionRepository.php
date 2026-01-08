<?php
/**
 * SESSIONREPOSITORY.PHP - Repository de Sessões de Estudo
 * 
 * Gerencia sessões, progresso e queries relacionadas
 */

namespace App\Repositories;

use App\Models\StudySession;

class SessionRepository extends Repository {
    protected $model = StudySession::class;
    
    /**
     * Cria nova sessão de estudo
     * 
     * @param int $userId
     * @param string $pdfName
     * @param string $pdfContent
     * @param array $coreTopics
     * @param int|null $disciplineId
     * @return StudySession
     */
    public function createSession($userId, $pdfName, $pdfContent, $coreTopics, $disciplineId = null) {
        $session = new StudySession();
        $session->user_id = $userId;
        $session->discipline_id = $disciplineId;
        $session->pdf_name = $pdfName;
        $session->pdf_content = $pdfContent;
        $session->core_topics = $coreTopics;
        
        $session->save();
        
        // Cria progresso inicial
        $session->createProgress();
        
        return $session;
    }
    
    /**
     * Obtém sessões do usuário
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserSessions($userId, $limit = 10) {
        $sql = "SELECT id, pdf_name, created_at, updated_at 
                FROM study_sessions 
                WHERE user_id = ? 
                ORDER BY updated_at DESC 
                LIMIT ?";
        
        return $this->fetchAll($sql, [$userId, $limit]);
    }
    
    /**
     * Obtém sessões com progresso
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserSessionsWithProgress($userId, $limit = 20) {
        return StudySession::getUserSessionsWithProgress($userId, $limit);
    }
    
    /**
     * Obtém progresso de uma sessão
     * 
     * @param int $sessionId
     * @return array|null
     */
    public function getProgress($sessionId) {
        $session = $this->find($sessionId);
        
        if (!$session) {
            return null;
        }
        
        return $session->progress();
    }
    
    /**
     * Atualiza progresso
     * 
     * @param int $sessionId
     * @param int $correct
     * @param int $total
     * @param int $difficulty
     * @param array $weakPoints
     * @return bool
     */
    public function updateProgress($sessionId, $correct, $total, $difficulty, $weakPoints = []) {
        $session = $this->find($sessionId);
        
        if (!$session) {
            return false;
        }
        
        return $session->updateProgress($correct, $total, $difficulty, $weakPoints);
    }
    
    /**
     * Adiciona tempo de estudo
     * 
     * @param int $sessionId
     * @param int $seconds
     * @return bool
     */
    public function addStudyTime($sessionId, $seconds) {
        $session = $this->find($sessionId);
        
        if (!$session) {
            return false;
        }
        
        return $session->addStudyTime($seconds);
    }
    
    /**
     * Recalcula progresso de uma sessão
     * 
     * @param int $sessionId
     * @return bool
     */
    public function recalculateProgress($sessionId) {
        $session = $this->find($sessionId);
        
        if (!$session) {
            return false;
        }
        
        return $session->recalculateProgress();
    }
    
    /**
     * Recalcula progresso de usuário em uma sessão
     * 
     * @param int $userId
     * @param int $sessionId
     * @return bool
     */
    public function recalculateUserProgress($userId, $sessionId) {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
                FROM questions
                WHERE user_id = ? AND session_id = ? AND user_answer IS NOT NULL";
        
        $stats = $this->fetchOne($sql, [$userId, $sessionId]);
        
        if (!$stats) {
            return false;
        }
        
        $progress = $this->getProgress($sessionId);
        
        if (!$progress) {
            return false;
        }
        
        return $this->updateProgress(
            $sessionId,
            $stats['correct'],
            $stats['total'],
            $progress['difficulty_level'],
            $progress['weak_points'] ?? []
        );
    }
    
    /**
     * Obtém tópico específico de uma sessão
     * 
     * @param int $sessionId
     * @param int $topicId
     * @return array|null
     */
    public function getTopic($sessionId, $topicId) {
        $session = $this->find($sessionId);
        
        if (!$session) {
            return null;
        }
        
        return $session->getTopic($topicId);
    }
    
    /**
     * Obtém tópico aleatório
     * 
     * @param int $sessionId
     * @return array|null
     */
    public function getRandomTopic($sessionId) {
        $session = $this->find($sessionId);
        
        if (!$session) {
            return null;
        }
        
        return $session->getRandomTopic();
    }
    
    /**
     * Busca sessões por disciplina
     * 
     * @param int $disciplineId
     * @return array
     */
    public function getByDiscipline($disciplineId) {
        $sql = "SELECT * FROM study_sessions WHERE discipline_id = ? ORDER BY created_at DESC";
        
        $data = $this->fetchAll($sql, [$disciplineId]);
        
        return array_map(function($row) {
            return StudySession::hydrate($row);
        }, $data);
    }
    
    /**
     * Conta sessões por usuário
     * 
     * @param int $userId
     * @return int
     */
    public function countByUser($userId) {
        $sql = "SELECT COUNT(*) as count FROM study_sessions WHERE user_id = ?";
        
        $result = $this->fetchOne($sql, [$userId]);
        
        return (int) $result['count'];
    }
    
    /**
     * Obtém estatísticas agregadas de uma sessão
     * 
     * @param int $sessionId
     * @return array
     */
    public function getSessionStatistics($sessionId) {
        $sql = "SELECT 
                    s.pdf_name,
                    s.created_at,
                    up.correct_answers,
                    up.total_answers,
                    up.difficulty_level,
                    up.study_time_seconds,
                    CASE 
                        WHEN up.total_answers > 0 
                        THEN ROUND((up.correct_answers * 100.0) / up.total_answers, 2)
                        ELSE 0
                    END as percentage
                FROM study_sessions s
                LEFT JOIN user_progress up ON up.session_id = s.id
                WHERE s.id = ?";
        
        return $this->fetchOne($sql, [$sessionId]);
    }
    
    /**
     * Obtém sessões recentes do sistema
     * 
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10) {
        $sql = "SELECT s.*, u.name as user_name 
                FROM study_sessions s
                JOIN users u ON s.user_id = u.id
                ORDER BY s.created_at DESC
                LIMIT ?";
        
        return $this->fetchAll($sql, [$limit]);
    }
    
    /**
     * Busca sessões por nome/conteúdo
     * 
     * @param string $search
     * @param int|null $userId
     * @return array
     */
    public function search($search, $userId = null) {
        $sql = "SELECT * FROM study_sessions WHERE pdf_name LIKE ?";
        $params = ['%' . $search . '%'];
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY updated_at DESC";
        
        $data = $this->fetchAll($sql, $params);
        
        return array_map(function($row) {
            return StudySession::hydrate($row);
        }, $data);
    }
    
    /**
     * Deleta sessão e dados relacionados
     * 
     * @param int $sessionId
     * @return bool
     */
    public function deleteSession($sessionId) {
        // DELETE CASCADE vai remover automaticamente:
        // - user_progress
        // - questions
        // - question_challenges (via questions)
        
        return $this->delete($sessionId);
    }
    
    /**
     * Obtém sessões que precisam de atenção
     * (baixo desempenho ou nível de dificuldade baixo)
     * 
     * @param int $userId
     * @return array
     */
    public function getNeedingAttention($userId) {
        $sql = "SELECT s.*, up.difficulty_level, up.correct_answers, up.total_answers
                FROM study_sessions s
                JOIN user_progress up ON up.session_id = s.id
                WHERE s.user_id = ? 
                AND up.difficulty_level <= 2
                ORDER BY up.difficulty_level ASC, s.updated_at DESC";
        
        return $this->fetchAll($sql, [$userId]);
    }
}