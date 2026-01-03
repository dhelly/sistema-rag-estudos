<?php
/**
 * DATABASE v2.1 - Suporte MySQL Multi-usuário
 * 
 * Salve este arquivo como: database.php
 */

require_once 'config.php';

class Database {
    private $conn;
    private $dbType;
    
    public function __construct() {
        $this->dbType = getConfig('DB_TYPE', 'mysql');
        
        if ($this->dbType === 'mysql') {
            $this->connectMySQL();
        } else {
            $this->connectSQLite();
        }
        
        $this->createTables();
    }
    
    private function connectMySQL() {
        try {
            $host = getConfig('DB_HOST', 'localhost');
            $port = getConfig('DB_PORT', '3306');
            $dbname = getConfig('DB_NAME', 'sistema_rag');
            $user = getConfig('DB_USER', 'root');
            $pass = getConfig('DB_PASS', '');
            $charset = getConfig('DB_CHARSET', 'utf8mb4');
            
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            $this->conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Erro ao conectar MySQL: " . $e->getMessage());
        }
    }
    
    private function connectSQLite() {
        try {
            $dbFile = getConfig('DB_FILE', 'study_system.db');
            $this->conn = new PDO("sqlite:{$dbFile}");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erro ao conectar SQLite: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        if ($this->dbType === 'mysql') {
            $this->createMySQLTables();
        } else {
            $this->createSQLiteTables();
        }
    }
    
    private function createMySQLTables() {
        // Tabela de usuários
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                is_admin BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de sessões de estudo
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS study_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                pdf_name VARCHAR(500) NOT NULL,
                pdf_content LONGTEXT NOT NULL,
                core_topics JSON NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de progresso do usuário
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS user_progress (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de questões geradas
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS questions (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de estatísticas agregadas
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS user_statistics (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Tabela de questionamentos de gabarito
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS question_challenges (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Criar usuário admin padrão se não existir
        $this->createDefaultAdmin();
    }
    
    private function createSQLiteTables() {
        // Mantém compatibilidade com SQLite da v2.0
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                name TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME
            )
        ");
        
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS study_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                pdf_name TEXT NOT NULL,
                pdf_content TEXT NOT NULL,
                core_topics TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id)
            )
        ");
        
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS user_progress (
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
            )
        ");
        
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS questions (
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
            )
        ");
        
        $this->createDefaultAdmin();
    }
    
    private function createDefaultAdmin() {
        try {
            $email = getConfig('ADMIN_EMAIL', 'admin@exemplo.com');
            $password = getConfig('ADMIN_PASSWORD', 'admin123');
            
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if (!$stmt->fetch()) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $this->conn->prepare("
                    INSERT INTO users (email, password, name, is_admin) 
                    VALUES (?, ?, ?, ?)
                ");
                $isAdmin = $this->dbType === 'mysql' ? 1 : 1;
                $stmt->execute([$email, $hashedPassword, 'Administrador', $isAdmin]);
            }
        } catch (Exception $e) {
            // Ignora se já existir
        }
    }
    
    // ==========================================
    // MÉTODOS DE USUÁRIOS
    // ==========================================
    
    public function createUser($email, $password, $name) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("
            INSERT INTO users (email, password, name) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$email, $hashedPassword, $name]);
        return $this->conn->lastInsertId();
    }
    
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public function getUserById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateLastLogin($userId) {
        $stmt = $this->conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        if ($this->dbType === 'sqlite') {
            $stmt = $this->conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        }
        $stmt->execute([$userId]);
    }
    
    public function verifyPassword($email, $password) {
        $user = $this->getUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    // ==========================================
    // MÉTODOS DE SESSÕES DE ESTUDO
    // ==========================================
    
    public function createSession($userId, $pdfName, $pdfContent, $coreTopics) {
        $coreTopicsJson = json_encode($coreTopics);
        
        $stmt = $this->conn->prepare("
            INSERT INTO study_sessions (user_id, pdf_name, pdf_content, core_topics) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $pdfName, $pdfContent, $coreTopicsJson]);
        
        $sessionId = $this->conn->lastInsertId();
        
        // Cria progresso inicial
        $stmt = $this->conn->prepare("
            INSERT INTO user_progress (session_id, user_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$sessionId, $userId]);
        
        return $sessionId;
    }
    
