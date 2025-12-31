# 🧠 Sistema RAG de Estudos Inteligente v2.0

> Sistema de estudos adaptativo baseado em IA com questões estilo CESPE, análise Pareto (80/20) e suporte a múltiplos provedores de IA.

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Produção-success)](https://github.com)

---

## 📋 Índice

- [Sobre o Projeto](#-sobre-o-projeto)
- [Características](#-características)
- [Tecnologias](#-tecnologias)
- [Pré-requisitos](#-pré-requisitos)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Uso](#-uso)
- [Provedores de IA](#-provedores-de-ia)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [API e Integrações](#-api-e-integrações)
- [Troubleshooting](#-troubleshooting)
- [Contribuindo](#-contribuindo)
- [Roadmap](#-roadmap)
- [Licença](#-licença)

---

## 🎯 Sobre o Projeto

O **Sistema RAG de Estudos Inteligente** é uma aplicação web que utiliza IA para criar um ambiente de estudos personalizado e adaptativo. O sistema analisa materiais de estudo (PDFs ou resumos) usando o **Princípio de Pareto** para identificar os 20% do conteúdo que geram 80% dos resultados, e então gera questões estilo CESPE/CEBRASPE com dificuldade progressiva.

### 🎓 Ideal Para:

- 📚 Concurseiros preparando para CESPE/CEBRASPE
- 🎓 Estudantes universitários
- 📖 Profissionais estudando para certificações
- 🧑‍🏫 Professores criando materiais de estudo

---

## ✨ Características

### 🔐 Sistema de Autenticação
- Login seguro com usuário e senha
- Gerenciamento de sessões com timeout configurável
- Proteção de rotas

### 🤖 Múltiplos Provedores de IA
- **Anthropic Claude** (Sonnet 4) - Melhor para PDFs
- **OpenAI GPT-4** - Rápido e eficiente
- **DeepSeek** - Econômico
- **Ollama** - Local e gratuito

### 📊 Análise Inteligente (Princípio de Pareto 80/20)
- Identifica automaticamente os tópicos essenciais
- Prioriza conteúdo de alto impacto
- Otimiza tempo de estudo

### 🎯 Questões Adaptativas Estilo CESPE
- 5 níveis de dificuldade progressiva
- Questões de Certo/Errado
- Explicações detalhadas
- Ajuste automático baseado em desempenho

### 📈 Sistema de Acompanhamento
- Estatísticas em tempo real
- Identificação de pontos fracos
- Reforço automático de conceitos
- Histórico de progresso

### 📄 Suporte a Múltiplos Formatos
- Upload direto de PDF (Anthropic)
- Cole resumo já processado (80/20)
- Persistência em SQLite

---

## 🛠️ Tecnologias

### Backend
- **PHP 8.0+** - Linguagem principal
- **SQLite3** - Banco de dados
- **cURL** - Requisições HTTP

### Frontend
- **HTML5/CSS3** - Estrutura e estilo
- **Tailwind CSS** - Framework CSS
- **JavaScript** - Interatividade

### IA e APIs
- **Anthropic Claude API** - Processamento de PDFs e geração
- **OpenAI API** - Alternativa de geração
- **DeepSeek API** - Opção econômica
- **Ollama** - Solução local e gratuita

---

## 📦 Pré-requisitos

### Obrigatórios:
```bash
- PHP >= 8.0
- Extensão SQLite3
- Extensão cURL
- Servidor web (Apache/Nginx) ou PHP built-in server
```

### Pelo menos uma chave de API:
- [Anthropic](https://console.anthropic.com/) (recomendado para PDFs)
- [OpenAI](https://platform.openai.com/)
- [DeepSeek](https://platform.deepseek.com/)
- [Ollama](https://ollama.com/) (instalação local, gratuito)

### Verificar requisitos:
```bash
php -v                                    # Versão do PHP
php -m | grep sqlite3                     # SQLite3 instalado?
php -m | grep curl                        # cURL instalado?
```

---

## 🚀 Instalação

### 1. Clone o repositório
```bash
git clone https://github.com/seu-usuario/sistema-rag-estudos.git
cd sistema-rag-estudos
```

### 2. Instale dependências do sistema (se necessário)

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install php php-sqlite3 php-curl
```

**CentOS/RHEL:**
```bash
sudo yum install php php-sqlite3 php-curl
```

**macOS:**
```bash
brew install php
```

**Windows (Laragon):**
- Extensões já incluídas
- Apenas habilite no `php.ini` se necessário

### 3. Configure permissões
```bash
chmod 755 *.php
chmod 777 .                    # Para criar banco SQLite
chmod 600 .env                 # Proteger configurações
```

---

## ⚙️ Configuração

### 1. Criar arquivo .env

```bash
cp .env.example .env
nano .env
```

### 2. Configurar credenciais básicas

```env
# ============================================
# AUTENTICAÇÃO
# ============================================
LOGIN_USERNAME=seu_usuario
LOGIN_PASSWORD=sua_senha_forte_aqui

# Timeout da sessão (em segundos)
SESSION_TIMEOUT=3600

# ============================================
# ESCOLHA PELO MENOS UM PROVEDOR DE IA
# ============================================

# Opção 1: Anthropic Claude (recomendado)
ANTHROPIC_API_KEY=sk-ant-api03-XXXXX
ANTHROPIC_MODEL=claude-sonnet-4-20250514
DEFAULT_AI_PROVIDER=anthropic

# Opção 2: OpenAI GPT-4
OPENAI_API_KEY=sk-XXXXX
OPENAI_MODEL=gpt-4o
# DEFAULT_AI_PROVIDER=openai

# Opção 3: DeepSeek (mais econômico)
DEEPSEEK_API_KEY=sk-XXXXX
DEEPSEEK_MODEL=deepseek-chat
# DEFAULT_AI_PROVIDER=deepseek

# Opção 4: Ollama (local e gratuito)
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
# DEFAULT_AI_PROVIDER=ollama

# ============================================
# BANCO DE DADOS
# ============================================
DB_FILE=study_system.db

# ============================================
# DEBUG (false em produção)
# ============================================
DEBUG_MODE=true
```

### 3. Iniciar o servidor

**Desenvolvimento (PHP built-in):**
```bash
php -S localhost:8000
```

**Produção (Apache/Nginx):**
- Configure virtual host apontando para o diretório
- Certifique-se que `.htaccess` está configurado

---

## 💻 Uso

### 1. Acesse o sistema

```
http://localhost:8000/login.php
```

### 2. Faça login

Use as credenciais configuradas no `.env`

### 3. Escolha o método de entrada

#### **Opção A: Upload de PDF**
- Clique na aba "📄 Upload de PDF"
- Selecione seu PDF de estudos
- Sistema extrai e analisa automaticamente
- *Disponível apenas com Anthropic Claude*

#### **Opção B: Resumo Pronto (80/20)**
- Clique na aba "📝 Resumo Pronto"
- Cole um resumo já processado
- Sistema estrutura os tópicos
- *Funciona com todos os provedores*

### 4. Estude com questões adaptativas

- Clique em "Gerar Questão"
- Responda CERTO ou ERRADO
- Veja explicação detalhada
- Sistema ajusta dificuldade automaticamente

### 5. Acompanhe seu progresso

- **Acertos/Total** - Quantas questões você acertou
- **Nível** - Dificuldade atual (1-5)
- **Aproveitamento** - Percentual de acertos
- **Pontos fracos** - Tópicos que precisam de reforço

---

## 🤖 Provedores de IA

### Comparação de Provedores

| Provedor | Custo/Sessão | PDF Nativo | Qualidade | Velocidade | Uso Recomendado |
|----------|--------------|------------|-----------|------------|-----------------|
| **Anthropic** | $0.50-1.00 | ✅ Sim | ⭐⭐⭐⭐⭐ | Rápido | Análise de PDFs |
| **OpenAI** | $0.30-0.80 | ❌ Não | ⭐⭐⭐⭐⭐ | Muito Rápido | Geração rápida |
| **DeepSeek** | $0.10-0.30 | ❌ Não | ⭐⭐⭐⭐ | Rápido | Custo-benefício |
| **Ollama** | **GRÁTIS** | ❌ Não | ⭐⭐⭐ | Médio* | Uso frequente |

*Depende do hardware

### Configuração Específica por Provedor

#### Anthropic Claude
```env
ANTHROPIC_API_KEY=sk-ant-api03-XXXXX
ANTHROPIC_MODEL=claude-sonnet-4-20250514
DEFAULT_AI_PROVIDER=anthropic
```
**Obter chave:** https://console.anthropic.com/

#### OpenAI GPT-4
```env
OPENAI_API_KEY=sk-XXXXX
OPENAI_MODEL=gpt-4o
DEFAULT_AI_PROVIDER=openai
```
**Obter chave:** https://platform.openai.com/

#### DeepSeek
```env
DEEPSEEK_API_KEY=sk-XXXXX
DEEPSEEK_MODEL=deepseek-chat
DEFAULT_AI_PROVIDER=deepseek
```
**Obter chave:** https://platform.deepseek.com/

#### Ollama (Local)
```bash
# Instalar Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Baixar modelo
ollama pull llama3.2

# Iniciar servidor
ollama serve
```

```env
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_MODEL=llama3.2
DEFAULT_AI_PROVIDER=ollama
```

---

## 📁 Estrutura do Projeto

```
sistema-rag-estudos/
├── 📄 .env.example              # Modelo de configuração
├── 📄 .env                      # Suas configurações (criar)
├── 📄 config.php                # Configurações do sistema
├── 📄 auth.php                  # Sistema de autenticação
├── 📄 database.php              # Gerenciamento SQLite
├── 📄 api.php                   # APIs unificadas
├── 📄 login.php                 # Página de login
├── 📄 logout.php                # Logout
├── 📄 index.php                 # Interface principal
├── 📄 fix_ssl.php               # Correção SSL (opcional)
├── 📄 README.md                 # Esta documentação
├── 📂 uploads/                  # PDFs enviados (auto)
├── 📄 study_system.db           # Banco SQLite (auto)
└── 📄 cacert.pem                # Certificado SSL (auto)
```

---

## 🔌 API e Integrações

### Endpoints da API Anthropic

```php
// Extração de PDF
POST https://api.anthropic.com/v1/messages
Headers:
  - x-api-key: sua_chave
  - anthropic-version: 2023-06-01
Body: { model, max_tokens, messages }
```

### Endpoints da API OpenAI

```php
// Chat Completions
POST https://api.openai.com/v1/chat/completions
Headers:
  - Authorization: Bearer sua_chave
Body: { model, messages, temperature, max_tokens }
```

### Endpoints da API DeepSeek

```php
// Chat Completions (compatível com OpenAI)
POST https://api.deepseek.com/v1/chat/completions
Headers:
  - Authorization: Bearer sua_chave
Body: { model, messages, temperature, max_tokens }
```

### API Ollama (Local)

```php
// Generate
POST http://localhost:11434/api/generate
Body: { model, prompt, stream: false }
```

---

## 🐛 Troubleshooting

### Erro: "Provedor não configurado"
**Causa:** Chave API não configurada no `.env`  
**Solução:** Configure a chave do provedor desejado

### Erro: "SSL certificate problem"
**Causa:** Certificado SSL não encontrado (Windows)  
**Solução:** Execute `php fix_ssl.php` e configure `php.ini`

### Erro: "Session already started"
**Causa:** Múltiplas chamadas de `session_start()`  
**Solução:** Já corrigido na v2.0 - atualize os arquivos

### Erro: "Cannot connect to Ollama"
**Causa:** Ollama não está rodando  
**Solução:** Execute `ollama serve` em um terminal

### Erro: "Permission denied"
**Causa:** Sem permissão para criar banco SQLite  
**Solução:** `chmod 777 .` na pasta do projeto

### Upload de PDF muito lento
**Causa:** PDF grande ou conexão lenta  
**Solução:** Use a opção "Resumo Pronto (80/20)"

### Questões de baixa qualidade
**Causa:** Conteúdo resumido demais  
**Solução:** Forneça mais contexto no resumo inicial

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Siga estes passos:

1. **Fork** o projeto
2. Crie uma **branch** para sua feature (`git checkout -b feature/NovaFuncionalidade`)
3. **Commit** suas mudanças (`git commit -m 'Adiciona nova funcionalidade'`)
4. **Push** para a branch (`git push origin feature/NovaFuncionalidade`)
5. Abra um **Pull Request**

### Diretrizes:
- ✅ Siga o padrão PSR-2 para PHP
- ✅ Comente código complexo
- ✅ Teste antes de enviar
- ✅ Atualize a documentação se necessário

---

## 🗺️ Roadmap

### v2.1 (Próxima Release)
- [ ] Sistema multi-usuário com banco de usuários
- [ ] Relatórios de progresso em PDF
- [ ] Gráficos de desempenho
- [ ] Exportação para Anki

### v2.2
- [ ] Modo escuro
- [ ] Flashcards automáticos
- [ ] Estatísticas avançadas
- [ ] Compartilhamento de materiais

### v3.0
- [ ] Aplicativo móvel (React Native)
- [ ] API REST completa
- [ ] Sincronização em nuvem
- [ ] Modo colaborativo
- [ ] Gamificação e rankings

---

## 📊 Estatísticas do Projeto

- **Linhas de código:** ~2.500
- **Arquivos PHP:** 7
- **Provedores de IA:** 4
- **Níveis de dificuldade:** 5
- **Tempo médio de setup:** 10 minutos

---

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

## 👥 Autores

- **Seu Nome** - *Trabalho Inicial* - [GitHub](https://github.com/seu-usuario)

---

## 🙏 Agradecimentos

- [Anthropic](https://anthropic.com) - API Claude
- [OpenAI](https://openai.com) - API GPT
- [DeepSeek](https://deepseek.com) - API DeepSeek
- [Ollama](https://ollama.com) - IA Local
- [Tailwind CSS](https://tailwindcss.com) - Framework CSS
- Comunidade PHP

---

## 📞 Suporte

- 📧 Email: seu-email@exemplo.com
- 💬 Issues: [GitHub Issues](https://github.com/seu-usuario/sistema-rag-estudos/issues)
- 📖 Docs: [Wiki do Projeto](https://github.com/seu-usuario/sistema-rag-estudos/wiki)

---

## 🌟 Mostre seu apoio

Se este projeto te ajudou, considere dar uma ⭐️!

---

<div align="center">

**Desenvolvido com ❤️ e ☕ para estudantes e concurseiros**

[⬆ Voltar ao topo](#-sistema-rag-de-estudos-inteligente-v20)

</div>