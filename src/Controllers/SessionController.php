<?php
/**
 * SESSIONCONTROLLER.PHP - Controlador de Sessões de Estudo
 * 
 * Substitui index.php e sessions.php (800+ linhas combinadas)
 * Gerencia upload, análise, listagem e estudo
 */

namespace App\Controllers;

use App\Services\AIService;
use App\Services\AnalysisService;
use App\Services\QuestionGeneratorService;
use App\Repositories\SessionRepository;
use App\Middleware\AuthMiddleware;

class SessionController {
    private $aiService;
    private $analysisService;
    private $questionService;
    private $sessionRepo;
    
    public function __construct() {
        AuthMiddleware::require();
        
        $this->aiService = new AIService();
        $this->analysisService = new AnalysisService($this->aiService);
        $this->questionService = new QuestionGeneratorService($this->aiService);
        $this->sessionRepo = new SessionRepository();
    }
    
    /**
     * Tela inicial - upload ou estudo
     */
    public function index() {
        $userId = user_id();
        
        // Verifica se tem sessão ativa
        $sessionId = $_SESSION['session_id'] ?? null;
        
        if ($sessionId) {
            // Mostra área de estudo
            return $this->study($sessionId);
        }
        
        // Mostra área de upload
        $providers = AIService::getAvailableProviders();
        $currentProvider = $this->aiService->getProviderName();
        $userSessionsCount = $this->sessionRepo->countByUser($userId);
        
        view('sessions/upload', [
            'providers' => $providers,
            'current_provider' => $currentProvider,
            'user_sessions_count' => $userSessionsCount,
            'supports_pdf' => $this->aiService->supportsPDF()
        ]);
    }
    
    /**
     * Troca provider de IA
     */
    public function changeProvider() {
        $provider = $_POST['provider'] ?? '';
        
        try {
            $availableProviders = array_keys(AIService::getAvailableProviders());
            
            if (!in_array($provider, $availableProviders)) {
                throw new \Exception("Provider inválido");
            }
            
            $this->aiService->setProvider($provider);
            
            $providerInfo = $this->aiService->getProviderInfo();
            flash_success("Provider alterado para: " . $providerInfo['name']);
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('index.php'));
    }
    
    /**
     * Processa upload de PDF
     */
    public function uploadPDF() {
        try {
            if (!isset($_FILES['pdf'])) {
                throw new \Exception("Nenhum arquivo enviado");
            }
            
            $file = $_FILES['pdf'];
            $userId = user_id();
            $disciplineId = !empty($_POST['discipline_id']) ? (int)$_POST['discipline_id'] : null;
            
            // Processa PDF
            $result = $this->analysisService->processPDFUpload($file, $userId, $disciplineId);
            
            // Define sessão ativa
            $_SESSION['session_id'] = $result['session_id'];
            
            flash_success("PDF processado com sucesso: " . $result['session_name']);
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('index.php'));
    }
    
    /**
     * Processa upload de texto (resumo)
     */
    public function uploadText() {
        try {
            $summaryText = trim($_POST['summary_text'] ?? '');
            $materialName = trim($_POST['material_name'] ?? '');
            $userId = user_id();
            $disciplineId = !empty($_POST['discipline_id']) ? (int)$_POST['discipline_id'] : null;
            
            // Processa texto
            $result = $this->analysisService->processTextSummary(
                $summaryText,
                $materialName,
                $userId,
                $disciplineId
            );
            
            // Define sessão ativa
            $_SESSION['session_id'] = $result['session_id'];
            
            flash_success("Resumo processado com sucesso: " . $result['session_name']);
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('index.php'));
    }
    
