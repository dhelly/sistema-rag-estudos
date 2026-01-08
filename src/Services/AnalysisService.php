<?php
/**
 * ANALYSISSERVICE.PHP - Serviço de Análise de Conteúdo
 * 
 * Gerencia análise de PDFs e criação de sessões de estudo
 */

namespace App\Services;

use App\Repositories\SessionRepository;
use App\Repositories\DisciplineRepository;

class AnalysisService {
    private $aiService;
    private $sessionRepo;
    private $disciplineRepo;
    
    public function __construct(AIService $aiService = null) {
        $this->aiService = $aiService ?? new AIService();
        $this->sessionRepo = new SessionRepository();
        $this->disciplineRepo = new DisciplineRepository();
    }
    
    /**
     * Processa upload de PDF
     * 
     * @param array $file $_FILES['pdf']
     * @param int $userId
     * @param int|null $disciplineId
     * @return array ['session_id' => int, 'core_topics' => array]
     * @throws \Exception
     */
    public function processPDFUpload($file, $userId, $disciplineId = null) {
        // Valida arquivo
        $this->validatePDFFile($file);
        
        // Extrai nome
        $pdfName = basename($file['name']);
        
        // Lê conteúdo
        $pdfData = file_get_contents($file['tmp_name']);
        $base64Data = base64_encode($pdfData);
        
        // Extrai texto
        $extractedText = $this->aiService->extractPDFText($base64Data);
        
        // Analisa conteúdo
        $analysis = $this->aiService->analyzeContent($extractedText);
        
        // Valida análise
        if (!isset($analysis['coreTopics']) || empty($analysis['coreTopics'])) {
            throw new \Exception("Análise não retornou tópicos válidos");
        }
        
        // Cria sessão
        $session = $this->sessionRepo->createSession(
            $userId,
            $pdfName,
            $extractedText,
            $analysis['coreTopics'],
            $disciplineId
        );
        
        return [
            'session_id' => $session->id,
            'session_name' => $pdfName,
            'core_topics' => $analysis['coreTopics'],
            'content_length' => strlen($extractedText)
        ];
    }
    
    /**
     * Processa resumo pré-formatado (texto)
     * 
     * @param string $summaryText
     * @param string $materialName
     * @param int $userId
     * @param int|null $disciplineId
     * @return array
     * @throws \Exception
     */
    public function processTextSummary($summaryText, $materialName, $userId, $disciplineId = null) {
        // Valida texto
        if (empty(trim($summaryText))) {
            throw new \Exception("Resumo não pode estar vazio");
        }
        
        if (strlen($summaryText) < 50) {
            throw new \Exception("Resumo muito curto. Mínimo 50 caracteres.");
        }
        
        // Nome padrão se não fornecido
        if (empty($materialName)) {
            $materialName = 'Resumo 80/20 - ' . date('d/m/Y H:i');
        }
        
        // Processa com IA
        $analysis = $this->aiService->processPreSummarized($summaryText);
        
        // Valida
        if (!isset($analysis['coreTopics']) || empty($analysis['coreTopics'])) {
            throw new \Exception("Não foi possível estruturar o resumo");
        }
        
        // Cria sessão
        $session = $this->sessionRepo->createSession(
            $userId,
            $materialName,
            $summaryText,
            $analysis['coreTopics'],
            $disciplineId
        );
        
        return [
            'session_id' => $session->id,
            'session_name' => $materialName,
            'core_topics' => $analysis['coreTopics'],
            'content_length' => strlen($summaryText)
        ];
    }
    
    /**
     * Valida arquivo PDF
     * 
     * @param array $file
     * @throws \Exception
     */
    private function validatePDFFile($file) {
        // Verifica erros de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede tamanho máximo permitido pelo servidor',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede tamanho máximo do formulário',
                UPLOAD_ERR_PARTIAL => 'Upload foi feito parcialmente',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
            ];
            
            $errorMsg = $errors[$file['error']] ?? 'Erro desconhecido no upload';
            throw new \Exception($errorMsg);
        }
        
        // Verifica tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            throw new \Exception("Arquivo deve ser PDF. Tipo detectado: $mimeType");
        }
        
        // Verifica tamanho
        $maxSize = config('storage.max_file_size_bytes');
        if ($file['size'] > $maxSize) {
            $maxSizeFormatted = format_bytes($maxSize);
            throw new \Exception("Arquivo muito grande. Máximo: $maxSizeFormatted");
        }
        
        // Verifica extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            throw new \Exception("Extensão inválida. Use apenas .pdf");
        }
    }
    
    /**
     * Re-analisa sessão existente
     * 
     * @param int $sessionId
     * @return array
     * @throws \Exception
     */
    public function reanalyzeSession($sessionId) {
        $session = $this->sessionRepo->find($sessionId);
        
        if (!$session) {
            throw new \Exception("Sessão não encontrada");
        }
        
        // Re-analisa conteúdo
        $analysis = $this->aiService->analyzeContent($session->pdf_content);
        
        // Atualiza tópicos
        $session->core_topics = $analysis['coreTopics'];
        $session->save();
        
        // Recalcula progresso
        $this->sessionRepo->recalculateProgress($sessionId);
        
        return [
            'session_id' => $sessionId,
            'core_topics' => $analysis['coreTopics']
        ];
    }
    
    /**
     * Obtém estatísticas de análise
     * 
     * @param int $sessionId
     * @return array
     */
    public function getAnalysisStatistics($sessionId) {
        $session = $this->sessionRepo->find($sessionId);
        
        if (!$session) {
            return null;
        }
        
        $topics = $session->core_topics;
        
        return [
            'total_topics' => count($topics),
            'content_size' => strlen($session->pdf_content),
            'content_size_formatted' => format_bytes(strlen($session->pdf_content)),
            'avg_key_points' => array_sum(array_map(function($t) {
                return count($t['keyPoints'] ?? []);
            }, $topics)) / count($topics),
            'difficulty_distribution' => array_count_values(array_column($topics, 'difficulty'))
        ];
    }
    
    /**
     * Valida se provider suporta o método de upload
     * 
     * @param string $method 'pdf' ou 'text'
     * @return bool
     */
    public function validateUploadMethod($method) {
        if ($method === 'pdf') {
            return $this->aiService->supportsPDF();
        }
        
        return true; // Texto é sempre suportado
    }
    
    /**
     * Obtém sugestões de melhoria para análise
     * 
     * @param array $coreTopics
     * @return array
     */
    public function getSuggestions($coreTopics) {
        $suggestions = [];
        
        // Verifica quantidade de tópicos
        if (count($coreTopics) < 4) {
            $suggestions[] = [
                'type' => 'warning',
                'message' => 'Poucos tópicos identificados. Material pode ser muito específico.'
            ];
        }
        
        if (count($coreTopics) > 8) {
            $suggestions[] = [
                'type' => 'info',
                'message' => 'Muitos tópicos. Considere focar nos mais importantes.'
            ];
        }
        
        // Verifica pontos-chave
        foreach ($coreTopics as $topic) {
            if (count($topic['keyPoints']) < 2) {
                $suggestions[] = [
                    'type' => 'warning',
                    'message' => "Tópico '{$topic['title']}' tem poucos pontos-chave."
                ];
            }
        }
        
        return $suggestions;
    }
}