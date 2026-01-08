<?php
/**
 * QUESTIONGENERATORSERVICE.PHP - Geração de Questões
 * 
 * Gerencia geração, resposta e lógica adaptativa de questões
 */

namespace App\Services;

use App\Repositories\SessionRepository;
use App\Repositories\QuestionRepository;

class QuestionGeneratorService {
    private $aiService;
    private $sessionRepo;
    private $questionRepo;
    
    public function __construct(AIService $aiService = null) {
        $this->aiService = $aiService ?? new AIService();
        $this->sessionRepo = new SessionRepository();
        $this->questionRepo = new QuestionRepository();
    }
    
    /**
     * Gera nova questão para uma sessão
     * 
     * @param int $sessionId
     * @param int $userId
     * @return array ['question_id' => int, 'question' => array]
     * @throws \Exception
     */
    public function generateQuestion($sessionId, $userId) {
        // Busca sessão
        $session = $this->sessionRepo->find($sessionId);
        if (!$session) {
            throw new \Exception("Sessão não encontrada");
        }
        
        // Busca progresso
        $progress = $this->sessionRepo->getProgress($sessionId);
        if (!$progress) {
            throw new \Exception("Progresso não encontrado");
        }
        
        // Seleciona tópico (prioriza weak points)
        $topic = $this->selectTopic($session, $progress);
        
        // Verifica se é weak point
        $isWeakPoint = in_array($topic['id'], $progress['weak_points'] ?? []);
        
        // Gera questão com IA
        $questionData = $this->aiService->generateQuestion(
            $session->pdf_content,
            $topic,
            $progress['difficulty_level'],
            $isWeakPoint
        );
        
        // Salva no banco
        $question = $this->questionRepo->saveQuestion(
            $sessionId,
            $userId,
            $questionData,
            $progress['difficulty_level']
        );
        
        return [
            'question_id' => $question->id,
            'question' => [
                'id' => $question->id,
                'statement' => $questionData['statement'],
                'difficulty' => $progress['difficulty_level'],
                'key_concept' => $questionData['keyConceptTested'],
                'topic' => $topic['title'],
                'is_weak_point' => $isWeakPoint
            ]
        ];
    }
    
    /**
     * Processa resposta do usuário
     * 
     * @param int $questionId
     * @param bool $userAnswer
     * @param int|null $responseTime
     * @return array Resultado e próximos passos
     * @throws \Exception
     */
    public function processAnswer($questionId, $userAnswer, $responseTime = null) {
        // Busca questão
        $question = $this->questionRepo->find($questionId);
        if (!$question) {
            throw new \Exception("Questão não encontrada");
        }
        
        // Registra resposta
        $this->questionRepo->answerQuestion($questionId, $userAnswer, $responseTime);
        
        // Verifica se está correta
        $isCorrect = ($userAnswer == $question->correct_answer);
        
        // Atualiza progresso
        $this->updateProgress($question->session_id, $question->topic_id, $isCorrect);
        
        // Busca progresso atualizado
        $progress = $this->sessionRepo->getProgress($question->session_id);
        
        return [
            'is_correct' => $isCorrect,
            'correct_answer' => $question->correct_answer,
            'explanation' => $question->explanation,
            'key_concept' => $question->key_concept,
            'progress' => [
                'correct_answers' => $progress['correct_answers'],
                'total_answers' => $progress['total_answers'],
                'difficulty_level' => $progress['difficulty_level'],
                'percentage' => $progress['total_answers'] > 0 
                    ? round(($progress['correct_answers'] / $progress['total_answers']) * 100, 1)
                    : 0
            ]
        ];
    }
    
    /**
     * Seleciona tópico para próxima questão
     * 
     * @param object $session
     * @param array $progress
     * @return array
     */
    private function selectTopic($session, $progress) {
        $topics = $session->core_topics;
        $weakPoints = $progress['weak_points'] ?? [];
        
        // 70% de chance de escolher weak point
        if (!empty($weakPoints) && rand(0, 100) > 30) {
            $weakTopicId = $weakPoints[array_rand($weakPoints)];
            
            foreach ($topics as $topic) {
                if ($topic['id'] == $weakTopicId) {
                    return $topic;
                }
            }
        }
        
        // Senão, escolhe aleatório
        return $topics[array_rand($topics)];
    }
    
