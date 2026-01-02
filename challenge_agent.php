<?php
/**
 * CHALLENGE AGENT v2.1 - Agente Questionador de Gabaritos
 * 
 * Salve este arquivo como: challenge_agent.php
 * 
 * Usa Tavily Search API para verificar informações na web
 */

require_once 'config.php';
require_once 'database.php';
require_once 'api.php';

class ChallengeAgent {
    private $db;
    private $ai;
    private $tavilyApiKey;
    
    public function __construct() {
        $this->db = new Database();
        $this->ai = new UnifiedAI();
        $this->tavilyApiKey = getConfig('TAVILY_API_KEY');
        
        if (empty($this->tavilyApiKey)) {
            throw new Exception("TAVILY_API_KEY não configurada no .env");
        }
    }
    
    /**
     * Processa um questionamento de gabarito
     */
    public function processChallenge($questionId, $userId, $userArgument) {
        // Buscar questão original
        $question = $this->db->getQuestion($questionId);
        if (!$question) {
            throw new Exception("Questão não encontrada.");
        }
        
        // Verificar se já existem muitos questionamentos
        $challengeCount = $this->db->countQuestionChallenges($questionId);
        $maxChallenges = getConfig('MAX_CHALLENGES_PER_QUESTION', 3);
        
        if ($challengeCount >= $maxChallenges) {
            throw new Exception("Esta questão já atingiu o limite de questionamentos.");
        }
        
        // Criar registro do questionamento
        $challengeId = $this->db->createChallenge(
            $questionId,
            $userId,
            $userArgument,
            $question['correct_answer'],
            $question['explanation']
        );
        
        // ETAPA 1: Buscar informações na web com Tavily
        $webSources = $this->searchWebWithTavily($question['statement'], $userArgument);
        
        // ETAPA 2: Analisar com IA usando os dados da web
        $aiAnalysis = $this->analyzeWithAI($question, $userArgument, $webSources);
        
        // ETAPA 3: Decidir se aceita ou rejeita o questionamento
        $result = $this->makeDecision($aiAnalysis);
        
        // ETAPA 4: Atualizar o questionamento no banco
        $this->db->updateChallenge(
            $challengeId,
            $aiAnalysis['analysis'],
            $webSources,
            $result['decision'],
            $result['suggested_answer'] ?? null,
            $result['updated_explanation'] ?? null
        );
        
        // ETAPA 5: Se aceito, atualizar a questão
        if ($result['decision'] === 'accepted') {
            $this->db->updateQuestionAfterChallenge(
                $questionId,
                $result['suggested_answer'],
                $result['updated_explanation']
            );
            
            // Recalcular progresso de todos os usuários que responderam
            $this->recalculateAffectedUsers($questionId);
        }
        
        return [
            'challenge_id' => $challengeId,
            'decision' => $result['decision'],
            'analysis' => $aiAnalysis['analysis'],
            'web_sources' => $webSources,
            'updated_answer' => $result['suggested_answer'] ?? null,
            'updated_explanation' => $result['updated_explanation'] ?? null
        ];
    }
    
    /**
     * Busca informações na web usando Tavily
     */
    private function searchWebWithTavily($questionStatement, $userArgument) {
        $searchQuery = $this->buildSearchQuery($questionStatement, $userArgument);
        
        
        // 1. ENSURE VALID SEARCH_DEPTH
        $configDepth = getConfig('TAVILY_SEARCH_DEPTH', 'basic');
        $allowedDepth = ['basic', 'advanced', 'fast', 'ultra-fast'];
        $searchDepth = in_array($configDepth, $allowedDepth) ? $configDepth : 'basic';
        
        // 2. ENSURE VALID MAX_RESULTS (0-20)
        $maxResults = (int)getConfig('TAVILY_MAX_RESULTS', 5);
        if ($maxResults < 0 || $maxResults > 20) {
            $maxResults = 5; // Default to 5 if out of range[citation:2][citation:9]
        }
        
        $ch = curl_init('https://api.tavily.com/search');

        $data = [
            'api_key' => $this->tavilyApiKey,
            'query' => $searchQuery,
            'search_depth' => $searchDepth, // Use the validated value
            'max_results' => $maxResults,   // Use the validated value
            'include_answer' => true,
            'include_raw_content' => false,
            'include_images' => false
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            throw new Exception("Erro ao buscar na web: {$error}");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro na API Tavily: HTTP {$httpCode} - {$response}");
        }
        
        $result = json_decode($response, true);
        
        // Processar resultados
        $sources = [];
        
        if (isset($result['results'])) {
            foreach ($result['results'] as $item) {
                $sources[] = [
                    'title' => $item['title'] ?? 'Sem título',
                    'url' => $item['url'] ?? '',
                    'content' => $item['content'] ?? '',
                    'score' => $item['score'] ?? 0
                ];
            }
        }
        
        // Adicionar resposta resumida se disponível
        if (isset($result['answer'])) {
            $sources['tavily_answer'] = $result['answer'];
        }
        
        return $sources;
    }
    
