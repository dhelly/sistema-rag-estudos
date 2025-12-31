<?php
/**
 * Script para Corrigir Problema SSL no Windows/Laragon
 * 
 * Execute este arquivo UMA VEZ: php fix_ssl.php
 * 
 * O que este script faz:
 * 1. Baixa o certificado CA atualizado usando múltiplos métodos
 * 2. Salva na pasta do projeto
 * 3. Configura o PHP para usar este certificado
 */

echo "======================================\n";
echo "🔧 Correção SSL para Windows/Laragon\n";
echo "======================================\n\n";

// URL do certificado CA oficial
$cacertUrl = 'https://curl.se/ca/cacert.pem';
$cacertPath = __DIR__ . '/cacert.pem';
$backupUrl = 'http://curl.se/ca/cacert.pem'; // Fallback HTTP

echo "📥 Tentando baixar certificado CA...\n";

// Método 1: Usar cURL (recomendado)
function downloadWithCurl($url, $outputFile) {
    if (!function_exists('curl_init')) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false, // Temporariamente desabilitado para o download
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_TIMEOUT => 30
    ]);
    
    $data = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "   cURL error: {$error}\n";
        return false;
    }
    
    if ($data && strlen($data) > 10000) { // Verifica se tem tamanho razoável
        return file_put_contents($outputFile, $data);
    }
    
    return false;
}

// Método 2: Usar fopen com contexto (se habilitado)
function downloadWithFopen($url, $outputFile) {
    // Tenta criar contexto sem verificação SSL
    $contextOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0'
        ]
    ];
    
    try {
        $context = stream_context_create($contextOptions);
        $data = @file_get_contents($url, false, $context);
        
        if ($data && strlen($data) > 10000) {
            return file_put_contents($outputFile, $data);
        }
    } catch (Exception $e) {
        echo "   file_get_contents error: {$e->getMessage()}\n";
    }
    
    return false;
}

// Tenta diferentes métodos
$success = false;
if (function_exists('curl_init')) {
    echo "   Tentando com cURL...\n";
    $success = downloadWithCurl($cacertUrl, $cacertPath);
    
    if (!$success) {
        echo "   Tentando com HTTP (fallback)...\n";
        $success = downloadWithCurl($backupUrl, $cacertPath);
    }
}

if (!$success && ini_get('allow_url_fopen')) {
    echo "   Tentando com file_get_contents...\n";
    $success = downloadWithFopen($cacertUrl, $cacertPath);
    
    if (!$success) {
        $success = downloadWithFopen($backupUrl, $cacertPath);
    }
}

if (!$success) {
    // Método 3: Download manual alternativo - usar certificado do Windows
    echo "⚠️  Não foi possível baixar automaticamente.\n";
    echo "   Vou criar um certificado básico para você...\n";
    
    // Cria um certificado CA básico de exemplo
    $basicCert = <<<EOT
# Certificado CA básico para desenvolvimento
# Este é um certificado temporário. Para produção, baixe o cacert.pem completo:
# https://curl.se/ca/cacert.pem

# OU use os certificados do sistema Windows:
# 1. Abra o PowerShell como Administrador
# 2. Execute: certutil -generateSSTFromWU roots.sst
# 3. Converta o arquivo .sst para .pem se necessário

# Entrada temporária para permitir conexões SSL
-----BEGIN CERTIFICATE-----
MIIDQTCCAimgAwIBAgITBmyfz5m/jAo54vB4ikPmljZbyjANBgkqhkiG9w0BAQsF
ADA5MQswCQYDVQQGEwJVUzEPMA0GA1UEChMGQW1hem9uMRkwFwYDVQQDExBBbWF6
b24gUm9vdCBDQSAxMB4XDTE1MDUyNjAwMDAwMFoXDTM4MDExNzAwMDAwMFowOTEL
MAkGA1UEBhMCVVMxDzANBgNVBAoTBkFtYXpvbjEZMBcGA1UEAxMQQW1hem9uIFJv
b3QgQ0EgMTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALJ4gHHKeNXj
ca9HgFB0fW7Y14h29Jlo91ghYPl0hAEvrAIthtOgQ3pOsqTQNroBvo3bSMgHFzZM
9O6II8c+6zf1tRn4SWiw3te5djgdYZ6k/oI2peVKVuRF4fn9tBb6dNqcmzU5L/qw
IFAGbHrQgLKm+a/sRxmPUDgH3KKHOVj4utWp+UhnMJbulHheb4mjUcAwhmahRWa6
VOujw5H5SNz/0egwLX0tdHA114gk957EWW67c4cX8jJGKLhD+rcdqsq08p8kDi1L
93FcXmn/6pUCyziKrlA4b9v7LWIbxcceVOF34GfID5yHI9Y/QCB/IIDEgEw+OyQm
jgSubJrIqg0CAwEAAaNCMEAwDwYDVR0TAQH/BAUwAwEB/zAOBgNVHQ8BAf8EBAMC
AYYwHQYDVR0OBBYEFIQYzIU07LwMlJQuCFmcx7IQTgoIMA0GCSqGSIb3DQEBCwUA
A4IBAQCY8jdaQZChGsV2USggNiMOruYou6r4lK5IpDB/G/wkjUu0yKGX9rbxenDI
U5PMCCjjmCXPI6T53iHTfIUJrU6adTrCC2qJeHZERxhlbI1Bjjt/msv0tadQ1wUs
N+gDS63pYaACbvXy8MWy7Vu33PqUXHeeE6V/Uq2V8viTO96LXFvKWlJbYK8U90vv
o/ufQJVtMVT8QtPHRh8jrdkPSHCa2XV4cdFyQzR1bldZwgJcJmApzyMZFo6IQ6XU
5MsI+yMRQ+hDKXJioaldXgjUkK642M4UwtBV8ob2xJNDd2ZhwLnoQdeXeGADbkpy
rqXRfboQnoZsG4q5WTP468SQvvG5
-----END CERTIFICATE-----
EOT;
    
    if (file_put_contents($cacertPath, $basicCert)) {
        echo "✅ Certificado básico criado!\n";
        $success = true;
    } else {
        die("❌ ERRO: Não foi possível criar o arquivo de certificado!\n");
    }
}

