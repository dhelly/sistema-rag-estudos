<?php
/**
 * MIGRATION.PHP - Sistema de Migrations
 * 
 * Gerencia versionamento do schema do banco de dados.
 * Permite executar e reverter alterações de forma controlada.
 */

namespace App\Database;

use PDO;
use Exception;

class Migration {
    private $db;
    private $migrationsTable = 'migrations';
    private $migrationsPath;
    
    public function __construct() {
        $this->db = Connection::getInstance();
        $this->migrationsPath = SRC_PATH . '/Database/migrations';
        
        // Garante que diretório existe
        if (!file_exists($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
        
        $this->createMigrationsTable();
    }
    
    /**
     * Cria tabela de controle de migrations
     */
    private function createMigrationsTable() {
        $dbType = $this->db->getType();
        
        if ($dbType === 'mysql') {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        }
        
        $this->db->exec($sql);
    }
    
    /**
     * Executa todas as migrations pendentes
     * 
     * @return array Migrations executadas
     */
    public function run() {
        $executed = [];
        $batch = $this->getNextBatchNumber();
        
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            echo "✅ Nenhuma migration pendente.\n";
            return $executed;
        }
        
        echo "🚀 Executando " . count($pending) . " migration(s)...\n\n";
        
        foreach ($pending as $migration) {
            try {
                echo "⏳ Executando: {$migration}... ";
                
                $this->executeMigration($migration, 'up');
                $this->recordMigration($migration, $batch);
                
                $executed[] = $migration;
                echo "✅\n";
                
            } catch (Exception $e) {
                echo "❌\n";
                echo "Erro: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "\n✨ Migrations executadas com sucesso!\n";
        
        return $executed;
    }
    
    /**
     * Reverte último batch de migrations
     * 
     * @return array Migrations revertidas
     */
    public function rollback() {
        $reverted = [];
        $lastBatch = $this->getLastBatchNumber();
        
        if ($lastBatch === 0) {
            echo "⚠️  Nenhuma migration para reverter.\n";
            return $reverted;
        }
        
        $migrations = $this->getMigrationsFromBatch($lastBatch);
        
        echo "🔄 Revertendo " . count($migrations) . " migration(s) do batch {$lastBatch}...\n\n";
        
        // Reverte em ordem inversa
        foreach (array_reverse($migrations) as $migration) {
            try {
                echo "⏳ Revertendo: {$migration}... ";
                
                $this->executeMigration($migration, 'down');
                $this->removeMigration($migration);
                
                $reverted[] = $migration;
                echo "✅\n";
                
            } catch (Exception $e) {
                echo "❌\n";
                echo "Erro: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
        
        echo "\n✨ Rollback executado com sucesso!\n";
        
        return $reverted;
    }
    
    /**
     * Reseta todas as migrations
     */
    public function reset() {
        echo "🔄 Resetando todas as migrations...\n\n";
        
        while ($this->getLastBatchNumber() > 0) {
            $this->rollback();
        }
        
        echo "\n✨ Reset completo!\n";
    }
    
    /**
     * Reseta e executa todas as migrations novamente
     */
    public function refresh() {
        echo "🔄 Refresh: resetando e re-executando migrations...\n\n";
        
        $this->reset();
        $this->run();
        
        echo "\n✨ Refresh completo!\n";
    }
    
    /**
     * Lista status das migrations
     */
    public function status() {
        $all = $this->getAllMigrationFiles();
        $executed = $this->getExecutedMigrations();
        
        echo "\n📊 Status das Migrations:\n";
        echo str_repeat("=", 80) . "\n";
        printf("%-50s %-10s %-10s\n", "Migration", "Status", "Batch");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($all as $migration) {
            $migrationName = basename($migration, '.php');
            $status = in_array($migrationName, array_column($executed, 'migration')) ? '✅ Executada' : '⏳ Pendente';
            $batch = '';
            
            foreach ($executed as $exec) {
                if ($exec['migration'] === $migrationName) {
                    $batch = $exec['batch'];
                    break;
                }
            }
            
            printf("%-50s %-10s %-10s\n", $migrationName, $status, $batch);
        }
        
        echo str_repeat("=", 80) . "\n\n";
        
        $pending = count($all) - count($executed);
        echo "Total: " . count($all) . " migrations | ";
        echo "Executadas: " . count($executed) . " | ";
        echo "Pendentes: $pending\n\n";
    }
    
    /**
     * Obtém migrations pendentes
     * 
     * @return array
     */
    private function getPendingMigrations() {
        $all = $this->getAllMigrationFiles();
        $executed = $this->getExecutedMigrations();
        
        $executedNames = array_column($executed, 'migration');
        
        $pending = [];
        foreach ($all as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $executedNames)) {
                $pending[] = $name;
            }
        }
        
        return $pending;
    }
    
    /**
     * Obtém todos os arquivos de migration
     * 
     * @return array
     */
    private function getAllMigrationFiles() {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        return $files;
    }
    
    /**
     * Obtém migrations já executadas
     * 
     * @return array
     */
    private function getExecutedMigrations() {
        $stmt = $this->db->prepare(
            "SELECT migration, batch FROM {$this->migrationsTable} ORDER BY id ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Obtém migrations de um batch específico
     * 
     * @param int $batch
     * @return array
     */
    private function getMigrationsFromBatch($batch) {
        $stmt = $this->db->prepare(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id ASC"
        );
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Obtém próximo número de batch
     * 
     * @return int
     */
    private function getNextBatchNumber() {
        $stmt = $this->db->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch();
        return ($result['max_batch'] ?? 0) + 1;
    }
    
    /**
     * Obtém último número de batch
     * 
     * @return int
     */
    private function getLastBatchNumber() {
        $stmt = $this->db->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch();
        return $result['max_batch'] ?? 0;
    }
    
    /**
     * Executa migration
     * 
     * @param string $migration Nome da migration
     * @param string $direction 'up' ou 'down'
     */
    private function executeMigration($migration, $direction) {
        $file = $this->migrationsPath . '/' . $migration . '.php';
        
        if (!file_exists($file)) {
            throw new Exception("Arquivo de migration não encontrado: $file");
        }
        
        // Obtém SQL da migration
        $sql = require $file;
        
        if (!isset($sql[$direction])) {
            throw new Exception("Direção '$direction' não encontrada na migration $migration");
        }
        
        // Executa SQL
        $statements = is_array($sql[$direction]) ? $sql[$direction] : [$sql[$direction]];
        
        foreach ($statements as $statement) {
            if (!empty(trim($statement))) {
                $this->db->exec($statement);
            }
        }
    }
    
    /**
     * Registra migration como executada
     * 
     * @param string $migration
     * @param int $batch
     */
    private function recordMigration($migration, $batch) {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)"
        );
        $stmt->execute([$migration, $batch]);
    }
    
    /**
     * Remove registro de migration
     * 
     * @param string $migration
     */
    private function removeMigration($migration) {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->migrationsTable} WHERE migration = ?"
        );
        $stmt->execute([$migration]);
    }
    
    /**
     * Cria novo arquivo de migration
     * 
     * @param string $name Nome da migration
     * @return string Caminho do arquivo criado
     */
    public function create($name) {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationsPath . '/' . $filename;
        
        $template = $this->getMigrationTemplate($name);
        
        file_put_contents($filepath, $template);
        
        echo "✅ Migration criada: $filename\n";
        
        return $filepath;
    }
    
    /**
     * Obtém template de migration
     * 
     * @param string $name
     * @return string
     */
    private function getMigrationTemplate($name) {
        $dbType = $this->db->getType();
        
        return <<<PHP
<?php
/**
 * Migration: $name
 * 
 * Created: {date('Y-m-d H:i:s')}
 * Database: $dbType
 */

return [
    /**
     * Run the migration (UP)
     */
    'up' => [
        "-- Adicione seu SQL aqui
        -- CREATE TABLE exemplo (...)",
    ],
    
    /**
     * Reverse the migration (DOWN)
     */
    'down' => [
        "-- Reverta as mudanças aqui
        -- DROP TABLE IF EXISTS exemplo",
    ],
];
PHP;
    }
}

// =====================================
// CLI HELPER
// =====================================

/**
 * Executa comando de migration via CLI
 * 
 * Uso: php migrate.php [comando] [args]
 * 
 * Comandos:
 *   run      - Executa migrations pendentes
 *   rollback - Reverte último batch
 *   reset    - Reverte todas as migrations
 *   refresh  - Reset + run
 *   status   - Mostra status das migrations
 *   create   - Cria nova migration
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/../../bootstrap.php';
    
    $migration = new Migration();
    
    $command = $argv[1] ?? 'status';
    
    try {
        switch ($command) {
            case 'run':
            case 'migrate':
                $migration->run();
                break;
                
            case 'rollback':
                $migration->rollback();
                break;
                
            case 'reset':
                $migration->reset();
                break;
                
            case 'refresh':
                $migration->refresh();
                break;
                
            case 'status':
                $migration->status();
                break;
                
            case 'create':
                $name = $argv[2] ?? null;
                if (!$name) {
                    echo "❌ Erro: especifique o nome da migration.\n";
                    echo "Uso: php migrate.php create nome_da_migration\n";
                    exit(1);
                }
                $migration->create($name);
                break;
                
            default:
                echo "❌ Comando desconhecido: $command\n\n";
                echo "Comandos disponíveis:\n";
                echo "  run      - Executa migrations pendentes\n";
                echo "  rollback - Reverte último batch\n";
                echo "  reset    - Reverte todas as migrations\n";
                echo "  refresh  - Reset + run\n";
                echo "  status   - Mostra status das migrations\n";
                echo "  create   - Cria nova migration\n";
                exit(1);
        }
        
    } catch (Exception $e) {
        echo "\n❌ ERRO: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}