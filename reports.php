<?php
/**
 * REPORTS PAGE v2.2 - Página de Relatórios (HTML)
 * 
 * Salve este arquivo como: reports.php
 * Agora gera HTML otimizado para impressão (Ctrl+P → Salvar como PDF)
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'pdf_generator.php';

// Requer login
Auth::requireLogin();

$db = new Database();
$userId = Auth::getUserId();

$message = '';
$error = '';

// Processar ações
$action = $_GET['action'] ?? '';

if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sessionId = $_POST['session_id'] ?? null;
        
        $pdfGen = new PDFGenerator();
        $result = $pdfGen->generateProgressReport($userId, $sessionId);
        
        // Redirecionar para o relatório HTML
        if ($result['type'] === 'html') {
            header('Location: ' . $result['url']);
            exit;
        } else {
            throw new Exception("Erro ao gerar relatório.");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar estatísticas do usuário
$stats = $db->getUserStatistics($userId);
$sessions = $db->getUserSessions($userId, 20);
$history = $db->getProgressHistory($userId, 30);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Sistema RAG de Estudos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen p-4">
    
    <?php
    // Buscar contagem de usuários pendentes (apenas para admins)
    $pendingUsersCount = 0;
    if (Auth::isAdmin()) {
        $pendingUsersCount = $db->countPendingUsers();
    }
    ?>

    <!-- Header -->
    <div class="max-w-4xl mx-auto mb-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
            <div class="flex items-center gap-4">
                <span>👤 <?= htmlspecialchars(Auth::getUserName()) ?></span>
                <span>⏱️ <?= Auth::getSessionDuration() ?></span>
                
                <!-- Seletor de Provedor (apenas em index.php) -->
                <?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
                <form method="POST" action="?action=change_provider" class="inline-flex items-center gap-2" onsubmit="showLoading('Alterando provedor...')">
                    <span>🤖</span>
                    <select name="provider" onchange="this.form.submit()" class="bg-white/20 border border-white/30 rounded px-3 py-1 text-white text-sm focus:outline-none focus:ring-2 focus:ring-white/50 [&_option]:text-black [&_option]:bg-white">
                        <?php foreach (getAvailableProviders() as $key => $name): ?>
                            <option value="<?= $key ?>" <?= getCurrentProvider() === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2">
                <?php if (Auth::isAdmin()): ?>
                    <a href="admin_users.php" class="px-3 py-1 bg-purple-500/80 hover:bg-purple-600 rounded transition-colors flex items-center gap-1">
                        👑 Admin
                        <?php if ($pendingUsersCount > 0): ?>
                            <span class="ml-1 px-2 py-0.5 bg-orange-500 text-white rounded-full text-xs font-bold">
                                <?= $pendingUsersCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                
                <?php if (isset($userSessionsCount) && $userSessionsCount > 0): ?>
                    <a href="sessions.php" class="px-3 py-1 bg-indigo-500/80 hover:bg-indigo-600 rounded transition-colors flex items-center gap-1">
                        📚 Sessões (<?= $userSessionsCount ?>)
                    </a>
                <?php endif; ?>
                
                <a href="reports.php" class="px-3 py-1 bg-blue-500/80 hover:bg-blue-600 rounded transition-colors">
                    📊 Relatórios
                </a>
                
                <form method="POST" action="logout.php" class="inline">
                    <button type="submit" class="px-3 py-1 bg-red-500/80 hover:bg-red-600 rounded transition-colors">
                        Sair →
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="max-w-6xl mx-auto">
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                ✗ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Título -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3 mb-2">
                📊 Relatórios e Estatísticas
            </h1>
            <p class="text-gray-600">Visualize seu progresso e gere relatórios em HTML para impressão</p>
            
            <!-- Instruções -->
            <div class="mt-4 p-4 bg-blue-50 border-l-4 border-blue-400 rounded">
                <div class="flex items-start gap-2">
                    <span class="text-2xl">💡</span>
                    <div>
                        <p class="text-sm font-semibold text-blue-800 mb-1">Como salvar como PDF:</p>
                        <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                            <li>Clique em "Gerar Relatório"</li>
                            <li>Na página do relatório, pressione <kbd class="px-2 py-1 bg-blue-200 rounded">Ctrl+P</kbd> ou clique no botão "Imprimir"</li>
                            <li>Escolha "Salvar como PDF" como impressora</li>
                            <li>Salve o arquivo no seu computador</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg">
                <div class="text-4xl font-bold"><?= $stats['total_sessions'] ?? 0 ?></div>
                <div class="text-sm opacity-90">Sessões de Estudo</div>
            </div>
            
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg">
                <div class="text-4xl font-bold"><?= $stats['total_questions'] ?? 0 ?></div>
                <div class="text-sm opacity-90">Questões Respondidas</div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg">
                <?php
                $totalQ = $stats['total_questions'] ?? 0;
                $correctQ = $stats['total_correct'] ?? 0;
                $percentage = $totalQ > 0 ? round(($correctQ / $totalQ) * 100, 1) : 0;
                ?>
                <div class="text-4xl font-bold"><?= $percentage ?>%</div>
                <div class="text-sm opacity-90">Taxa de Acerto</div>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg">
                <?php
                $seconds = $stats['total_study_time'] ?? 0;
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                $timeStr = $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
                ?>
                <div class="text-4xl font-bold"><?= $timeStr ?></div>
                <div class="text-sm opacity-90">Tempo de Estudo</div>
            </div>
        </div>

        <!-- Gráfico de Progresso -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📈 Progresso nos Últimos 30 Dias</h2>
            <canvas id="progressChart" height="80"></canvas>
        </div>

        <!-- Gerar Relatórios -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📄 Gerar Relatório HTML</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Relatório Geral -->
                <div class="border-2 border-indigo-200 rounded-lg p-6 hover:border-indigo-400 transition-colors">
                    <h3 class="font-bold text-lg text-gray-800 mb-2">📊 Relatório Completo</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Todas as suas estatísticas, progresso e desempenho por tópico.
                    </p>
                    <form method="POST" action="?action=generate" target="_blank">
                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-bold hover:shadow-lg transition-all">
                            🖨️ Gerar Relatório Completo
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">
                        ℹ️ Abre em nova aba. Use Ctrl+P para salvar como PDF.
                    </p>
                </div>

                <!-- Relatório por Sessão -->
                <div class="border-2 border-indigo-200 rounded-lg p-6 hover:border-indigo-400 transition-colors">
                    <h3 class="font-bold text-lg text-gray-800 mb-2">📚 Relatório por Sessão</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Escolha uma sessão específica para gerar relatório detalhado.
                    </p>
                    <form method="POST" action="?action=generate" target="_blank">
                        <select name="session_id" class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg mb-3 focus:border-indigo-500">
                            <option value="">Selecione uma sessão</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session['id'] ?>">
                                    <?= htmlspecialchars($session['pdf_name']) ?> 
                                    (<?= date('d/m/Y', strtotime($session['created_at'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-lg font-bold hover:shadow-lg transition-all">
                            🖨️ Gerar Relatório da Sessão
                        </button>
                    </form>
                    <p class="text-xs text-gray-500 mt-2">
                        ℹ️ Abre em nova aba. Use Ctrl+P para salvar como PDF.
                    </p>
                </div>
            </div>
        </div>

        <!-- Histórico de Sessões -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📚 Minhas Sessões de Estudo</h2>
            
            <?php if (empty($sessions)): ?>
                <p class="text-gray-600 text-center py-8">Você ainda não iniciou nenhuma sessão de estudo.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Material</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Criado em</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Última Atualização</th>
                                <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($session['pdf_name']) ?></td>
                                    <td class="px-4 py-3 text-sm"><?= date('d/m/Y H:i', strtotime($session['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-sm"><?= date('d/m/Y H:i', strtotime($session['updated_at'])) ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" action="?action=generate" class="inline" target="_blank">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <button type="submit" class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 text-sm font-semibold">
                                                📄 Relatório
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Gráfico de progresso
        const ctx = document.getElementById('progressChart');
        
        const historyData = <?= json_encode($history) ?>;
        
        const labels = historyData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        });
        
        const questionsData = historyData.map(d => parseInt(d.questions));
        const correctData = historyData.map(d => parseInt(d.correct));
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Questões Respondidas',
                        data: questionsData,
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Questões Corretas',
                        data: correctData,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>

</body>
</html>