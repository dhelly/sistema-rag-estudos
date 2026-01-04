<?php
/**
 * ADMIN_PROMPTS.PHP - Editor de Prompts por Disciplina
 * 
 * Crie este arquivo como: admin_prompts.php
 * Permite configurar prompts específicos para cada agente por disciplina
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
            case 'create_discipline':
                $name = trim($_POST['name']);
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
                $description = trim($_POST['description']);
                $icon = trim($_POST['icon']) ?: '📚';
                $color = $_POST['color'] ?: 'indigo';
                
                $db->createDiscipline($name, $slug, $description, $icon, $color, $userId);
                $message = "Disciplina '{$name}' criada com sucesso!";
                break;
                
            case 'save_prompt':
                $disciplineId = (int)$_POST['discipline_id'];
                $agentType = $_POST['agent_type'];
                $promptContent = trim($_POST['prompt_content']);
                $systemInstructions = trim($_POST['system_instructions']);
                $examples = trim($_POST['examples']);
                
                $db->saveAgentPrompt($disciplineId, $agentType, $promptContent, $systemInstructions, $examples, $userId);
                $message = "Prompt do agente salvo com sucesso!";
                break;
                
            case 'delete_discipline':
                $disciplineId = (int)$_POST['discipline_id'];
                $discipline = $db->getDiscipline($disciplineId);
                
                if ($discipline['slug'] === 'geral') {
                    throw new Exception("Não é possível excluir a disciplina genérica.");
                }
                
                $db->deleteDiscipline($disciplineId);
                $message = "Disciplina excluída com sucesso!";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Buscar disciplinas
$disciplines = $db->getAllDisciplines();

// Disciplina selecionada
$selectedDisciplineId = $_GET['discipline'] ?? ($disciplines[0]['id'] ?? null);
$selectedDiscipline = $selectedDisciplineId ? $db->getDiscipline($selectedDisciplineId) : null;

// Buscar prompts da disciplina selecionada
$prompts = [];
if ($selectedDisciplineId) {
    $promptsData = $db->getAllPromptsForDiscipline($selectedDisciplineId);
    foreach ($promptsData as $prompt) {
        $prompts[$prompt['agent_type']] = $prompt;
    }
}

// Tipos de agentes
$agentTypes = [
    'analyzer' => [
        'name' => 'Agente Analisador',
        'icon' => '🔍',
        'description' => 'Analisa o conteúdo e identifica os 20% essenciais (Pareto)',
        'default_prompt' => 'Você é um Agente Analisador especializado em identificar os 20% de conteúdo mais importantes que geram 80% dos resultados (Princípio de Pareto).

Analise este material de estudo e identifique os tópicos ESSENCIAIS:

{content}

Retorne APENAS um JSON (sem markdown, sem explicações) com esta estrutura:
{
  "coreTopics": [
    {
      "id": 1,
      "title": "Título conciso do tópico",
      "importance": "Alta",
      "keyPoints": ["ponto 1", "ponto 2", "ponto 3"],
      "difficulty": 1
    }
  ]
}

Identifique 4-6 tópicos fundamentais.'
    ],
    'generator' => [
        'name' => 'Agente Gerador',
        'icon' => '⚡',
        'description' => 'Gera questões estilo CESPE baseadas no conteúdo',
        'default_prompt' => 'Você é um Agente Gerador de Questões especializado em criar questões estilo CESPE (Certo/Errado).

Conteúdo de referência:
{content}

Tópico foco: {topic_title}
Pontos-chave: {key_points}

Nível de dificuldade: {difficulty}/5

Crie uma questão CESPE seguindo estas diretrizes:
- Seja preciso e técnico
- Use termos do próprio material
- Para dificuldade 3+: inclua pegadinhas sutis

Retorne APENAS JSON (sem markdown):
{
  "statement": "afirmação da questão",
  "correctAnswer": true,
  "topicId": {topic_id},
  "explanation": "explicação detalhada",
  "keyConceptTested": "conceito principal"
}'
    ],
    'challenger' => [
        'name' => 'Agente Questionador',
        'icon' => '⚖️',
        'description' => 'Analisa questionamentos de gabaritos com busca web',
        'default_prompt' => 'Você é um Agente Questionador especializado em validar gabaritos de questões.

QUESTÃO ORIGINAL:
Afirmação: {statement}
Gabarito atual: {current_answer}
Explicação atual: {explanation}

QUESTIONAMENTO DO ALUNO:
{user_argument}

FONTES DA WEB (Tavily Search):
{web_sources}

INSTRUÇÕES:
1. Analise cuidadosamente o questionamento
2. Considere as fontes web encontradas
3. Verifique se há erro no gabarito
4. Se o aluno tiver razão, sugira correção

Retorne APENAS JSON (sem markdown):
{
  "decision": "accepted" ou "rejected",
  "confidence": 0.0 a 1.0,
  "analysis": "análise detalhada",
  "reasoning": "raciocínio baseado nas fontes",
  "suggested_answer": true ou false (se accepted),
  "updated_explanation": "nova explicação" (se accepted)
}'
    ]
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Prompts - Sistema RAG</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-button {
            transition: all 0.3s ease;
        }
        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        textarea {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen p-4">
    
    <!-- Header -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 flex justify-between items-center text-white text-sm">
            <div class="flex items-center gap-4">
                <a href="index.php" class="hover:text-indigo-200">← Voltar</a>
                <span>👤 <?= htmlspecialchars(Auth::getUserName()) ?> (Admin)</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="admin_users.php" class="px-3 py-1 bg-purple-500/80 hover:bg-purple-600 rounded">
                    👥 Usuários
                </a>
                <form method="POST" action="logout.php" class="inline">
                    <button type="submit" class="px-3 py-1 bg-red-500/80 hover:bg-red-600 rounded">
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
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
                        🎯 Editor de Prompts por Disciplina
                    </h1>
                    <p class="text-gray-600 mt-2">
                        Configure instruções específicas para cada agente de IA em cada disciplina
                    </p>
                </div>
                <button 
                    onclick="document.getElementById('newDisciplineModal').classList.remove('hidden')"
                    class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold hover:shadow-lg"
                >
                    + Nova Disciplina
                </button>
            </div>
            
            <!-- Seletor de Disciplina -->
            <div class="flex gap-2 flex-wrap">
                <?php foreach ($disciplines as $disc): ?>
                    <a 
                        href="?discipline=<?= $disc['id'] ?>"
                        class="px-4 py-2 rounded-lg font-semibold transition-all <?= $disc['id'] == $selectedDisciplineId ? 'bg-' . $disc['color'] . '-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"
                    >
                        <?= htmlspecialchars($disc['icon']) ?> <?= htmlspecialchars($disc['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($selectedDiscipline): ?>
            <!-- Tabs dos Agentes -->
            <div class="bg-white rounded-2xl shadow-2xl p-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?= htmlspecialchars($selectedDiscipline['icon']) ?> 
                        <?= htmlspecialchars($selectedDiscipline['name']) ?>
                    </h2>
                    
                    <?php if ($selectedDiscipline['slug'] !== 'geral'): ?>
                        <button 
                            onclick="confirmDelete(<?= $selectedDiscipline['id'] ?>, '<?= htmlspecialchars(addslashes($selectedDiscipline['name'])) ?>')"
                            class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
                        >
                            🗑️ Excluir Disciplina
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Tabs -->
                <div class="flex gap-2 mb-6 border-b-2 border-gray-200">
                    <?php foreach ($agentTypes as $type => $info): ?>
                        <button 
                            onclick="showAgent('<?= $type ?>')"
                            id="tab-<?= $type ?>"
                            class="tab-button px-6 py-3 rounded-t-lg font-semibold <?= $type === 'analyzer' ? 'active' : '' ?>"
                        >
                            <?= $info['icon'] ?> <?= $info['name'] ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                <!-- Conteúdo das Tabs -->
                <?php foreach ($agentTypes as $type => $info): ?>
                    <div id="agent-<?= $type ?>" class="<?= $type !== 'analyzer' ? 'hidden' : '' ?>">
                        <form method="POST" action="?action=save_prompt">
                            <input type="hidden" name="discipline_id" value="<?= $selectedDiscipline['id'] ?>">
                            <input type="hidden" name="agent_type" value="<?= $type ?>">
                            
                            <!-- Descrição do Agente -->
                            <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 rounded">
                                <p class="text-sm text-blue-800">
                                    <strong><?= $info['icon'] ?> Função:</strong> <?= $info['description'] ?>
                                </p>
                            </div>
                            
                            <!-- Editor de Prompt Principal -->
                            <div class="mb-6">
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    📝 Prompt Principal
                                    <span class="text-xs font-normal text-gray-500 ml-2">
                                        (Instruções principais para o agente)
                                    </span>
                                </label>
                                <textarea 
                                    name="prompt_content" 
                                    rows="15"
                                    required
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 text-sm"
                                    placeholder="Digite o prompt do agente..."
                                ><?= htmlspecialchars($prompts[$type]['prompt_content'] ?? $info['default_prompt']) ?></textarea>
                                
                                <div class="mt-2 flex gap-2">
                                    <button 
                                        type="button"
                                        onclick="resetPrompt('<?= $type ?>')"
                                        class="text-xs text-gray-600 hover:text-gray-800"
                                    >
                                        🔄 Restaurar Padrão
                                    </button>
                                    <span class="text-xs text-gray-400">|</span>
                                    <span class="text-xs text-gray-500">
                                        Use {content}, {topic_title}, {difficulty}, etc. como variáveis
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Instruções do Sistema (Opcional) -->
                            <div class="mb-6">
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    ⚙️ Instruções do Sistema (Opcional)
                                    <span class="text-xs font-normal text-gray-500 ml-2">
                                        (Contexto adicional ou regras específicas)
                                    </span>
                                </label>
                                <textarea 
                                    name="system_instructions" 
                                    rows="5"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 text-sm"
                                    placeholder="Ex: 'Para esta disciplina, foque em...' ou 'Evite usar termos técnicos como...'"
                                ><?= htmlspecialchars($prompts[$type]['system_instructions'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Exemplos (Opcional) -->
                            <div class="mb-6">
                                <label class="block text-sm font-bold text-gray-700 mb-2">
                                    💡 Exemplos (Opcional)
                                    <span class="text-xs font-normal text-gray-500 ml-2">
                                        (Exemplos de boa execução para o agente)
                                    </span>
                                </label>
                                <textarea 
                                    name="examples" 
                                    rows="5"
                                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 text-sm"
                                    placeholder="Cole exemplos de perguntas ideais, formatos esperados, etc."
                                ><?= htmlspecialchars($prompts[$type]['examples'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Informações da Versão -->
                            <?php if (isset($prompts[$type])): ?>
                                <div class="mb-6 p-3 bg-gray-50 rounded text-xs text-gray-600">
                                    📌 Versão: <?= $prompts[$type]['version'] ?> | 
                                    Última atualização: <?= date('d/m/Y H:i', strtotime($prompts[$type]['updated_at'])) ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Botões de Ação -->
                            <div class="flex gap-3">
                                <button 
                                    type="submit"
                                    class="px-8 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg font-bold hover:shadow-lg"
                                >
                                    💾 Salvar Prompt
                                </button>
                                <button 
                                    type="button"
                                    onclick="testPrompt('<?= $type ?>')"
                                    class="px-6 py-3 bg-blue-100 text-blue-700 rounded-lg font-semibold hover:bg-blue-200"
                                >
                                    🧪 Testar
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Dicas -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 mt-6 text-white">
            <h3 class="text-lg font-bold mb-3">💡 Dicas de Uso</h3>
            <ul class="space-y-2 text-sm">
                <li>✅ Use variáveis como {content}, {topic_title}, {difficulty} nos prompts</li>
                <li>✅ Seja específico sobre formato de saída (JSON, texto, etc.)</li>
                <li>✅ Inclua exemplos para melhorar a qualidade das respostas</li>
                <li>✅ Teste os prompts após cada modificação</li>
                <li>✅ Para Direito: enfatize legislação e jurisprudência</li>
                <li>✅ Para Exatas: foque em fórmulas e resolução passo a passo</li>
                <li>✅ Para Humanas: priorize contexto histórico e análise crítica</li>
            </ul>
        </div>
    </div>

    <!-- Modal Nova Disciplina -->
    <div id="newDisciplineModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-6">
                ➕ Nova Disciplina
            </h3>
            
            <form method="POST" action="?action=create_discipline">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Nome da Disciplina
                    </label>
                    <input 
                        type="text" 
                        name="name" 
                        required
                        placeholder="Ex: Direito Constitucional"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500"
                    >
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Descrição
                    </label>
                    <textarea 
                        name="description" 
                        rows="3"
                        placeholder="Breve descrição da disciplina..."
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500"
                    ></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Ícone (Emoji)
                        </label>
                        <input 
                            type="text" 
                            name="icon" 
                            placeholder="📚"
                            maxlength="5"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 text-center text-2xl"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Cor
                        </label>
                        <select 
                            name="color"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500"
                        >
                            <option value="indigo">Índigo</option>
                            <option value="blue">Azul</option>
                            <option value="green">Verde</option>
                            <option value="red">Vermelho</option>
                            <option value="purple">Roxo</option>
                            <option value="orange">Laranja</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button 
                        type="submit"
                        class="flex-1 py-3 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700"
                    >
                        Criar Disciplina
                    </button>
                    <button 
                        type="button"
                        onclick="document.getElementById('newDisciplineModal').classList.add('hidden')"
                        class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-bold hover:bg-gray-300"
                    >
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Excluir Disciplina -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
            <div class="text-center mb-6">
                <div class="text-6xl mb-4">⚠️</div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">
                    Excluir Disciplina?
                </h3>
                <p class="text-gray-600" id="deleteMessage"></p>
            </div>
            
            <form method="POST" action="?action=delete_discipline" id="deleteForm">
                <input type="hidden" name="discipline_id" id="deleteDisciplineId">
                <div class="flex gap-3">
                    <button 
                        type="button" 
                        onclick="closeDeleteModal()"
                        class="flex-1 py-3 bg-gray-200 text-gray-700 rounded-lg font-bold"
                    >
                        Cancelar
                    </button>
                    <button 
                        type="submit"
                        class="flex-1 py-3 bg-red-600 text-white rounded-lg font-bold"
                    >
                        Sim, Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Dados dos prompts padrão
        const defaultPrompts = <?= json_encode(array_map(fn($type) => $type['default_prompt'], $agentTypes)) ?>;
        
        function showAgent(agentType) {
            // Esconder todas
            document.querySelectorAll('[id^="agent-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id^="tab-"]').forEach(el => el.classList.remove('active'));
            
            // Mostrar selecionada
            document.getElementById('agent-' + agentType).classList.remove('hidden');
            document.getElementById('tab-' + agentType).classList.add('active');
        }
        
        function resetPrompt(agentType) {
            if (confirm('Tem certeza que deseja restaurar o prompt padrão? Isso substituirá o conteúdo atual.')) {
                const textarea = document.querySelector('#agent-' + agentType + ' textarea[name="prompt_content"]');
                textarea.value = defaultPrompts[agentType];
            }
        }
        
        function testPrompt(agentType) {
            alert('Funcionalidade de teste em desenvolvimento!\n\nEm breve você poderá testar os prompts diretamente aqui.');
        }
        
        function confirmDelete(disciplineId, disciplineName) {
            document.getElementById('deleteDisciplineId').value = disciplineId;
            document.getElementById('deleteMessage').textContent = 
                `Tem certeza que deseja excluir "${disciplineName}"? Todos os prompts configurados para esta disciplina serão perdidos.`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Fechar modais com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('newDisciplineModal').classList.add('hidden');
                closeDeleteModal();
            }
        });
    </script>

</body>
</html>