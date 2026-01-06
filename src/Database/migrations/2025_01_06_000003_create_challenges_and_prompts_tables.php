<?php
/**
 * Migration: 003_create_challenges_and_prompts_tables
 * 
 * Cria tabelas de questionamentos e prompts customizados
 * 
 * Salvar como: src/Database/migrations/2025_01_06_000003_create_challenges_and_prompts_tables.php
 */

$dbType = \App\Database\Connection::getInstance()->getType();

if ($dbType === 'mysql') {
    return [
        'up' => [
            // Tabela de questionamentos
            "CREATE TABLE IF NOT EXISTS question_challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question_id INT NOT NULL,
                user_id INT NOT NULL,
                user_argument TEXT NOT NULL,
                ai_analysis TEXT NOT NULL,
                web_sources JSON DEFAULT NULL,
                challenge_result ENUM('accepted', 'rejected', 'pending') DEFAULT 'pending',
                original_answer BOOLEAN NOT NULL,
                suggested_answer BOOLEAN NULL,
                original_explanation TEXT NOT NULL,
                updated_explanation TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TIMESTAMP NULL,
                FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_question_user (question_id, user_id),
                INDEX idx_result (challenge_result)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            // Tabela de prompts customizados
            "CREATE TABLE IF NOT EXISTS agent_prompts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                discipline_id INT NOT NULL,
                agent_type ENUM('analyzer', 'generator', 'challenger') NOT NULL,
                prompt_content TEXT NOT NULL,
                system_instructions TEXT,
                examples TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                version INT DEFAULT 1,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY idx_discipline_agent (discipline_id, agent_type),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ],
        
        'down' => [
            "DROP TABLE IF EXISTS agent_prompts",
            "DROP TABLE IF EXISTS question_challenges",
        ]
    ];
    
} else { // SQLite
    return [
        'up' => [
            "CREATE TABLE IF NOT EXISTS question_challenges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                question_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                user_argument TEXT NOT NULL,
                ai_analysis TEXT NOT NULL,
                web_sources TEXT,
                challenge_result TEXT DEFAULT 'pending',
                original_answer INTEGER NOT NULL,
                suggested_answer INTEGER,
                original_explanation TEXT NOT NULL,
                updated_explanation TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME,
                FOREIGN KEY(question_id) REFERENCES questions(id),
                FOREIGN KEY(user_id) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS agent_prompts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                discipline_id INTEGER NOT NULL,
                agent_type TEXT NOT NULL CHECK(agent_type IN ('analyzer', 'generator', 'challenger')),
                prompt_content TEXT NOT NULL,
                system_instructions TEXT,
                examples TEXT,
                is_active INTEGER DEFAULT 1,
                version INTEGER DEFAULT 1,
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(discipline_id) REFERENCES disciplines(id),
                FOREIGN KEY(created_by) REFERENCES users(id),
                UNIQUE(discipline_id, agent_type)
            )",
        ],
        
        'down' => [
            "DROP TABLE IF EXISTS agent_prompts",
            "DROP TABLE IF EXISTS question_challenges",
        ]
    ];
}