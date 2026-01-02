<?php
/**
 * ARQUIVO 5 de 6: index.php (CORRIGIDO)
 * 
 * Salve este arquivo como: index.php
 * Este é o arquivo principal da aplicação
 */

require_once 'config.php';
require_once 'auth.php';
require_once 'database.php';
require_once 'api.php';

// Requer login
Auth::requireLogin();

$db = new Database();
$api = new UnifiedAI();

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
                    } else {
                        throw new Exception("Provedor inválido ou não configurado!");
                    }
                }
                break;
                
            case 'upload':
                if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
                    $pdfName = basename($_FILES['pdf']['name']);
                    $tmpPath = $_FILES['pdf']['tmp_name'];
                    
                    // Ler e converter PDF para base64
                    $pdfData = file_get_contents($tmpPath);
                    $base64Data = base64_encode($pdfData);
                    
                    // Extrair texto do PDF
                    $extractedText = $api->extractPDFText($base64Data);
                    
                    // Analisar conteúdo
                    $analysisText = $api->analyzeContent($extractedText);
                    $cleanJson = preg_replace('/```json|```/', '', $analysisText);
                    $analysis = json_decode(trim($cleanJson), true);
                    
                    if (!isset($analysis['coreTopics'])) {
                        throw new Exception("Erro ao analisar o PDF. Tente novamente.");
                    }
                    
                    // Criar sessão
                    $sessionId = $db->createSession($pdfName, $extractedText, $analysis['coreTopics']);
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
                    
                    // Processar o texto resumido e estruturar em JSON
                    $analysisText = $api->processPreSummarized($summaryText);
                    $cleanJson = preg_replace('/```json|```/', '', $analysisText);
                    $analysis = json_decode(trim($cleanJson), true);
                    
                    if (!isset($analysis['coreTopics'])) {
                        throw new Exception("Erro ao processar o resumo. Verifique o formato do texto.");
                    }
                    
                    // Criar sessão com o conteúdo resumido
                    $sessionId = $db->createSession($materialName, $summaryText, $analysis['coreTopics']);
                    $_SESSION['session_id'] = $sessionId;
                    
                    $message = "Resumo processado com sucesso: {$materialName}";
                }
                break;
                
            case 'generate':
                if (isset($_SESSION['session_id'])) {
                    $sessionId = $_SESSION['session_id'];
                    $session = $db->getSession($sessionId);
                    $progress = $db->getProgress($sessionId);
                    
                    // Selecionar tópico
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
                    
                    // Gerar questão
                    $questionText = $api->generateQuestion(
                        $session['pdf_content'],
                        $selectedTopic,
                        $progress['difficulty_level'],
                        $isWeakPoint
                    );
                    
                    $cleanJson = preg_replace('/```json|```/', '', $questionText);
                    $question = json_decode(trim($cleanJson), true);
                    
                    if (!isset($question['statement'])) {
                        throw new Exception("Erro ao gerar questão. Tente novamente.");
                    }
                    
                    // Salvar questão
                    $questionId = $db->saveQuestion($sessionId, $question, $progress['difficulty_level']);
                    $_SESSION['current_question'] = $questionId;
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
                    
                    // Atualizar estatísticas
                    $correct = $progress['correct_answers'] + ($isCorrect ? 1 : 0);
                    $total = $progress['total_answers'] + 1;
                    $difficulty = $progress['difficulty_level'];
                    $weakPoints = $progress['weak_points'];
                    
                    // Ajustar dificuldade
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
                        'explanation' => $question['explanation']
                    ];
                    
                    unset($_SESSION['current_question']);
                }
                break;
                
            case 'reset':
                // Salvar dados de login antes de limpar
                $username = $_SESSION['username'] ?? null;
                $loginTime = $_SESSION['login_time'] ?? null;
                $lastActivity = $_SESSION['last_activity'] ?? null;
                $loggedIn = $_SESSION['logged_in'] ?? null;
                $aiProvider = $_SESSION['ai_provider'] ?? null;
                
                // Limpar apenas dados da sessão de estudo
                unset($_SESSION['session_id']);
                unset($_SESSION['current_question']);
                unset($_SESSION['last_answer']);
                
                // Restaurar dados de login
                if ($loggedIn) {
                    $_SESSION['logged_in'] = $loggedIn;
                    $_SESSION['username'] = $username;
                    $_SESSION['login_time'] = $loginTime;
                    $_SESSION['last_activity'] = time(); // Atualiza atividade
                    if ($aiProvider) {
                        $_SESSION['ai_provider'] = $aiProvider;
                    }
                }
                
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

if (isset($_SESSION['session_id'])) {
    $session = $db->getSession($_SESSION['session_id']);
    $progress = $db->getProgress($_SESSION['session_id']);
    
    if (isset($_SESSION['current_question'])) {
        $currentQuestion = $db->getQuestion($_SESSION['current_question']);
    }
}

