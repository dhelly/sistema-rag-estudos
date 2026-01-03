<?php
/**
 * ARQUIVO 4 de 6: api.php (CORRIGIDO COMPLETO)
 * 
 * Salve este arquivo como: api.php
 * Suporte para múltiplos provedores de IA
 */

require_once 'config.php';

class UnifiedAI {
    private $provider;
    private $config;
    
    public function __construct($provider = null) {
        $this->provider = $provider ?? getCurrentProvider();
        $this->config = getProviderConfig($this->provider);
        
        if (!$this->config) {
            throw new Exception("Provedor '{$this->provider}' não configurado!");
        }
        
        if (!$this->config['available']) {
            throw new Exception("Provedor '{$this->provider}' não disponível. Configure a chave API no .env");
        }
    }
    
    public function extractPDFText($base64Data) {
        // Apenas Anthropic suporta PDFs nativamente
        if ($this->provider === 'anthropic') {
            return $this->anthropicExtractPDF($base64Data);
        }
        
        // Outros provedores: retorna mensagem pedindo uso de resumo
        throw new Exception("Este provedor não suporta extração direta de PDF. Use a opção 'Resumo Pronto (80/20)'.");
    }
    
    private function anthropicExtractPDF($base64Data) {
        $data = [
            'model' => $this->config['model'],
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
        
        return $this->makeAnthropicRequest($data);
    }
    
    public function analyzeContent($content) {
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

        return $this->sendMessage($prompt);
    }
    
    public function processPreSummarized($summaryText) {
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
- Retorne 4-6 tópicos no máximo
- Se o resumo não estiver claro, faça o melhor possível para estruturá-lo";

        return $this->sendMessage($prompt);
    }
    
    public function generateQuestion($pdfContent, $topic, $difficulty, $isWeakPoint) {
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

        return $this->sendMessage($prompt);
    }
    
    public function sendMessage($prompt) {
        switch ($this->provider) {
            case 'anthropic':
                return $this->makeAnthropicRequest([
                    'model' => $this->config['model'],
                    'max_tokens' => 2000,
                    'messages' => [['role' => 'user', 'content' => $prompt]]
                ]);
                
            case 'openai':
            case 'deepseek':
                return $this->makeOpenAIStyleRequest($prompt);
                
            case 'ollama':
                return $this->makeOllamaRequest($prompt);
                
            default:
                throw new Exception("Provedor não suportado: {$this->provider}");
        }
    }
    
    private function makeAnthropicRequest($data) {
        $ch = curl_init($this->config['endpoint']);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key'],
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => $this->getCacertPath()
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            throw new Exception("Erro cURL: {$error}");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro na API Anthropic: HTTP {$httpCode} - {$response}");
        }
        
        $result = json_decode($response, true);
        return $result['content'][0]['text'] ?? '';
    }
    
    private function makeOpenAIStyleRequest($prompt) {
        $ch = curl_init($this->config['endpoint']);
        
        $data = [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config['api_key']
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => $this->getCacertPath()
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            throw new Exception("Erro cURL: {$error}");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro na API {$this->config['name']}: HTTP {$httpCode} - {$response}");
        }
        
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? '';
    }
    
    private function makeOllamaRequest($prompt) {
        $ch = curl_init($this->config['endpoint']);
        
        $data = [
            'model' => $this->config['model'],
            'prompt' => $prompt,
            'stream' => false
        ];
        
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
            throw new Exception("Erro ao conectar com Ollama: {$error}. Certifique-se que o Ollama está rodando.");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro no Ollama: HTTP {$httpCode} - {$response}");
        }
        
        $result = json_decode($response, true);
        return $result['response'] ?? '';
    }
    
    private function getCacertPath() {
        if (!empty(getConfig('CACERT_PATH'))) {
            return getConfig('CACERT_PATH');
        }
        
        $possiblePaths = [
            __DIR__ . '/cacert.pem',
            'C:/laragon/bin/php/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
}