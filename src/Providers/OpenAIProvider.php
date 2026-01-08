<?php
/**
 * OPENAIPROVIDER.PHP - Provider para OpenAI (GPT)
 * 
 * Salvar como: src/Providers/OpenAIProvider.php
 */

namespace App\Providers;

class OpenAIProvider extends BaseProvider {
    
    public function __construct() {
        parent::__construct('openai');
    }
    
    /**
     * {@inheritdoc}
     */
    public function analyzeContent($content) {
        $this->logApiCall('analyzeContent');
        
        try {
            $contentLimited = substr($content, 0, 15000);
            
            $prompt = "Você é um Agente Analisador especializado em identificar os 20% de conteúdo mais importantes que geram 80% dos resultados (Princípio de Pareto).

Analise este material de estudo e identifique os tópicos ESSENCIAIS:

{$contentLimited}

Retorne APENAS um JSON (sem markdown, sem explicações) com esta estrutura:
{
  \"coreTopics\": [
    {
      \"id\": 1,
      \"title\": \"Título conciso do tópico\",
      \"importance\": \"Alta\",
      \"keyPoints\": [\"ponto 1\", \"ponto 2\", \"ponto 3\"],
      \"difficulty\": 1
    }
  ]
}

Identifique 4-6 tópicos fundamentais, não mais que isso.";

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
            $prompt = "Você recebeu um resumo já processado seguindo a regra 80/20.

RESUMO: {$summaryText}

Estruture em JSON:
{
  \"coreTopics\": [
    {
      \"id\": 1,
      \"title\": \"Tópico\",
      \"importance\": \"Alta\",
      \"keyPoints\": [\"ponto 1\", \"ponto 2\"],
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
            $weakPointNote = $isWeakPoint ? 'IMPORTANTE: Este é um ponto fraco do aluno.' : '';
            $keyPoints = implode(', ', $topic['keyPoints']);
            $contentLimited = substr($pdfContent, 0, 10000);
            
            $prompt = "Você é um Gerador de Questões CESPE.

Conteúdo: {$contentLimited}
Tópico: {$topic['title']}
Pontos-chave: {$keyPoints}
Dificuldade: {$difficulty}/5
{$weakPointNote}

Retorne APENAS JSON:
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
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000
        ];
        
        return $this->makeRequest($data);
    }
    
    /**
     * Faz requisição para API OpenAI
     * 
     * @param array $data
     * @return string
     */
    private function makeRequest($data) {
        $ch = curl_init($this->endpoint);
        
        $options = $this->getDefaultCurlOptions();
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
        $options[CURLOPT_HTTPHEADER] = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        
        $this->handleCurlError($ch);
        $this->handleHttpResponse($ch, $response);
        
        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new \Exception("Resposta inesperada da API OpenAI");
        }
        
        return $result['choices'][0]['message']['content'];
    }
}