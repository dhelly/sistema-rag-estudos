<?php
/**
 * ADMIN_USERS.PHP - Painel de Gerenciamento de Usuários
 * 
 * Crie este arquivo como: admin_users.php
 * Apenas administradores podem acessar
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';

// Requer ser admin
Auth::requireAdmin();

$db = new Database();
$userId = Auth::getUserId();

$message = '';
$error = '';

// Processar ações
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'activate':
                if (isset($_POST['user_id'])) {
                    $targetUserId = (int)$_POST['user_id'];
                    if ($db->activateUser($targetUserId, $userId)) {
                        $user = $db->getUserById($targetUserId);
                        $message = "Usuário {$user['name']} foi ativado com sucesso!";
                    } else {
                        throw new Exception("Erro ao ativar usuário.");
                    }
                }
                break;
                
            case 'deactivate':
                if (isset($_POST['user_id'])) {
                    $targetUserId = (int)$_POST['user_id'];
                    
                    // Não pode desativar a si mesmo
                    if ($targetUserId == $userId) {
                        throw new Exception("Você não pode desativar sua própria conta!");
                    }
                    
                    if ($db->deactivateUser($targetUserId)) {
                        $user = $db->getUserById($targetUserId);
                        $message = "Usuário {$user['name']} foi desativado.";
                    } else {
                        throw new Exception("Erro ao desativar usuário.");
                    }
                }
                break;
                
            case 'toggle_admin':
                if (isset($_POST['user_id']) && isset($_POST['is_admin'])) {
                    $targetUserId = (int)$_POST['user_id'];
                    $isAdmin = $_POST['is_admin'] === '1';
                    
                    // Não pode remover admin de si mesmo
                    if ($targetUserId == $userId && !$isAdmin) {
                        throw new Exception("Você não pode remover seu próprio privilégio de administrador!");
                    }
                    
                    if ($db->toggleAdmin($targetUserId, $isAdmin)) {
                        $status = $isAdmin ? 'promovido a' : 'removido de';
                        $user = $db->getUserById($targetUserId);
                        $message = "Usuário {$user['name']} foi {$status} administrador.";
                    } else {
                        throw new Exception("Erro ao alterar privilégios.");
                    }
                }
                break;
                
            case 'delete':
                if (isset($_POST['user_id'])) {
                    $targetUserId = (int)$_POST['user_id'];
                    
                    // Não pode excluir a si mesmo
                    if ($targetUserId == $userId) {
                        throw new Exception("Você não pode excluir sua própria conta!");
                    }
                    
                    $user = $db->getUserById($targetUserId);
                    if ($db->deleteUser($targetUserId)) {
                        $message = "Usuário {$user['name']} foi excluído permanentemente.";
                    } else {
                        throw new Exception("Erro ao excluir usuário.");
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar todos os usuários
$allUsers = $db->getAllUsers('created_at', 'DESC');

// Buscar usuários pendentes
$pendingUsers = $db->getPendingUsers();
$pendingCount = count($pendingUsers);

// Contar por status
$activeCount = 0;
$inactiveCount = 0;
$adminCount = 0;

foreach ($allUsers as $user) {
    if ($user['active']) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if ($user['is_admin']) {
        $adminCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Usuários - Sistema RAG</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen p-4">
    
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
            <div class="flex items-center gap-4">
                <a href="index.php" class="hover:text-indigo-200 flex items-center gap-1">
                    ← Voltar
                </a>
                <span>👤 <?= htmlspecialchars(Auth::getUserName()) ?> (Admin)</span>
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
        
        <!-- Título -->
        <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                        👥 Administração de Usuários
                    </h1>
                    <p class="text-gray-600 mt-2">
                        Gerencie usuários, ative novos cadastros e controle permissões
                    </p>
                </div>
                <?php if ($pendingCount > 0): ?>
                    <div class="px-6 py-3 bg-orange-100 border-2 border-orange-300 rounded-xl">
                        <div class="text-2xl font-bold text-orange-700"><?= $pendingCount ?></div>
                        <div class="text-xs text-orange-600 font-semibold">Aguardando Aprovação</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-5 rounded-xl border-2 border-blue-200">
                    <div class="text-3xl font-bold text-blue-700"><?= count($allUsers) ?></div>
                    <div class="text-sm text-blue-600 font-semibold">Total de Usuários</div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-5 rounded-xl border-2 border-green-200">
                    <div class="text-3xl font-bold text-green-700"><?= $activeCount ?></div>
                    <div class="text-sm text-green-600 font-semibold">Usuários Ativos</div>
                </div>
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-5 rounded-xl border-2 border-orange-200">
                    <div class="text-3xl font-bold text-orange-700"><?= $inactiveCount ?></div>
                    <div class="text-sm text-orange-600 font-semibold">Aguardando Ativação</div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-5 rounded-xl border-2 border-purple-200">
                    <div class="text-3xl font-bold text-purple-700"><?= $adminCount ?></div>
                    <div class="text-sm text-purple-600 font-semibold">Administradores</div>
                </div>
            </div>
        </div>

        <!-- Usuários Pendentes -->
        <?php if (!empty($pendingUsers)): ?>
            <div class="bg-white rounded-2xl shadow-2xl p-8 mb-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="text-3xl">⏳</div>
                    <div>
                        <h2 class="text-2xl font-bold text-orange-700">
                            Usuários Aguardando Aprovação
                        </h2>
                        <p class="text-sm text-orange-600">
                            Novos cadastros que precisam de sua aprovação para acessar o sistema
                        </p>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <?php foreach ($pendingUsers as $user): ?>
                        <div class="bg-orange-50 border-2 border-orange-300 rounded-xl p-6 hover:shadow-lg transition-shadow">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-800 mb-1">
                                        <?= htmlspecialchars($user['name']) ?>
                                    </h3>
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <span>📧 <?= htmlspecialchars($user['email']) ?></span>
                                        <span>📅 Cadastrado em <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="?action=activate" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg font-bold hover:bg-green-700 transition-colors">
                                            ✓ Ativar
                                        </button>
                                    </form>
                                    <button 
                                        onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>')"
                                        class="px-4 py-3 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors"
                                        title="Rejeitar e excluir"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Todos os Usuários -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="text-3xl">👥</div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        Todos os Usuários
                    </h2>
                    <p class="text-sm text-gray-600">
                        Gerencie status, permissões e acesso dos usuários
                    </p>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Usuário</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Tipo</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Último Acesso</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-700">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr class="border-b hover:bg-gray-50 <?= $user['id'] == $userId ? 'bg-indigo-50' : '' ?>">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-800">
                                        <?= htmlspecialchars($user['name']) ?>
                                        <?php if ($user['id'] == $userId): ?>
                                            <span class="ml-2 text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded">você</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($user['email']) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($user['active']): ?>
                                        <span class="inline-block px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-bold">
                                            ✓ Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-bold">
                                            ⏳ Pendente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($user['is_admin']): ?>
                                        <span class="inline-block px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-bold">
                                            👑 Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-bold">
                                            👤 Usuário
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca' ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <?php if ($user['active']): ?>
                                            <?php if ($user['id'] != $userId): ?>
                                                <form method="POST" action="?action=deactivate" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="px-3 py-1 bg-orange-100 text-orange-700 rounded hover:bg-orange-200 text-xs font-semibold" title="Desativar">
                                                        🔒 Desativar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" action="?action=activate" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 text-xs font-semibold" title="Ativar">
                                                    ✓ Ativar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (!$user['is_admin']): ?>
                                            <form method="POST" action="?action=toggle_admin" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="is_admin" value="1">
                                                <button type="submit" class="px-3 py-1 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 text-xs font-semibold" title="Tornar Admin">
                                                    👑 Admin
                                                </button>
                                            </form>
                                        <?php elseif ($user['id'] != $userId): ?>
                                            <form method="POST" action="?action=toggle_admin" class="inline">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="is_admin" value="0">
                                                <button type="submit" class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 text-xs font-semibold" title="Remover Admin">
                                                    Remover Admin
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['id'] != $userId): ?>
                                            <button 
                                                onclick="confirmDelete(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>')"
                                                class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 text-xs font-semibold"
                                                title="Excluir usuário"
                                            >
                                                🗑️
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Informações -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 mt-6 text-white">
            <h3 class="text-lg font-bold mb-3 flex items-center gap-2">
                💡 Informações Importantes
            </h3>
            <ul class="space-y-2 text-sm">
                <li class="flex items-start gap-2">
                    <span>🔒</span>
                    <span>Usuários precisam ser <strong>ativados manualmente</strong> antes de acessar o sistema</span>
                </li>
                <li class="flex items-start gap-2">
                    <span>⚠️</span>
                    <span>Você não pode desativar ou remover admin da sua própria conta</span>
                </li>
                <li class="flex items-start gap-2">
                    <span>🗑️</span>
                    <span>Excluir um usuário remove permanentemente todos os seus dados (sessões, questões, progresso)</span>
                </li>
                <li class="flex items-start gap-2">
                    <span>👑</span>
                    <span>Administradores podem gerenciar usuários e acessar todos os recursos</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
            <div class="text-center mb-6">
                <div class="text-6xl mb-4">⚠️</div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">
                    Excluir Usuário?
                </h3>
                <p class="text-gray-600" id="deleteMessage">
                    Esta ação não pode ser desfeita. Todos os dados serão perdidos.
                </p>
            </div>
            
            <form method="POST" action="?action=delete" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
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
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteMessage').textContent = 
                `Tem certeza que deseja excluir "${userName}"? Todos os dados de estudo (sessões, questões, progresso) serão perdidos permanentemente.`;
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