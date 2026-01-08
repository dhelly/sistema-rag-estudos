<?php
/**
 * AIPROVIDERINTERFACE.PHP - Contrato para Provedores de IA
 * 
 * Define métodos que TODOS os provedores devem implementar
 * Permite trocar de provider sem mudar código
 */

namespace App\Providers;

interface AIProviderInterface {
    /**
     * Extrai texto de PDF (base64)
     * 
     * @param string $base64Data
     * @return string Texto extraído
     * @throws \Exception Se provider não suportar
     */
    public function extractPDFText($base64Data);
    
    /**
     * Analisa conteúdo e identifica tópicos essenciais (Pareto 80/20)
     * 
     * @param string $content
     * @return array ['coreTopics' => [...]]
     */
    public function analyzeContent($content);
    
    /**
     * Processa resumo já formatado (80/20)
     * 
     * @param string $summaryText
     * @return array ['coreTopics' => [...]]
     */
    public function processPreSummarized($summaryText);
    
    /**
     * Gera questão CESPE baseada no conteúdo e tópico
     * 
     * @param string $pdfContent
     * @param array $topic
     * @param int $difficulty (1-5)
     * @param bool $isWeakPoint
     * @return array Dados da questão
     */
    public function generateQuestion($pdfContent, $topic, $difficulty, $isWeakPoint);
    
    /**
     * Envia mensagem direta para a IA
     * 
     * @param string $prompt
     * @param array $options Opções adicionais
     * @return string Resposta da IA
     */
    public function sendMessage($prompt, $options = []);
    
    /**
     * Obtém nome do provider
     * 
     * @return string
     */
    public function getName();
    
    /**
     * Verifica se provider está disponível
     * 
     * @return bool
     */
    public function isAvailable();
    
    /**
     * Obtém modelo sendo usado
     * 
     * @return string
     */
    public function getModel();
}