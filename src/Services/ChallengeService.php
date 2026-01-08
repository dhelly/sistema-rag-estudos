<?php
/**
 * CHALLENGESERVICE.PHP - Sistema de Questionamento de Gabaritos
 * 
 * Salvar como: src/Services/ChallengeService.php
 * Substitui challenge_agent.php
 */

namespace App\Services;

use App\Repositories\QuestionRepository;

class ChallengeService {
    private $aiService;
    private $questionRepo;
    private $tavilyApiKey;
    
    public function __construct(AIService $aiService = null) {
        $this->aiService = $aiService ?? new AIService();
        $this->questionRepo = new QuestionRepository();
        $this->tavilyApiKey = config('challenges.tavily.api_key');
        
        if (!config('challenges.enabled')) {
            throw new \Exception("Sistema de questionamento está desabilitado");
        }
        
        if (empty($this->tavilyApiKey)) {
            throw new \Exception("Tavily API Key não configurada");
        }
    }
    
    /**
     * Processa questionamento de gabarito
     * 
     * @param int $questionId
     * @param int $userId
     * @param string $userArgument
     * @return array
     */
    public function processChallenge($questionId, $userId, $userArgument) {
        // Busca questão
        $question = $this->questionRepo->find($questionId);
        if (!$question) {
            throw new \Exception("Questão não encontrada");
        }
        
        // Verifica limite de questionamentos
        $maxChallenges = config('challenges.max_per_question');
        // Implementação simplificada - adicionar contagem no repository
        
        // Busca na web
        $webSources = $this->searchWeb($question->statement, $userArgument);
        
        // Analisa com IA
        $analysis = $this->analyzeWithAI($question, $userArgument, $webSources);
        
        // Processa decisão
        $result = $this->processDecision($analysis);
        
        // Salva no banco (implementar método no repository)
        // $this->saveChallengeRecord(...)
        
        // Se aceito, atualiza questão
        if ($result['decision'] === 'accepted') {
            $this->questionRepo->updateAfterChallenge(
                $questionId,
                $result['suggested_answer'],
                $result['updated_explanation']
            );
            
            // Recalcula progresso afetado
            $questionService = new QuestionGeneratorService();
            $questionService->recalculateAfterGabaritoChange($questionId);
        }
        
        return $result;
    }
    
    /**
     * Busca informações na web via Tavily
     * 
     * @param string $questionStatement
     * @param string $userArgument
     * @return array
     */
    private function searchWeb($questionStatement, $userArgument) {
        $searchQuery = $this->buildSearchQuery($questionStatement, $userArgument);
        
        $ch = curl_init('https://api.tavily.com/search');
        
        $data = [
            'api_key' => $this->tavilyApiKey,
            'query' => $searchQuery,
            'search_depth' => config('challenges.tavily.search_depth'),
            'max_results' => config('challenges.tavily.max_results'),
            'include_answer' => true,
            'include_raw_content' => false,
            'include_images' => false
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new \Exception("Erro ao buscar na web: " . curl_error($ch));
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Erro na API Tavily: HTTP $httpCode");
        }
        
        $result = json_decode($response, true);
        
        $sources = [];
        
        if (isset($result['results'])) {
            foreach ($result['results'] as $item) {
                $sources[] = [
                    'title' => $item['title'] ?? '',
                    'url' => $item['url'] ?? '',
                    'content' => $item['content'] ?? '',
                    'score' => $item['score'] ?? 0
                ];
            }
        }
        
        if (isset($result['answer'])) {
            $sources['tavily_answer'] = $result['answer'];
        }
        
        return $sources;
    }
    
    /**
     * Constrói query de busca
     */
    private function buildSearchQuery($questionStatement, $userArgument) {
        $question = preg_replace('/[^\w\s\-áéíóúâêôãõç]/ui', '', $questionStatement);
        $argument = preg_replace('/[^\w\s\-áéíóúâêôãõç]/ui', '', $userArgument);
        
        $question = substr($question, 0, 200);
        $argument = substr($argument, 0, 100);
        
        return trim($question . ' ' . $argument);
    }
    
    /**
     * Analisa com IA
     */
    private function analyzeWithAI($question, $userArgument, $webSources) {
        $webContext = $this->formatWebSources($webSources);
        
        $prompt = "Você é um Agente Questionador especializado em validar gabaritos CESPE.

QUESTÃO:
Afirmação: {$question->statement}
Gabarito: " . ($question->correct_answer ? 'CERTO' : 'ERRADO') . "
Explicação: {$question->explanation}

QUESTIONAMENTO DO ALUNO:
{$userArgument}

FONTES WEB:
{$webContext}

Retorne APENAS JSON:
{
  \"decision\": \"accepted\" ou \"rejected\",
  \"confidence\": 0.0 a 1.0,
  \"analysis\": \"análise detalhada\",
  \"reasoning\": \"raciocínio baseado nas fontes\",
  \"suggested_answer\": true ou false (se accepted),
  \"updated_explanation\": \"nova explicação\" (se accepted)
}

REGRA: Confidence < 0.7 = rejeitar automaticamente.";

        $response = $this->aiService->sendMessage($prompt);
        $clean = preg_replace('/```json|```/', '', $response);
        
        return json_decode(trim($clean), true);
    }
    
    /**
     * Formata fontes para prompt
     */
    private function formatWebSources($sources) {
        $formatted = "";
        
        if (isset($sources['tavily_answer'])) {
            $formatted .= "RESUMO: {$sources['tavily_answer']}\n\n";
        }
        
        $formatted .= "FONTES:\n";
        $index = 1;
        
        foreach ($sources as $key => $source) {
            if ($key === 'tavily_answer' || !is_array($source)) continue;
            
            $formatted .= "\n[{$index}] {$source['title']}\n";
            $formatted .= "URL: {$source['url']}\n";
            $formatted .= substr($source['content'], 0, 300) . "...\n";
            
            $index++;
        }
        
        return $formatted;
    }
    
    /**
     * Processa decisão
     */
    private function processDecision($analysis) {
        $confidence = $analysis['confidence'] ?? 0;
        
        if ($confidence < 0.7) {
            $analysis['decision'] = 'rejected';
        }
        
        return [
            'decision' => $analysis['decision'],
            'analysis' => $analysis['analysis'],
            'suggested_answer' => $analysis['suggested_answer'] ?? null,
            'updated_explanation' => $analysis['updated_explanation'] ?? null
        ];
    }
}