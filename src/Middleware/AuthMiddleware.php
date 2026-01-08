<?php
/**
 * ADMINMIDDLEWARE.PHP - Middleware de Administrador
 * 
 * Substitui Auth::requireAdmin() 
 * Garante que apenas admins acessem rotas protegidas
 */

namespace App\Middleware;

class AdminMiddleware {
    
    /**
     * Executa middleware
     * 
     * @param callable $next
     * @return mixed
     */
    public function handle($next) {
        // Primeiro verifica se está logado
        AuthMiddleware::require();
        
        // Depois verifica se é admin
        if (!$this->isAdmin()) {
            $this->redirectToHome("Você não tem permissão para acessar esta área.");
            return;
        }
        
        // Continua para próximo middleware/controller
        return $next();
    }
    
    /**
     * Verifica se usuário é admin
     * 
     * @return bool
     */
    private function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
    
    /**
     * Redireciona para home
     * 
     * @param string|null $message
     */
    private function redirectToHome($message = null) {
        if ($message) {
            flash_error($message);
        }
        
        redirect(url('index.php'));
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
     * Verifica se usuário atual é admin (método estático)
     * 
     * @return bool
     */
    public static function check() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
    
    /**
     * Obtém lista de admins do sistema
     * 
     * @return array
     */
    public static function getAdmins() {
        return \App\Models\User::getAdmins();
    }
    
    /**
     * Conta admins ativos
     * 
     * @return int
     */
    public static function countAdmins() {
        return count(self::getAdmins());
    }
    
    /**
     * Verifica se é o único admin (não pode se remover)
     * 
     * @param int $userId
     * @return bool
     */
    public static function isOnlyAdmin($userId) {
        $admins = self::getAdmins();
        
        if (count($admins) !== 1) {
            return false;
        }
        
        return $admins[0]->id === $userId;
    }
}

/**
 * Helper global para compatibilidade
 */

if (!function_exists('is_admin')) {
    /**
     * Verifica se usuário atual é admin
     * 
     * @return bool
     */
    function is_admin() {
        return AdminMiddleware::check();
    }
}