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
    private $db;
    private $disciplineId;
    
    public function __construct($provider = null, $disciplineId = null) {
        $this->provider = $provider ?? getCurrentProvider();
        $this->config = getProviderConfig($this->provider);
        $this->disciplineId = $disciplineId;
        $this->db = new Database();
        
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

    /**
     * Obtém prompt customizado ou usa o padrão
     */
    private function getCustomPrompt($agentType, $defaultPrompt) {
        if (!$this->disciplineId) {
            return $defaultPrompt;
        }
        
        $promptData = $this->db->getAgentPrompt($this->disciplineId, $agentType);
        
        if ($promptData && !empty($promptData['prompt_content'])) {
            return $promptData['prompt_content'];
        }
        
        return $defaultPrompt;
    }

    /**
     * Substitui variáveis no prompt
     */
    private function replacePromptVariables($prompt, $variables = []) {
        foreach ($variables as $key => $value) {
            $prompt = str_replace('{' . $key . '}', $value, $prompt);
        }
        return $prompt;
}

    public function analyzeContent($content) {
        $contentLimited = substr($content, 0, 15000);
        
        $defaultPrompt = "Você é um Agente Analisador especializado em identificar os 20% de conteúdo mais importantes que geram 80% dos resultados (Princípio de Pareto).

    Analise este material de estudo e identifique os tópicos ESSENCIAIS:

    {content}

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

        // Buscar prompt customizado
        $prompt = $this->getCustomPrompt('analyzer', $defaultPrompt);
        
        // Substituir variáveis
        $prompt = $this->replacePromptVariables($prompt, [
            'content' => $contentLimited
        ]);

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
        
        $defaultPrompt = "Você é um Agente Gerador de Questões especializado em criar questões estilo CESPE (Certo/Errado).

    Conteúdo de referência:
    {content}

    Tópico foco: {topic_title}
    Pontos-chave: {key_points}

    Nível de dificuldade: {difficulty}/5
    {weak_point_note}

    Crie uma questão CESPE seguindo estas diretrizes:
    - Dificuldade {difficulty}: {difficulty_desc}
    - Seja preciso e técnico
    - Use termos do próprio material
    - Para dificuldade 3+: inclua pegadinhas sutis

    Retorne APENAS JSON (sem markdown):
    {
    \"statement\": \"afirmação da questão\",
    \"correctAnswer\": true,
    \"topicId\": {topic_id},
    \"explanation\": \"explicação detalhada\",
    \"keyConceptTested\": \"conceito principal\"
    }";

        // Buscar prompt customizado
        $prompt = $this->getCustomPrompt('generator', $defaultPrompt);
        
        // Substituir variáveis
        $prompt = $this->replacePromptVariables($prompt, [
            'content' => $contentLimited,
            'topic_title' => $topic['title'],
            'key_points' => $keyPoints,
            'difficulty' => $difficulty,
            'difficulty_desc' => $difficultyDesc[$difficulty],
            'weak_point_note' => $weakPointNote,
            'topic_id' => $topic['id']
        ]);

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

    public function getChallengePrompt($question, $userArgument, $webContext, $disciplineId = null) {
        $this->disciplineId = $disciplineId;
        
        $defaultPrompt = "Você é um Agente Questionador especializado em validar gabaritos de questões estilo CESPE.

    QUESTÃO ORIGINAL:
    Afirmação: {statement}
    Gabarito atual: {current_answer}
    Explicação atual: {explanation}
    Conceito testado: {key_concept}

    QUESTIONAMENTO DO ALUNO:
    {user_argument}

    FONTES DA WEB (Tavily Search):
    {web_sources}

    INSTRUÇÕES:
    1. Analise cuidadosamente o questionamento do aluno
    2. Considere as fontes da web encontradas
    3. Verifique se há erro no gabarito original
    4. Se o aluno tiver razão, sugira o gabarito correto e nova explicação
    5. Se o aluno estiver errado, explique por que o gabarito está correto

    Retorne APENAS JSON (sem markdown):
    {
    \"decision\": \"accepted\" ou \"rejected\",
    \"confidence\": 0.0 a 1.0,
    \"analysis\": \"análise detalhada do questionamento\",
    \"reasoning\": \"raciocínio baseado nas fontes web\",
    \"suggested_answer\": true ou false (apenas se decision = accepted),
    \"updated_explanation\": \"nova explicação\" (apenas se decision = accepted),
    \"key_sources\": [\"fonte1\", \"fonte2\"]
    }

    IMPORTANTE:
    - Seja rigoroso: só aceite se tiver certeza absoluta
    - Confidence < 0.7 = rejeitar automaticamente
    - Cite as fontes web na análise
    - Mantenha tom educativo e respeitoso";

        // Buscar prompt customizado
        $prompt = $this->getCustomPrompt('challenger', $defaultPrompt);
        
        // Substituir variáveis
        $prompt = $this->replacePromptVariables($prompt, [
            'statement' => $question['statement'],
            'current_answer' => $question['correct_answer'] ? 'CERTO' : 'ERRADO',
            'explanation' => $question['explanation'],
            'key_concept' => $question['key_concept'],
            'user_argument' => $userArgument,
            'web_sources' => $webContext
        ]);
        
        return $prompt;
    }
}