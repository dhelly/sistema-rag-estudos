<?php
/**
 * INDEX.PHP v2.3.1 - Versão Simplificada
 * 
 * Listagem de sessões movida para sessions.php
 * Mantém apenas upload e área de estudo
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'api.php';
require_once 'challenge_agent.php';

Auth::requireLogin();

$db = new Database();
$api = new UnifiedAI();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];

// Processar ações
$action = $_GET['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'change_provider':
                if (isset($_POST['provider'])) {
                    $provider = $_POST['provider'];
                    $availableProviders = array_keys(getAvailableProviders());
                    
                    if (in_array($provider, $availableProviders)) {
                        setCurrentProvider($provider);
                        $api = new UnifiedAI($provider);
                        $message = "Provedor alterado para: " . getProviderConfig($provider)['name'];
                    }
                }
                break;
                
            case 'upload':
                if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
                    $pdfName = basename($_FILES['pdf']['name']);
                    $tmpPath = $_FILES['pdf']['tmp_name'];
                    
                    $pdfData = file_get_contents($tmpPath);
                    $base64Data = base64_encode($pdfData);
                    
                    $extractedText = $api->extractPDFText($base64Data);
                    $analysisText = $api->analyzeContent($extractedText);
                    $cleanJson = preg_replace('/```json|```/', '', $analysisText);
                    $analysis = json_decode(trim($cleanJson), true);
                    
                    if (!isset($analysis['coreTopics'])) {
                        throw new Exception("Erro ao analisar o PDF. Tente novamente.");
                    }
                    
                    $sessionId = $db->createSession($userId, $pdfName, $extractedText, $analysis['coreTopics']);
                    $_SESSION['session_id'] = $sessionId;
                    
                    $message = "PDF processado com sucesso: {$pdfName}";
                }
                break;
                
            case 'upload_text':
                if (isset($_POST['summary_text']) && !empty(trim($_POST['summary_text']))) {
                    $summaryText = trim($_POST['summary_text']);
                    $materialName = !empty($_POST['material_name']) 
                        ? trim($_POST['material_name']) 
                        : 'Resumo 80/20 - ' . date('d/m/Y H:i');
                    
                    $analysisText = $api->processPreSummarized($summaryText);
                    $cleanJson = preg_replace('/```json|```/', '', $analysisText);
                    $analysis = json_decode(trim($cleanJson), true);
                    
                    if (!isset($analysis['coreTopics'])) {
                        throw new Exception("Erro ao processar o resumo.");
                    }
                    
                    $sessionId = $db->createSession($userId, $materialName, $summaryText, $analysis['coreTopics']);
                    $_SESSION['session_id'] = $sessionId;
                    
                    $message = "Resumo processado com sucesso: {$materialName}";
                }
                break;
                
            case 'generate':
                if (isset($_SESSION['session_id'])) {
                    $sessionId = $_SESSION['session_id'];
                    $session = $db->getSession($sessionId);
                    $progress = $db->getProgress($sessionId);
                    
                    $weakPoints = $progress['weak_points'];
                    $topics = $session['core_topics'];
                    $selectedTopic = null;
                    
                    if (!empty($weakPoints) && rand(0, 100) > 30) {
                        $weakTopicId = $weakPoints[array_rand($weakPoints)];
                        foreach ($topics as $topic) {
                            if ($topic['id'] == $weakTopicId) {
                                $selectedTopic = $topic;
                                break;
                            }
                        }
                    }
                    
                    if (!$selectedTopic) {
                        $selectedTopic = $topics[array_rand($topics)];
                    }
                    
                    $isWeakPoint = in_array($selectedTopic['id'], $weakPoints);
                    
                    $questionText = $api->generateQuestion(
                        $session['pdf_content'],
                        $selectedTopic,
                        $progress['difficulty_level'],
                        $isWeakPoint
                    );
                    
                    $cleanJson = preg_replace('/```json|```/', '', $questionText);
                    $question = json_decode(trim($cleanJson), true);
                    
                    if (!isset($question['statement'])) {
                        throw new Exception("Erro ao gerar questão.");
                    }
                    
                    $questionId = $db->saveQuestion($sessionId, $userId, $question, $progress['difficulty_level']);
                    $_SESSION['current_question'] = $questionId;
                    
                    unset($_SESSION['last_answer']);
                    unset($_SESSION['challenge_result']);
                }
                break;
                
            case 'answer':
                if (isset($_SESSION['current_question']) && isset($_POST['answer'])) {
                    $questionId = $_SESSION['current_question'];
                    $userAnswer = $_POST['answer'] === 'true';
                    
                    $question = $db->getQuestion($questionId);
                    $db->answerQuestion($questionId, $userAnswer);
                    
                    $sessionId = $_SESSION['session_id'];
                    $progress = $db->getProgress($sessionId);
                    
                    $isCorrect = $userAnswer == $question['correct_answer'];
                    
                    $correct = $progress['correct_answers'] + ($isCorrect ? 1 : 0);
                    $total = $progress['total_answers'] + 1;
                    $difficulty = $progress['difficulty_level'];
                    $weakPoints = $progress['weak_points'];
                    
                    if ($isCorrect) {
                        $recentCorrect = $total >= 3 && ($correct / $total) >= 0.7;
                        if ($recentCorrect && $difficulty < 5) {
                            $difficulty++;
                        }
                        $weakPoints = array_values(array_diff($weakPoints, [$question['topic_id']]));
                    } else {
                        if ($difficulty > 1) {
                            $difficulty = max(1, $difficulty - 1);
                        }
                        if (!in_array($question['topic_id'], $weakPoints)) {
                            $weakPoints[] = $question['topic_id'];
                        }
                    }
                    
                    $db->updateProgress($sessionId, $correct, $total, $difficulty, $weakPoints);
                    
                    $_SESSION['last_answer'] = [
                        'correct' => $isCorrect,
                        'explanation' => $question['explanation'],
                        'question_id' => $questionId,
                        'correct_answer' => $question['correct_answer'],
                        'statement' => $question['statement']
                    ];
                    
                    unset($_SESSION['current_question']);
                }
                break;
                
            case 'challenge':
                if (isset($_POST['question_id']) && isset($_POST['argument'])) {
                    $questionId = $_POST['question_id'];
                    $argument = trim($_POST['argument']);
                    
                    if (empty($argument) || strlen($argument) < 20) {
                        throw new Exception("Argumentação deve ter pelo menos 20 caracteres.");
                    }
                    
                    $challengeAgent = new ChallengeAgent();
                    $result = $challengeAgent->processChallenge($questionId, $userId, $argument);
                    
                    $_SESSION['challenge_result'] = $result;
                    
                    if ($result['decision'] === 'accepted') {
                        $message = "✅ Questionamento ACEITO!";
                    } else {
                        $message = "📋 Questionamento analisado.";
                    }
                }
                break;
                
            case 'reset':
                unset($_SESSION['session_id']);
                unset($_SESSION['current_question']);
                unset($_SESSION['last_answer']);
                unset($_SESSION['challenge_result']);
                
                header('Location: index.php');
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Carregar dados da sessão atual
$session = null;
$progress = null;
$currentQuestion = null;
$lastAnswer = $_SESSION['last_answer'] ?? null;
$challengeResult = $_SESSION['challenge_result'] ?? null;

if (isset($_SESSION['session_id'])) {
    $session = $db->getSession($_SESSION['session_id']);
    $progress = $db->getProgress($_SESSION['session_id']);
    
    if (isset($_SESSION['current_question'])) {
        $currentQuestion = $db->getQuestion($_SESSION['current_question']);
    }
}

// Contar sessões do usuário
$userSessionsCount = count($db->getUserSessions($userId, 100));

?>

<?php
    // ==========================================
    // PREPARAÇÃO DO HEADER
    // ==========================================
    $pendingUsersCount = 0;
    if (Auth::isAdmin()) {
        if (!isset($db)) {
            $db = new Database();
        }
        $pendingUsersCount = $db->countPendingUsers();
    }

    if (!isset($userSessionsCount)) {
        if (!isset($db)) {
            $db = new Database();
        }
        $userSessionsCount = count($db->getUserSessions(Auth::getUserId(), 100));
    }

    $currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema RAG de Estudos Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #loadingOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            backdrop-filter: blur(5px);
        }
        
        #loadingOverlay.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 4px solid #fff;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .pulse-text {
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        body.loading {
            pointer-events: none;
        }
        
        body.loading #loadingOverlay {
            pointer-events: all;
        }

        /* Animação de slide down */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slideDown {
            animation: slideDown 0.2s ease-out;
        }

        /* Badge animado "Novo!" */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Rotação da seta */
        .rotate-180 {
            transform: rotate(180deg);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen p-4">
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="text-center">
            <div class="spinner mx-auto mb-6"></div>
            <div class="text-white text-xl font-bold pulse-text" id="loadingText">
                Processando...
            </div>
            <div class="text-indigo-200 text-sm mt-2">
                Por favor, aguarde
            </div>
        </div>
    </div>
    
    <!-- Header -->
    <?php
    // Buscar contagem de usuários pendentes (apenas para admins)
    $pendingUsersCount = 0;
    if (Auth::isAdmin()) {
        $pendingUsersCount = $db->countPendingUsers();
    }
    ?>

    <!-- ========================================== -->
    <!-- HEADER UNIVERSAL COM DROPDOWN CLICK -->
    <!-- ========================================== -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
            <!-- Lado Esquerdo -->
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
            
            <!-- Lado Direito -->
            <div class="flex items-center gap-2">
                <!-- DROPDOWN ADMIN (CLICK) -->
                <?php if (Auth::isAdmin()): ?>
                    <div class="relative" id="adminDropdown">
                        <button 
                            onclick="toggleAdminMenu(event)" 
                            class="px-3 py-1 bg-purple-500/80 hover:bg-purple-600 rounded transition-colors flex items-center gap-1 cursor-pointer"
                            id="adminMenuButton"
                        >
                            👑 Admin
                            <?php if ($pendingUsersCount > 0): ?>
                                <span class="ml-1 px-2 py-0.5 bg-orange-500 text-white rounded-full text-xs font-bold">
                                    <?= $pendingUsersCount ?>
                                </span>
                            <?php endif; ?>
                            <span class="ml-1 text-xs transition-transform" id="dropdownArrow">▼</span>
                        </button>
                        
                        <!-- Menu Dropdown -->
                        <div 
                            id="adminMenu" 
                            class="hidden absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-2xl z-50 border-2 border-purple-200 animate-slideDown"
                        >
                            <div class="py-2">
                                <!-- Gerenciar Usuários -->
                                <a href="admin_users.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-purple-50 transition-colors <?= $currentPage === 'admin_users.php' ? 'bg-purple-100 border-l-4 border-purple-600' : '' ?>">
                                    <span class="text-xl">👥</span>
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm">Gerenciar Usuários</div>
                                        <div class="text-xs text-gray-500">Ativar e administrar</div>
                                    </div>
                                    <?php if ($pendingUsersCount > 0): ?>
                                        <span class="px-2 py-1 bg-orange-500 text-white rounded-full text-xs font-bold">
                                            <?= $pendingUsersCount ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                
                                <!-- Editor de Prompts (NOVO!) -->
                                <a href="admin_prompts.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-indigo-50 transition-colors <?= $currentPage === 'admin_prompts.php' ? 'bg-indigo-100 border-l-4 border-indigo-600' : '' ?>">
                                    <span class="text-xl">🎯</span>
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm">Editor de Prompts</div>
                                        <div class="text-xs text-gray-500">Customizar por disciplina</div>
                                    </div>
                                    <span class="px-2 py-1 bg-green-500 text-white rounded-full text-xs font-bold animate-pulse">
                                        Novo!
                                    </span>
                                </a>
                                
                                <div class="border-t border-gray-200 my-1"></div>
                                
                                <!-- Relatórios Gerais -->
                                <a href="reports.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-blue-50 transition-colors">
                                    <span class="text-xl">📊</span>
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm">Relatórios do Sistema</div>
                                        <div class="text-xs text-gray-500">Visão geral completa</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Sessões -->
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

    
    <div class="max-w-4xl mx-auto">
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
        
        <?php if (!$session): ?>
            <!-- Tela de Upload Simplificada -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="text-center mb-8">
                    <div class="text-6xl mb-4">🧠</div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        Sistema RAG de Estudos Inteligente
                    </h1>
                    <p class="text-gray-600">
                        Baseado no Princípio de Pareto (80/20) com questões adaptativas estilo CESPE
                    </p>
                </div>

                <?php if ($userSessionsCount > 0): ?>
                    <!-- Link para Sessões Existentes -->
                    <div class="mb-8 p-6 bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="text-4xl">📚</div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800">
                                        Você tem <?= $userSessionsCount ?> <?= $userSessionsCount === 1 ? 'sessão' : 'sessões' ?> de estudo
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Continue de onde parou e economize tokens!
                                    </p>
                                </div>
                            </div>
                            <a href="sessions.php" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl font-bold hover:shadow-lg transition-all">
                                Ver Sessões →
                            </a>
                        </div>
                    </div>
                    
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center gap-3">
                            <div class="h-px flex-1 bg-gray-300 w-20"></div>
                            <span class="text-gray-500 font-semibold">ou crie uma nova</span>
                            <div class="h-px flex-1 bg-gray-300 w-20"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 mb-6">
                    <button onclick="showTab('pdf')" id="tab-pdf" class="flex-1 py-3 px-4 text-center font-semibold text-indigo-600 border-b-2 border-indigo-600">
                        📄 Upload de PDF
                    </button>
                    <button onclick="showTab('text')" id="tab-text" class="flex-1 py-3 px-4 text-center font-semibold text-gray-500 border-b-2 border-transparent hover:text-indigo-600">
                        📝 Resumo Pronto (80/20)
                    </button>
                </div>

                <!-- PDF Upload -->
                <div id="content-pdf">
                    <form method="POST" action="?action=upload" enctype="multipart/form-data" id="pdfUploadForm" class="border-4 border-dashed border-indigo-300 rounded-xl p-12 text-center hover:border-indigo-500 transition-colors">
                        <div class="text-5xl mb-4">📄</div>
                        <label class="cursor-pointer">
                            <span class="text-lg font-semibold text-indigo-600 hover:text-indigo-700">
                                Clique para fazer upload do PDF
                            </span>
                            <input type="file" name="pdf" accept="application/pdf" required class="hidden" onchange="handlePdfUpload(this)">
                        </label>
                        <p class="text-sm text-gray-500 mt-2">
                            O sistema identificará os 20% mais importantes do conteúdo
                        </p>
                        <p class="text-xs text-orange-600 mt-2">
                            ⚠️ Disponível apenas com Anthropic Claude
                        </p>
                    </form>
                </div>

                <!-- Text Upload -->
                <div id="content-text" class="hidden">
                    <form method="POST" action="?action=upload_text" onsubmit="showLoading('Processando resumo...')">
                        <textarea 
                            name="summary_text" 
                            rows="12" 
                            required
                            placeholder="Cole aqui o resumo 80/20..."
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 font-mono text-sm mb-4"
                        ></textarea>
                        
                        <input 
                            type="text" 
                            name="material_name" 
                            placeholder="Nome do material (opcional)"
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 mb-6"
                        >

                        <button 
                            type="submit" 
                            class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg transform hover:scale-105 transition-all"
                        >
                            Processar Resumo →
                        </button>
                    </form>
                </div>
            </div>

            <script>
                function showTab(tab) {
                    document.getElementById('content-pdf').classList.toggle('hidden', tab !== 'pdf');
                    document.getElementById('content-text').classList.toggle('hidden', tab !== 'text');
                    
                    ['pdf', 'text'].forEach(t => {
                        const tabEl = document.getElementById('tab-' + t);
                        if (t === tab) {
                            tabEl.classList.add('text-indigo-600', 'border-indigo-600');
                            tabEl.classList.remove('text-gray-500', 'border-transparent');
                        } else {
                            tabEl.classList.remove('text-indigo-600', 'border-indigo-600');
                            tabEl.classList.add('text-gray-500', 'border-transparent');
                        }
                    });
                }
                
                function handlePdfUpload(input) {
                    if (input.files && input.files[0]) {
                        showLoading('Extraindo texto do PDF...');
                        setTimeout(() => document.getElementById('pdfUploadForm').submit(), 100);
                    }
                }
            </script>
        <?php else: ?>
            <!-- A PARTIR DAQUI É A MESMA TELA DE ESTUDOS DO CÓDIGO ANTERIOR -->
            <!-- Cole aqui toda a parte de "Tela de Estudos" do código anterior -->
        <!-- Tela de Estudos -->
            <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            📚 <?= htmlspecialchars($session['pdf_name']) ?>
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            Focando nos 20% que geram 80% de resultados
                        </p>
                    </div>
                    <form method="POST" action="?action=reset">
                        <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm">
                            Trocar Sessão
                        </button>
                    </form>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-xl">
                        <div class="text-2xl font-bold text-green-700"><?= $progress['correct_answers'] ?></div>
                        <div class="text-xs text-green-600">Acertos</div>
                    </div>
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-xl">
                        <div class="text-2xl font-bold text-blue-700"><?= $progress['total_answers'] ?></div>
                        <div class="text-xs text-blue-600">Total</div>
                    </div>
                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-xl">
                        <div class="text-2xl font-bold text-purple-700">⚡ <?= $progress['difficulty_level'] ?></div>
                        <div class="text-xs text-purple-600">Nível</div>
                    </div>
                    <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-xl">
                        <div class="text-2xl font-bold text-orange-700">
                            <?= $progress['total_answers'] > 0 ? round(($progress['correct_answers'] / $progress['total_answers']) * 100) : 0 ?>%
                        </div>
                        <div class="text-xs text-orange-600">Aproveit.</div>
                    </div>
                </div>

                <?php if (!empty($progress['weak_points'])): ?>
                    <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded">
                        <div class="flex items-start gap-2">
                            <div class="text-yellow-600 text-xl">⚠️</div>
                            <div>
                                <div class="text-sm font-semibold text-yellow-800">
                                    Pontos que precisam de reforço:
                                </div>
                                <div class="text-xs text-yellow-700 mt-1">
                                    <?php
                                    $weakTopics = [];
                                    foreach ($progress['weak_points'] as $wpId) {
                                        foreach ($session['core_topics'] as $topic) {
                                            if ($topic['id'] == $wpId) {
                                                $weakTopics[] = $topic['title'];
                                            }
                                        }
                                    }
                                    echo htmlspecialchars(implode(', ', $weakTopics));
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tópicos Essenciais -->
            <div class="bg-white rounded-2xl shadow-2xl p-6 mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                    📊 Tópicos Essenciais (20% que geram 80% de resultado)
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($session['core_topics'] as $topic): ?>
                        <div class="p-3 rounded-lg border-2 <?= in_array($topic['id'], $progress['weak_points']) ? 'bg-red-50 border-red-200' : 'bg-indigo-50 border-indigo-200' ?>">
                            <div class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($topic['title']) ?></div>
                            <div class="text-xs text-gray-600 mt-1">
                                <?= htmlspecialchars($topic['importance']) ?> | Dif. <?= $topic['difficulty'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Área da Questão -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <?php if (!$currentQuestion && !$lastAnswer): ?>
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">💡</div>
                        <form method="POST" action="?action=generate" onsubmit="showLoading('Gerando questão personalizada...')">
                            <button type="submit" class="px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg transform hover:scale-105 transition-all">
                                Gerar Questão
                            </button>
                        </form>
                    </div>
                <?php elseif ($currentQuestion): ?>
                    <div>
                        <div class="mb-6">
                            <div class="inline-block px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-sm font-semibold mb-4">
                                Nível <?= $progress['difficulty_level'] ?> • <?= htmlspecialchars($currentQuestion['key_concept']) ?>
                            </div>
                            <p class="text-lg leading-relaxed text-gray-800">
                                <?= htmlspecialchars($currentQuestion['statement']) ?>
                            </p>
                        </div>

                        <form method="POST" action="?action=answer" onsubmit="showLoading('Processando resposta...')" class="flex gap-4">
                            <button type="submit" name="answer" value="true" class="flex-1 py-4 bg-green-500 text-white rounded-xl font-bold text-lg hover:bg-green-600 transition-colors">
                                ✓ CERTO
                            </button>
                            <button type="submit" name="answer" value="false" class="flex-1 py-4 bg-red-500 text-white rounded-xl font-bold text-lg hover:bg-red-600 transition-colors">
                                ✗ ERRADO
                            </button>
                        </form>
                    </div>
                <?php elseif ($lastAnswer): ?>
                    <div>
                        <!-- Questão Original -->
                        <div class="mb-4 p-4 bg-gray-50 border-2 border-gray-200 rounded-lg">
                            <p class="text-sm font-semibold text-gray-700 mb-2">📝 Questão:</p>
                            <p class="text-gray-800"><?= htmlspecialchars($lastAnswer['statement']) ?></p>
                        </div>

                        <!-- Feedback da Resposta -->
                        <div class="p-6 rounded-xl mb-4 <?= $lastAnswer['correct'] ? 'bg-green-50 border-2 border-green-200' : 'bg-red-50 border-2 border-red-200' ?>">
                            <p class="text-lg leading-relaxed text-gray-700">
                                <strong><?= $lastAnswer['correct'] ? '✓ CORRETO!' : '✗ ERRADO.' ?></strong><br>
                                <?= htmlspecialchars($lastAnswer['explanation']) ?>
                            </p>
                        </div>
                        
                        <!-- Botão de Questionamento -->
                        <?php if (getConfig('ALLOW_QUESTION_CHALLENGE', 'true') === 'true' && !$challengeResult): ?>
                            <div class="mb-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                                <div class="flex items-start gap-3">
                                    <div class="text-2xl">🤔</div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-yellow-800 mb-2">
                                            Discorda do gabarito?
                                        </p>
                                        <p class="text-xs text-yellow-700 mb-3">
                                            Nosso Agente Questionador vai pesquisar na internet e verificar sua argumentação.
                                        </p>
                                        <button 
                                            onclick="document.getElementById('challengeForm').classList.toggle('hidden')"
                                            class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 text-sm font-semibold transition-colors"
                                        >
                                            ⚖️ Questionar Gabarito
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Formulário de Questionamento -->
                                <div id="challengeForm" class="hidden mt-4 pt-4 border-t border-yellow-300">
                                    <form method="POST" action="?action=challenge" id="challengeSubmitForm">
                                        <input type="hidden" name="question_id" value="<?= $lastAnswer['question_id'] ?>">
                                        
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            Sua Argumentação:
                                        </label>
                                        <textarea 
                                            name="argument" 
                                            rows="4" 
                                            required
                                            minlength="20"
                                            placeholder="Explique por que você acredita que o gabarito está incorreto. Seja específico e forneça argumentos sólidos..."
                                            class="w-full px-3 py-2 border-2 border-gray-300 rounded-lg focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 text-sm"
                                        ></textarea>
                                        
                                        <div class="flex gap-2 mt-3">
                                            <button 
                                                type="button"
                                                onclick="submitChallenge()"
                                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-semibold text-sm transition-colors"
                                            >
                                                🔍 Enviar para Análise
                                            </button>
                                            <button 
                                                type="button"
                                                onclick="document.getElementById('challengeForm').classList.add('hidden')"
                                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 font-semibold text-sm transition-colors"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Resultado do Questionamento -->
                        <?php if ($challengeResult): ?>
                            <div class="mb-4 p-6 rounded-xl border-2 <?= $challengeResult['decision'] === 'accepted' ? 'bg-green-50 border-green-300' : 'bg-orange-50 border-orange-300' ?>">
                                <div class="flex items-start gap-3 mb-4">
                                    <div class="text-3xl"><?= $challengeResult['decision'] === 'accepted' ? '✅' : '📋' ?></div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-lg <?= $challengeResult['decision'] === 'accepted' ? 'text-green-800' : 'text-orange-800' ?> mb-2">
                                            <?= $challengeResult['decision'] === 'accepted' ? 'Questionamento ACEITO!' : 'Análise do Questionamento' ?>
                                        </h3>
                                        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">
                                            <?= htmlspecialchars($challengeResult['analysis']) ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Fontes Web -->
                                <?php if (!empty($challengeResult['web_sources'])): ?>
                                    <div class="mt-4 pt-4 border-t <?= $challengeResult['decision'] === 'accepted' ? 'border-green-200' : 'border-orange-200' ?>">
                                        <p class="text-xs font-semibold text-gray-600 mb-2">🌐 Fontes consultadas na web:</p>
                                        <ul class="text-xs text-gray-600 space-y-1">
                                            <?php 
                                            $sources = $challengeResult['web_sources'];
                                            $count = 0;
                                            foreach ($sources as $key => $source): 
                                                if ($key === 'tavily_answer' || $count >= 3) continue;
                                                if (!is_array($source)) continue;
                                                $count++;
                                            ?>
                                                <li>
                                                    • <a href="<?= htmlspecialchars($source['url'] ?? '#') ?>" target="_blank" class="text-indigo-600 hover:underline">
                                                        <?= htmlspecialchars($source['title'] ?? 'Fonte sem título') ?>
                                                    </a>
                                                    <?php if (isset($source['score'])): ?>
                                                        <span class="text-gray-400">(<?= round($source['score'] * 100) ?>% relevância)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        
                                        <?php if (isset($sources['tavily_answer'])): ?>
                                            <div class="mt-3 p-3 bg-white/50 rounded text-xs text-gray-700">
                                                <strong>Resumo Tavily:</strong><br>
                                                <?= htmlspecialchars(substr($sources['tavily_answer'], 0, 300)) ?><?= strlen($sources['tavily_answer']) > 300 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Gabarito Atualizado -->
                                <?php if ($challengeResult['decision'] === 'accepted' && isset($challengeResult['updated_explanation'])): ?>
                                    <div class="mt-4 pt-4 border-t border-green-200">
                                        <p class="text-xs font-semibold text-green-800 mb-2">✏️ Gabarito Corrigido:</p>
                                        <div class="p-3 bg-white rounded-lg border-2 border-green-300">
                                            <p class="text-sm text-gray-700">
                                                <strong>Resposta correta:</strong> 
                                                <span class="inline-block px-2 py-1 rounded <?= $challengeResult['updated_answer'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= $challengeResult['updated_answer'] ? 'CERTO ✓' : 'ERRADO ✗' ?>
                                                </span>
                                            </p>
                                            <p class="text-sm text-gray-700 mt-2">
                                                <?= htmlspecialchars($challengeResult['updated_explanation']) ?>
                                            </p>
                                        </div>
                                        <p class="text-xs text-green-700 mt-2 flex items-center gap-1">
                                            <span>ℹ️</span>
                                            <span>Suas estatísticas foram recalculadas automaticamente. Parabéns por contribuir para melhorar o sistema!</span>
                                        </p>
                                    </div>
                                <?php elseif ($challengeResult['decision'] === 'rejected'): ?>
                                    <div class="mt-4 pt-4 border-t border-orange-200">
                                        <p class="text-xs text-orange-700 flex items-center gap-1">
                                            <span>💡</span>
                                            <span>O gabarito original foi mantido. Continue estudando e, se tiver novas evidências, pode questionar novamente.</span>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Botão Próxima Questão -->
                        <form method="POST" action="?action=generate" onsubmit="showLoading('Gerando próxima questão...')">
                            <button type="submit" class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg transition-all">
                                Próxima Questão →
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center text-white mt-8 pb-4">
            <p class="text-sm opacity-75">
                Sistema RAG com IA • Princípio de Pareto (80/20) • Questões Adaptativas CESPE
            </p>
            <p class="text-xs opacity-60 mt-1">
                v2.3 - Com Gerenciamento Inteligente de Sessões
            </p>
        </div>
    </div>

    <script>
        // Sistema de Loading Global
        let loadingOverlay = null;
        let loadingText = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadingOverlay = document.getElementById('loadingOverlay');
            loadingText = document.getElementById('loadingText');
        });
        
        function showLoading(message = 'Processando...') {
            if (loadingOverlay && loadingText) {
                loadingText.textContent = message;
                loadingOverlay.classList.add('active');
                document.body.classList.add('loading');
            }
        }
        
        function hideLoading() {
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active');
                document.body.classList.remove('loading');
            }
        }
        
        // Função específica para questionamento
        function submitChallenge() {
            const form = document.getElementById('challengeSubmitForm');
            const textarea = form.querySelector('textarea[name="argument"]');
            
            if (!textarea.value || textarea.value.trim().length < 20) {
                alert('Sua argumentação deve ter pelo menos 20 caracteres.');
                return;
            }
            
            showLoading('🔍 Buscando informações na web...');
            
            setTimeout(() => {
                loadingText.textContent = '🤖 Analisando com IA...';
            }, 2000);
            
            setTimeout(() => {
                loadingText.textContent = '⚖️ Verificando gabarito...';
            }, 4000);
            
            form.submit();
        }
        
        window.addEventListener('load', function() {
            setTimeout(hideLoading, 500);
        });
        
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            let submitted = false;
            form.addEventListener('submit', function(e) {
                if (submitted) {
                    e.preventDefault();
                    return false;
                }
                submitted = true;
                
                setTimeout(() => {
                    submitted = false;
                }, 5000);
            });
        });
        
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                hideLoading();
            }
        });

        // Função para toggle do menu admin
        function toggleAdminMenu(event) {
            event.stopPropagation();
            const menu = document.getElementById('adminMenu');
            const arrow = document.getElementById('dropdownArrow');
            
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                arrow.classList.add('rotate-180');
            } else {
                menu.classList.add('hidden');
                arrow.classList.remove('rotate-180');
            }
        }

        // Fechar menu ao clicar fora
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminDropdown');
            const menu = document.getElementById('adminMenu');
            const arrow = document.getElementById('dropdownArrow');
            
            if (dropdown && menu && !dropdown.contains(event.target)) {
                menu.classList.add('hidden');
                if (arrow) {
                    arrow.classList.remove('rotate-180');
                }
            }
        });

        // Fechar menu com ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const menu = document.getElementById('adminMenu');
                const arrow = document.getElementById('dropdownArrow');
                if (menu) {
                    menu.classList.add('hidden');
                }
                if (arrow) {
                    arrow.classList.remove('rotate-180');
                }
            }
        });
        </script>

</body>
</html>