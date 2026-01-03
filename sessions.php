<?php
/**
 * SESSIONS.PHP v2.3 - Gerenciador de Sessões de Estudo
 * 
 * Salve este arquivo como: sessions.php
 * Página dedicada para visualizar e gerenciar todas as sessões
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

// Requer login
Auth::requireLogin();

$db = new Database();
$userId = Auth::getUserId();

$message = '';
$error = '';

// Processar ações
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'select_session':
                if (isset($_POST['session_id'])) {
                    $sessionId = (int)$_POST['session_id'];
                    
                    // Verificar se sessão pertence ao usuário
                    $session = $db->getSession($sessionId);
                    if ($session && $session['user_id'] == $userId) {
                        $_SESSION['session_id'] = $sessionId;
                        
                        // Limpar questão atual e feedbacks ao trocar de sessão
                        unset($_SESSION['current_question']);
                        unset($_SESSION['last_answer']);
                        unset($_SESSION['challenge_result']);
                        
                        // Redirecionar para index
                        header('Location: index.php');
                        exit;
                    } else {
                        throw new Exception("Sessão não encontrada ou sem permissão.");
                    }
                }
                break;
                
            case 'delete_session':
                if (isset($_POST['session_id'])) {
                    $sessionId = (int)$_POST['session_id'];
                    
                    // Verificar se sessão pertence ao usuário
                    $session = $db->getSession($sessionId);
                    if ($session && $session['user_id'] == $userId) {
                        $db->deleteSession($sessionId);
                        $message = "Sessão excluída com sucesso!";
                        
                        // Se era a sessão ativa, limpar
                        if (isset($_SESSION['session_id']) && $_SESSION['session_id'] == $sessionId) {
                            unset($_SESSION['session_id']);
                            unset($_SESSION['current_question']);
                            unset($_SESSION['last_answer']);
                            unset($_SESSION['challenge_result']);
                        }
                    } else {
                        throw new Exception("Sessão não encontrada ou sem permissão.");
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar todas as sessões do usuário
$allSessions = $db->getUserSessionsWithProgress($userId, 100);

// Agrupar por nível de dificuldade
$sessionsByLevel = [
    'critical' => [], // Nível 1-2
    'attention' => [], // Nível 3
    'good' => [] // Nível 4-5
];

foreach ($allSessions as $sess) {
    if ($sess['difficulty_level'] <= 2) {
        $sessionsByLevel['critical'][] = $sess;
    } elseif ($sess['difficulty_level'] == 3) {
        $sessionsByLevel['attention'][] = $sess;
    } else {
        $sessionsByLevel['good'][] = $sess;
    }
}

// Estatísticas gerais
$totalSessions = count($allSessions);
$totalQuestions = array_sum(array_column($allSessions, 'total_answers'));
$totalCorrect = array_sum(array_column($allSessions, 'correct_answers'));
$avgPercentage = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Sessões - Sistema RAG de Estudos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .session-card {
            transition: all 0.3s ease;
        }
        .session-card:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen p-4">
    
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
            <div class="flex items-center gap-4">
                <a href="index.php" class="hover:text-indigo-200 flex items-center gap-1">
                    ← Voltar ao Estudo
                </a>
                <span>👤 <?= htmlspecialchars(Auth::getUserName()) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <a href="reports.php" class="px-3 py-1 bg-blue-500/80 hover:bg-blue-600 rounded transition-colors">
                    📊 Relatórios
                </a>
                <form method="POST" action="logout.php" class="inline">
                    <button type="submit" class="px-3 py-1 bg-red-500/80 hover:bg-red-600 rounded transition-colors">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto">
        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded">
                ✓ <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                ✗ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Título e Estatísticas Gerais -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                        📚 Minhas Sessões de Estudo
                    </h1>
                    <p class="text-gray-600 mt-2">
                        Gerencie todas as suas sessões de estudo em um só lugar
                    </p>
                </div>
                <a href="index.php" class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold hover:shadow-lg transition-all">
                    + Nova Sessão
                </a>
            </div>
            
            <!-- Cards de Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-5 rounded-xl border-2 border-blue-200">
                    <div class="text-3xl font-bold text-blue-700"><?= $totalSessions ?></div>
                    <div class="text-sm text-blue-600 font-semibold">Sessões Criadas</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-5 rounded-xl border-2 border-green-200">
                    <div class="text-3xl font-bold text-green-700"><?= $totalQuestions ?></div>
                    <div class="text-sm text-green-600 font-semibold">Questões Respondidas</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-5 rounded-xl border-2 border-purple-200">
                    <div class="text-3xl font-bold text-purple-700"><?= $avgPercentage ?>%</div>
                    <div class="text-sm text-purple-600 font-semibold">Taxa Média de Acerto</div>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-5 rounded-xl border-2 border-orange-200">
                    <div class="text-3xl font-bold text-orange-700"><?= count($sessionsByLevel['critical']) ?></div>
                    <div class="text-sm text-orange-600 font-semibold">Precisam de Atenção</div>
                </div>
            </div>
        </div>

        <?php if (empty($allSessions)): ?>
            <!-- Nenhuma Sessão -->
            <div class="bg-white rounded-2xl shadow-2xl p-12 text-center">
                <div class="text-6xl mb-4">📖</div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    Você ainda não tem sessões de estudo
                </h2>
                <p class="text-gray-600 mb-6">
                    Comece criando sua primeira sessão para começar a estudar!
                </p>
                <a href="index.php" class="inline-block px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg transition-all">
                    Criar Primeira Sessão →
                </a>
            </div>
        <?php else: ?>
            
            <!-- Sessões Críticas (Nível 1-2) -->
            <?php if (!empty($sessionsByLevel['critical'])): ?>
                <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="text-3xl">🔴</div>
                        <div>
                            <h2 class="text-2xl font-bold text-red-700">
                                Precisam de Atenção Urgente
                            </h2>
                            <p class="text-sm text-red-600">
                                Nível 1-2: Foque nestas sessões para melhorar seu desempenho
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($sessionsByLevel['critical'] as $sess): 
                            $percentage = $sess['total_answers'] > 0 
                                ? round(($sess['correct_answers'] / $sess['total_answers']) * 100) 
                                : 0;
                        ?>
                            <div class="session-card bg-red-50 border-2 border-red-300 rounded-xl p-6 hover:shadow-xl">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                                            <?= htmlspecialchars($sess['pdf_name']) ?>
                                        </h3>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <span class="flex items-center gap-1">
                                                <span class="text-red-600">⚡</span>
                                                Nível <?= $sess['difficulty_level'] ?>/5
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <span class="text-green-600">✓</span>
                                                <?= $sess['correct_answers'] ?>/<?= $sess['total_answers'] ?>
                                            </span>
                                            <span class="px-2 py-1 bg-red-200 text-red-800 rounded-full text-xs font-bold">
                                                <?= $percentage ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-xs text-gray-500 mb-4">
                                    📅 Criado em <?= date('d/m/Y', strtotime($sess['created_at'])) ?>
                                    • Última atividade: <?= date('d/m/Y', strtotime($sess['updated_at'])) ?>
                                </div>
                                
                                <div class="flex gap-2">
                                    <form method="POST" action="?action=select_session" class="flex-1">
                                        <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg font-bold hover:shadow-lg transition-all">
                                            🎯 Estudar Agora
                                        </button>
                                    </form>
                                    <button 
                                        onclick="confirmDelete(<?= $sess['id'] ?>, '<?= htmlspecialchars(addslashes($sess['pdf_name'])) ?>')"
                                        class="px-4 py-3 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors"
                                        title="Excluir sessão"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sessões de Atenção (Nível 3) -->
            <?php if (!empty($sessionsByLevel['attention'])): ?>
                <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="text-3xl">🟡</div>
                        <div>
                            <h2 class="text-2xl font-bold text-yellow-700">
                                Precisam de Reforço
                            </h2>
                            <p class="text-sm text-yellow-600">
                                Nível 3: Continue praticando para alcançar a excelência
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($sessionsByLevel['attention'] as $sess): 
                            $percentage = $sess['total_answers'] > 0 
                                ? round(($sess['correct_answers'] / $sess['total_answers']) * 100) 
                                : 0;
                        ?>
                            <div class="session-card bg-yellow-50 border-2 border-yellow-300 rounded-xl p-6 hover:shadow-xl">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                                            <?= htmlspecialchars($sess['pdf_name']) ?>
                                        </h3>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <span class="flex items-center gap-1">
                                                <span class="text-yellow-600">⚡</span>
                                                Nível <?= $sess['difficulty_level'] ?>/5
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <span class="text-green-600">✓</span>
                                                <?= $sess['correct_answers'] ?>/<?= $sess['total_answers'] ?>
                                            </span>
                                            <span class="px-2 py-1 bg-yellow-200 text-yellow-800 rounded-full text-xs font-bold">
                                                <?= $percentage ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-xs text-gray-500 mb-4">
                                    📅 Criado em <?= date('d/m/Y', strtotime($sess['created_at'])) ?>
                                    • Última atividade: <?= date('d/m/Y', strtotime($sess['updated_at'])) ?>
                                </div>
                                
                                <div class="flex gap-2">
                                    <form method="POST" action="?action=select_session" class="flex-1">
                                        <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-lg font-bold hover:shadow-lg transition-all">
                                            📖 Continuar
                                        </button>
                                    </form>
                                    <button 
                                        onclick="confirmDelete(<?= $sess['id'] ?>, '<?= htmlspecialchars(addslashes($sess['pdf_name'])) ?>')"
                                        class="px-4 py-3 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition-colors"
                                        title="Excluir sessão"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sessões Boas (Nível 4-5) -->
            <?php if (!empty($sessionsByLevel['good'])): ?>
                <div class="bg-white rounded-2xl shadow-2xl p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="text-3xl">🟢</div>
                        <div>
                            <h2 class="text-2xl font-bold text-green-700">
                                Excelente Desempenho
                            </h2>
                            <p class="text-sm text-green-600">
                                Nível 4-5: Continue mantendo este ritmo de estudos
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($sessionsByLevel['good'] as $sess): 
                            $percentage = $sess['total_answers'] > 0 
                                ? round(($sess['correct_answers'] / $sess['total_answers']) * 100) 
                                : 0;
                        ?>
                            <div class="session-card bg-green-50 border-2 border-green-300 rounded-xl p-6 hover:shadow-xl">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-bold text-gray-800 mb-2">
                                            <?= htmlspecialchars($sess['pdf_name']) ?>
                                        </h3>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <span class="flex items-center gap-1">
                                                <span class="text-green-600">⚡</span>
                                                Nível <?= $sess['difficulty_level'] ?>/5
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <span class="text-green-600">✓</span>
                                                <?= $sess['correct_answers'] ?>/<?= $sess['total_answers'] ?>
                                            </span>
                                            <span class="px-2 py-1 bg-green-200 text-green-800 rounded-full text-xs font-bold">
                                                <?= $percentage ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-xs text-gray-500 mb-4">
                                    📅 Criado em <?= date('d/m/Y', strtotime($sess['created_at'])) ?>
                                    • Última atividade: <?= date('d/m/Y', strtotime($sess['updated_at'])) ?>
                                </div>
                                
                                <div class="flex gap-2">
                                    <form method="POST" action="?action=select_session" class="flex-1">
                                        <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-bold hover:shadow-lg transition-all">
                                            ✨ Revisar
                                        </button>
                                    </form>
                                    <button 
                                        onclick="confirmDelete(<?= $sess['id'] ?>, '<?= htmlspecialchars(addslashes($sess['pdf_name'])) ?>')"
                                        class="px-4 py-3 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors"
                                        title="Excluir sessão"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- Dicas de Uso -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 mt-6 text-white">
            <h3 class="text-lg font-bold mb-3 flex items-center gap-2">
                💡 Dicas de Estudo
            </h3>
            <ul class="space-y-2 text-sm">
                <li class="flex items-start gap-2">
                    <span>🎯</span>
                    <span>Foque primeiro nas sessões marcadas em <strong class="text-red-300">vermelho</strong> - são as que mais precisam de atenção</span>
                </li>
                <li class="flex items-start gap-2">
                    <span>📈</span>
                    <span>O sistema ajusta automaticamente a dificuldade baseado no seu desempenho</span>
                </li>
                <li class="flex items-start gap-2">
                    <span>🔄</span>
                    <span>Revise periodicamente as sessões em <strong class="text-green-300">verde</strong> para manter o conhecimento fresco</span>
                </li>
                <li class="flex items-start gap-2">
                    <span>💰</span>
                    <span>Economize tokens reutilizando sessões existentes ao invés de criar novas</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 transform transition-all">
            <div class="text-center mb-6">
                <div class="text-6xl mb-4">⚠️</div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">
                    Excluir Sessão?
                </h3>
                <p class="text-gray-600" id="deleteMessage">
                    Esta ação não pode ser desfeita.
                </p>
            </div>
            
            <form method="POST" action="?action=delete_session" id="deleteForm">
                <input type="hidden" name="session_id" id="deleteSessionId">
                <div class="flex gap-3">
                    <button 
                        type="button" 
                        onclick="closeDeleteModal()"
                        class="flex-1 py-3 bg-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-300 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="submit"
                        class="flex-1 py-3 bg-red-600 text-white rounded-lg font-bold hover:bg-red-700 transition-colors"
                    >
                        Sim, Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(sessionId, sessionName) {
            document.getElementById('deleteSessionId').value = sessionId;
            document.getElementById('deleteMessage').textContent = 
                `Tem certeza que deseja excluir "${sessionName}"? Todo o progresso será perdido.`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>

</body>
</html>