    /**
     * Constrói query de busca otimizada
     */
    private function buildSearchQuery($questionStatement, $userArgument) {
        // Remove caracteres especiais e limita tamanho
        $question = preg_replace('/[^\w\s\-áéíóúâêôãõç]/ui', '', $questionStatement);
        $argument = preg_replace('/[^\w\s\-áéíóúâêôãõç]/ui', '', $userArgument);
        
        $question = substr($question, 0, 200);
        $argument = substr($argument, 0, 100);
        
        // Combina questão e argumento
        return "{$question} {$argument}";
    }
    
    /**
     * Analisa questionamento com IA usando dados da web
     */
    private function analyzeWithAI($question, $userArgument, $webSources) {
        // Preparar contexto com fontes web
        $webContext = $this->formatWebSourcesForAI($webSources);
        
        $prompt = "Você é um Agente Questionador especializado em validar gabaritos de questões estilo CESPE.

QUESTÃO ORIGINAL:
Afirmação: {$question['statement']}
Gabarito atual: " . ($question['correct_answer'] ? 'CERTO' : 'ERRADO') . "
Explicação atual: {$question['explanation']}
Conceito testado: {$question['key_concept']}

QUESTIONAMENTO DO ALUNO:
{$userArgument}

FONTES DA WEB (Tavily Search):
{$webContext}

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

        $response = $this->ai->sendMessage($prompt);
        
        // Limpar e parsear JSON
        $cleanJson = preg_replace('/```json|```/', '', $response);
        $analysis = json_decode(trim($cleanJson), true);
        
        if (!isset($analysis['decision'])) {
            throw new Exception("Erro ao processar análise da IA.");
        }
        
        return $analysis;
    }
    
    /**
     * Formata fontes web para prompt da IA
     */
    private function formatWebSourcesForAI($webSources) {
        $formatted = "";
        
        if (isset($webSources['tavily_answer'])) {
            $formatted .= "RESUMO TAVILY:\n{$webSources['tavily_answer']}\n\n";
        }
        
        $formatted .= "FONTES DETALHADAS:\n";
        $index = 1;
        
        foreach ($webSources as $key => $source) {
            if ($key === 'tavily_answer') continue;
            
            $formatted .= "\n[Fonte {$index}] {$source['title']}\n";
            $formatted .= "URL: {$source['url']}\n";
            $formatted .= "Conteúdo: " . substr($source['content'], 0, 500) . "...\n";
            $formatted .= "Relevância: " . round($source['score'] * 100) . "%\n";
            
            $index++;
        }
        
        return $formatted;
    }
    
    /**
     * Toma decisão final baseada na análise
     */
    private function makeDecision($aiAnalysis) {
        $decision = $aiAnalysis['decision'];
        $confidence = $aiAnalysis['confidence'] ?? 0;
        
        // Se confiança baixa, rejeitar automaticamente
        if ($confidence < 0.7) {
            $decision = 'rejected';
        }
        
        $result = [
            'decision' => $decision,
            'analysis' => $aiAnalysis['analysis']
        ];
        
        if ($decision === 'accepted') {
            $result['suggested_answer'] = $aiAnalysis['suggested_answer'];
            $result['updated_explanation'] = $aiAnalysis['updated_explanation'];
        }
        
        return $result;
    }
    
    /**
     * Recalcula progresso de usuários afetados
     */
    private function recalculateAffectedUsers($questionId) {
        // $question = $this->db->getQuestion($questionId);
        
        // Buscar todos os usuários que responderam esta questão
        // Buscar todos os usuários que responderam esta questão usando o novo método
        $affected = $this->db->getUsersAffectedByQuestion($questionId);
        // $stmt = $this->db->conn->prepare("
        //     SELECT DISTINCT user_id, session_id 
        //     FROM questions 
        //     WHERE id = ?
        // ");
        // $stmt->execute([$questionId]);
        // $affected = $stmt->fetchAll();
        
        foreach ($affected as $user) {
            $this->db->recalculateUserProgress($user['user_id'], $user['session_id']);
        }
    }
    
    /**
     * Obtém histórico de questionamentos de uma questão
     */
    // public function getChallengeHistory($questionId) {
    //     return $this->db->getQuestionChallenges($questionId);
    // }
    public function getChallengeHistory($questionId) {
        return $this->db->getQuestionChallengesFull($questionId);
    }
}