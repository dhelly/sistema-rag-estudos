<?php
/**
 * BASEPROVIDER.PHP - Classe Base para Providers
 * 
 * Implementa lógica comum a todos os providers
 */

namespace App\Providers;

abstract class BaseProvider implements AIProviderInterface {
    protected $config;
    protected $apiKey;
    protected $model;
    protected $endpoint;
    protected $name;
    
    public function __construct($providerName) {
        $this->config = config("ai.providers.$providerName");
        
        if (!$this->config) {
            throw new \Exception("Provedor '$providerName' não configurado");
        }
        
        $this->apiKey = $this->config['api_key'];
        $this->model = $this->config['model'];
        $this->endpoint = $this->config['endpoint'];
        $this->name = $this->config['name'];
        
        if (!$this->config['enabled']) {
            throw new \Exception("Provedor '{$this->name}' não está disponível. Configure a API key no .env");
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName() {
        return $this->name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAvailable() {
        return $this->config['enabled'];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Limpa JSON de markdown
     * 
     * @param string $response
     * @return string
     */
    protected function cleanJsonResponse($response) {
        return preg_replace('/```json|```/', '', $response);
    }
    
    /**
     * Parse JSON response
     * 
     * @param string $response
     * @return array
     * @throws \Exception
     */
    protected function parseJsonResponse($response) {
        $clean = trim($this->cleanJsonResponse($response));
        $data = json_decode($clean, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Erro ao decodificar JSON: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Obtém caminho do cacert
     * 
     * @return string|null
     */
    protected function getCacertPath() {
        return config('security.cacert_path');
    }
    
    /**
     * Valida resposta de análise
     * 
     * @param array $data
     * @throws \Exception
     */
    protected function validateAnalysisResponse($data) {
        if (!isset($data['coreTopics'])) {
            throw new \Exception("Resposta inválida: 'coreTopics' não encontrado");
        }
        
        if (!is_array($data['coreTopics'])) {
            throw new \Exception("Resposta inválida: 'coreTopics' deve ser array");
        }
    }
    
    /**
     * Valida resposta de questão
     * 
     * @param array $data
     * @throws \Exception
     */
    protected function validateQuestionResponse($data) {
        $required = ['statement', 'correctAnswer', 'topicId', 'explanation', 'keyConceptTested'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Resposta inválida: campo '$field' não encontrado");
            }
        }
    }
    
    /**
     * Loga chamada da API
     * 
     * @param string $method
     * @param array $params
     */
    protected function logApiCall($method, $params = []) {
        if (config('debug')) {
            $message = sprintf(
                "API Call: %s -> %s | Model: %s",
                $this->name,
                $method,
                $this->model
            );
            
            log_message($message, 'debug', 'api');
        }
    }
    
    /**
     * Loga erro da API
     * 
     * @param string $method
     * @param \Exception $e
     */
    protected function logApiError($method, $e) {
        $message = sprintf(
            "API Error: %s -> %s | Error: %s",
            $this->name,
            $method,
            $e->getMessage()
        );
        
        log_message($message, 'error', 'api');
    }
    
    /**
     * Monta opções cURL padrão
     * 
     * @return array
     */
    protected function getDefaultCurlOptions() {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ];
        
        // Verifica SSL apenas em produção
        if (config('security.ssl_verify')) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            
            $cacert = $this->getCacertPath();
            if ($cacert) {
                $options[CURLOPT_CAINFO] = $cacert;
            }
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        
        return $options;
    }
    
    /**
     * Trata erro de cURL
     * 
     * @param resource $ch
     * @throws \Exception
     */
    protected function handleCurlError($ch) {
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Erro cURL: $error");
        }
    }
    
    /**
     * Trata resposta HTTP
     * 
     * @param resource $ch
     * @param string $response
     * @throws \Exception
     */
    protected function handleHttpResponse($ch, $response) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Erro na API {$this->name}: HTTP $httpCode - $response");
        }
    }
    
    /**
     * Método padrão para extractPDFText
     * (maioria dos providers não suporta)
     * 
     * @throws \Exception
     */
    public function extractPDFText($base64Data) {
        throw new \Exception("Provider '{$this->name}' não suporta extração direta de PDF. Use a opção 'Resumo Pronto (80/20)'.");
    }
}