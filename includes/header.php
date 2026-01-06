<?php
/**
 * HEADER.PHP - Header Universal do Sistema
 * 
 * Salve como: includes/header.php
 * Ou use diretamente nas páginas
 */

// Buscar contagem de usuários pendentes (apenas para admins)
$pendingUsersCount = 0;
if (Auth::isAdmin()) {
    if (!isset($db)) {
        $db = new Database();
    }
    $pendingUsersCount = $db->countPendingUsers();
}

// Contar sessões do usuário (se não estiver definido)
if (!isset($userSessionsCount)) {
    if (!isset($db)) {
        $db = new Database();
    }
    $userSessionsCount = count($db->getUserSessions(Auth::getUserId(), 100));
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Header Universal -->
<div class="max-w-7xl mx-auto mb-4">
    <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
        <!-- Lado Esquerdo: Info do Usuário -->
        <div class="flex items-center gap-4">
            <span>👤 <?= htmlspecialchars(Auth::getUserName()) ?></span>
            <span>⏱️ <?= Auth::getSessionDuration() ?></span>
            
            <!-- Seletor de Provedor (apenas em index.php) -->
            <?php if ($currentPage === 'index.php'): ?>
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
        
        <!-- Lado Direito: Menu de Navegação -->
        <div class="flex items-center gap-2">
            <!-- Menu Admin -->
            <?php if (Auth::isAdmin()): ?>
                <!-- Dropdown Admin -->
                <div class="relative group">
                    <button class="px-3 py-1 bg-purple-500/80 hover:bg-purple-600 rounded transition-colors flex items-center gap-1">
                        👑 Admin
                        <?php if ($pendingUsersCount > 0): ?>
                            <span class="ml-1 px-2 py-0.5 bg-orange-500 text-white rounded-full text-xs font-bold">
                                <?= $pendingUsersCount ?>
                            </span>
                        <?php endif; ?>
                        <span class="ml-1">▼</span>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                        <div class="py-2">
                            <a href="admin_users.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-purple-50 transition-colors">
                                <span>👥</span>
                                <span class="font-semibold">Gerenciar Usuários</span>
                                <?php if ($pendingUsersCount > 0): ?>
                                    <span class="ml-auto px-2 py-0.5 bg-orange-500 text-white rounded-full text-xs font-bold">
                                        <?= $pendingUsersCount ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            
                            <a href="admin_prompts.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-indigo-50 transition-colors">
                                <span>🎯</span>
                                <span class="font-semibold">Editor de Prompts</span>
                                <span class="ml-auto text-xs text-gray-500">Novo!</span>
                            </a>
                            
                            <div class="border-t border-gray-200 my-1"></div>
                            
                            <a href="reports.php" class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-blue-50 transition-colors">
                                <span>📊</span>
                                <span class="font-semibold">Relatórios Gerais</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Sessões (se houver) -->
            <?php if ($userSessionsCount > 0): ?>
                <a href="sessions.php" class="px-3 py-1 bg-indigo-500/80 hover:bg-indigo-600 rounded transition-colors flex items-center gap-1">
                    📚 Sessões (<?= $userSessionsCount ?>)
                </a>
            <?php endif; ?>
            
            <!-- Relatórios -->
            <a href="reports.php" class="px-3 py-1 bg-blue-500/80 hover:bg-blue-600 rounded transition-colors">
                📊 Relatórios
            </a>
            
            <!-- Logout -->
            <form method="POST" action="logout.php" class="inline">
                <button type="submit" class="px-3 py-1 bg-red-500/80 hover:bg-red-600 rounded transition-colors">
                    Sair →
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* Dropdown hover effect */
.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

.group:hover .group-hover\:visible {
    visibility: visible;
}

/* Animação suave do dropdown */
.group > div {
    transform: translateY(-10px);
    transition: all 0.2s ease-in-out;
}

.group:hover > div {
    transform: translateY(0);
}
</style>