    public function getSession($sessionId) {
        $stmt = $this->conn->prepare("SELECT * FROM study_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        
        if ($session) {
            $session['core_topics'] = json_decode($session['core_topics'], true);
        }
        
        return $session;
    }
    
    public function getUserSessions($userId, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT id, pdf_name, created_at, updated_at 
            FROM study_sessions 
            WHERE user_id = ? 
            ORDER BY updated_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca sessões do usuário com dados de progresso
     * Ordenadas por dificuldade (menor para maior) para priorizar onde usuário está pior
     */
    public function getUserSessionsWithProgress($userId, $limit = 20) {
        if ($this->dbType === 'mysql') {
            $stmt = $this->conn->prepare("
                SELECT 
                    s.id,
                    s.pdf_name,
                    s.created_at,
                    s.updated_at,
                    COALESCE(up.difficulty_level, 1) as difficulty_level,
                    COALESCE(up.correct_answers, 0) as correct_answers,
                    COALESCE(up.total_answers, 0) as total_answers,
                    COALESCE(up.study_time_seconds, 0) as study_time_seconds
                FROM study_sessions s
                LEFT JOIN user_progress up ON s.id = up.session_id AND up.user_id = ?
                WHERE s.user_id = ?
                ORDER BY difficulty_level ASC, total_answers DESC, s.updated_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $userId, $limit]);
        } else {
            // SQLite
            $stmt = $this->conn->prepare("
                SELECT 
                    s.id,
                    s.pdf_name,
                    s.created_at,
                    s.updated_at,
                    COALESCE(up.difficulty_level, 1) as difficulty_level,
                    COALESCE(up.correct_answers, 0) as correct_answers,
                    COALESCE(up.total_answers, 0) as total_answers,
                    COALESCE(up.study_time_seconds, 0) as study_time_seconds
                FROM study_sessions s
                LEFT JOIN user_progress up ON s.id = up.session_id AND up.user_id = ?
                WHERE s.user_id = ?
                ORDER BY difficulty_level ASC, total_answers DESC, s.updated_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $userId, $limit]);
        }
        
        return $stmt->fetchAll();
    }
    // ==========================================
    // MÉTODOS DE PROGRESSO
    // ==========================================
    
