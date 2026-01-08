<?php
/**
 * REPORTCONTROLLER.PHP - Controlador de Relatórios
 * 
 * Substitui reports.php (300+ linhas)
 * Gerencia geração e visualização de relatórios
 */

namespace App\Controllers;

use App\Services\ReportService;
use App\Repositories\UserRepository;
use App\Repositories\SessionRepository;
use App\Middleware\AuthMiddleware;

class ReportController {
    private $reportService;
    private $userRepo;
    private $sessionRepo;
    
    public function __construct() {
        AuthMiddleware::require();
        
        $this->reportService = new ReportService();
        $this->userRepo = new UserRepository();
        $this->sessionRepo = new SessionRepository();
    }
    
    /**
     * Página principal de relatórios
     */
    public function index() {
        $userId = user_id();
        
        // Busca estatísticas
        $stats = $this->userRepo->getStatistics($userId);
        $sessions = $this->sessionRepo->getUserSessions($userId, 20);
        $history = $this->userRepo->getProgressHistory($userId, 30);
        
        // Calcula percentual
        $totalQ = $stats['total_questions'] ?? 0;
        $correctQ = $stats['total_correct'] ?? 0;
        $percentage = $totalQ > 0 ? round(($correctQ / $totalQ) * 100, 1) : 0;
        
        // Formata tempo
        $seconds = $stats['total_study_time'] ?? 0;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $timeStr = $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
        
        view('reports/index', [
            'stats' => [
                'total_sessions' => $stats['total_sessions'] ?? 0,
                'total_questions' => $totalQ,
                'percentage' => $percentage,
                'time_str' => $timeStr
            ],
            'sessions' => $sessions,
            'history' => $history
        ]);
    }
    
    /**
     * Gera relatório HTML para impressão
     */
    public function generate() {
        try {
            $userId = user_id();
            $sessionId = !empty($_POST['session_id']) ? (int)$_POST['session_id'] : null;
            
            // Gera HTML do relatório
            $html = $this->reportService->generateProgressReport($userId, $sessionId);
            
            // Salva arquivo
            $fileName = 'relatorio_' . $userId . '_' . date('YmdHis') . '.html';
            $filePath = $this->reportService->saveReport($html, $fileName);
            
            // Redireciona para o arquivo
            redirect(url('storage/reports/' . $fileName));
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
            redirect(url('reports.php'));
        }
    }
    
    /**
     * API: Dados para gráficos (AJAX)
     */
    public function chartData() {
        if (!AuthMiddleware::check()) {
            json_error("Não autenticado", 401);
        }
        
        $userId = user_id();
        $type = $_GET['type'] ?? 'progress';
        
        switch ($type) {
            case 'progress':
                $days = (int)($_GET['days'] ?? 30);
                $data = $this->userRepo->getProgressHistory($userId, $days);
                
                json_success([
                    'labels' => array_column($data, 'date'),
                    'questions' => array_column($data, 'questions'),
                    'correct' => array_column($data, 'correct')
                ]);
                break;
                
            case 'topics':
                $sessionId = !empty($_GET['session_id']) ? (int)$_GET['session_id'] : null;
                $data = $this->userRepo->getTopicPerformance($userId, $sessionId);
                
                json_success([
                    'topics' => array_column($data, 'key_concept'),
                    'percentages' => array_column($data, 'percentage')
                ]);
                break;
                
            case 'difficulty':
                $stats = $this->userRepo->getStatistics($userId);
                
                json_success([
                    'avg_difficulty' => round($stats['avg_difficulty'] ?? 1, 1)
                ]);
                break;
                
            default:
                json_error("Tipo de gráfico inválido");
        }
    }
    
    /**
     * API: Estatísticas resumidas (AJAX)
     */
    public function stats() {
        if (!AuthMiddleware::check()) {
            json_error("Não autenticado", 401);
        }
        
        $userId = user_id();
        $stats = $this->userRepo->getStatistics($userId);
        
        json_success($stats);
    }
    
    /**
     * Exporta dados em CSV
     */
    public function exportCSV() {
        try {
            $userId = user_id();
            $type = $_GET['type'] ?? 'progress';
            
            // Headers para download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Ymd') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            switch ($type) {
                case 'progress':
                    fputcsv($output, ['Data', 'Questões', 'Corretas', 'Percentual']);
                    
                    $history = $this->userRepo->getProgressHistory($userId, 365);
                    
                    foreach ($history as $day) {
                        $percentage = $day['questions'] > 0 
                            ? round(($day['correct'] / $day['questions']) * 100, 1) 
                            : 0;
                        
                        fputcsv($output, [
                            $day['date'],
                            $day['questions'],
                            $day['correct'],
                            $percentage . '%'
                        ]);
                    }
                    break;
                    
                case 'topics':
                    fputcsv($output, ['Tópico', 'Total', 'Corretas', 'Percentual']);
                    
                    $topics = $this->userRepo->getTopicPerformance($userId);
                    
                    foreach ($topics as $topic) {
                        fputcsv($output, [
                            $topic['key_concept'],
                            $topic['total'],
                            $topic['correct'],
                            $topic['percentage'] . '%'
                        ]);
                    }
                    break;
                    
                case 'sessions':
                    fputcsv($output, ['Nome', 'Criado em', 'Questões', 'Acertos', '%']);
                    
                    $sessions = $this->sessionRepo->getUserSessionsWithProgress($userId, 1000);
                    
                    foreach ($sessions as $session) {
                        $percentage = $session['total_answers'] > 0
                            ? round(($session['correct_answers'] / $session['total_answers']) * 100, 1)
                            : 0;
                        
                        fputcsv($output, [
                            $session['pdf_name'],
                            $session['created_at'],
                            $session['total_answers'],
                            $session['correct_answers'],
                            $percentage . '%'
                        ]);
                    }
                    break;
            }
            
            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
            redirect(url('reports.php'));
        }
    }
    
    /**
     * Comparação de desempenho (para admins)
     */
    public function compare() {
        AdminMiddleware::require();
        
        // Implementar comparação entre usuários
        // ...
        
        view('reports/compare');
    }
}