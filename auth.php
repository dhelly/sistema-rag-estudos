<?php
/**
 * ARQUIVO 2 de 6: auth.php (CORRIGIDO)
 * 
 * Salve este arquivo como: auth.php
 * Sistema de autenticação simples
 */

require_once 'config.php';

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
    
    public static function login($username, $password) {
        $validUsername = getConfig('LOGIN_USERNAME', 'admin');
        $validPassword = getConfig('LOGIN_PASSWORD', 'admin');
        
        if ($username === $validUsername && $password === $validPassword) {
            self::startSession();
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            return true;
        }
        
        return false;
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
    
    public static function getUsername() {
        self::startSession();
        return $_SESSION['username'] ?? 'Usuário';
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