// Limpar feedback após exibir
if (isset($_SESSION['last_answer'])) {
    unset($_SESSION['last_answer']);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema RAG de Estudos Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen p-4">
    
    <!-- Header com Info do Usuário -->
    <div class="max-w-4xl mx-auto mb-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
            <div class="flex items-center gap-4">
                <span>👤 <?= htmlspecialchars(Auth::getUsername()) ?></span>
                <span>⏱️ <?= Auth::getSessionDuration() ?></span>
                
                <!-- Seletor de Provedor -->
                <form method="POST" action="?action=change_provider" class="inline-flex items-center gap-2">
                    <span>🤖</span>
                    <select name="provider" onchange="this.form.submit()" class="bg-white/20 border border-white/30 rounded px-3 py-1 text-white text-sm focus:outline-none focus:ring-2 focus:ring-white/50 [&_option]:text-black [&_option]:bg-white">
                        <?php foreach (getAvailableProviders() as $key => $name): ?>
                            <option value="<?= $key ?>" <?= getCurrentProvider() === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <form method="POST" action="logout.php" class="inline">
                <button type="submit" class="px-3 py-1 bg-red-500/80 hover:bg-red-600 rounded transition-colors">
                    Sair →
                </button>
            </form>
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
            <!-- Tela de Upload -->
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

                <!-- Tabs de Seleção -->
                <div class="flex border-b border-gray-200 mb-6">
                    <button onclick="showTab('pdf')" id="tab-pdf" class="flex-1 py-3 px-4 text-center font-semibold text-indigo-600 border-b-2 border-indigo-600 transition-colors">
                        📄 Upload de PDF
                    </button>
                    <button onclick="showTab('text')" id="tab-text" class="flex-1 py-3 px-4 text-center font-semibold text-gray-500 border-b-2 border-transparent hover:text-indigo-600 transition-colors">
                        📝 Resumo Pronto (80/20)
                    </button>
                </div>

                <!-- Tab 1: Upload de PDF -->
                <div id="content-pdf">
                    <form method="POST" action="?action=upload" enctype="multipart/form-data" class="border-4 border-dashed border-indigo-300 rounded-xl p-12 text-center hover:border-indigo-500 transition-colors">
                        <div class="text-5xl mb-4">📄</div>
                        <label class="cursor-pointer">
                            <span class="text-lg font-semibold text-indigo-600 hover:text-indigo-700">
                                Clique para fazer upload do PDF
                            </span>
                            <input type="file" name="pdf" accept="application/pdf" required class="hidden" onchange="this.form.submit()">
                        </label>
                        <p class="text-sm text-gray-500 mt-2">
                            O sistema identificará os 20% mais importantes do conteúdo
                        </p>
                        <p class="text-xs text-orange-600 mt-2">
                            ⚠️ Disponível apenas com Anthropic Claude
                        </p>
                    </form>
                </div>

                <!-- Tab 2: Texto Resumido -->
                <div id="content-text" class="hidden">
                    <form method="POST" action="?action=upload_text">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Cole aqui o resumo 80/20 gerado por outra LLM:
                            </label>
                            <textarea 
                                name="summary_text" 
                                rows="12" 
                                required
                                placeholder="Exemplo:

Tópico 1: Conceitos Fundamentais de Direito Constitucional
- Ponto-chave 1: Princípios constitucionais básicos
- Ponto-chave 2: Hierarquia das normas
- Ponto-chave 3: Controle de constitucionalidade

Tópico 2: Direitos e Garantias Fundamentais
- Ponto-chave 1: Direitos individuais
- Ponto-chave 2: Direitos sociais
- Ponto-chave 3: Remédios constitucionais

..."
                                class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors font-mono text-sm"
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-2">
                                💡 Dica: Cole o conteúdo já processado com os tópicos essenciais identificados
                            </p>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Nome do material (opcional):
                            </label>
                            <input 
                                type="text" 
                                name="material_name" 
                                placeholder="Ex: Direito Constitucional - Resumo 80/20"
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                            >
                        </div>

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
                    // Esconder todos os conteúdos
                    document.getElementById('content-pdf').classList.add('hidden');
                    document.getElementById('content-text').classList.add('hidden');
                    
                    // Remover estilos ativos de todas as tabs
                    document.getElementById('tab-pdf').classList.remove('text-indigo-600', 'border-indigo-600');
                    document.getElementById('tab-pdf').classList.add('text-gray-500', 'border-transparent');
                    document.getElementById('tab-text').classList.remove('text-indigo-600', 'border-indigo-600');
                    document.getElementById('tab-text').classList.add('text-gray-500', 'border-transparent');
                    
                    // Mostrar conteúdo selecionado
                    document.getElementById('content-' + tab).classList.remove('hidden');
                    
                    // Ativar tab selecionada
                    document.getElementById('tab-' + tab).classList.remove('text-gray-500', 'border-transparent');
                    document.getElementById('tab-' + tab).classList.add('text-indigo-600', 'border-indigo-600');
                }
            </script>
        <?php else: ?>
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
                            Nova Sessão
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
                        <form method="POST" action="?action=generate">
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

                        <form method="POST" action="?action=answer" class="flex gap-4">
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
                        <div class="p-6 rounded-xl mb-4 <?= $lastAnswer['correct'] ? 'bg-green-50 border-2 border-green-200' : 'bg-red-50 border-2 border-red-200' ?>">
                            <p class="text-lg leading-relaxed text-gray-700">
                                <strong><?= $lastAnswer['correct'] ? '✓ CORRETO!' : '✗ ERRADO.' ?></strong><br>
                                <?= htmlspecialchars($lastAnswer['explanation']) ?>
                            </p>
                        </div>
                        <form method="POST" action="?action=generate">
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
        </div>
    </div>

</body>
</html>