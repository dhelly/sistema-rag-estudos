<?php
/**
 * ARQUIVO 3 de 6: login.php (CORRIGIDO)
 * 
 * Salve este arquivo como: login.php
 * Página de login do sistema
 */

require_once 'config.php';
require_once 'auth.php';

$error = '';

// Inicia sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se já está logado, redireciona
if (Auth::checkLogin()) {
    header('Location: index.php');
    exit;
}

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (Auth::login($email, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Email ou senha inválidos!';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema RAG de Estudos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-indigo-900 via-purple-900 to-pink-900 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-md w-full">
        <!-- Logo/Título -->
        <div class="text-center mb-8">
            <div class="text-6xl mb-4">🧠</div>
            <h1 class="text-3xl font-bold text-white mb-2">
                Sistema RAG de Estudos
            </h1>
            <p class="text-indigo-200">
                Faça login para continuar
            </p>
        </div>

        <!-- Card de Login -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                    <div class="flex items-center">
                        <span class="text-xl mr-2">⚠️</span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-6">
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

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Senha
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-colors"
                        placeholder="Digite sua senha"
                    >
                </div>

                <button 
                    type="submit"
                    class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-bold text-lg hover:shadow-lg transform hover:scale-105 transition-all"
                >
                    Entrar →
                </button>
            </form>

            <?php if (getConfig('ALLOW_REGISTRATION', 'true') === 'true'): ?>
                <div class="mt-6 text-center">
                    <p class="text-gray-600">
                        Não tem uma conta?
                        <a href="register.php" class="text-indigo-600 hover:text-indigo-700 font-semibold">
                            Criar conta
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Informações de Configuração -->
        <div class="mt-6 text-center text-indigo-200 text-sm">
            <p>Configure usuário e senha no arquivo <code class="bg-indigo-800 px-2 py-1 rounded">.env</code></p>
        </div>
    </div>

</body>
</html>