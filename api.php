<?php
/**
 * ARQUIVO 3 de 4: api.php
 * 
 * Salve este arquivo como: api.php
 */

require_once 'config.php';

class AnthropicAPI {
    
    public function extractPDFText($base64Data) {
        $data = [
            'model' => ANTHROPIC_MODEL,
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
    }
    
    public function analyzeContent($content) {
        // Limita o conteúdo para não exceder tokens
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

        $data = [
            'model' => ANTHROPIC_MODEL,
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        return $this->makeRequest($data);
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

        $data = [
            'model' => ANTHROPIC_MODEL,
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        return $this->makeRequest($data);
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
        
        // Limita o conteúdo do PDF
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

        $data = [
            'model' => ANTHROPIC_MODEL,
            'max_tokens' => 1500,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        return $this->makeRequest($data);
    }
    
    private function makeRequest($data) {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 120,
            // Corrige problema SSL no Windows
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CAINFO => $this->getCacertPath()
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            // Não fecha mais curl_close() - deprecated no PHP 8.5+
            throw new Exception("Erro cURL: {$error}");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() removido - não é mais necessário no PHP 8.0+
        
        if ($httpCode !== 200) {
            throw new Exception("Erro na API: HTTP {$httpCode} - {$response}");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['content'][0]['text'])) {
            throw new Exception("Resposta inválida da API: " . print_r($result, true));
        }
        
        return $result['content'][0]['text'];
    }
    
    private function getCacertPath() {
        // Verifica se existe certificado CA no sistema
        $possiblePaths = [
            __DIR__ . '/cacert.pem', // Pasta do projeto
            'C:/laragon/bin/php/cacert.pem', // Laragon
            '/etc/ssl/certs/ca-certificates.crt', // Linux
            '/etc/ssl/certs/ca-bundle.crt', // CentOS/RHEL
            '/usr/local/share/certs/ca-root-nss.crt', // FreeBSD
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Se não encontrou, retorna null (usará o padrão do sistema)
        return null;
    }
}
?>