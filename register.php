<?php
/**
 * REGISTER v2.1 - Página de Registro
 * 
 * Salve este arquivo como: register.php
 */

require_once 'config.php';
require_once 'auth.php';

$error = '';
$success = '';

// Verifica se registro está habilitado
$allowRegistration = getConfig('ALLOW_REGISTRATION', 'true') === 'true';

// Inicia sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já está logado, redireciona
if (Auth::checkLogin()) {
    header('Location: index.php');
    exit;
}

// Processa registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowRegistration) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        if ($password !== $confirmPassword) {
            throw new Exception("As senhas não coincidem.");
        }
        
        Auth::register($email, $password, $name);
        $success = "Conta criada com sucesso! Você já pode fazer login.";
        
        // Redireciona para login após 2 segundos
        header("refresh:2;url=login.php");
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Sistema RAG de Estudos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-md w-full">
        <!-- Logo/Título -->
        <div class="text-center mb-8">
            <div class="text-6xl mb-4">🧠</div>
            <h1 class="text-3xl font-bold text-white mb-2">
                Criar Nova Conta
            </h1>
            <p class="text-indigo-200">
                Sistema RAG de Estudos v2.1
            </p>
        </div>

        <!-- Card de Registro -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <span class="text-xl mr-2">⚠️</span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <span class="text-xl mr-2">✓</span>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$allowRegistration): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <span class="text-xl mr-2">⚠️</span>
                        <span>Registro de novos usuários está desabilitado.</span>
                    </div>
                </div>
                <a href="login.php" class="block text-center text-indigo-600 hover:text-indigo-700 font-semibold">
                    ← Voltar para o login
                </a>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Nome Completo
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            required
                            minlength="3"
                            autocomplete="name"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                            placeholder="Digite seu nome"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Email
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            required
                            autocomplete="email"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                            placeholder="seu@email.com"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Senha
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            required
                            minlength="6"
                            autocomplete="new-password"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                            placeholder="Mínimo 6 caracteres"
                        >
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirmar Senha
                        </label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            required
                            minlength="6"
                            autocomplete="new-password"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                            placeholder="Digite a senha novamente"
                        >
                    </div>

                    <button 
                        type="submit"
                        class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg transform hover:scale-105 transition-all"
                    >
                        Criar Conta
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Já tem uma conta?
                        <a href="login.php" class="text-indigo-600 hover:text-indigo-700 font-semibold">
                            Fazer login
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rodapé -->
        <div class="mt-6 text-center text-indigo-200 text-sm">
            <p>Ao criar uma conta, você concorda com nossos termos de uso</p>
        </div>
    </div>

</body>
</html>