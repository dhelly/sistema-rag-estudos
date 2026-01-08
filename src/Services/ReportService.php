<?php
// =====================================
// REPORTSERVICE.PHP
// 
// Salvar como: src/Services/ReportService.php
// =====================================

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\SessionRepository;

class ReportService {
    private $userRepo;
    private $sessionRepo;
    
    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->sessionRepo = new SessionRepository();
    }
    
    /**
     * Gera relatório de progresso
     * 
     * @param int $userId
     * @param int|null $sessionId
     * @return string HTML do relatório
     */
    public function generateProgressReport($userId, $sessionId = null) {
        $user = $this->userRepo->find($userId);
        $stats = $this->userRepo->getStatistics($userId);
        $history = $this->userRepo->getProgressHistory($userId, 30);
        $topicPerformance = $this->userRepo->getTopicPerformance($userId, $sessionId);
        
        $sessionData = null;
        if ($sessionId) {
            $sessionData = $this->sessionRepo->find($sessionId);
        }
        
        return $this->buildReportHTML($user, $stats, $history, $topicPerformance, $sessionData);
    }
    
    /**
     * Constrói HTML do relatório
     */
    private function buildReportHTML($user, $stats, $history, $topicPerformance, $sessionData) {
        // Usar o template do pdf_generator.php
        // Adaptado para nova estrutura
        
        $userName = e($user->name);
        $userEmail = e($user->email);
        
        // Calcular percentuais
        $totalQ = $stats['total_questions'] ?? 0;
        $totalCorrect = $stats['total_correct'] ?? 0;
        $percentage = $totalQ > 0 ? round(($totalCorrect / $totalQ) * 100, 1) : 0;
        
        // HTML completo (usar template do pdf_generator.php)
        // ...código HTML do relatório...
        
        return "<!DOCTYPE html><!-- HTML do relatório --></html>";
    }
    
    /**
     * Salva relatório como arquivo
     */
    public function saveReport($html, $fileName) {
        $reportsDir = storage_path('reports');
        
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }
        
        $filePath = $reportsDir . '/' . $fileName;
        file_put_contents($filePath, $html);
        
        return $filePath;
    }
}