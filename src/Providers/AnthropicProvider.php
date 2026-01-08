<?php
/**
 * ANTHROPICPROVIDER.PHP - Provider para Anthropic Claude
 * 
 * Implementação específica para API do Claude
 */

namespace App\Providers;

class AnthropicProvider extends BaseProvider {
    
    public function __construct() {
        parent::__construct('anthropic');
    }
    
    /**
     * {@inheritdoc}
     * 
     * Anthropic é o ÚNICO provider que suporta PDF nativo
     */
    public function extractPDFText($base64Data) {
        $this->logApiCall('extractPDFText');
        
        try {
            $data = [
                'model' => $this->model,
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'document',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => 'application/pdf',
                                    'data' => $base64Data
                                ]
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Extraia todo o conteúdo textual deste PDF de forma organizada e completa.'
                            ]
                        ]
                    ]
                ]
            ];
            
            return $this->makeRequest($data);
            
        } catch (\Exception $e) {
            $this->logApiError('extractPDFText', $e);
            throw $e;
        }
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
            $prompt = "Você recebeu um resumo já processado seguindo a regra 80/20 (Princípio de Pareto).

Sua tarefa é ESTRUTURAR este conteúdo no formato JSON necessário para o sistema.

RESUMO FORNECIDO:
{$summaryText}

Analise o resumo e organize em tópicos essenciais. Retorne APENAS JSON (sem markdown):

{
  \"coreTopics\": [
    {
      \"id\": 1,
      \"title\": \"Nome do tópico extraído do resumo\",
      \"importance\": \"Alta\",
      \"keyPoints\": [\"ponto-chave 1\", \"ponto-chave 2\", \"ponto-chave 3\"],
      \"difficulty\": 1
    }
  ]
}

INSTRUÇÕES:
- Identifique os principais tópicos/temas do resumo
- Extraia os pontos-chave de cada tópico
- Classifique a dificuldade de 1 (básico) a 5 (avançado)
- Retorne 4-6 tópicos no máximo";

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
            $weakPointNote = $isWeakPoint ? 'IMPORTANTE: Este é um ponto fraco do aluno. Reforce conceitos básicos.' : '';
            
            $difficultyDesc = [
                1 => 'Básica e direta',
                2 => 'Intermediária',
                3 => 'Avançada com pegadinhas sutis',
                4 => 'Muito complexa com múltiplos conceitos',
                5 => 'Expert com armadilhas elaboradas'
            ];
            
            $keyPoints = implode(', ', $topic['keyPoints']);
            $contentLimited = substr($pdfContent, 0, 10000);
            
            $prompt = "Você é um Agente Gerador de Questões especializado em criar questões estilo CESPE (Certo/Errado).

Conteúdo de referência:
{$contentLimited}

Tópico foco: {$topic['title']}
Pontos-chave: {$keyPoints}

Nível de dificuldade: {$difficulty}/5
{$weakPointNote}

Crie uma questão CESPE seguindo estas diretrizes:
- Dificuldade {$difficulty}: {$difficultyDesc[$difficulty]}
- Seja preciso e técnico
- Use termos do próprio material
- Para dificuldade 3+: inclua pegadinhas sutis

Retorne APENAS JSON (sem markdown):
{
  \"statement\": \"afirmação da questão\",
  \"correctAnswer\": true,
  \"topicId\": {$topic['id']},
  \"explanation\": \"explicação detalhada\",
  \"keyConceptTested\": \"conceito principal\"
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
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        return $this->makeRequest($data);
    }
    
    /**
     * Faz requisição para API Anthropic
     * 
     * @param array $data
     * @return string
     * @throws \Exception
     */
    private function makeRequest($data) {
        $ch = curl_init($this->endpoint);
        
        $options = $this->getDefaultCurlOptions();
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
        $options[CURLOPT_HTTPHEADER] = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        
        $this->handleCurlError($ch);
        $this->handleHttpResponse($ch, $response);
        
        $result = json_decode($response, true);
        
        if (!isset($result['content'][0]['text'])) {
            throw new \Exception("Resposta inesperada da API Anthropic");
        }
        
        return $result['content'][0]['text'];
    }
}