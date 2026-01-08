<?php
/**
 * INDEX.PHP - Front Controller
 * 
 * Ponto de entrada único da aplicação.
 * Todas as requisições são redirecionadas para este arquivo via .htaccess.
 */

// =====================================
// 1. INICIALIZAÇÃO DA APLICAÇÃO
// =====================================

// Carrega bootstrap
require_once __DIR__ . '/../bootstrap.php';

// =====================================
// 2. CONFIGURAÇÃO DE ROTAS
// =====================================

/**
 * Roteamento simples baseado em URI
 * 
 * Estrutura: /controller/action/params
 */
$routes = [
    // Rota raiz
    '/' => 'SessionController@dashboard',
    
    // Autenticação
    '/login' => 'AuthController@login',
    '/login/submit' => 'AuthController@loginSubmit',
    '/register' => 'AuthController@register',
    '/register/submit' => 'AuthController@registerSubmit',
    '/logout' => 'AuthController@logout',
    '/profile' => 'AuthController@profile',
    '/profile/update' => 'AuthController@updateProfile',
    '/change-password' => 'AuthController@changePassword',
    '/change-password/submit' => 'AuthController@changePasswordSubmit',
    
    // Dashboard e sessões
    '/dashboard' => 'SessionController@dashboard',
    '/sessions' => 'SessionController@index',
    '/sessions/create' => 'SessionController@create',
    '/sessions/store' => 'SessionController@store',
    '/sessions/(\d+)' => 'SessionController@show',
    '/sessions/(\d+)/edit' => 'SessionController@edit',
    '/sessions/(\d+)/update' => 'SessionController@update',
    '/sessions/(\d+)/delete' => 'SessionController@delete',
    '/sessions/(\d+)/report' => 'SessionController@report',
    
    // Questões e questionamentos
    '/questions' => 'QuestionController@index',
    '/questions/create' => 'QuestionController@create',
    '/questions/store' => 'QuestionController@store',
    '/questions/(\d+)' => 'QuestionController@show',
    '/questions/(\d+)/edit' => 'QuestionController@edit',
    '/questions/(\d+)/update' => 'QuestionController@update',
    '/questions/(\d+)/delete' => 'QuestionController@delete',
    '/questions/(\d+)/challenge' => 'QuestionController@challenge',
    '/questions/(\d+)/challenge/store' => 'QuestionController@storeChallenge',
    '/questions/(\d+)/answer' => 'QuestionController@answer',
    '/questions/(\d+)/rate' => 'QuestionController@rate',
    '/questions/search' => 'QuestionController@search',
    '/questions/export' => 'QuestionController@export',
    
    // Relatórios
    '/reports' => 'ReportController@index',
    '/reports/(\d+)' => 'ReportController@show',
    '/reports/(\d+)/download' => 'ReportController@download',
    '/reports/generate' => 'ReportController@generate',
    
    // Admin
    '/admin' => 'AdminController@dashboard',
    '/admin/users' => 'AdminController@users',
    '/admin/users/(\d+)/edit' => 'AdminController@editUser',
    '/admin/users/(\d+)/update' => 'AdminController@updateUser',
    '/admin/users/(\d+)/delete' => 'AdminController@deleteUser',
    '/admin/sessions' => 'AdminController@sessions',
    '/admin/questions' => 'AdminController@questions',
    '/admin/statistics' => 'AdminController@statistics',
    '/admin/settings' => 'AdminController@settings',
    '/admin/settings/update' => 'AdminController@updateSettings',
    
    // API (para AJAX)
    '/api/questions/(\d+)/similar' => 'QuestionController@similarQuestions',
    '/api/ai/test' => 'QuestionController@testAI',
    '/api/stats' => 'SessionController@stats',
];

// =====================================
// 3. OBTÉM URI DA REQUISIÇÃO
// =====================================

// Remove query string
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove base path se necessário
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

// Normaliza URI
$uri = rtrim($uri, '/');
if (empty($uri)) {
    $uri = '/';
}

// =====================================
// 4. MIDDLEWARE GLOBAL
// =====================================

// Verifica se está em modo manutenção
if (env('MAINTENANCE_MODE', 'false') === 'true' && !str_starts_with($uri, '/admin')) {
    http_response_code(503);
    echo view('errors.maintenance', [], null);
    exit;
}

// =====================================
// 5. ROTEAMENTO
// =====================================

$matched = false;
$params = [];

foreach ($routes as $pattern => $handler) {
    // Converte padrão para regex
    $regex = '#^' . $pattern . '$#';
    
    if (preg_match($regex, $uri, $matches)) {
        $matched = true;
        
        // Remove a correspondência completa (índice 0)
        array_shift($matches);
        $params = $matches;
        
        // Separa controller e action
        list($controllerName, $action) = explode('@', $handler);
        
        break;
    }
}

// =====================================
// 6. EXECUÇÃO DO CONTROLLER
// =====================================

if ($matched) {
    // Namespace completo do controller
    $controllerClass = 'App\\Controllers\\' . $controllerName;
    
    // Verifica se controller existe
    if (!class_exists($controllerClass)) {
        throw new Exception("Controller não encontrado: $controllerClass");
    }
    
    // Instancia controller
    $controller = new $controllerClass();
    
    // Verifica se action existe
    if (!method_exists($controller, $action)) {
        throw new Exception("Action não encontrada: $action em $controllerClass");
    }
    
    // Aplica middleware específico do controller
    $controller->applyMiddleware($action);
    
    // Executa action com parâmetros
    call_user_func_array([$controller, $action], $params);
    
} else {
    // 404 - Rota não encontrada
    http_response_code(404);
    echo view('errors.404', [], null);
}

// =====================================
// 7. FINALIZAÇÃO
// =====================================

// Se debug está ativo, mostra informações no final
if (config('debug') && php_sapi_name() !== 'cli') {
    echo "<!--\n";
    echo "DEBUG INFO:\n";
    echo "URI: $uri\n";
    echo "Matched: " . ($matched ? 'Yes' : 'No') . "\n";
    echo "Controller: " . ($controllerName ?? 'N/A') . "\n";
    echo "Action: " . ($action ?? 'N/A') . "\n";
    echo "Memory: " . format_bytes(memory_get_peak_usage()) . "\n";
    echo "Execution time: " . (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) . "s\n";
    echo "-->\n";
}