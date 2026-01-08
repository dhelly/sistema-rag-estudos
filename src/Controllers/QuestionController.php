<?php
/**
 * QUESTIONCONTROLLER.PHP - Controlador de Questões
 * 
 * Salvar como: src/Controllers/QuestionController.php
 * Gerencia geração, resposta e questionamento de gabaritos
 */

namespace App\Controllers;

use App\Services\QuestionGeneratorService;
use App\Services\ChallengeService;
use App\Middleware\AuthMiddleware;

class QuestionController {
    private $questionService;
    private $challengeService;
    
    public function __construct() {
        AuthMiddleware::require();
        
        $this->questionService = new QuestionGeneratorService();
    }
    
    /**
     * Gera nova questão
     */
    public function generate() {
        try {
            $sessionId = $_SESSION['session_id'] ?? null;
            
            if (!$sessionId) {
                throw new \Exception("Nenhuma sessão ativa");
            }
            
            $result = $this->questionService->generateQuestion($sessionId, user_id());
            
            // Define como questão atual
            $_SESSION['current_question'] = $result['question_id'];
            
            // Limpa respostas anteriores
            unset($_SESSION['last_answer']);
            unset($_SESSION['challenge_result']);
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('index.php'));
    }
    
    /**
     * Processa resposta do usuário
     */
    public function answer() {
        try {
            $questionId = $_SESSION['current_question'] ?? null;
            
            if (!$questionId) {
                throw new \Exception("Nenhuma questão ativa");
            }
            
            $userAnswer = $_POST['answer'] === 'true';
            
            $result = $this->questionService->processAnswer($questionId, $userAnswer);
            
            // Salva resultado na sessão
            $_SESSION['last_answer'] = [
                'correct' => $result['is_correct'],
                'explanation' => $result['explanation'],
                'question_id' => $questionId,
                'correct_answer' => $result['correct_answer'],
                'key_concept' => $result['key_concept']
            ];
            
            // Remove questão atual
            unset($_SESSION['current_question']);
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('index.php'));
    }
    
    /**
     * Processa questionamento de gabarito
     */
    public function challenge() {
        try {
            if (!config('challenges.enabled')) {
                throw new \Exception("Sistema de questionamento está desabilitado");
            }
            
            $questionId = (int)($_POST['question_id'] ?? 0);
            $argument = trim($_POST['argument'] ?? '');
            
            if (empty($argument) || strlen($argument) < 20) {
                throw new \Exception("Argumentação deve ter pelo menos 20 caracteres");
            }
            
            // Inicializa challenge service
            $this->challengeService = new ChallengeService();
            
            $result = $this->challengeService->processChallenge(
                $questionId,
                user_id(),
                $argument
            );
            
            // Salva resultado na sessão
            $_SESSION['challenge_result'] = $result;
            
            if ($result['decision'] === 'accepted') {
                flash_success("✅ Questionamento ACEITO! Gabarito foi corrigido.");
            } else {
                flash_success("📋 Questionamento analisado.");
            }
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('index.php'));
    }
}
