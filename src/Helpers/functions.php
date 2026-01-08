<?php
/**
 * FUNCTIONS.PHP - Funções Auxiliares Globais
 * 
 * Centraliza funções auxiliares que eram duplicadas em vários arquivos.
 * Carregado automaticamente pelo bootstrap.php
 */

// =====================================
// CONFIGURAÇÕES (Substitui getConfig())
// =====================================

/**
 * Obtém valor de configuração usando dot notation
 * 
 * @param string $key Chave da config (ex: 'database.mysql.host')
 * @param mixed $default Valor padrão se não encontrar
 * @return mixed
 */
function config($key, $default = null) {
    return Config::get($key, $default);
}

/**
 * Obtém variável de ambiente
 * 
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// =====================================
// SESSÃO E AUTENTICAÇÃO
// =====================================

/**
 * Obtém ID do usuário logado
 * 
 * @return int|null
 */
function user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtém nome do usuário logado
 * 
 * @return string
 */
function user_name() {
    return $_SESSION['user_name'] ?? 'Usuário';
}

/**
 * Obtém email do usuário logado
 * 
 * @return string
 */
function user_email() {
    return $_SESSION['user_email'] ?? '';
}

/**
 * Verifica se usuário está autenticado
 * 
 * @return bool
 */
function is_authenticated() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

/**
 * Verifica se usuário é admin
 * 
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// =====================================
// PATHS E URLS
// =====================================

/**
 * Retorna caminho completo para arquivo em storage
 * 
 * @param string $path
 * @return string
 */
