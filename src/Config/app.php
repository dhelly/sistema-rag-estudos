<?php
/**
 * APP.PHP - Configurações da Aplicação
 * 
 * Centraliza TODAS as configurações em um único lugar.
 * Substitui as múltiplas chamadas getConfig() espalhadas pelo código.
 */

// =====================================
// FUNÇÕES AUXILIARES PARA CONFIGURAÇÃO
// =====================================

/**
 * Verifica se está em modo debug
 */
function config_isDebug() {
    return ($_ENV['DEBUG_MODE'] ?? 'false') === 'true';
}

/**
 * Converte string de tamanho para bytes
 */
function config_parseSize($size) {
    $unit = strtoupper(substr($size, -1));
    $value = (int) substr($size, 0, -1);
    
    switch ($unit) {
        case 'G': return $value * 1024 * 1024 * 1024;
        case 'M': return $value * 1024 * 1024;
        case 'K': return $value * 1024;
        default: return (int) $size;
    }
}

/**
 * Encontra arquivo cacert.pem
 */
function config_findCacert() {
    $paths = [
        ROOT_PATH . '/cacert.pem',
        'C:/laragon/bin/php/cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/certs/ca-bundle.crt',
        '/usr/local/share/certs/ca-root-nss.crt',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

return [
    // =====================================
    // INFORMAÇÕES DA APLICAÇÃO
    // =====================================
    'name' => 'Sistema RAG de Estudos Inteligente',
    'version' => '3.0.0',
    'environment' => $_ENV['APP_ENV'] ?? 'production', // development, production
    'debug' => config_isDebug(),
    'timezone' => $_ENV['TIMEZONE'] ?? 'America/Sao_Paulo',
    
    // =====================================
    // BANCO DE DADOS
    // =====================================
    'database' => [
        'type' => $_ENV['DB_TYPE'] ?? 'mysql',
        
        // MySQL
        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_NAME'] ?? 'sistema_rag',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
        
        // SQLite
        'sqlite' => [
            'database' => ROOT_PATH . '/' . ($_ENV['DB_FILE'] ?? 'study_system.db'),
        ],
    ],
    
    // =====================================
    // SISTEMA DE USUÁRIOS
    // =====================================
    'auth' => [
        'allow_registration' => ($_ENV['ALLOW_REGISTRATION'] ?? 'true') === 'true',
        'session_timeout' => (int)($_ENV['SESSION_TIMEOUT'] ?? 3600), // segundos
        'password_min_length' => 6,
        'name_min_length' => 3,
        
        'admin' => [
            'email' => $_ENV['ADMIN_EMAIL'] ?? 'admin@exemplo.com',
            'password' => $_ENV['ADMIN_PASSWORD'] ?? 'admin123',
            'name' => 'Administrador',
        ],
    ],
    
    // =====================================
    // PROVEDORES DE IA
    // =====================================
    'ai' => [
        'default_provider' => $_ENV['DEFAULT_AI_PROVIDER'] ?? 'anthropic',
        
        'providers' => [
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'api_key' => $_ENV['ANTHROPIC_API_KEY'] ?? null,
                'model' => $_ENV['ANTHROPIC_MODEL'] ?? 'claude-sonnet-4-20250514',
                'endpoint' => 'https://api.anthropic.com/v1/messages',
                'enabled' => !empty($_ENV['ANTHROPIC_API_KEY']),
            ],
            
            'openai' => [
                'name' => 'OpenAI GPT',
                'api_key' => $_ENV['OPENAI_API_KEY'] ?? null,
                'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'enabled' => !empty($_ENV['OPENAI_API_KEY']),
            ],
            
            'deepseek' => [
                'name' => 'DeepSeek',
                'api_key' => $_ENV['DEEPSEEK_API_KEY'] ?? null,
                'model' => $_ENV['DEEPSEEK_MODEL'] ?? 'deepseek-chat',
                'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
                'enabled' => !empty($_ENV['DEEPSEEK_API_KEY']),
            ],
            
            'ollama' => [
                'name' => 'Ollama (Local)',
                'api_key' => null,
                'model' => $_ENV['OLLAMA_MODEL'] ?? 'llama3.2',
                'endpoint' => ($_ENV['OLLAMA_BASE_URL'] ?? 'http://localhost:11434') . '/api/generate',
                'enabled' => true, // Sempre disponível se instalado
            ],
        ],
    ],
    
    // =====================================
    // SISTEMA DE QUESTIONAMENTO
    // =====================================
    'challenges' => [
        'enabled' => ($_ENV['ALLOW_QUESTION_CHALLENGE'] ?? 'true') === 'true',
        'max_per_question' => (int)($_ENV['MAX_CHALLENGES_PER_QUESTION'] ?? 3),
        
        'tavily' => [
            'api_key' => $_ENV['TAVILY_API_KEY'] ?? null,
            'search_depth' => $_ENV['TAVILY_SEARCH_DEPTH'] ?? 'basic', // basic, advanced
            'max_results' => (int)($_ENV['TAVILY_MAX_RESULTS'] ?? 5),
            'enabled' => !empty($_ENV['TAVILY_API_KEY']),
        ],
    ],
    
    // =====================================
    // UPLOAD E ARMAZENAMENTO
    // =====================================
    'storage' => [
        'uploads_dir' => STORAGE_PATH . '/uploads',
        'reports_dir' => STORAGE_PATH . '/reports',
        'logs_dir' => STORAGE_PATH . '/logs',
        'cache_dir' => STORAGE_PATH . '/cache',
        'anki_export_dir' => STORAGE_PATH . '/exports/anki',
        
        'max_file_size' => $_ENV['MAX_FILE_SIZE'] ?? '50M',
        'max_file_size_bytes' => config_parseSize($_ENV['MAX_FILE_SIZE'] ?? '50M'),
        
        'allowed_mimetypes' => [
            'application/pdf',
        ],
    ],
    
    // =====================================
    // RELATÓRIOS PDF
    // =====================================
    'reports' => [
        'logo_path' => PUBLIC_PATH . '/assets/images/logo.png',
        'enable_html_reports' => true, // HTML otimizado para impressão
        'enable_pdf_generation' => false, // Requer biblioteca externa
    ],
    
    // =====================================
    // SEGURANÇA
    // =====================================
    'security' => [
        'ssl_verify' => !config_isDebug(), // Desativa verificação SSL em dev
        'cacert_path' => $_ENV['CACERT_PATH'] ?? config_findCacert(),
        
        'rate_limiting' => [
            'enabled' => true,
            'max_requests' => 100,
            'window_seconds' => 3600, // 1 hora
        ],
        
        'csrf' => [
            'enabled' => true,
            'token_name' => '_csrf_token',
        ],
    ],
    
    // =====================================
    // CACHE
    // =====================================
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // file, redis, memcached
        'ttl' => 3600, // 1 hora
        'prefix' => 'rag_',
    ],
    
    // =====================================
    // LOGGING
    // =====================================
    'logging' => [
        'enabled' => true,
        'level' => config_isDebug() ? 'debug' : 'error', // debug, info, warning, error
        'max_files' => 30, // Dias de logs
        'channels' => [
            'app' => STORAGE_PATH . '/logs/app.log',
            'errors' => STORAGE_PATH . '/logs/errors.log',
            'exceptions' => STORAGE_PATH . '/logs/exceptions.log',
            'queries' => STORAGE_PATH . '/logs/queries.log',
            'api' => STORAGE_PATH . '/logs/api.log',
        ],
    ],
    
    // =====================================
    // INTERNACIONALIZAÇÃO
    // =====================================
    'i18n' => [
        'default_locale' => 'pt_BR',
        'available_locales' => ['pt_BR', 'en_US'],
        'fallback_locale' => 'pt_BR',
    ],
    
    // =====================================
    // FEATURES FLAGS
    // =====================================
    'features' => [
        'multi_user' => true,
        'admin_panel' => true,
        'challenge_system' => true,
        'discipline_prompts' => true,
        'reports_generation' => true,
        'anki_export' => false, // Futuro
    ],
];

// =====================================
// HELPER CLASS PARA ACESSAR CONFIGS
// =====================================
class Config {
    private static $config = null;
    
    /**
     * Carrega configurações
     */
    private static function load() {
        if (self::$config === null) {
            self::$config = $GLOBALS['app_config'];
        }
    }
    
    /**
     * Obtém configuração usando dot notation
     * Exemplo: Config::get('database.mysql.host')
     */
    public static function get($key, $default = null) {
        self::load();
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Verifica se configuração existe
     */
    public static function has($key) {
        return self::get($key) !== null;
    }
    
    /**
     * Obtém todas as configurações
     */
    public static function all() {
        self::load();
        return self::$config;
    }
    
    /**
     * Verifica se está em modo debug
     */
    public static function isDebug() {
        return config_isDebug();
    }
    
    /**
     * Converte string de tamanho para bytes
     */
    public static function parseSize($size) {
        return config_parseSize($size);
    }
    
    /**
     * Encontra arquivo cacert.pem
     */
    public static function findCacert() {
        return config_findCacert();
    }
}