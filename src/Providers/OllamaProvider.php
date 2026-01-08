<?php
/**
 * OLLAMAPROVIDER.PHP - Provider para Ollama (Local)
 * 
 * Implementação para modelos locais via Ollama
 */

namespace App\Providers;

class OllamaProvider extends BaseProvider {
    
    public function __construct() {
        parent::__construct('ollama');
    }
    
    /**
     * {@inheritdoc}
     */
    public function analyzeContent($content) {
        $this->logApiCall('analyzeContent');
        
        try {
            $contentLimited = substr($content, 0, 15000);
            
            $prompt = "Você é um Agente Analisador especializado em identificar os 20% de conteúdo mais importantes.

Analise este material:

{$contentLimited}

Retorne APENAS JSON (sem markdown):
{
  \"coreTopics\": [
    {
      \"id\": 1,
      \"title\": \"Título do tópico\",
      \"importance\": \"Alta\",
      \"keyPoints\": [\"ponto 1\", \"ponto 2\"],
      \"difficulty\": 1
    }
  ]
}

4-6 tópicos no máximo.";

            $response = $this->sendMessage($prompt);
            $data = $this->parseJsonResponse($response);
            $this->validateAnalysisResponse($data);
            
            return $data;
            
        } catch (\Exception $e) {
            $this->logApiError('analyzeContent', $e);
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function processPreSummarized($summaryText) {
        $this->logApiCall('processPreSummarized');
        
        try {
            $prompt = "Estruture este resumo em JSON:

{$summaryText}

Formato:
{
  \"coreTopics\": [
    {
      \"id\": 1,
      \"title\": \"Tópico\",
      \"importance\": \"Alta\",
      \"keyPoints\": [\"ponto\"],
      \"difficulty\": 1
    }
  ]
}";

            $response = $this->sendMessage($prompt);
            $data = $this->parseJsonResponse($response);
            $this->validateAnalysisResponse($data);
            
            return $data;
            
        } catch (\Exception $e) {
            $this->logApiError('processPreSummarized', $e);
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateQuestion($pdfContent, $topic, $difficulty, $isWeakPoint) {
        $this->logApiCall('generateQuestion');
        
        try {
            $weakPointNote = $isWeakPoint ? 'Ponto fraco do aluno.' : '';
            $keyPoints = implode(', ', $topic['keyPoints']);
            $contentLimited = substr($pdfContent, 0, 8000); // Menor para Ollama
            
            $prompt = "Crie questão CESPE (Certo/Errado).

Conteúdo: {$contentLimited}
Tópico: {$topic['title']}
Pontos: {$keyPoints}
Dificuldade: {$difficulty}/5
{$weakPointNote}

JSON apenas:
{
  \"statement\": \"afirmação\",
  \"correctAnswer\": true,
  \"topicId\": {$topic['id']},
  \"explanation\": \"explicação\",
  \"keyConceptTested\": \"conceito\"
}";

            $response = $this->sendMessage($prompt);
            $data = $this->parseJsonResponse($response);
            $this->validateQuestionResponse($data);
            
            return $data;
            
        } catch (\Exception $e) {
            $this->logApiError('generateQuestion', $e);
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function sendMessage($prompt, $options = []) {
        $data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'num_predict' => $options['max_tokens'] ?? 2000
            ]
        ];
        
        return $this->makeRequest($data);
    }
    
    /**
     * Verifica se Ollama está rodando
     * 
     * @return bool
     */
    public function isRunning() {
        try {
            $ch = curl_init(str_replace('/api/generate', '/api/tags', $this->endpoint));
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPGET => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAvailable() {
        return $this->isRunning();
    }
    
    /**
     * Faz requisição para Ollama
     * 
     * @param array $data
     * @return string
     */
    private function makeRequest($data) {
        // Verifica se Ollama está rodando
        if (!$this->isRunning()) {
            throw new \Exception("Ollama não está rodando. Inicie o Ollama antes de usar este provider.");
        }
        
        $ch = curl_init($this->endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 120
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Erro ao conectar com Ollama: $error");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Erro no Ollama: HTTP $httpCode - $response");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['response'])) {
            throw new \Exception("Resposta inesperada do Ollama");
        }
        
        return $result['response'];
    }
}