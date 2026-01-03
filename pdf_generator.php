<?php
/**
 * PDF GENERATOR v2.2 - Geração de Relatórios SEM DEPENDÊNCIAS
 * 
 * Salve este arquivo como: pdf_generator.php
 * 
 * SOLUÇÃO: Gera HTML estilizado para impressão/salvar como PDF
 * Funciona em qualquer servidor PHP sem instalações adicionais
 */

require_once 'config.php';
require_once 'database.php';

class PDFGenerator {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Gera relatório de progresso do usuário
     */
    public function generateProgressReport($userId, $sessionId = null) {
        // Buscar dados do usuário
        $user = $this->db->getUserById($userId);
        $stats = $this->db->getUserStatistics($userId);
        $history = $this->db->getProgressHistory($userId, 30);
        $topicPerformance = $this->db->getTopicPerformance($userId, $sessionId);
        
        // Se sessionId específico, buscar dados da sessão
        $sessionData = null;
        if ($sessionId) {
            $sessionData = $this->db->getSession($sessionId);
        }
        
        // Criar HTML do relatório
        $html = $this->buildReportHTML($user, $stats, $history, $topicPerformance, $sessionData);
        
        // Salvar como HTML otimizado para impressão
        $result = $this->saveAsHTML($html, $user['name']);
        
        return $result;
    }
    
    /**
     * Constrói HTML do relatório otimizado para impressão
     */
    private function buildReportHTML($user, $stats, $history, $topicPerformance, $sessionData) {
        $userName = htmlspecialchars($user['name']);
        $userEmail = htmlspecialchars($user['email']);
        $currentDate = date('d/m/Y H:i');
        
        // Calcular percentuais
        $totalQuestions = $stats['total_questions'] ?? 0;
        $totalCorrect = $stats['total_correct'] ?? 0;
        $percentage = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 1) : 0;
        
        // Converter tempo de estudo
        $studyTime = $this->formatStudyTime($stats['total_study_time'] ?? 0);
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Progresso - {$userName}</title>
    <style>
        /* Reset e Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.95;
            margin: 5px 0;
        }
        
        /* Content */
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #dee2e6;
        }
        
        .stat-value {
            font-size: 42px;
            font-weight: 800;
            color: #667eea;
            display: block;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        /* Performance Bar */
        .performance-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .performance-bar {
            background: #e9ecef;
            height: 35px;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .performance-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            transition: width 0.3s ease;
        }
        
        /* Topics */
        .topic-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .topic-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
        }
        
        .topic-name {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 10px;
            color: #495057;
        }
        
        .topic-stats {
            font-size: 13px;
            color: #6c757d;
        }
        
        .topic-bar {
            background: #e9ecef;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .topic-bar-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Badge */
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 8px;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #667eea;
            color: white;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        td {
            font-size: 14px;
        }
        
        /* Footer */
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #6c757d;
            border-top: 3px solid #dee2e6;
        }
        
        .footer p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .footer strong {
            color: #495057;
            font-size: 15px;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
            }
            
            .no-print {
                display: none !important;
            }
            
            .section {
                page-break-inside: avoid;
            }
        }
        
        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            z-index: 1000;
            transition: transform 0.2s;
        }
        
        .print-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .print-button:active {
            transform: translateY(0);
        }
        
        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    
    <!-- Botão de Impressão -->
    <button onclick="window.print()" class="print-button no-print">
        🖨️ Imprimir / Salvar PDF
    </button>
    
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Relatório de Progresso</h1>
            <p><strong>{$userName}</strong></p>
            <p>{$userEmail}</p>
            <p>Gerado em: {$currentDate}</p>
        </div>

        <div class="content">
            <!-- Resumo Geral -->
            <div class="section">
                <div class="section-title">📈 Resumo Geral</div>
                <div class="stats-grid">
                    <div class="stat-box">
                        <span class="stat-value">{$stats['total_sessions']}</span>
                        <span class="stat-label">Sessões</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value">{$totalQuestions}</span>
                        <span class="stat-label">Questões</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value">{$percentage}%</span>
                        <span class="stat-label">Acertos</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value">{$studyTime}</span>
                        <span class="stat-label">Tempo</span>
                    </div>
                </div>
                
                <div class="performance-section">
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: {$percentage}%;">
                            {$totalCorrect} de {$totalQuestions} corretas
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desempenho por Tópico -->
            <div class="section">
                <div class="section-title">🎯 Desempenho por Tópico</div>
