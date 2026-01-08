<?php
/**
 * AISERVICE.PHP - Orquestrador de Provedores de IA
 * 
 * Gerencia qual provider usar e facilita troca entre eles
 * Substitui a lógica em api.php
 */

namespace App\Services;

use App\Providers\AIProviderInterface;
use App\Providers\AnthropicProvider;
use App\Providers\OpenAIProvider;
use App\Providers\DeepSeekProvider;
use App\Providers\OllamaProvider;

class AIService {
    private $provider;
    private $providerName;
    
    /**
     * @param string|null $providerName Nome do provider ou usa o da sessão/config
     */
    public function __construct($providerName = null) {
        $this->providerName = $providerName ?? $this->getCurrentProvider();
        $this->provider = $this->createProvider($this->providerName);
    }
    
    /**
     * Obtém provider atual da sessão ou config
     * 
     * @return string
     */
    private function getCurrentProvider() {
        return $_SESSION['ai_provider'] ?? config('ai.default_provider');
    }
    
    /**
     * Define provider na sessão
     * 
     * @param string $providerName
     */
    public function setProvider($providerName) {
        $this->providerName = $providerName;
        $this->provider = $this->createProvider($providerName);
        $_SESSION['ai_provider'] = $providerName;
    }
    
    /**
     * Cria instância do provider
     * 
     * @param string $providerName
     * @return AIProviderInterface
     * @throws \Exception
     */
    private function createProvider($providerName) {
        switch ($providerName) {
            case 'anthropic':
                return new AnthropicProvider();
                
            case 'openai':
                return new OpenAIProvider();
                
            case 'deepseek':
                return new DeepSeekProvider();
                
            case 'ollama':
                return new OllamaProvider();
                
            default:
                throw new \Exception("Provider desconhecido: $providerName");
        }
    }
    
    /**
     * Obtém provider atual
     * 
     * @return AIProviderInterface
     */
    public function getProvider() {
        return $this->provider;
    }
    
    /**
     * Obtém nome do provider atual
     * 
     * @return string
     */
    public function getProviderName() {
        return $this->providerName;
    }
    
    /**
     * Verifica se provider suporta extração de PDF
     * 
     * @return bool
     */
    public function supportsPDF() {
        return $this->providerName === 'anthropic';
    }
    
    /**
     * Extrai texto de PDF
     * 
     * @param string $base64Data
     * @return string
     * @throws \Exception
     */
    public function extractPDFText($base64Data) {
        if (!$this->supportsPDF()) {
            throw new \Exception("Provider '{$this->provider->getName()}' não suporta extração de PDF. Use Anthropic Claude ou a opção 'Resumo Pronto'.");
        }
        
        return $this->provider->extractPDFText($base64Data);
    }
    
    /**
     * Analisa conteúdo e identifica tópicos essenciais
     * 
     * @param string $content
     * @return array
     */
    public function analyzeContent($content) {
        return $this->provider->analyzeContent($content);
    }
    
    /**
     * Processa resumo pré-formatado
     * 
     * @param string $summaryText
     * @return array
     */
    public function processPreSummarized($summaryText) {
        return $this->provider->processPreSummarized($summaryText);
    }
    
    /**
     * Gera questão baseada no conteúdo
     * 
     * @param string $pdfContent
     * @param array $topic
     * @param int $difficulty
     * @param bool $isWeakPoint
     * @return array
     */
    public function generateQuestion($pdfContent, $topic, $difficulty, $isWeakPoint = false) {
        return $this->provider->generateQuestion($pdfContent, $topic, $difficulty, $isWeakPoint);
    }
    
    /**
     * Envia mensagem direta
     * 
     * @param string $prompt
     * @param array $options
     * @return string
     */
    public function sendMessage($prompt, $options = []) {
        return $this->provider->sendMessage($prompt, $options);
    }
    
    /**
     * Obtém lista de providers disponíveis
     * 
     * @return array ['provider_key' => 'Provider Name']
     */
    public static function getAvailableProviders() {
        $providers = [];
        $configs = config('ai.providers');
        
        foreach ($configs as $key => $config) {
            if ($config['enabled']) {
                $providers[$key] = $config['name'];
            }
        }
        
        return $providers;
    }
    
    /**
     * Obtém informações do provider atual
     * 
     * @return array
     */
    public function getProviderInfo() {
        return [
            'name' => $this->provider->getName(),
            'model' => $this->provider->getModel(),
            'supports_pdf' => $this->supportsPDF(),
            'available' => $this->provider->isAvailable()
        ];
    }
    
    /**
     * Testa conexão com o provider
     * 
     * @return bool
     */
    public function testConnection() {
        try {
            $this->provider->sendMessage("Test", ['max_tokens' => 10]);
            return true;
        } catch (\Exception $e) {
            log_message("Teste de conexão falhou: " . $e->getMessage(), 'error', 'api');
            return false;
        }
    }
}