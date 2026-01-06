<?php
/**
 * BOOTSTRAP.PHP - Inicialização da Aplicação
 * 
 * Este arquivo é o ponto de partida de TODA a aplicação.
 * Deve ser incluído em TODOS os entry points (public/index.php, CLI, testes, etc)
 */

// Define caminho raiz da aplicação
define('ROOT_PATH', __DIR__);
define('SRC_PATH', ROOT_PATH . '/src');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// ===================================
// 1. AUTOLOADER (PSR-4)
// ===================================
spl_autoload_register(function ($class) {
    // Namespace base: App\
    $prefix = 'App\\';
    $base_dir = SRC_PATH . '/';
    
    // Verifica se a classe usa o namespace base
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Não é do nosso namespace
    }
    
    // Obtém o nome relativo da classe
    $relative_class = substr($class, $len);
    
    // Substitui namespace separators por directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Se o arquivo existe, inclui
    if (file_exists($file)) {
        require $file;
    }
});

// ===================================
// 2. CARREGAR VARIÁVEIS DE AMBIENTE
// ===================================
function loadEnv($path = ROOT_PATH . '/.env') {
    if (!file_exists($path)) {
        die("❌ ERRO CRÍTICO: Arquivo .env não encontrado!\nCopie .env.example para .env e configure.");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora comentários
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove aspas
            $value = trim($value, '"\'');
            
            // Define como variável de ambiente
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv();

// ===================================
// 3. CONFIGURAÇÕES PHP
// ===================================

// Debug mode
$debugMode = ($_ENV['DEBUG_MODE'] ?? 'false') === 'true';

if ($debugMode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Configurações de performance
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');

// Configurações de upload
$maxFileSize = $_ENV['MAX_FILE_SIZE'] ?? '50M';
ini_set('upload_max_filesize', $maxFileSize);
ini_set('post_max_size', $maxFileSize);

// Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'America/Sao_Paulo');

// ===================================
// 4. CRIAR DIRETÓRIOS NECESSÁRIOS
// ===================================
$directories = [
    STORAGE_PATH . '/uploads',
    STORAGE_PATH . '/reports',
    STORAGE_PATH . '/logs',
    STORAGE_PATH . '/cache',
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ===================================
// 5. INICIAR SESSÃO (se não for CLI)
// ===================================
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $sessionTimeout = (int)($_ENV['SESSION_TIMEOUT'] ?? 3600);
    
    ini_set('session.gc_maxlifetime', $sessionTimeout);
    ini_set('session.cookie_lifetime', $sessionTimeout);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Segurança adicional em produção
    if (!$debugMode) {
        ini_set('session.cookie_secure', 1); // Apenas HTTPS
    }
    
    session_start();
}

// ===================================
// 6. CARREGAR HELPERS GLOBAIS
// ===================================
require_once SRC_PATH . '/Helpers/functions.php';

// ===================================
// 7. ERROR HANDLER CUSTOMIZADO
// ===================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Não faz nada se error_reporting está desligado
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorType = 'ERROR';
    switch ($errno) {
        case E_WARNING:
        case E_USER_WARNING:
            $errorType = 'WARNING';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errorType = 'NOTICE';
            break;
    }
    
    $message = "[$errorType] $errstr in $errfile on line $errline";
    
    // Log para arquivo
    error_log($message, 3, STORAGE_PATH . '/logs/errors.log');
    
    // Se debug está ativo, mostra na tela
    if ($_ENV['DEBUG_MODE'] === 'true') {
        echo "<div style='background:#fee; border:2px solid #c33; padding:10px; margin:10px; border-radius:5px;'>";
        echo "<strong>$errorType:</strong> $errstr<br>";
        echo "<small>File: $errfile | Line: $errline</small>";
        echo "</div>";
    }
    
    return true;
});

// Exception handler
set_exception_handler(function($exception) {
    $message = sprintf(
        "[EXCEPTION] %s in %s:%d\nStack trace:\n%s",
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    
    // Log
    error_log($message, 3, STORAGE_PATH . '/logs/exceptions.log');
    
    // Response
    if ($_ENV['DEBUG_MODE'] === 'true') {
        echo "<pre style='background:#fff3cd; border:2px solid #856404; padding:15px; margin:10px;'>";
        echo "<strong>EXCEPTION:</strong> " . htmlspecialchars($exception->getMessage()) . "\n\n";
        echo "<strong>File:</strong> " . $exception->getFile() . "\n";
        echo "<strong>Line:</strong> " . $exception->getLine() . "\n\n";
        echo "<strong>Stack Trace:</strong>\n" . htmlspecialchars($exception->getTraceAsString());
        echo "</pre>";
    } else {
        http_response_code(500);
        echo "Ocorreu um erro interno. Por favor, tente novamente mais tarde.";
    }
    
    exit(1);
});

// ===================================
// 8. VERIFICAR EXTENSÕES OBRIGATÓRIAS
// ===================================
$requiredExtensions = ['pdo', 'curl', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die("❌ ERRO: Extensões PHP obrigatórias não encontradas: " . implode(', ', $missingExtensions));
}

// Verificar extensões de banco de dados
$dbType = $_ENV['DB_TYPE'] ?? 'mysql';
if ($dbType === 'mysql' && !extension_loaded('pdo_mysql')) {
    die("❌ ERRO: Extensão pdo_mysql não está instalada.");
}
if ($dbType === 'sqlite' && !extension_loaded('pdo_sqlite')) {
    die("❌ ERRO: Extensão pdo_sqlite não está instalada.");
}

// ===================================
// 9. CARREGAR CONFIGURAÇÕES DA APLICAÇÃO
// ===================================
// Essas serão criadas na próxima fase
$GLOBALS['app_config'] = require SRC_PATH . '/Config/app.php';

// ===================================
// ✅ APLICAÇÃO INICIALIZADA COM SUCESSO
// ===================================

// Em modo debug, mostrar que bootstrap foi carregado
if ($debugMode && php_sapi_name() !== 'cli') {
    error_log("✅ Bootstrap carregado com sucesso", 3, STORAGE_PATH . '/logs/app.log');
}