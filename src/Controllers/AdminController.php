<?php


// =====================================
// ADMINCONTROLLER.PHP
// 
// Salvar como: src/Controllers/AdminController.php
// =====================================

namespace App\Controllers;

use App\Repositories\UserRepository;
use App\Repositories\DisciplineRepository;
use App\Middleware\AdminMiddleware;

class AdminController {
    private $userRepo;
    private $disciplineRepo;
    
    public function __construct() {
        AdminMiddleware::require();
        
        $this->userRepo = new UserRepository();
        $this->disciplineRepo = new DisciplineRepository();
    }
    
    /**
     * Página de gerenciamento de usuários
     */
    public function users() {
        $allUsers = $this->userRepo->getAllUsers();
        $pendingUsers = $this->userRepo->getPending();
        $statusCount = $this->userRepo->countByStatus();
        
        view('admin/users', [
            'all_users' => $allUsers,
            'pending_users' => $pendingUsers,
            'status_count' => $statusCount
        ]);
    }
    
    /**
     * Ativa usuário
     */
    public function activateUser() {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if (!$this->userRepo->activate($userId, user_id())) {
                throw new \Exception("Erro ao ativar usuário");
            }
            
            $user = $this->userRepo->find($userId);
            flash_success("Usuário {$user->name} foi ativado com sucesso!");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_users.php'));
    }
    
    /**
     * Desativa usuário
     */
    public function deactivateUser() {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId == user_id()) {
                throw new \Exception("Você não pode desativar sua própria conta!");
            }
            
            if (!$this->userRepo->deactivate($userId)) {
                throw new \Exception("Erro ao desativar usuário");
            }
            
            $user = $this->userRepo->find($userId);
            flash_success("Usuário {$user->name} foi desativado.");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_users.php'));
    }
    
    /**
     * Alterna privilégio de admin
     */
    public function toggleAdmin() {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            $isAdmin = $_POST['is_admin'] === '1';
            
            if ($userId == user_id() && !$isAdmin) {
                throw new \Exception("Você não pode remover seu próprio privilégio de admin!");
            }
            
            if (!$this->userRepo->toggleAdmin($userId, $isAdmin)) {
                throw new \Exception("Erro ao alterar privilégios");
            }
            
            $user = $this->userRepo->find($userId);
            $status = $isAdmin ? 'promovido a' : 'removido de';
            flash_success("Usuário {$user->name} foi {$status} administrador.");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_users.php'));
    }
    
    /**
     * Deleta usuário
     */
    public function deleteUser() {
        try {
            $userId = (int)($_POST['user_id'] ?? 0);
            
            if ($userId == user_id()) {
                throw new \Exception("Você não pode excluir sua própria conta!");
            }
            
            $user = $this->userRepo->find($userId);
            $userName = $user->name;
            
            if (!$this->userRepo->delete($userId)) {
                throw new \Exception("Erro ao excluir usuário");
            }
            
            flash_success("Usuário {$userName} foi excluído permanentemente.");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_users.php'));
    }
    
    /**
     * Página de edição de prompts
     */
    public function prompts() {
        $disciplines = $this->disciplineRepo->getAllOrdered();
        $selectedDisciplineId = $_GET['discipline'] ?? ($disciplines[0]->id ?? null);
        
        $selectedDiscipline = null;
        $prompts = [];
        
        if ($selectedDisciplineId) {
            $selectedDiscipline = $this->disciplineRepo->find($selectedDisciplineId);
            $promptsData = $this->disciplineRepo->getAllPrompts($selectedDisciplineId);
            
            foreach ($promptsData as $prompt) {
                $prompts[$prompt['agent_type']] = $prompt;
            }
        }
        
        view('admin/prompts', [
            'disciplines' => $disciplines,
            'selected_discipline' => $selectedDiscipline,
            'prompts' => $prompts
        ]);
    }
    
    /**
     * Cria nova disciplina
     */
    public function createDiscipline() {
        try {
            $name = trim($_POST['name']);
            $slug = slugify($name);
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon']) ?: '📚';
            $color = $_POST['color'] ?: 'indigo';
            
            $this->disciplineRepo->createDiscipline(
                $name,
                $slug,
                $description,
                $icon,
                $color,
                user_id()
            );
            
            flash_success("Disciplina '{$name}' criada com sucesso!");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_prompts.php'));
    }
    
    /**
     * Salva prompt de agente
     */
    public function savePrompt() {
        try {
            $disciplineId = (int)$_POST['discipline_id'];
            $agentType = $_POST['agent_type'];
            $promptContent = trim($_POST['prompt_content']);
            $systemInstructions = trim($_POST['system_instructions'] ?? '');
            $examples = trim($_POST['examples'] ?? '');
            
            $this->disciplineRepo->saveAgentPrompt(
                $disciplineId,
                $agentType,
                $promptContent,
                $systemInstructions,
                $examples,
                user_id()
            );
            
            flash_success("Prompt do agente salvo com sucesso!");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_prompts.php?discipline=' . $disciplineId));
    }
    
    /**
     * Deleta disciplina
     */
    public function deleteDiscipline() {
        try {
            $disciplineId = (int)$_POST['discipline_id'];
            
            $this->disciplineRepo->deleteDiscipline($disciplineId);
            
            flash_success("Disciplina excluída com sucesso!");
            
        } catch (\Exception $e) {
            flash_error($e->getMessage());
        }
        
        redirect(url('admin_prompts.php'));
    }
}