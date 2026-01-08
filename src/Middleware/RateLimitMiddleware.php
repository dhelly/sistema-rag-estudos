<?php
/**
 * RATELIMITMIDDLEWARE.PHP - Limitador de Taxa
 * 
 * Previne abuso de API/requisições
 * Útil para proteger rotas sensíveis
 */

namespace App\Middleware;

class RateLimitMiddleware {
    private $maxRequests;
    private $windowSeconds;
    private $storageKey;
    
    /**
     * @param int $maxRequests Máximo de requisições permitidas
     * @param int $windowSeconds Janela de tempo em segundos
     */
    public function __construct($maxRequests = null, $windowSeconds = null) {
        $this->maxRequests = $maxRequests ?? config('security.rate_limiting.max_requests', 100);
        $this->windowSeconds = $windowSeconds ?? config('security.rate_limiting.window_seconds', 3600);
    }
    
    /**
     * Executa middleware
     * 
     * @param callable $next
     * @return mixed
     */
    public function handle($next) {
        if (!config('security.rate_limiting.enabled')) {
            return $next();
        }
        
        $identifier = $this->getIdentifier();
        
        // Verifica rate limit
        if ($this->isRateLimited($identifier)) {
            $this->sendRateLimitResponse();
            return;
        }
        
        // Registra tentativa
        $this->recordAttempt($identifier);
        
        return $next();
    }
    
    /**
     * Obtém identificador único (IP ou user_id)
     * 
     * @return string
     */
    private function getIdentifier() {
        // Usa user_id se logado, senão IP
        if (isset($_SESSION['user_id'])) {
            return 'user_' . $_SESSION['user_id'];
        }
        
        return 'ip_' . $this->getClientIp();
    }
    
    /**
     * Obtém IP do cliente
     * 
     * @return string
     */
    private function getClientIp() {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Se tiver múltiplos IPs, pega o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Verifica se está sob rate limit
     * 
     * @param string $identifier
     * @return bool
     */
    private function isRateLimited($identifier) {
        $cacheFile = $this->getCacheFile($identifier);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (!$data) {
            return false;
        }
        
        // Limpa tentativas antigas
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) {
            return (time() - $timestamp) < $this->windowSeconds;
        });
        
        // Verifica se excedeu limite
        return count($data['attempts']) >= $this->maxRequests;
    }
    
    /**
     * Registra tentativa
     * 
     * @param string $identifier
     */
    private function recordAttempt($identifier) {
        $cacheFile = $this->getCacheFile($identifier);
        
        $data = ['attempts' => []];
        
        if (file_exists($cacheFile)) {
            $existing = json_decode(file_get_contents($cacheFile), true);
            if ($existing) {
                $data = $existing;
            }
        }
        
        // Adiciona timestamp atual
        $data['attempts'][] = time();
        
        // Limpa tentativas antigas
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) {
            return (time() - $timestamp) < $this->windowSeconds;
        });
        
        // Salva
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Obtém caminho do arquivo de cache
     * 
     * @param string $identifier
     * @return string
     */
    private function getCacheFile($identifier) {
        $cacheDir = storage_path('cache/rate_limit');
        
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $hash = md5($identifier);
        return $cacheDir . '/' . $hash . '.json';
    }
    
    /**
     * Envia resposta de rate limit
     */
    private function sendRateLimitResponse() {
        http_response_code(429); // Too Many Requests
        
        header('Retry-After: ' . $this->windowSeconds);
        
        // Se for AJAX/API
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Too many requests',
                'message' => 'Você excedeu o limite de requisições. Tente novamente mais tarde.',
                'retry_after' => $this->windowSeconds
            ]);
        } else {
            // Página HTML
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Muitas Requisições</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            text-align: center; 
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            background: white;
            color: #333;
            padding: 40px;
            border-radius: 10px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Muitas Requisições</h1>
        <p>Você excedeu o limite de requisições permitidas.</p>
        <p>Por favor, aguarde alguns minutos antes de tentar novamente.</p>
        <p><small>Limite: ' . $this->maxRequests . ' requisições por ' . 
            round($this->windowSeconds / 60) . ' minutos</small></p>
    </div>
</body>
</html>';
        }
        
        exit;
    }
    
    /**
     * Limpa cache antigo (manutenção)
     * 
     * @return int Arquivos removidos
     */
    public static function cleanOldCache() {
        $cacheDir = storage_path('cache/rate_limit');
        
        if (!file_exists($cacheDir)) {
            return 0;
        }
        
        $files = glob($cacheDir . '/*.json');
        $removed = 0;
        $maxAge = 86400; // 24 horas
        
        foreach ($files as $file) {
            if ((time() - filemtime($file)) > $maxAge) {
                unlink($file);
                $removed++;
            }
        }
        
        return $removed;
    }
    
    /**
     * Obtém estatísticas de rate limit
     * 
     * @param string $identifier
     * @return array|null
     */
    public static function getStats($identifier) {
        $middleware = new self();
        $cacheFile = $middleware->getCacheFile($identifier);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        
        if (!$data) {
            return null;
        }
        
        // Limpa tentativas antigas
        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($middleware) {
            return (time() - $timestamp) < $middleware->windowSeconds;
        });
        
        return [
            'attempts' => count($data['attempts']),
            'max_attempts' => $middleware->maxRequests,
            'remaining' => max(0, $middleware->maxRequests - count($data['attempts'])),
            'window_seconds' => $middleware->windowSeconds,
            'reset_at' => time() + $middleware->windowSeconds
        ];
    }
}

/**
 * Helper global para aplicar rate limit em rotas específicas
 */

if (!function_exists('rate_limit')) {
    /**
     * Aplica rate limit na rota atual
     * 
     * @param int|null $maxRequests
     * @param int|null $windowSeconds
     * @return void
     */
    function rate_limit($maxRequests = null, $windowSeconds = null) {
        $middleware = new RateLimitMiddleware($maxRequests, $windowSeconds);
        $middleware->handle(function() {
            // Não faz nada, apenas valida
        });
    }
}