<?php
/**
 * ARQUIVO 2 de 4: database.php
 * 
 * Salve este arquivo como: database.php
 */

require_once 'config.php';

class Database {
    private $db;
    
    public function __construct() {
        try {
            $this->db = new SQLite3(DB_FILE);
            $this->db->busyTimeout(5000);
            $this->createTables();
        } catch (Exception $e) {
            die("Erro ao conectar ao banco de dados: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        // Tabela de sessões de estudo
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS study_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pdf_name TEXT NOT NULL,
                pdf_content TEXT NOT NULL,
                core_topics TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Tabela de progresso do usuário
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                correct_answers INTEGER DEFAULT 0,
                total_answers INTEGER DEFAULT 0,
                difficulty_level INTEGER DEFAULT 1,
                weak_points TEXT DEFAULT '[]',
                FOREIGN KEY(session_id) REFERENCES study_sessions(id)
            )
        ");
        
        // Tabela de questões geradas
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id INTEGER NOT NULL,
                statement TEXT NOT NULL,
                correct_answer INTEGER NOT NULL,
                topic_id INTEGER NOT NULL,
                explanation TEXT NOT NULL,
                key_concept TEXT NOT NULL,
                difficulty INTEGER NOT NULL,
                user_answer INTEGER,
                answered_at DATETIME,
                FOREIGN KEY(session_id) REFERENCES study_sessions(id)
            )
        ");
    }
    
    public function createSession($pdfName, $pdfContent, $coreTopics) {
        $stmt = $this->db->prepare("
            INSERT INTO study_sessions (pdf_name, pdf_content, core_topics) 
            VALUES (:pdf_name, :pdf_content, :core_topics)
        ");
        $stmt->bindValue(':pdf_name', $pdfName, SQLITE3_TEXT);
        $stmt->bindValue(':pdf_content', $pdfContent, SQLITE3_TEXT);
        $stmt->bindValue(':core_topics', json_encode($coreTopics), SQLITE3_TEXT);
        $stmt->execute();
        
        $sessionId = $this->db->lastInsertRowID();
        
        // Cria progresso inicial
        $stmt = $this->db->prepare("
            INSERT INTO user_progress (session_id) VALUES (:session_id)
        ");
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_INTEGER);
        $stmt->execute();
        
        return $sessionId;
    }
    
    public function getSession($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM study_sessions WHERE id = :id");
        $stmt->bindValue(':id', $sessionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $session = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($session) {
            $session['core_topics'] = json_decode($session['core_topics'], true);
        }
        
        return $session;
    }
    
    public function getLatestSession() {
        $result = $this->db->query("SELECT * FROM study_sessions ORDER BY id DESC LIMIT 1");
        $session = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($session) {
            $session['core_topics'] = json_decode($session['core_topics'], true);
        }
        
        return $session;
    }
    
    public function getProgress($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM user_progress WHERE session_id = :session_id");
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $progress = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($progress) {
            $progress['weak_points'] = json_decode($progress['weak_points'], true);
        }
        
        return $progress;
    }
    
    public function updateProgress($sessionId, $correct, $total, $difficulty, $weakPoints) {
        $stmt = $this->db->prepare("
            UPDATE user_progress 
            SET correct_answers = :correct, 
                total_answers = :total, 
                difficulty_level = :difficulty,
                weak_points = :weak_points
            WHERE session_id = :session_id
        ");
        $stmt->bindValue(':correct', $correct, SQLITE3_INTEGER);
        $stmt->bindValue(':total', $total, SQLITE3_INTEGER);
        $stmt->bindValue(':difficulty', $difficulty, SQLITE3_INTEGER);
        $stmt->bindValue(':weak_points', json_encode($weakPoints), SQLITE3_TEXT);
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    public function saveQuestion($sessionId, $question, $difficulty) {
        $stmt = $this->db->prepare("
            INSERT INTO questions (session_id, statement, correct_answer, topic_id, explanation, key_concept, difficulty)
            VALUES (:session_id, :statement, :correct_answer, :topic_id, :explanation, :key_concept, :difficulty)
        ");
        $stmt->bindValue(':session_id', $sessionId, SQLITE3_INTEGER);
        $stmt->bindValue(':statement', $question['statement'], SQLITE3_TEXT);
        $stmt->bindValue(':correct_answer', $question['correctAnswer'] ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':topic_id', $question['topicId'], SQLITE3_INTEGER);
        $stmt->bindValue(':explanation', $question['explanation'], SQLITE3_TEXT);
        $stmt->bindValue(':key_concept', $question['keyConceptTested'], SQLITE3_TEXT);
        $stmt->bindValue(':difficulty', $difficulty, SQLITE3_INTEGER);
        $stmt->execute();
        
        return $this->db->lastInsertRowID();
    }
    
    public function answerQuestion($questionId, $userAnswer) {
        $stmt = $this->db->prepare("
            UPDATE questions 
            SET user_answer = :user_answer, answered_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->bindValue(':user_answer', $userAnswer ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $questionId, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    public function getQuestion($questionId) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = :id");
        $stmt->bindValue(':id', $questionId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

