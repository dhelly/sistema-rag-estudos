<?php
/**
 * Migration: 004_seed_initial_data
 * 
 * Insere dados iniciais (admin e disciplina padrão)
 * 
 * Salvar como: src/Database/migrations/2025_01_06_000004_seed_initial_data.php
 */

$dbType = \App\Database\Connection::getInstance()->getType();
$db = \App\Database\Connection::getInstance();

// Preparar dados
$adminEmail = config('auth.admin.email');
$adminPassword = password_hash(config('auth.admin.password'), PASSWORD_BCRYPT);
$adminName = config('auth.admin.name');

$timestamp = $dbType === 'mysql' ? 'NOW()' : "datetime('now')";

return [
    'up' => [
        // Criar usuário admin (apenas se não existir)
        "INSERT INTO users (email, password, name, is_admin, active, activated_at) 
         SELECT '$adminEmail', '$adminPassword', '$adminName', " . ($dbType === 'mysql' ? '1, 1' : '1, 1') . ", $timestamp
         WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = '$adminEmail')",
        
        // Criar disciplina genérica (apenas se não existir)
        "INSERT INTO disciplines (name, slug, description, icon, color, is_active) 
         SELECT 'Geral / Genérico', 'geral', 'Prompts genéricos para qualquer disciplina', '📚', 'indigo', " . ($dbType === 'mysql' ? '1' : '1') . "
         WHERE NOT EXISTS (SELECT 1 FROM disciplines WHERE slug = 'geral')",
    ],
    
    'down' => [
        // Remove admin
        "DELETE FROM users WHERE email = '$adminEmail'",
        
        // Remove disciplina geral
        "DELETE FROM disciplines WHERE slug = 'geral'",
    ]
];