HTML;

        if (!empty($topicPerformance)) {
            $html .= '<div class="topic-grid">';
            foreach ($topicPerformance as $topic) {
                $topicName = htmlspecialchars($topic['key_concept']);
                $topicPercentage = round($topic['percentage'], 1);
                $topicTotal = $topic['total'];
                $topicCorrect = $topic['correct'];
                
                $badgeClass = 'badge-success';
                if ($topicPercentage < 50) {
                    $badgeClass = 'badge-danger';
                } elseif ($topicPercentage < 70) {
                    $badgeClass = 'badge-warning';
                }
                
                $html .= <<<HTML
                <div class="topic-item">
                    <div class="topic-name">
                        {$topicName} 
                        <span class="badge {$badgeClass}">{$topicPercentage}%</span>
                    </div>
                    <div class="topic-stats">{$topicCorrect} de {$topicTotal} questões</div>
                    <div class="topic-bar">
                        <div class="topic-bar-fill" style="width: {$topicPercentage}%;">
                            {$topicPercentage}%
                        </div>
                    </div>
                </div>
HTML;
            }
            $html .= '</div>';
        } else {
            $html .= '<p style="text-align: center; color: #6c757d; padding: 40px;">Nenhum dado disponível ainda. Continue estudando!</p>';
        }

        $html .= '</div>'; // Fecha section de tópicos

        // Histórico dos Últimos 30 Dias
        $html .= <<<HTML
            <div class="section">
                <div class="section-title">📅 Histórico dos Últimos 30 Dias</div>
HTML;

        if (!empty($history)) {
            $html .= <<<HTML
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Questões</th>
                            <th>Acertos</th>
                            <th>Taxa de Acerto</th>
                        </tr>
                    </thead>
                    <tbody>
HTML;
            
            foreach ($history as $day) {
                $date = date('d/m/Y', strtotime($day['date']));
                $questions = $day['questions'];
                $correct = $day['correct'];
                $rate = $questions > 0 ? round(($correct / $questions) * 100, 1) : 0;
                
                $html .= <<<HTML
                        <tr>
                            <td><strong>{$date}</strong></td>
                            <td>{$questions}</td>
                            <td>{$correct}</td>
                            <td><strong>{$rate}%</strong></td>
                        </tr>
HTML;
            }
            
            $html .= <<<HTML
                    </tbody>
                </table>
HTML;
        } else {
            $html .= '<p style="text-align: center; color: #6c757d; padding: 40px;">Nenhuma atividade nos últimos 30 dias.</p>';
        }

        $html .= '</div>'; // Fecha section de histórico

        // Informações da sessão específica (se fornecida)
        if ($sessionData) {
            $sessionName = htmlspecialchars($sessionData['pdf_name']);
            $sessionDate = date('d/m/Y H:i', strtotime($sessionData['created_at']));
            
            $html .= <<<HTML
            <div class="section">
                <div class="section-title">📚 Sessão de Estudo</div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
                    <p style="margin-bottom: 10px;"><strong>Material:</strong> {$sessionName}</p>
                    <p><strong>Iniciado em:</strong> {$sessionDate}</p>
                </div>
            </div>
HTML;
        }

        $html .= <<<HTML
        </div> <!-- Fecha content -->

        <!-- Footer -->
        <div class="footer">
            <p><strong>Sistema RAG de Estudos Inteligente v2.2</strong></p>
            <p>Baseado no Princípio de Pareto (80/20) com IA</p>
            <p style="margin-top: 10px;">Este relatório foi gerado automaticamente pelo sistema.</p>
        </div>
    </div>

    <script>
        // Adicionar instruções para o usuário
        window.addEventListener('load', function() {
            console.log('%c📊 Relatório Carregado!', 'color: #667eea; font-size: 16px; font-weight: bold;');
            console.log('%c🖨️ Clique no botão "Imprimir / Salvar PDF" ou use Ctrl+P', 'color: #495057; font-size: 14px;');
            console.log('%c💡 Na janela de impressão, escolha "Salvar como PDF"', 'color: #495057; font-size: 14px;');
        });
    </script>

</body>
</html>
HTML;

        return $html;
    }
    
    /**
     * Salva como HTML otimizado para impressão
     */
    private function saveAsHTML($html, $userName) {
        // Criar diretório de relatórios se não existir
        $reportsDir = getConfig('PDF_REPORTS_DIR', 'reports/');
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }
        
        // Nome do arquivo
        $fileName = 'relatorio_' . preg_replace('/[^a-zA-Z0-9]/', '_', $userName) . '_' . date('YmdHis') . '.html';
        $filePath = $reportsDir . $fileName;
        
        // Salvar HTML
        file_put_contents($filePath, $html);
        
        return [
            'path' => $filePath,
            'filename' => $fileName,
            'url' => $reportsDir . $fileName,
            'type' => 'html'
        ];
    }
    
    /**
     * Formata tempo de estudo em horas e minutos
     */
    private function formatStudyTime($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        } else {
            return "{$minutes}min";
        }
    }
}