    /**
     * Atualiza progresso após resposta
     * 
     * @param int $sessionId
     * @param int $topicId
     * @param bool $isCorrect
     */
    private function updateProgress($sessionId, $topicId, $isCorrect) {
        $progress = $this->sessionRepo->getProgress($sessionId);
        
        // Atualiza contadores
        $correct = $progress['correct_answers'] + ($isCorrect ? 1 : 0);
        $total = $progress['total_answers'] + 1;
        $difficulty = $progress['difficulty_level'];
        $weakPoints = $progress['weak_points'] ?? [];
        
        // Ajusta dificuldade
        if ($isCorrect) {
            // Remove de weak points se acertou
            $weakPoints = array_values(array_diff($weakPoints, [$topicId]));
            
            // Aumenta dificuldade se está indo bem
            $recentCorrect = $total >= 3 && ($correct / $total) >= 0.7;
            if ($recentCorrect && $difficulty < 5) {
                $difficulty++;
            }
        } else {
            // Adiciona a weak points se errou
            if (!in_array($topicId, $weakPoints)) {
                $weakPoints[] = $topicId;
            }
            
            // Diminui dificuldade se está errando muito
            if ($difficulty > 1) {
                $difficulty = max(1, $difficulty - 1);
            }
        }
        
        // Salva no banco
        $this->sessionRepo->updateProgress($sessionId, $correct, $total, $difficulty, $weakPoints);
    }
    
    /**
     * Obtém estatísticas de questões
     * 
     * @param int $sessionId
     * @return array
     */
    public function getQuestionStatistics($sessionId) {
        $stats = $this->questionRepo->getStatistics(user_id(), $sessionId);
        
        return [
            'total' => $stats['total'] ?? 0,
            'answered' => $stats['answered'] ?? 0,
            'correct' => $stats['correct'] ?? 0,
            'percentage' => $stats['answered'] > 0 
                ? round(($stats['correct'] / $stats['answered']) * 100, 1)
                : 0,
            'avg_difficulty' => round($stats['avg_difficulty'] ?? 1, 1),
            'avg_response_time' => round($stats['avg_response_time'] ?? 0, 1)
        ];
    }
    
    /**
     * Obtém histórico de questões
     * 
     * @param int $sessionId
     * @param int $limit
     * @return array
     */
    public function getQuestionHistory($sessionId, $limit = 20) {
        $questions = $this->questionRepo->getBySession($sessionId, true);
        
        return array_slice($questions, 0, $limit);
    }
    
    /**
     * Recalcula progresso após mudança de gabarito
     * 
     * @param int $questionId
     * @return array Usuários afetados
     */
    public function recalculateAfterGabaritoChange($questionId) {
        $affected = $this->questionRepo->getUsersAffectedByQuestion($questionId);
        
        foreach ($affected as $user) {
            $this->sessionRepo->recalculateUserProgress(
                $user['user_id'],
                $user['session_id']
            );
        }
        
        return $affected;
    }
    
    /**
     * Sugere próximo nível de dificuldade
     * 
     * @param int $sessionId
     * @return array
     */
    public function suggestNextDifficulty($sessionId) {
        $stats = $this->getQuestionStatistics($sessionId);
        $progress = $this->sessionRepo->getProgress($sessionId);
        
        $currentDifficulty = $progress['difficulty_level'];
        $percentage = $stats['percentage'];
        
        $suggestion = [
            'current' => $currentDifficulty,
            'suggested' => $currentDifficulty,
            'reason' => ''
        ];
        
        if ($percentage >= 80 && $currentDifficulty < 5) {
            $suggestion['suggested'] = $currentDifficulty + 1;
            $suggestion['reason'] = 'Excelente desempenho! Hora de aumentar o desafio.';
        } elseif ($percentage < 50 && $currentDifficulty > 1) {
            $suggestion['suggested'] = $currentDifficulty - 1;
            $suggestion['reason'] = 'Vamos consolidar os fundamentos antes de avançar.';
        } else {
            $suggestion['reason'] = 'Continue no nível atual para aperfeiçoar.';
        }
        
        return $suggestion;
    }
    
    /**
     * Obtém weak points formatados
     * 
     * @param int $sessionId
     * @return array
     */
    public function getWeakPointsDetails($sessionId) {
        $session = $this->sessionRepo->find($sessionId);
        $progress = $this->sessionRepo->getProgress($sessionId);
        
        if (!$session || !$progress) {
            return [];
        }
        
        $weakPoints = $progress['weak_points'] ?? [];
        $details = [];
        
        foreach ($weakPoints as $topicId) {
            foreach ($session->core_topics as $topic) {
                if ($topic['id'] == $topicId) {
                    $details[] = [
                        'id' => $topic['id'],
                        'title' => $topic['title'],
                        'key_points' => $topic['keyPoints'],
                        'importance' => $topic['importance'] ?? 'Média'
                    ];
                    break;
                }
            }
        }
        
        return $details;
    }
}