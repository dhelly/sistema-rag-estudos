<?php
/**
 * Migration: 001_create_users_table
 * 
 * Cria tabela de usuários com sistema de ativação
 * 
 * Salvar como: src/Database/migrations/2025_01_06_000001_create_users_table.php
 */

$dbType = \App\Database\Connection::getInstance()->getType();

if ($dbType === 'mysql') {
    return [
        'up' => "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                is_admin BOOLEAN DEFAULT FALSE,
                active BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                activated_at TIMESTAMP NULL,
                activated_by INT NULL,
                INDEX idx_email (email),
                INDEX idx_active (active),
                FOREIGN KEY (activated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'down' => "DROP TABLE IF EXISTS users"
    ];
    
} else { // SQLite
    return [
        'up' => "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                name TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                active INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                activated_at DATETIME,
                activated_by INTEGER,
                FOREIGN KEY(activated_by) REFERENCES users(id)
            )
        ",
        
        'down' => "DROP TABLE IF EXISTS users"
    ];
}