    public function getProgress($sessionId) {
        $stmt = $this->conn->prepare("SELECT * FROM user_progress WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $progress = $stmt->fetch();
        
        if ($progress) {
            $progress['weak_points'] = json_decode($progress['weak_points'] ?? '[]', true);
        }
        
        return $progress;
    }
    
    public function updateProgress($sessionId, $correct, $total, $difficulty, $weakPoints) {
        $weakPointsJson = json_encode($weakPoints);
        
        $stmt = $this->conn->prepare("
            UPDATE user_progress 
            SET correct_answers = ?, 
                total_answers = ?, 
                difficulty_level = ?,
                weak_points = ?
            WHERE session_id = ?
        ");
        $stmt->execute([$correct, $total, $difficulty, $weakPointsJson, $sessionId]);
    }
    
    public function updateStudyTime($sessionId, $seconds) {
        $stmt = $this->conn->prepare("
            UPDATE user_progress 
            SET study_time_seconds = study_time_seconds + ? 
            WHERE session_id = ?
        ");
        $stmt->execute([$seconds, $sessionId]);
    }
    
    // ==========================================
    // MÉTODOS DE QUESTÕES
    // ==========================================
    
    public function saveQuestion($sessionId, $userId, $question, $difficulty) {
        $stmt = $this->conn->prepare("
            INSERT INTO questions (session_id, user_id, statement, correct_answer, topic_id, explanation, key_concept, difficulty)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $question['statement'],
            $question['correctAnswer'] ? 1 : 0,
            $question['topicId'],
            $question['explanation'],
            $question['keyConceptTested'],
            $difficulty
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    public function answerQuestion($questionId, $userAnswer, $responseTime = null) {
        if ($this->dbType === 'mysql') {
            $stmt = $this->conn->prepare("
                UPDATE questions 
                SET user_answer = ?, response_time_seconds = ?, answered_at = NOW() 
                WHERE id = ?
            ");
        } else {
            $stmt = $this->conn->prepare("
                UPDATE questions 
                SET user_answer = ?, response_time_seconds = ?, answered_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
        }
        $stmt->execute([$userAnswer ? 1 : 0, $responseTime, $questionId]);
    }
    
    public function getQuestion($questionId) {
        $stmt = $this->conn->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        return $stmt->fetch();
    }
    
    // ==========================================
    // MÉTODOS DE ESTATÍSTICAS
    // ==========================================
    
    public function getUserStatistics($userId) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as total_sessions,
                COUNT(q.id) as total_questions,
                SUM(CASE WHEN q.user_answer = q.correct_answer THEN 1 ELSE 0 END) as total_correct,
                SUM(COALESCE(up.study_time_seconds, 0)) as total_study_time,
                AVG(up.difficulty_level) as avg_difficulty
            FROM users u
            LEFT JOIN study_sessions s ON s.user_id = u.id
            LEFT JOIN questions q ON q.user_id = u.id
            LEFT JOIN user_progress up ON up.user_id = u.id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function getProgressHistory($userId, $days = 30) {
        if ($this->dbType === 'mysql') {
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(answered_at) as date,
                    COUNT(*) as questions,
                    SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
                FROM questions
                WHERE user_id = ? AND answered_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(answered_at)
                ORDER BY date ASC
            ");
        } else {
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(answered_at) as date,
                    COUNT(*) as questions,
                    SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
                FROM questions
                WHERE user_id = ? AND answered_at >= datetime('now', '-' || ? || ' days')
                GROUP BY DATE(answered_at)
                ORDER BY date ASC
            ");
        }
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll();
    }
    
    public function getTopicPerformance($userId, $sessionId = null) {
        $sql = "
            SELECT 
                key_concept,
                COUNT(*) as total,
                SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct,
                ROUND(100.0 * SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) / COUNT(*), 2) as percentage
            FROM questions
            WHERE user_id = ?
        ";
        
        $params = [$userId];
        if ($sessionId) {
            $sql .= " AND session_id = ?";
            $params[] = $sessionId;
        }
        
        $sql .= " GROUP BY key_concept ORDER BY percentage DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // ==========================================
    // MÉTODOS DE QUESTIONAMENTO
    // ==========================================

    public function getUsersAffectedByQuestion($questionId) {
        $stmt = $this->conn->prepare("
            SELECT DISTINCT user_id, session_id 
            FROM questions 
            WHERE id = ?
        ");
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }

    public function getQuestionChallengesFull($questionId) {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.name as user_name, u.email as user_email 
            FROM question_challenges c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.question_id = ? 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }
    
    public function createChallenge($questionId, $userId, $userArgument, $originalAnswer, $originalExplanation) {
        $stmt = $this->conn->prepare("
            INSERT INTO question_challenges 
            (question_id, user_id, user_argument, ai_analysis, original_answer, original_explanation, challenge_result)
            VALUES (?, ?, ?, '', ?, ?, 'pending')
        ");
        $stmt->execute([
            $questionId,
            $userId,
            $userArgument,
            $originalAnswer ? 1 : 0,
            $originalExplanation
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    public function updateChallenge($challengeId, $aiAnalysis, $webSources, $result, $suggestedAnswer = null, $updatedExplanation = null) {
        $webSourcesJson = json_encode($webSources);
        
        if ($this->dbType === 'mysql') {
            $stmt = $this->conn->prepare("
                UPDATE question_challenges 
                SET ai_analysis = ?, 
                    web_sources = ?,
                    challenge_result = ?,
                    suggested_answer = ?,
                    updated_explanation = ?,
                    reviewed_at = NOW()
                WHERE id = ?
            ");
        } else {
            $stmt = $this->conn->prepare("
                UPDATE question_challenges 
                SET ai_analysis = ?, 
                    web_sources = ?,
                    challenge_result = ?,
                    suggested_answer = ?,
                    updated_explanation = ?,
                    reviewed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
        }
        
        $stmt->execute([
            $aiAnalysis,
            $webSourcesJson,
            $result,
            $suggestedAnswer !== null ? ($suggestedAnswer ? 1 : 0) : null,
            $updatedExplanation,
            $challengeId
        ]);
    }
    
    public function getChallenge($challengeId) {
        $stmt = $this->conn->prepare("SELECT * FROM question_challenges WHERE id = ?");
        $stmt->execute([$challengeId]);
        $challenge = $stmt->fetch();
        
        if ($challenge && $challenge['web_sources']) {
            $challenge['web_sources'] = json_decode($challenge['web_sources'], true);
        }
        
        return $challenge;
    }
    
    public function getQuestionChallenges($questionId) {
        $stmt = $this->conn->prepare("
            SELECT c.*, u.name as user_name, u.email as user_email
            FROM question_challenges c
            JOIN users u ON c.user_id = u.id
            WHERE c.question_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$questionId]);
        return $stmt->fetchAll();
    }
    
    public function countQuestionChallenges($questionId) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM question_challenges 
            WHERE question_id = ?
        ");
        $stmt->execute([$questionId]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
    
    public function updateQuestionAfterChallenge($questionId, $newCorrectAnswer, $newExplanation) {
        $stmt = $this->conn->prepare("
            UPDATE questions 
            SET correct_answer = ?, 
                explanation = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $newCorrectAnswer ? 1 : 0,
            $newExplanation,
            $questionId
        ]);
    }
    
    public function recalculateUserProgress($userId, $sessionId) {
        // Recalcula estatísticas após mudança de gabarito
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN user_answer = correct_answer THEN 1 ELSE 0 END) as correct
            FROM questions
            WHERE user_id = ? AND session_id = ? AND user_answer IS NOT NULL
        ");
        $stmt->execute([$userId, $sessionId]);
        $stats = $stmt->fetch();
        
        if ($stats) {
            $stmt = $this->conn->prepare("
                UPDATE user_progress 
                SET correct_answers = ?, total_answers = ?
                WHERE user_id = ? AND session_id = ?
            ");
            $stmt->execute([
                $stats['correct'],
                $stats['total'],
                $userId,
                $sessionId
            ]);
        }
    }
    
    public function __destruct() {
        $this->conn = null;
    }
}