if ($success) {
    echo "✅ Certificado salvo com sucesso!\n";
    echo "📁 Local: {$cacertPath}\n";
    echo "📏 Tamanho: " . filesize($cacertPath) . " bytes\n\n";
}

// Verifica o php.ini
$phpIniPath = php_ini_loaded_file();
if (!$phpIniPath) {
    echo "⚠️  Não foi possível encontrar o php.ini\n";
} else {
    echo "📄 Arquivo php.ini: {$phpIniPath}\n\n";
}

echo "======================================\n";
echo "⚙️  CONFIGURAÇÃO NECESSÁRIA\n";
echo "======================================\n\n";

echo "1. HABILITE as extensões no php.ini:\n";
echo "   - Remova o ';' na frente de:\n";
echo "     extension=curl\n";
echo "     extension=openssl\n\n";

echo "2. Adicione/edite estas linhas:\n";
echo "   curl.cainfo = \"{$cacertPath}\"\n";
echo "   openssl.cafile = \"{$cacertPath}\"\n\n";

echo "3. Para Laragon:\n";
echo "   a) Edite: C:\\laragon\\bin\\php\\php-[versão]\\php.ini\n";
echo "   b) Reinicie o Laragon\n";
echo "   c) Verifique se as extensões estão habilitadas:\n";
echo "      php -m | findstr curl\n";
echo "      php -m | findstr openssl\n\n";

echo "======================================\n";
echo "🔄 TESTANDO CONFIGURAÇÃO ATUAL\n";
echo "======================================\n\n";

echo "Extensões carregadas:\n";
echo "- cURL: " . (function_exists('curl_version') ? '✅' : '❌') . "\n";
echo "- OpenSSL: " . (function_exists('openssl_verify') ? '✅' : '❌') . "\n";
echo "- allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✅' : '❌') . "\n\n";

// Testa conexão se cURL estiver disponível
if (function_exists('curl_init')) {
    echo "Testando conexão SSL...\n";
    
    $ch = curl_init('https://api.anthropic.com/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CAINFO => file_exists($cacertPath) ? $cacertPath : null,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FAILONERROR => true
    ]);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($error) {
        echo "⚠️  Erro na conexão: {$error}\n";
        echo "   Configure o php.ini conforme instruções acima.\n";
    } else {
        echo "✅ Conexão SSL funcionando! (HTTP {$httpCode})\n";
    }
} else {
    echo "⚠️  cURL não está disponível. Habilite no php.ini.\n";
}

echo "\n======================================\n";
echo "📝 RESUMO\n";
echo "======================================\n\n";

echo "1. Certificado criado em: {$cacertPath}\n";
echo "2. Edite o php.ini para:\n";
echo "   - Habilitar extensões curl e openssl\n";
echo "   - Adicionar caminho do certificado\n";
echo "3. Reinicie o Laragon/Apache\n";
echo "4. Execute novamente para testar\n\n";

echo "💡 DICA RÁPIDA para Laragon:\n";
echo "   php --ini                         # Mostra onde está o php.ini\n";
echo "   laragon restart                   # Reinicia o Laragon\n\n";

echo "✅ Script finalizado! Siga as instruções acima.\n";
?>