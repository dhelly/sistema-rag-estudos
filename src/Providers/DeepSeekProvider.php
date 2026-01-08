<?php
// =====================================
// DEEPSEEKKPROVIDER.PHP
// 
// Salvar como: src/Providers/DeepSeekProvider.php
// =====================================

namespace App\Providers;

/**
 * DeepSeek usa API compatível com OpenAI
 * Herda implementação do OpenAIProvider
 */
class DeepSeekProvider extends OpenAIProvider {
    
    public function __construct() {
        // Chama construtor do BaseProvider, não do OpenAI
        BaseProvider::__construct('deepseek');
    }
    
    // Todos os métodos são herdados de OpenAIProvider
    // Endpoint e modelo vêm da config
}