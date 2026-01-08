<?php
/**
 * AUTHCONTROLLER.PHP - Controlador de Autenticação
 * 
 * Substitui login.php e register.php
 * Gerencia login, registro e logout
 */

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Middleware\AuthMiddleware;

class AuthController {
    private $userRepo;
    
    public function __construct() {
        $this->userRepo = new UserRepository();
    }
    
    /**
     * Mostra formulário de login
     */
    public function showLogin() {
        // Se já está logado, redireciona
        if (AuthMiddleware::check()) {
            redirect(url('index.php'));
        }
        
        view('auth/login', [
            'allow_registration' => config('auth.allow_registration')
        ]);
    }
    
    /**
     * Processa login
     */
    public function login() {
        try {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Valida campos
            if (empty($email) || empty($password)) {
                throw new \Exception("Email e senha são obrigatórios");
            }
            
            // Verifica credenciais
            $user = $this->userRepo->verifyCredentials($email, $password);
            
            if (!$user) {
                throw new \Exception("Email ou senha inválidos");
            }
            
            // Verifica se está ativo
            if (!$user->isActive()) {
                throw new \Exception("Sua conta ainda não foi ativada pelo administrador. Por favor, aguarde a aprovação.");
            }
            
            // Faz login
            AuthMiddleware::login($user);
            
            // Redireciona para home
            redirect(url('index.php'));
            
        } catch (\Exception $e) {
            // Retorna para login com erro
            flash_error($e->getMessage());
            redirect(url('login.php'));
        }
    }
    
    /**
     * Mostra formulário de registro
     */
    public function showRegister() {
        // Verifica se registro está habilitado
        if (!config('auth.allow_registration')) {
            flash_error("Registro de novos usuários está desabilitado.");
            redirect(url('login.php'));
        }
        
        // Se já está logado, redireciona
        if (AuthMiddleware::check()) {
            redirect(url('index.php'));
        }
        
        view('auth/register');
    }
    
    /**
     * Processa registro
     */
    public function register() {
        try {
            // Verifica se registro está habilitado
            if (!config('auth.allow_registration')) {
                throw new \Exception("Registro de novos usuários está desabilitado.");
            }
            
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Valida dados
            $errors = $this->userRepo->validate([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'confirm_password' => $confirmPassword
            ]);
            
            if (!empty($errors)) {
                throw new \Exception(implode('<br>', $errors));
            }
            
            // Cria usuário (inativo, aguardando aprovação)
            $user = $this->userRepo->createUser($email, $password, $name, false, false);
            
            // Mensagem de sucesso
            flash_success("Conta criada com sucesso! Aguarde a ativação pelo administrador para fazer login.");
            
            // Redireciona para login
            redirect(url('login.php'));
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
            redirect(url('register.php'));
        }
    }
    
    /**
     * Faz logout
     */
    public function logout() {
        AuthMiddleware::logoutUser();
        
        flash_success("Você saiu com sucesso.");
        redirect(url('login.php'));
    }
    
    /**
     * Renova sessão (para AJAX keep-alive)
     */
    public function renewSession() {
        if (!AuthMiddleware::check()) {
            json_error("Não autenticado", 401);
        }
        
        AuthMiddleware::renewSession();
        
        json_success([
            'time_remaining' => AuthMiddleware::sessionTimeRemaining(),
            'expires_at' => time() + AuthMiddleware::sessionTimeRemaining()
        ]);
    }
    
    /**
     * Verifica status da sessão (AJAX)
     */
    public function checkSession() {
        if (!AuthMiddleware::check()) {
            json_response([
                'authenticated' => false
            ]);
        }
        
        json_response([
            'authenticated' => true,
            'user_id' => user_id(),
            'user_name' => user_name(),
            'is_admin' => is_admin(),
            'time_remaining' => AuthMiddleware::sessionTimeRemaining(),
            'expiring_soon' => AuthMiddleware::sessionExpiringSoon()
        ]);
    }
}