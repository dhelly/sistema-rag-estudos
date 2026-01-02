<?php
/**
 * AUTH v2.1 - Sistema Multi-usuário
 * 
 * Salve este arquivo como: auth.php
 */

require_once 'config.php';
require_once 'database.php';

class Auth {
    
    private static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function checkLogin() {
        self::startSession();
        
        // Verifica se está logado
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Verifica se user_id existe
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verifica timeout da sessão
        if (isset($_SESSION['last_activity'])) {
            $timeout = getConfig('SESSION_TIMEOUT', 3600);
            if (time() - $_SESSION['last_activity'] > $timeout) {
                self::logout();
                return false;
            }
        }
        
        // Atualiza última atividade
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function login($email, $password) {
        $db = new Database();
        $user = $db->verifyPassword($email, $password);
        
        if ($user) {
            self::startSession();
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            
            // Atualiza último login
            $db->updateLastLogin($user['id']);
            
            return true;
        }
        
        return false;
    }
    
    public static function register($email, $password, $name) {
        // Verifica se registro está habilitado
        if (getConfig('ALLOW_REGISTRATION', 'true') !== 'true') {
            throw new Exception("Registro de novos usuários está desabilitado.");
        }
        
        // Valida email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email inválido.");
        }
        
        // Valida senha
        if (strlen($password) < 6) {
            throw new Exception("A senha deve ter no mínimo 6 caracteres.");
        }
        
        // Valida nome
        if (strlen($name) < 3) {
            throw new Exception("O nome deve ter no mínimo 3 caracteres.");
        }
        
        $db = new Database();
        
        // Verifica se email já existe
        if ($db->getUserByEmail($email)) {
            throw new Exception("Este email já está cadastrado.");
        }
        
        // Cria usuário
        $userId = $db->createUser($email, $password, $name);
        
        return $userId;
    }
    
    public static function logout() {
        self::startSession();
        session_unset();
        session_destroy();
    }
    
    public static function requireLogin() {
        if (!self::checkLogin()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function getUserId() {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserEmail() {
        self::startSession();
        return $_SESSION['user_email'] ?? 'Usuário';
    }
    
    public static function getUserName() {
        self::startSession();
        return $_SESSION['user_name'] ?? 'Usuário';
    }
    
    public static function isAdmin() {
        self::startSession();
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
    }
    
    public static function getSessionDuration() {
        self::startSession();
        if (isset($_SESSION['login_time'])) {
            $duration = time() - $_SESSION['login_time'];
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            return sprintf('%02d:%02d', $hours, $minutes);
        }
        return '00:00';
    }
}