<?php
/**
 * ARQUIVO 1 de 4: config.php
 * 
 * Salve este arquivo como: config.php
 */

// ============================================
// CONFIGURAÇÕES DO SISTEMA
// ============================================

// IMPORTANTE: Coloque sua chave da API Anthropic aqui
// Obtenha em: https://console.anthropic.com/
define('ANTHROPIC_API_KEY', 'sk'); // SUBSTITUA pela sua chave real

// Modelo do Claude a ser usado
define('ANTHROPIC_MODEL', 'claude-haiku-4-5');

// Nome do arquivo do banco de dados SQLite
define('DB_FILE', 'study_system.db');

// Diretório para uploads de PDFs
define('UPLOAD_DIR', 'uploads/');

// ============================================
// CRIAÇÃO AUTOMÁTICA DE DIRETÓRIOS
// ============================================

// Cria diretório de uploads se não existir
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Verifica se as extensões necessárias estão instaladas
if (!extension_loaded('sqlite3')) {
    die('ERRO: Extensão SQLite3 não está instalada. Instale com: sudo apt-get install php-sqlite3');
}

if (!extension_loaded('curl')) {
    die('ERRO: Extensão cURL não está instalada. Instale com: sudo apt-get install php-curl');
}

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300); // 5 minutos para processamento de PDFs
ini_set('memory_limit', '256M');

// Configurações de upload
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');

?>