    /**
     * Lista todas as sessões do usuário
     */
    public function list() {
        $userId = user_id();
        
        // Busca sessões com progresso
        $sessions = $this->sessionRepo->getUserSessionsWithProgress($userId, 100);
        
        // Agrupa por nível de dificuldade
        $grouped = [
            'critical' => [],
            'attention' => [],
            'good' => []
        ];
        
        foreach ($sessions as $session) {
            if ($session['difficulty_level'] <= 2) {
                $grouped['critical'][] = $session;
            } elseif ($session['difficulty_level'] == 3) {
                $grouped['attention'][] = $session;
            } else {
                $grouped['good'][] = $session;
            }
        }
        
        // Estatísticas gerais
        $totalSessions = count($sessions);
        $totalQuestions = array_sum(array_column($sessions, 'total_answers'));
        $totalCorrect = array_sum(array_column($sessions, 'correct_answers'));
        $avgPercentage = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 1) : 0;
        
        view('sessions/list', [
            'sessions_grouped' => $grouped,
            'total_sessions' => $totalSessions,
            'total_questions' => $totalQuestions,
            'total_correct' => $totalCorrect,
            'avg_percentage' => $avgPercentage
        ]);
    }
    
    /**
     * Seleciona sessão para estudar
     */
    public function select() {
        try {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            
            // Verifica se sessão existe e pertence ao usuário
            $session = $this->sessionRepo->find($sessionId);
            
            if (!$session || $session->user_id != user_id()) {
                throw new \Exception("Sessão não encontrada");
            }
            
            // Define como sessão ativa
            $_SESSION['session_id'] = $sessionId;
            
            // Limpa dados de questão anterior
            unset($_SESSION['current_question']);
            unset($_SESSION['last_answer']);
            unset($_SESSION['challenge_result']);
            
            redirect(url('index.php'));
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
            redirect(url('sessions.php'));
        }
    }
    
    /**
     * Deleta sessão
     */
    public function delete() {
        try {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            
            // Verifica permissão
            $session = $this->sessionRepo->find($sessionId);
            
            if (!$session || $session->user_id != user_id()) {
                throw new \Exception("Sessão não encontrada");
            }
            
            $sessionName = $session->pdf_name;
            
            // Deleta
            $this->sessionRepo->deleteSession($sessionId);
            
            // Se era a sessão ativa, limpa
            if (isset($_SESSION['session_id']) && $_SESSION['session_id'] == $sessionId) {
                unset($_SESSION['session_id']);
                unset($_SESSION['current_question']);
                unset($_SESSION['last_answer']);
                unset($_SESSION['challenge_result']);
            }
            
            flash_success("Sessão '$sessionName' excluída com sucesso!");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('sessions.php'));
    }
    
    /**
     * Reseta sessão atual (volta para upload)
     */
    public function reset() {
        unset($_SESSION['session_id']);
        unset($_SESSION['current_question']);
        unset($_SESSION['last_answer']);
        unset($_SESSION['challenge_result']);
        
        redirect(url('index.php'));
    }
    
    /**
     * Área de estudo de uma sessão
     */
    private function study($sessionId) {
        // Busca sessão
        $session = $this->sessionRepo->find($sessionId);
        
        if (!$session || $session->user_id != user_id()) {
            unset($_SESSION['session_id']);
            flash_error("Sessão não encontrada");
            redirect(url('index.php'));
        }
        
        // Busca progresso
        $progress = $this->sessionRepo->getProgress($sessionId);
        
        // Busca questão atual
        $currentQuestion = null;
        if (isset($_SESSION['current_question'])) {
            $currentQuestion = $this->questionService->questionRepo->find($_SESSION['current_question']);
        }
        
        // Busca última resposta
        $lastAnswer = $_SESSION['last_answer'] ?? null;
        
        // Busca resultado de challenge
        $challengeResult = $_SESSION['challenge_result'] ?? null;
        
        view('sessions/study', [
            'session' => $session,
            'progress' => $progress,
            'current_question' => $currentQuestion,
            'last_answer' => $lastAnswer,
            'challenge_result' => $challengeResult,
            'providers' => AIService::getAvailableProviders(),
            'current_provider' => $this->aiService->getProviderName()
        ]);
    }
}