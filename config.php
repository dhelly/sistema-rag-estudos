<?php
/**
 * ARQUIVO 1 de 6: config.php (ATUALIZADO)
 * 
 * Salve este arquivo como: config.php
 */

// ============================================
// CARREGADOR DE ARQUIVO .env
// ============================================

function loadEnv($path = '.env') {
    if (!file_exists($path)) {
        die("ERRO: Arquivo .env não encontrado! Copie .env.example para .env e configure.");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora comentários
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse linha KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove aspas se existirem
            $value = trim($value, '"\'');
            
            // Define como constante e variável de ambiente
            if (!defined($key)) {
                define($key, $value);
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Carrega variáveis do .env
loadEnv(__DIR__ . '/.env');

// ============================================
// CONFIGURAÇÕES GERAIS
// ============================================

// Debug mode
if (DEBUG_MODE === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configurações de tempo e memória
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

// Configurações de upload
ini_set('upload_max_filesize', MAX_FILE_SIZE);
ini_set('post_max_size', MAX_FILE_SIZE);

// ============================================
// CRIAÇÃO DE DIRETÓRIOS
// ============================================

if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// ============================================
// VERIFICAÇÃO DE EXTENSÕES
// ============================================

if (!extension_loaded('sqlite3')) {
    die('ERRO: Extensão SQLite3 não está instalada. Instale com: sudo apt-get install php-sqlite3');
}

if (!extension_loaded('curl')) {
    die('ERRO: Extensão cURL não está instalada. Instale com: sudo apt-get install php-curl');
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

function isDebugMode() {
    return getConfig('DEBUG_MODE') === 'true';
}

function getCurrentProvider() {
    return $_SESSION['ai_provider'] ?? getConfig('DEFAULT_AI_PROVIDER', 'anthropic');
}

function setCurrentProvider($provider) {
    $_SESSION['ai_provider'] = $provider;
}

// ============================================
// CONFIGURAÇÕES DE PROVEDORES DE IA
// ============================================

function getProviderConfig($provider = null) {
    $provider = $provider ?? getCurrentProvider();
    
    $configs = [
        'anthropic' => [
            'name' => 'Anthropic Claude',
            'api_key' => getConfig('ANTHROPIC_API_KEY'),
            'model' => getConfig('ANTHROPIC_MODEL'),
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'available' => !empty(getConfig('ANTHROPIC_API_KEY'))
        ],
        'openai' => [
            'name' => 'OpenAI GPT',
            'api_key' => getConfig('OPENAI_API_KEY'),
            'model' => getConfig('OPENAI_MODEL'),
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'available' => !empty(getConfig('OPENAI_API_KEY'))
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'api_key' => getConfig('DEEPSEEK_API_KEY'),
            'model' => getConfig('DEEPSEEK_MODEL'),
            'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
            'available' => !empty(getConfig('DEEPSEEK_API_KEY'))
        ],
        'ollama' => [
            'name' => 'Ollama (Local)',
            'api_key' => null,
            'model' => getConfig('OLLAMA_MODEL'),
            'endpoint' => getConfig('OLLAMA_BASE_URL') . '/api/generate',
            'available' => true // Sempre disponível se instalado
        ]
    ];
    
    return $configs[$provider] ?? null;
}

function getAvailableProviders() {
    $providers = [];
    foreach (['anthropic', 'openai', 'deepseek', 'ollama'] as $provider) {
        $config = getProviderConfig($provider);
        if ($config['available']) {
            $providers[$provider] = $config['name'];
        }
    }
    return $providers;
}