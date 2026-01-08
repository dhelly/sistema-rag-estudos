<?php
/**
 * AUTHMIDDLEWARE.PHP - Middleware de Autenticação
 * 
 * Substitui Auth::requireLogin() espalhado pelo código
 * Centraliza verificação de login e sessão
 */

namespace App\Middleware;

use App\Models\User;

class AuthMiddleware {
    
    /**
     * Executa middleware
     * 
     * @param callable $next
     * @return mixed
     */
    public function handle($next) {
        // Verifica se está logado
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
            return;
        }
        
        // Verifica se usuário está ativo
        if (!$this->isActive()) {
            $this->logout();
            $this->redirectToLogin("Sua conta foi desativada.");
            return;
        }
        
        // Verifica timeout da sessão
        if ($this->hasSessionExpired()) {
            $this->logout();
            $this->redirectToLogin("Sua sessão expirou. Faça login novamente.");
            return;
        }
        
        // Atualiza timestamp da última atividade
        $this->updateLastActivity();
        
        // Continua para próximo middleware/controller
        return $next();
    }
    
    /**
     * Verifica se usuário está autenticado
     * 
     * @return bool
     */
    private function isAuthenticated() {
        return isset($_SESSION['logged_in']) 
            && $_SESSION['logged_in'] === true 
            && isset($_SESSION['user_id']);
    }
    
    /**
     * Verifica se usuário está ativo no banco
     * 
     * @return bool
     */
    private function isActive() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $user = User::find($_SESSION['user_id']);
        
        if (!$user) {
            return false;
        }
        
        return $user->isActive();
    }
    
    /**
     * Verifica se sessão expirou
     * 
     * @return bool
     */
    private function hasSessionExpired() {
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
            return false;
        }
        
        $timeout = config('auth.session_timeout');
        $inactiveTime = time() - $_SESSION['last_activity'];
        
        return $inactiveTime > $timeout;
    }
    
    /**
     * Atualiza timestamp da última atividade
     */
    private function updateLastActivity() {
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Faz logout
     */
    private function logout() {
        session_unset();
        session_destroy();
    }
    
    /**
     * Redireciona para login
     * 
     * @param string|null $message
     */
    private function redirectToLogin($message = null) {
        if ($message) {
            flash_error($message);
        }
        
        redirect(url('login.php'));
    }
    
    /**
     * Método estático para uso direto (compatibilidade)
     * 
     * @return void
     */
    public static function require() {
        $middleware = new self();
        $middleware->handle(function() {
            // Não faz nada, apenas valida
        });
    }
    
    /**
     * Obtém usuário autenticado
     * 
     * @return User|null
     */
    public static function user() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return User::find($_SESSION['user_id']);
    }
    
    /**
     * Obtém ID do usuário autenticado
     * 
     * @return int|null
     */
    public static function userId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Verifica se usuário está logado (método estático)
     * 
     * @return bool
     */
    public static function check() {
        return isset($_SESSION['logged_in']) 
            && $_SESSION['logged_in'] === true 
            && isset($_SESSION['user_id']);
    }
    
    /**
     * Verifica se é guest (não logado)
     * 
     * @return bool
     */
    public static function guest() {
        return !self::check();
    }
    
    /**
     * Faz login do usuário
     * 
     * @param User $user
     * @return void
     */
    public static function login(User $user) {
        // Regenera ID da sessão por segurança
        session_regenerate_id(true);
        
        // Define variáveis de sessão
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['is_admin'] = $user->is_admin;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        
        // Atualiza último login no banco
        $user->updateLastLogin();
    }
    
    /**
     * Faz logout do usuário (método estático)
     * 
     * @return void
     */
    public static function logoutUser() {
        session_unset();
        session_destroy();
    }
    
    /**
     * Obtém duração da sessão formatada
     * 
     * @return string
     */
    public static function sessionDuration() {
        if (!isset($_SESSION['login_time'])) {
            return '00:00';
        }
        
        $duration = time() - $_SESSION['login_time'];
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        
        return sprintf('%02d:%02d', $hours, $minutes);
    }
    
    /**
     * Obtém tempo restante da sessão
     * 
     * @return int Segundos restantes
     */
    public static function sessionTimeRemaining() {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        
        $timeout = config('auth.session_timeout');
        $elapsed = time() - $_SESSION['last_activity'];
        $remaining = $timeout - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Verifica se sessão vai expirar em breve
     * 
     * @param int $threshold Segundos (padrão: 5 minutos)
     * @return bool
     */
    public static function sessionExpiringSoon($threshold = 300) {
        return self::sessionTimeRemaining() <= $threshold;
    }
    
    /**
     * Renova sessão (útil para AJAX)
     * 
     * @return void
     */
    public static function renewSession() {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Helpers globais para compatibilidade com código antigo
 */

if (!function_exists('auth')) {
    /**
     * Obtém instância do middleware de auth
     * 
     * @return AuthMiddleware
     */
    function auth() {
        return new AuthMiddleware();
    }
}

if (!function_exists('auth_user')) {
    /**
     * Obtém usuário autenticado
     * 
     * @return User|null
     */
    function auth_user() {
        return AuthMiddleware::user();
    }
}

if (!function_exists('auth_check')) {
    /**
     * Verifica se está autenticado
     * 
     * @return bool
     */
    function auth_check() {
        return AuthMiddleware::check();
    }
}

if (!function_exists('auth_guest')) {
    /**
     * Verifica se é guest
     * 
     * @return bool
     */
    function auth_guest() {
        return AuthMiddleware::guest();
    }
}