function storage_path($path = '') {
    return STORAGE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Retorna caminho público
 * 
 * @param string $path
 * @return string
 */
function public_path($path = '') {
    return PUBLIC_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Gera URL base da aplicação
 * 
 * @param string $path
 * @return string
 */
function url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    
    $base = $protocol . '://' . $host . $scriptName;
    
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Gera URL para asset (CSS, JS, imagens)
 * 
 * @param string $path
 * @return string
 */
function asset($path) {
    return url('assets/' . ltrim($path, '/'));
}

// =====================================
// REDIRECIONAMENTO
// =====================================

/**
 * Redireciona para URL
 * 
 * @param string $url
 * @param int $code
 */
function redirect($url, $code = 302) {
    header("Location: $url", true, $code);
    exit;
}

/**
 * Redireciona de volta para página anterior
 */
function redirect_back() {
    $referer = $_SERVER['HTTP_REFERER'] ?? url();
    redirect($referer);
}

// =====================================
// SANITIZAÇÃO E VALIDAÇÃO
// =====================================

/**
 * Escapa HTML para exibição segura
 * 
 * @param string $value
 * @return string
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Valida email
 * 
 * @param string $email
 * @return bool
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Limpa string
 * 
 * @param string $value
 * @return string
 */
function clean_string($value) {
    return trim(strip_tags($value));
}

/**
 * Gera slug a partir de string
 * 
 * @param string $text
 * @return string
 */
function slugify($text) {
    // Substitui acentos
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    
    // Remove caracteres especiais
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    
    // Remove hífens duplicados
    $text = preg_replace('/-+/', '-', $text);
    
    // Remove hífens do início e fim
    $text = trim($text, '-');
    
    return strtolower($text);
}

// =====================================
// FORMATAÇÃO
// =====================================

/**
 * Formata data para exibição
 * 
 * @param string $date
 * @param string $format
 * @return string
 */
function format_date($date, $format = 'd/m/Y H:i') {
    if (empty($date)) {
        return '-';
    }
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Formata segundos para tempo legível
 * 
 * @param int $seconds
 * @return string
 */
function format_time($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return sprintf('%dh %02dmin', $hours, $minutes);
    }
    
    return sprintf('%dmin', $minutes);
}

/**
 * Formata bytes para tamanho legível
 * 
 * @param int $bytes
 * @param int $precision
 * @return string
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Formata porcentagem
 * 
 * @param float $value
 * @param int $decimals
 * @return string
 */
function format_percentage($value, $decimals = 1) {
    return number_format($value, $decimals, ',', '.') . '%';
}

// =====================================
// JSON
// =====================================

/**
 * Retorna resposta JSON
 * 
 * @param mixed $data
 * @param int $code
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Retorna erro JSON
 * 
 * @param string $message
 * @param int $code
 */
function json_error($message, $code = 400) {
    json_response([
        'success' => false,
        'error' => $message
    ], $code);
}

/**
 * Retorna sucesso JSON
 * 
 * @param mixed $data
 */
function json_success($data = []) {
    json_response([
        'success' => true,
        'data' => $data
    ]);
}

// =====================================
// LOGGING
// =====================================

/**
 * Registra mensagem em log
 * 
 * @param string $message
 * @param string $level (debug, info, warning, error)
 * @param string $channel
 */
function log_message($message, $level = 'info', $channel = 'app') {
    $logFile = config("logging.channels.$channel", storage_path('logs/app.log'));
    
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    error_log($formattedMessage, 3, $logFile);
}

/**
 * Log de debug (apenas em modo debug)
 * 
 * @param mixed $data
 * @param string $label
 */
function debug_log($data, $label = 'DEBUG') {
    if (!config('debug')) {
        return;
    }
    
    $message = $label . ': ' . print_r($data, true);
    log_message($message, 'debug');
}

// =====================================
// ARRAYS E OBJETOS
// =====================================

/**
 * Obtém valor de array usando dot notation
 * 
 * @param array $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function array_get($array, $key, $default = null) {
    if (is_null($key)) {
        return $array;
    }
    
    if (isset($array[$key])) {
        return $array[$key];
    }
    
    foreach (explode('.', $key) as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return $default;
        }
        
        $array = $array[$segment];
    }
    
    return $array;
}

/**
 * Verifica se array é associativo
 * 
 * @param array $array
 * @return bool
 */
function is_assoc_array($array) {
    if (!is_array($array) || empty($array)) {
        return false;
    }
    
    return array_keys($array) !== range(0, count($array) - 1);
}

// =====================================
// STRINGS (PHP 8.0+ Compatible)
// =====================================

// str_starts_with e str_ends_with já são funções nativas do PHP 8.0+
// Definimos apenas se não existirem (para compatibilidade com PHP < 8.0)

if (!function_exists('str_starts_with')) {
    /**
     * Verifica se string começa com substring (PHP < 8.0)
     * 
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function str_starts_with($haystack, $needle) {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * Verifica se string termina com substring (PHP < 8.0)
     * 
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    function str_ends_with($haystack, $needle) {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * Trunca string com ellipsis
 * 
 * @param string $text
 * @param int $length
 * @param string $ellipsis
 * @return string
 */
function str_limit($text, $length = 100, $ellipsis = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $ellipsis;
}

// =====================================
// CSRF PROTECTION
// =====================================

/**
 * Gera token CSRF
 * 
 * @return string
 */
function csrf_token() {
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['_csrf_token'];
}

/**
 * Gera campo hidden com token CSRF
 * 
 * @return string
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Valida token CSRF
 * 
 * @param string $token
 * @return bool
 */
function csrf_verify($token) {
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

// =====================================
// VIEWS
// =====================================

/**
 * Renderiza view
 * 
 * @param string $view Nome da view (ex: 'auth/login')
 * @param array $data Dados para a view
 * @param string $layout Layout a ser usado
 */
function view($view, $data = [], $layout = 'main') {
    // Extrai variáveis para o escopo da view
    extract($data);
    
    // Inicia buffer
    ob_start();
    
    // Inclui a view
    $viewPath = SRC_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';
    
    if (!file_exists($viewPath)) {
        throw new Exception("View não encontrada: $view");
    }
    
    require $viewPath;
    
    // Obtém conteúdo da view
    $content = ob_get_clean();
    
    // Se não tem layout, retorna direto
    if ($layout === null) {
        echo $content;
        return;
    }
    
    // Inclui layout
    $layoutPath = SRC_PATH . '/Views/layouts/' . $layout . '.php';
    
    if (!file_exists($layoutPath)) {
        throw new Exception("Layout não encontrado: $layout");
    }
    
    require $layoutPath;
}

// =====================================
// MENSAGENS FLASH
// =====================================

/**
 * Define mensagem flash
 * 
 * @param string $key
 * @param mixed $value
 */
function flash($key, $value = null) {
    if ($value === null) {
        // Getter
        $flash = $_SESSION["_flash_$key"] ?? null;
        unset($_SESSION["_flash_$key"]);
        return $flash;
    }
    
    // Setter
    $_SESSION["_flash_$key"] = $value;
}

/**
 * Define mensagem de sucesso
 * 
 * @param string $message
 */
function flash_success($message) {
    flash('success', $message);
}

/**
 * Define mensagem de erro
 * 
 * @param string $message
 */
function flash_error($message) {
    flash('error', $message);
}

// =====================================
// UTILITÁRIOS
// =====================================

/**
 * Dump e die
 * 
 * @param mixed ...$vars
 */
function dd(...$vars) {
    echo '<pre style="background:#f5f5f5; padding:15px; border:2px solid #ddd; border-radius:5px; margin:10px;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    die(1);
}

/**
 * Dump variável
 * 
 * @param mixed $var
 */
function dump($var) {
    echo '<pre style="background:#f5f5f5; padding:15px; border:2px solid #ddd; border-radius:5px; margin:10px;">';
    var_dump($var);
    echo '</pre>';
}