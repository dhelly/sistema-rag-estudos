<?php
// =====================================
// DISCIPLINEREPOSITORY.PHP
// 
// Salvar como: src/Repositories/DisciplineRepository.php
// =====================================

namespace App\Repositories;

use App\Models\Discipline;

class DisciplineRepository extends Repository {
    protected $model = Discipline::class;
    
    /**
     * Cria nova disciplina
     * 
     * @param string $name
     * @param string $slug
     * @param string|null $description
     * @param string $icon
     * @param string $color
     * @param int|null $createdBy
     * @return Discipline
     */
    public function createDiscipline($name, $slug, $description = null, $icon = '📚', $color = 'indigo', $createdBy = null) {
        $discipline = new Discipline();
        $discipline->name = $name;
        $discipline->slug = $slug;
        $discipline->description = $description;
        $discipline->icon = $icon;
        $discipline->color = $color;
        $discipline->created_by = $createdBy;
        
        $discipline->save();
        
        return $discipline;
    }
    
    /**
     * Busca disciplina por slug
     * 
     * @param string $slug
     * @return Discipline|null
     */
    public function findBySlug($slug) {
        return Discipline::findBySlug($slug);
    }
    
    /**
     * Obtém apenas disciplinas ativas
     * 
     * @return array
     */
    public function getActive() {
        return Discipline::getActive();
    }
    
    /**
     * Obtém todas as disciplinas ordenadas
     * 
     * @param bool $activeOnly
     * @return array
     */
    public function getAllOrdered($activeOnly = false) {
        $sql = "SELECT * FROM disciplines";
        
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        
        $sql .= " ORDER BY name ASC";
        
        $data = $this->fetchAll($sql);
        
        return array_map(function($row) {
            return Discipline::hydrate($row);
        }, $data);
    }
    
    /**
     * Atualiza disciplina
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateDiscipline($id, $data) {
        $discipline = $this->find($id);
        
        if (!$discipline) {
            return false;
        }
        
        if (isset($data['name'])) $discipline->name = $data['name'];
        if (isset($data['description'])) $discipline->description = $data['description'];
        if (isset($data['icon'])) $discipline->icon = $data['icon'];
        if (isset($data['color'])) $discipline->color = $data['color'];
        if (isset($data['is_active'])) $discipline->is_active = $data['is_active'];
        
        return $discipline->save();
    }
    
    /**
     * Deleta disciplina
     * 
     * @param int $id
     * @return bool
     */
    public function deleteDiscipline($id) {
        $discipline = $this->find($id);
        
        if (!$discipline) {
            return false;
        }
        
        // Não permite deletar disciplina "geral"
        if ($discipline->slug === 'geral') {
            throw new \Exception("Não é possível excluir a disciplina genérica.");
        }
        
        return $discipline->delete();
    }
    
    /**
     * Obtém prompt de agente
     * 
     * @param int $disciplineId
     * @param string $agentType
     * @return array|null
     */
    public function getAgentPrompt($disciplineId, $agentType) {
        $discipline = $this->find($disciplineId);
        
        if (!$discipline) {
            return null;
        }
        
        return $discipline->getAgentPrompt($agentType);
    }
    
    /**
     * Salva prompt de agente
     * 
     * @param int $disciplineId
     * @param string $agentType
     * @param string $promptContent
     * @param string|null $systemInstructions
     * @param string|null $examples
     * @param int|null $createdBy
     * @return bool
     */
    public function saveAgentPrompt($disciplineId, $agentType, $promptContent, $systemInstructions = null, $examples = null, $createdBy = null) {
        $discipline = $this->find($disciplineId);
        
        if (!$discipline) {
            return false;
        }
        
        return $discipline->saveAgentPrompt($agentType, $promptContent, $systemInstructions, $examples, $createdBy);
    }
    
    /**
     * Obtém todos os prompts de uma disciplina
     * 
     * @param int $disciplineId
     * @return array
     */
    public function getAllPrompts($disciplineId) {
        $discipline = $this->find($disciplineId);
        
        if (!$discipline) {
            return [];
        }
        
        return $discipline->getAllPrompts();
    }
    
    /**
     * Conta sessões que usam a disciplina
     * 
     * @param int $disciplineId
     * @return int
     */
    public function countSessions($disciplineId) {
        $discipline = $this->find($disciplineId);
        
        if (!$discipline) {
            return 0;
        }
        
        return $discipline->countSessions();
    }
    
    /**
     * Busca disciplinas por nome
     * 
     * @param string $search
     * @return array
     */
    public function search($search) {
        $sql = "SELECT * FROM disciplines 
                WHERE name LIKE ? OR description LIKE ?
                ORDER BY name ASC";
        
        $searchTerm = '%' . $search . '%';
        $data = $this->fetchAll($sql, [$searchTerm, $searchTerm]);
        
        return array_map(function($row) {
            return Discipline::hydrate($row);
        }, $data);
    }
    
    /**
     * Obtém disciplinas mais usadas
     * 
     * @param int $limit
     * @return array
     */
    public function getMostUsed($limit = 10) {
        $sql = "SELECT d.*, COUNT(s.id) as usage_count
                FROM disciplines d
                LEFT JOIN study_sessions s ON s.discipline_id = d.id
                WHERE d.is_active = 1
                GROUP BY d.id
                ORDER BY usage_count DESC
                LIMIT ?";
        
        return $this->fetchAll($sql, [$limit]);
    }
}