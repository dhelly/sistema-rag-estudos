<?php
/**
 * Migration: 002_create_study_tables
 * 
 * Cria tabelas de sessões, progresso e questões
 * 
 * Salvar como: src/Database/migrations/2025_01_06_000002_create_study_tables.php
 */

$dbType = \App\Database\Connection::getInstance()->getType();

if ($dbType === 'mysql') {
    return [
        'up' => [
            // Tabela de disciplinas
            "CREATE TABLE IF NOT EXISTS disciplines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT '📚',
                color VARCHAR(20) DEFAULT 'indigo',
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_slug (slug),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabela de sessões de estudo
            "CREATE TABLE IF NOT EXISTS study_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                discipline_id INT NULL,
                pdf_name VARCHAR(500) NOT NULL,
                pdf_content LONGTEXT NOT NULL,
                core_topics JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabela de progresso
            "CREATE TABLE IF NOT EXISTS user_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                user_id INT NOT NULL,
                correct_answers INT DEFAULT 0,
                total_answers INT DEFAULT 0,
                difficulty_level INT DEFAULT 1,
                weak_points JSON DEFAULT NULL,
                study_time_seconds INT DEFAULT 0,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES study_sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_session_user (session_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabela de questões
            "CREATE TABLE IF NOT EXISTS questions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                user_id INT NOT NULL,
                statement TEXT NOT NULL,
                correct_answer BOOLEAN NOT NULL,
                topic_id INT NOT NULL,
                explanation TEXT NOT NULL,
                key_concept VARCHAR(255) NOT NULL,
                difficulty INT NOT NULL,
                user_answer BOOLEAN NULL,
                answered_at TIMESTAMP NULL,
                response_time_seconds INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES study_sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_session_user (session_id, user_id),
                INDEX idx_answered (answered_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabela de estatísticas
            "CREATE TABLE IF NOT EXISTS user_statistics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                total_sessions INT DEFAULT 0,
                total_questions INT DEFAULT 0,
                total_correct INT DEFAULT 0,
                total_study_time_seconds INT DEFAULT 0,
                average_difficulty DECIMAL(3,2) DEFAULT 1.00,
                best_topics JSON DEFAULT NULL,
                worst_topics JSON DEFAULT NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ],
        
        'down' => [
            "DROP TABLE IF EXISTS user_statistics",
            "DROP TABLE IF EXISTS questions",
            "DROP TABLE IF EXISTS user_progress",
            "DROP TABLE IF EXISTS study_sessions",
            "DROP TABLE IF EXISTS disciplines",
        ]
    ];
    
} else { // SQLite
    return [
        'up' => [
            "CREATE TABLE IF NOT EXISTS disciplines (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                icon TEXT DEFAULT '📚',
                color TEXT DEFAULT 'indigo',
                is_active INTEGER DEFAULT 1,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(created_by) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS study_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discipline_id INTEGER NULL,
                pdf_name TEXT NOT NULL,
                pdf_content TEXT NOT NULL,
                core_topics TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id),
                FOREIGN KEY(discipline_id) REFERENCES disciplines(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS user_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                correct_answers INTEGER DEFAULT 0,
                total_answers INTEGER DEFAULT 0,
                difficulty_level INTEGER DEFAULT 1,
                weak_points TEXT DEFAULT '[]',
                study_time_seconds INTEGER DEFAULT 0,
                FOREIGN KEY(session_id) REFERENCES study_sessions(id),
                FOREIGN KEY(user_id) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                statement TEXT NOT NULL,
                correct_answer INTEGER NOT NULL,
                topic_id INTEGER NOT NULL,
                explanation TEXT NOT NULL,
                key_concept TEXT NOT NULL,
                difficulty INTEGER NOT NULL,
                user_answer INTEGER,
                answered_at DATETIME,
                response_time_seconds INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES study_sessions(id),
                FOREIGN KEY(user_id) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS user_statistics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL UNIQUE,
                total_sessions INTEGER DEFAULT 0,
                total_questions INTEGER DEFAULT 0,
                total_correct INTEGER DEFAULT 0,
                total_study_time_seconds INTEGER DEFAULT 0,
                average_difficulty REAL DEFAULT 1.00,
                best_topics TEXT DEFAULT NULL,
                worst_topics TEXT DEFAULT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id)
            )",
        ],
        
        'down' => [
            "DROP TABLE IF EXISTS user_statistics",
            "DROP TABLE IF EXISTS questions",
            "DROP TABLE IF EXISTS user_progress",
            "DROP TABLE IF EXISTS study_sessions",
            "DROP TABLE IF EXISTS disciplines",
        ]
    ];
}