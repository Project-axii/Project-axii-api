# Project Axii — Documentação Completa da API

**Versão:** 1.0  
**Formato:** JSON (`Content-Type: application/json`)  
**Charset:** UTF-8

---

## Sumário

- [Visão Geral](#visão-geral)
- [Autenticação](#autenticação)
- [Rate Limiting](#rate-limiting)
- [Erros Comuns](#erros-comuns)
- [Módulo: Autenticação](#módulo-autenticação)
  - [POST /auth/login.php](#post-authloginphp)
  - [POST /auth/register.php](#post-authregisterphp)
  - [POST /auth/logout.php](#post-authlogoutphp)
  - [POST /auth/verify-token.php](#post-authverify-tokenphp)
- [Módulo: Usuário](#módulo-usuário)
  - [GET /user/get_profile.php](#get-userget_profilephp)
  - [PUT /user/update_profile.php](#put-userupdate_profilephp)
  - [PUT /user/update_password.php](#put-userupdate_passwordphp)
  - [POST /user/verify_password.php](#post-userverify_passwordphp)
  - [POST /user/update_photo.php](#post-userupdate_photophp)
  - [POST /user/validate_password.php](#post-uservalidate_passwordphp)
- [Módulo: Dispositivos](#módulo-dispositivos)
  - [GET /devices/list.php](#get-deviceslistphp)
  - [POST /devices/create.php](#post-devicescreatephp)
  - [PUT /devices/update.php](#put-devicesupdatephp)
  - [DELETE /devices/delete.php](#delete-devicesdeletephp)
  - [POST /devices/toggle.php](#post-devicestogglephp)
  - [GET /devices/rooms.php](#get-devicesroomsphp)
  - [POST /devices/toggle_group.php](#post-devicestoggle_groupphp)
- [Módulo: Rotinas](#módulo-rotinas)
  - [GET /routine/list.php](#get-routinelistphp)
  - [POST /routine/create.php](#post-routinecreatephp)
  - [PUT /routine/update.php](#put-routineupdatephp)
  - [DELETE /routine/delete.php](#delete-routinedeletephp)
  - [POST /routine/toggle.php](#post-routinetogglephp)
  - [POST /routine/execute.php](#post-routineexecutephp)
- [Módulo: Grupos](#módulo-grupos)
  - [GET /group/list.php](#get-grouplistphp)
  - [POST /group/create.php](#post-groupcreatephp)
- [Módulo: Listas de Tarefas](#módulo-listas-de-tarefas)
  - [GET /list/list.php](#get-listlistphp)
  - [POST /list/create.php](#post-listcreatephp)
  - [PUT /list/update.php](#put-listupdatephp)
  - [DELETE /list/delete.php](#delete-listdeletephp)
  - [POST /list/itens.php](#post-listitenphp)
  - [PUT /list/itens.php](#put-listitenphp)
  - [DELETE /list/itens.php](#delete-listitenphp)
  - [POST /list/toggle_item.php](#post-listtoggle_itemphp)
- [Módulo: Notificações](#módulo-notificações)
  - [GET /notifications/read.php](#get-notificationsreadphp)
  - [PUT /notifications/mark_read.php](#put-notificationsmark_readphp)
  - [DELETE /notifications/delete.php](#delete-notificationsdeletephp)

---

## Visão Geral

A **Project Axii API** é o backend de uma plataforma de automação e gerenciamento de dispositivos IoT. Ela permite que usuários autenticados cadastrem e controlem dispositivos em diferentes salas, criem rotinas de automação agendadas, organizem dispositivos em grupos, gerenciem listas de tarefas e visualizem notificações do sistema.

### Casos de uso principais

| Funcionalidade | Descrição |
|---------------|-----------|
| Autenticação | Cadastro, login, logout e verificação de sessão |
| Gerenciamento de perfil | Atualização de dados pessoais, senha e foto |
| Dispositivos | Cadastro e controle de dispositivos por sala e tipo |
| Rotinas | Automações agendadas para ligar/desligar dispositivos em horários programados |
| Grupos | Agrupamento lógico de dispositivos para controle em conjunto |
| Listas de tarefas | Listas pessoais com itens marcáveis como concluídos |
| Notificações | Alertas e avisos gerados automaticamente pelo sistema |

---

## Autenticação

A maioria dos endpoints exige autenticação via **Bearer Token** no header `Authorization`.

```
Authorization: Bearer <token>
```

O token é obtido nos endpoints de **login** ou **registro** e tem validade de **24 horas**.

### Endpoints que NÃO exigem autenticação

| Endpoint | Observação |
|----------|-----------|
| `POST /auth/login.php` | Gera o token |
| `POST /auth/register.php` | Gera o token |
| `POST /auth/verify-token.php` | Valida o token pelo body |
| `POST /user/validate_password.php` | Avalia força da senha — utilitário público |

Todos os demais endpoints **retornam 401** se o token não for enviado ou estiver expirado.

---

## Rate Limiting

Endpoints sensíveis possuem limitação de requisições por IP para prevenir abuso:

| Endpoint | Limite | Janela |
|----------|--------|--------|
| `POST /auth/login.php` | 5 tentativas | 10 minutos |
| `POST /auth/register.php` | 5 tentativas | 10 minutos |
| `POST /auth/verify-token.php` | 5 tentativas | 10 minutos |
| `POST /auth/logout.php` | 5 tentativas | 10 minutos |
| `POST /user/update_photo.php` | 5 tentativas | 10 minutos |

Quando o limite é atingido, a API retorna `429 Too Many Requests` com o header `Retry-After` indicando os segundos restantes.

**Exemplo de resposta 429:**
```json
{
  "success": false,
  "message": "Muitas tentativas. Tente novamente em 8 minuto(s).",
  "retry_after": 480,
  "limit": 5,
  "window": "10 min"
}
```

---

## Erros Comuns

| Código HTTP | Significado | Situações típicas |
|-------------|-------------|-------------------|
| `400` | Bad Request | Campo obrigatório ausente, dados inválidos |
| `401` | Unauthorized | Token ausente, inválido ou expirado |
| `404` | Not Found | Recurso não encontrado ou sem permissão de acesso |
| `405` | Method Not Allowed | Método HTTP incorreto para o endpoint |
| `409` | Conflict | Registro duplicado (e-mail, IP de dispositivo) |
| `429` | Too Many Requests | Rate limit atingido |
| `500` | Internal Server Error | Erro no servidor |
| `503` | Service Unavailable | Operação no banco não pôde ser concluída |

**Formato padrão de erro:**
```json
{
  "success": false,
  "message": "Descrição do erro para o usuário"
}
```

---

## Módulo: Autenticação

---

### POST /auth/login.php

Autentica um usuário com e-mail e senha. Retorna o token de sessão e os dados básicos do usuário.

**Autenticação:** Não requerida  
**Rate limit:** 5 tentativas / 10 min por IP. O contador é zerado automaticamente após login com sucesso.

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "email": "usuario@exemplo.com",
  "password": "minhasenha123"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `email` | string | Sim | E-mail do usuário cadastrado |
| `password` | string | Sim | Senha do usuário |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "token": "eyJpZCI6MSwiZW1haWwiOiJ1c2VyQGV4LmNvbSIsImV4cCI6MTc1MDAwMDAwMH0=",
  "user": {
    "id": 1,
    "nome": "João Silva",
    "email": "usuario@exemplo.com",
    "foto": "https://storage.supabase.co/profile-photos/user_1.jpg",
    "tipo_usuario": "professor"
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | E-mail e senha são obrigatórios | Campo em branco |
| `401` | E-mail ou senha inválidos | Credenciais incorretas ou e-mail não cadastrado |
| `429` | Muitas tentativas... | Rate limit atingido |

---

### POST /auth/register.php

Cria uma nova conta de usuário e retorna o token de sessão imediatamente, sem necessidade de fazer login separado.

**Autenticação:** Não requerida  
**Rate limit:** 5 tentativas / 10 min por IP

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "name": "João Silva",
  "email": "joao@exemplo.com",
  "password": "minhasenha123"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| `name` | string | Sim | Mínimo 3 caracteres |
| `email` | string | Sim | Formato de e-mail válido |
| `password` | string | Sim | Mínimo 6 caracteres |

**Resposta de sucesso — 201 Created:**
```json
{
  "success": true,
  "message": "Cadastro realizado com sucesso!",
  "token": "eyJpZCI6MSwiZW1haWwiOiJ1c2VyQGV4LmNvbSIsImV4cCI6MTc1MDAwMDAwMH0=",
  "user": {
    "id": 1,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "tipo_usuario": "professor"
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Nome deve ter pelo menos 3 caracteres | Nome muito curto |
| `400` | E-mail inválido | Formato incorreto |
| `400` | Senha deve ter pelo menos 6 caracteres | Senha muito curta |
| `409` | Este e-mail já está cadastrado | E-mail em uso por outra conta |

---

### POST /auth/logout.php

Registra o logout do usuário no log de atividades e encerra a sessão. Como o sistema não armazena tokens no banco, a invalidação efetiva do token deve ser feita pelo cliente (removendo-o do armazenamento local).

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Body:** Vazio (nenhum campo necessário)

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `401` | Token de autenticação não fornecido | Header ausente |
| `401` | Token inválido ou expirado | Token corrompido ou vencido |

---

### POST /auth/verify-token.php

Verifica se um token ainda é válido e retorna os dados atualizados do usuário. Útil para restaurar sessão ao reabrir o aplicativo.

**Autenticação:** Não requerida (o token é enviado no body)

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "token": "eyJpZCI6MSwiZW1haWwiOiJ1c2VyQGV4LmNvbSIsImV4cCI6MTc1MDAwMDAwMH0="
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `token` | string | Sim | Token a ser verificado |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "foto": "https://...",
    "tipo_usuario": "professor"
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Token não fornecido | Campo `token` ausente |
| `401` | Token expirado | TTL de 24h ultrapassado |
| `401` | Token inválido | Estrutura inválida |
| `401` | Usuário não encontrado | ID no token não existe mais no banco |

---

## Módulo: Usuário

---

### GET /user/get_profile.php

Retorna o perfil completo do usuário autenticado. Um usuário só pode consultar o próprio perfil — não é possível consultar o perfil de outros usuários.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Parâmetros de query:** Nenhum

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "foto": "https://storage.supabase.co/profile-photos/user_1.jpg",
    "tipo_usuario": "professor",
    "ativo": 1,
    "data_criacao": "2025-01-15 10:30:00",
    "data_atualizacao": "2025-06-01 14:22:00"
  }
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | int | ID único do usuário |
| `nome` | string | Nome completo |
| `email` | string | Endereço de e-mail |
| `foto` | string\|null | URL pública da foto de perfil |
| `tipo_usuario` | string | Papel do usuário (ex.: `professor`) |
| `ativo` | int | `1` = ativo, `0` = inativo |
| `data_criacao` | datetime | Data de criação da conta |
| `data_atualizacao` | datetime | Data da última atualização |

---

### PUT /user/update_profile.php

Atualiza o nome e o e-mail do usuário autenticado. O ID do usuário é extraído do token — não pode ser alterado via body.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "nome": "João Souza",
  "email": "novo@exemplo.com"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| `nome` | string | Sim | Não pode estar vazio |
| `email` | string | Sim | Formato de e-mail válido |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Perfil atualizado com sucesso",
  "user": {
    "id": 1,
    "nome": "João Souza",
    "email": "novo@exemplo.com",
    "foto": "https://...",
    "tipo_usuario": "professor"
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Nome e e-mail são obrigatórios | Campo em branco |
| `400` | E-mail inválido | Formato incorreto |
| `409` | Este e-mail já está em uso | E-mail pertence a outra conta |

---

### PUT /user/update_password.php

Altera a senha do usuário. Exige a senha atual como confirmação de identidade. O ID do usuário é extraído do token.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "currentPassword": "senhaAtual123",
  "newPassword": "novaSenha456",
  "confirmPassword": "novaSenha456"
}
```

> **Alias aceitos:** Os campos também podem ser enviados como `senha_atual`, `senha_nova` e `confirmar_senha`.

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| `currentPassword` | string | Sim | Senha atual do usuário |
| `newPassword` | string | Sim | Mínimo 6 caracteres |
| `confirmPassword` | string | Sim | Deve ser igual a `newPassword` |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Senha atualizada com sucesso"
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Todos os campos de senha são obrigatórios | Campo em branco |
| `400` | A nova senha e a confirmação não coincidem | `newPassword` ≠ `confirmPassword` |
| `400` | A senha deve ter no mínimo 6 caracteres | Nova senha curta demais |
| `400` | Senha atual incorreta | Senha atual não confere |
| `400` | A nova senha deve ser diferente da senha atual | Nova igual à atual |

> Uma notificação do tipo `aviso` é criada automaticamente quando a senha é alterada com sucesso.

---

### POST /user/verify_password.php

Verifica se uma senha fornecida corresponde à senha atual do usuário autenticado. Útil para confirmar identidade antes de ações críticas (ex.: excluir conta, revogar sessões).

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "password": "minhasenha123"
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "valid": true,
  "message": "Senha válida"
}
```

```json
{
  "success": true,
  "valid": false,
  "message": "Senha inválida"
}
```

> A resposta sempre tem código `200` — `valid` indica se a senha confere ou não.

---

### POST /user/update_photo.php

Faz upload de uma nova foto de perfil para o Supabase Storage e atualiza a URL no banco de dados.

**Autenticação:** Requerida (Bearer Token)  
**Rate limit:** 5 uploads / 10 min por IP  
**Formato:** `multipart/form-data`

**Headers:**
```
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

**Body (form-data):**

| Campo | Tipo | Obrigatório | Restrições |
|-------|------|-------------|------------|
| `photo` | arquivo | Sim | JPEG, PNG ou WEBP · Máximo 5 MB |

> O tipo MIME é verificado pelos **magic bytes** reais do arquivo (não pelo nome ou tipo declarado pelo cliente).

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Foto atualizada com sucesso",
  "photo_url": "https://storage.supabase.co/storage/v1/object/public/profile-photos/user_1_1749823741.jpg",
  "user": {
    "id": 1,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "foto": "https://storage.supabase.co/storage/v1/object/public/profile-photos/user_1_1749823741.jpg",
    "tipo_usuario": "professor"
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Nenhum arquivo foi enviado | Campo `photo` ausente |
| `400` | O arquivo excede o tamanho máximo permitido | Arquivo > 5 MB |
| `400` | Tipo de arquivo não permitido | Não é JPEG, PNG ou WEBP |
| `500` | Erro no upload da foto | Falha na comunicação com Supabase |

---

### POST /user/validate_password.php

Avalia a força de uma senha e retorna feedback detalhado com pontuação e sugestões. Endpoint público, sem autenticação — ideal para uso em formulários de cadastro e troca de senha no frontend.

**Autenticação:** Não requerida

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "password": "MinhaSenha@2025"
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "strength": {
    "score": 100,
    "level": "strong",
    "feedback": [
      "Comprimento adequado",
      "Contém letras maiúsculas",
      "Contém letras minúsculas",
      "Contém números",
      "Contém caracteres especiais"
    ]
  }
}
```

| Campo | Descrição |
|-------|-----------|
| `score` | Pontuação de 0 a 100 |
| `level` | `weak` (0–49) · `medium` (50–79) · `strong` (80–100) |
| `feedback` | Lista de critérios atendidos e sugestões de melhoria |

**Critérios de pontuação:**

| Critério | Pontos |
|----------|--------|
| 8+ caracteres | +25 |
| Letras maiúsculas | +25 |
| Letras minúsculas | +25 |
| Números | +15 |
| Caracteres especiais (`!@#$%...`) | +10 |

---

## Módulo: Dispositivos

Gerencia os dispositivos IoT cadastrados pelo usuário. Cada dispositivo pertence a uma sala e tem um tipo, status de conexão e estado de ativação.

**Tipos válidos de dispositivo:** `computador` · `projetor` · `iluminacao` · `ar_condicionado` · `outro`  
**Status válidos:** `online` · `offline` · `manutencao`

---

### GET /devices/list.php

Retorna todos os dispositivos do usuário autenticado. Pode ser filtrado por sala.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Parâmetros de query (opcionais):**

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `sala` | string | Filtra dispositivos de uma sala específica |

**Exemplo:** `GET /api/devices/list.php?sala=Sala%20101`

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "Computador Sala 101",
      "ip": "192.168.1.101",
      "tipo": "computador",
      "sala": "Sala 101",
      "descricao": "PC do professor",
      "status": "online",
      "ativo": true,
      "data_cadastro": "2025-01-15 10:30:00",
      "ultima_conexao": "2025-06-13 08:45:00"
    }
  ],
  "total": 1
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | int | ID único do dispositivo |
| `nome` | string | Nome identificador |
| `ip` | string | Endereço IP ou hostname |
| `tipo` | string | Tipo do dispositivo |
| `sala` | string | Sala onde está localizado |
| `descricao` | string | Descrição opcional |
| `status` | string | `online` / `offline` / `manutencao` |
| `ativo` | bool | Se o dispositivo está habilitado |
| `data_cadastro` | datetime | Data de criação do registro |
| `ultima_conexao` | datetime\|null | Última vez que o status foi atualizado |

---

### POST /devices/create.php

Cadastra um novo dispositivo. O IP deve ser único por usuário — não é possível cadastrar dois dispositivos com o mesmo IP.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "nome": "Projetor Sala 202",
  "ip": "192.168.1.202",
  "tipo": "projetor",
  "sala": "Sala 202",
  "descricao": "Projetor Epson 3500 lumens"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `nome` | string | Sim | Nome do dispositivo |
| `ip` | string | Sim | IP ou hostname do dispositivo |
| `tipo` | string | Sim | Um dos tipos válidos listados acima |
| `sala` | string | Sim | Nome da sala |
| `descricao` | string | Não | Descrição livre |

**Resposta de sucesso — 201 Created:**
```json
{
  "success": true,
  "message": "Dispositivo criado com sucesso",
  "data": {
    "id": 5,
    "nome": "Projetor Sala 202",
    "ip": "192.168.1.202",
    "tipo": "projetor",
    "sala": "Sala 202",
    "descricao": "Projetor Epson 3500 lumens",
    "status": "offline",
    "ativo": true,
    "data_cadastro": "2025-06-13 09:00:00",
    "ultima_conexao": null
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Campos obrigatórios: nome, ip, tipo, sala | Campo faltando |
| `400` | Tipo de dispositivo inválido | Valor não permitido para `tipo` |
| `409` | Já existe um dispositivo com este IP | IP duplicado para o usuário |

---

### PUT /devices/update.php

Atualiza campos de um dispositivo existente. Apenas os campos enviados são atualizados — os demais permanecem inalterados.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 5,
  "nome": "Projetor Novo Nome",
  "ip": "192.168.1.210",
  "tipo": "projetor",
  "sala": "Sala 303",
  "descricao": "Descrição atualizada",
  "status": "manutencao"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | int | Sim | ID do dispositivo a atualizar |
| `nome` | string | Não | Novo nome |
| `ip` | string | Não | Novo IP (deve ser único) |
| `tipo` | string | Não | Novo tipo (deve ser válido) |
| `sala` | string | Não | Nova sala |
| `descricao` | string | Não | Nova descrição |
| `status` | string | Não | Novo status (`online`/`offline`/`manutencao`) |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Dispositivo atualizado com sucesso",
  "data": { ... }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | ID do dispositivo não fornecido | Campo `id` ausente |
| `400` | Nenhum campo para atualizar | Body sem campos editáveis |
| `400` | Tipo de dispositivo inválido | Valor inválido para `tipo` |
| `400` | Status inválido | Valor inválido para `status` |
| `404` | Dispositivo não encontrado ou sem permissão | ID não existe ou pertence a outro usuário |
| `409` | Já existe outro dispositivo com este IP | IP já em uso |

---

### DELETE /devices/delete.php

Remove permanentemente um dispositivo e todas as suas associações com grupos. O ID pode ser enviado via query string ou body.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Opção 1 — Query string:**
```
DELETE /api/devices/delete.php?id=5
```

**Opção 2 — Body JSON:**
```json
{
  "id": 5
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Dispositivo deletado com sucesso",
  "data": {
    "id": 5,
    "nome": "Projetor Sala 202"
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | ID do dispositivo não fornecido | Nenhum ID informado |
| `404` | Dispositivo não encontrado ou sem permissão | ID inválido ou pertence a outro usuário |

---

### POST /devices/toggle.php

Alterna o status de um dispositivo entre `online` e `offline`. Suporta também alternância do estado de ativação (`ativo`).

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 5,
  "action": "toggle_status"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | int | Sim | ID do dispositivo |
| `action` | string | Não | `toggle_status` (padrão) ou `toggle_active` |

**Ações disponíveis:**

| `action` | Comportamento |
|----------|---------------|
| `toggle_status` (padrão) | Alterna entre `online` ↔ `offline` |
| `toggle_active` | Ativa ou desativa o dispositivo |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Dispositivo alternado com sucesso",
  "data": {
    "id": 5,
    "nome": "Projetor Sala 202",
    "ip": "192.168.1.202",
    "tipo": "projetor",
    "sala": "Sala 202",
    "descricao": "...",
    "status": "online",
    "ativo": true,
    "data_cadastro": "...",
    "ultima_conexao": "2025-06-13 09:15:00"
  }
}
```

---

### GET /devices/rooms.php

Retorna a lista de salas que possuem dispositivos cadastrados pelo usuário, com estatísticas de dispositivos online e offline por sala. Útil para dashboards e visões por localização.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "name": "Sala 101",
      "devices": 4,
      "online": 3,
      "offline": 1
    },
    {
      "name": "Laboratório",
      "devices": 10,
      "online": 10,
      "offline": 0
    }
  ],
  "total": 2
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `name` | string | Nome da sala |
| `devices` | int | Total de dispositivos na sala |
| `online` | int | Dispositivos com status `online` |
| `offline` | int | Dispositivos com status `offline` ou `manutencao` |

---

### POST /devices/toggle_group.php

Liga, desliga ou alterna todos os dispositivos de uma sala em uma única chamada. Pode ser filtrado por tipo de dispositivo.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "sala": "Sala 101",
  "action": "toggle",
  "tipo": "projetor"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `sala` | string | Sim | Nome da sala |
| `action` | string | Sim | `ligar` · `desligar` · `toggle` |
| `tipo` | string | Não | Filtra por tipo de dispositivo |

**Comportamento das ações:**

| `action` | Resultado |
|----------|-----------|
| `ligar` | Todos para `online` |
| `desligar` | Todos para `offline` |
| `toggle` | Se algum estiver `online`, todos vão para `offline`; caso contrário, todos vão para `online` |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Status do grupo atualizado com sucesso",
  "data": {
    "sala": "Sala 101",
    "tipo": "projetor",
    "action": "toggle",
    "new_status": "offline",
    "devices_updated": 3
  }
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | Nome da sala é obrigatório | Campo `sala` ausente |
| `400` | Ação inválida | `action` não é `ligar`, `desligar` ou `toggle` |
| `404` | Nenhum dispositivo encontrado nesta sala | Sala vazia ou sem permissão |

---

## Módulo: Rotinas

Rotinas são automações que executam uma ação em um dispositivo ou grupo em horários e dias da semana pré-configurados.

**Ações válidas:** `ligar` · `desligar` · `reiniciar` · `custom`  
**Dias válidos:** `domingo` · `segunda` · `terca` · `quarta` · `quinta` · `sexta` · `sabado`

---

### GET /routine/list.php

Retorna todas as rotinas do usuário, incluindo nome do dispositivo ou grupo alvo.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "Ligar projetores manhã",
      "descricao": "Liga todos os projetores às 7h30",
      "horario_ini": "07:30",
      "horario_fim": "12:00",
      "dias_semana": ["segunda", "terca", "quarta", "quinta", "sexta"],
      "acao": "ligar",
      "parametros": null,
      "ativo": true,
      "alvo_nome": "Projetor Sala 101",
      "alvo_tipo": "dispositivo",
      "id_dispositivo": 3,
      "id_grupo": null,
      "data_criacao": "2025-01-20 09:00:00"
    }
  ],
  "total": 1
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `horario_ini` | string | Horário de início no formato `HH:MM` |
| `horario_fim` | string | Horário de fim no formato `HH:MM` |
| `dias_semana` | string[] | Lista de dias em que a rotina é executada |
| `acao` | string | Ação a executar |
| `parametros` | object\|null | Dados extras para ação `custom` |
| `ativo` | bool | Se a rotina está habilitada |
| `alvo_nome` | string\|null | Nome do dispositivo ou grupo alvo |
| `alvo_tipo` | string\|null | `dispositivo` ou `grupo` |

---

### POST /routine/create.php

Cria uma nova rotina de automação. Deve ser associada a um dispositivo **ou** a um grupo, nunca a ambos nem a nenhum.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "nome": "Ligar projetores manhã",
  "descricao": "Automação matinal",
  "horario_ini": "07:30:00",
  "horario_fim": "12:00:00",
  "dias_semana": ["segunda", "terca", "quarta", "quinta", "sexta"],
  "acao": "ligar",
  "id_dispositivo": 3,
  "id_grupo": null,
  "parametros": null,
  "ativo": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `nome` | string | Sim | Nome da rotina |
| `descricao` | string | Não | Descrição opcional |
| `horario_ini` | string | Sim | Hora de início (`HH:MM:SS`) |
| `horario_fim` | string | Sim | Hora de fim (`HH:MM:SS`) |
| `dias_semana` | string[] | Sim | Lista de dias (pelo menos um) |
| `acao` | string | Sim | Uma das ações válidas |
| `id_dispositivo` | int\|null | Condicional | ID do dispositivo alvo |
| `id_grupo` | int\|null | Condicional | ID do grupo alvo |
| `parametros` | object\|null | Não | Parâmetros extras (para ação `custom`) |
| `ativo` | int | Não | `1` = ativa (padrão), `0` = inativa |

> **Regra:** `id_dispositivo` **ou** `id_grupo` deve ser fornecido (mas não ambos).

**Dias aceitos (normalização automática):**

| Enviado | Normalizado para |
|---------|-----------------|
| `segunda`, `segunda-feira` | `segunda` |
| `terça`, `terça-feira`, `terca-feira` | `terca` |
| `sábado`, `sabado-feira` | `sabado` |

**Resposta de sucesso — 201 Created:**
```json
{
  "success": true,
  "message": "Rotina criada com sucesso",
  "id": 1
}
```

---

### PUT /routine/update.php

Atualiza todos os campos de uma rotina existente. Todos os campos obrigatórios devem ser reenviados (substituição completa).

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:** Mesmo formato de criação, com o campo `id` adicional:
```json
{
  "id": 1,
  "nome": "Ligar projetores manhã (atualizado)",
  "horario_ini": "08:00:00",
  "horario_fim": "13:00:00",
  "dias_semana": ["segunda", "quarta", "sexta"],
  "acao": "ligar",
  "id_dispositivo": 3,
  "ativo": true
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Rotina atualizada com sucesso"
}
```

---

### DELETE /routine/delete.php

Remove uma rotina permanentemente.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Query string:**
```
DELETE /api/routine/delete.php?id=1
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Rotina deletada com sucesso"
}
```

**Erro:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | ID inválido | Parâmetro `id` ausente ou zero |
| `503` | Não foi possível deletar a rotina | Rotina não encontrada ou sem permissão |

---

### POST /routine/toggle.php

Ativa ou desativa uma rotina sem removê-la. Alterna o campo `ativo` entre `true` e `false`.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 1
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Status alterado com sucesso"
}
```

---

### POST /routine/execute.php

Executa uma rotina imediatamente, de forma manual, independente do horário ou dias configurados.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 1
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Rotina executada com sucesso"
}
```

**Erro:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `404` | Rotina não encontrada | ID inválido ou sem permissão |

---

## Módulo: Grupos

Grupos permitem agrupar dispositivos logicamente para controlá-los em conjunto via rotinas ou pela interface.

---

### GET /group/list.php

Retorna todos os grupos ativos do usuário, com contagem de dispositivos em cada grupo.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nome": "Projetores Bloco A",
      "descricao": "Todos os projetores do bloco A",
      "cor": "#3498db",
      "ativo": true,
      "data_criacao": "2025-02-10 14:00:00",
      "total_dispositivos": 5
    }
  ],
  "total": 1
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `cor` | string | Cor em hex para identificação visual |
| `total_dispositivos` | int | Número de dispositivos no grupo |

---

### POST /group/create.php

Cria um novo grupo e opcionalmente já associa dispositivos a ele.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "nome": "Projetores Bloco A",
  "descricao": "Todos os projetores do bloco A",
  "cor": "#3498db",
  "ativo": 1,
  "dispositivos": [1, 3, 5]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `nome` | string | Sim | Nome do grupo |
| `descricao` | string | Não | Descrição opcional |
| `cor` | string | Não | Cor em hex (padrão: `#3498db`) |
| `ativo` | int | Não | `1` = ativo (padrão) |
| `dispositivos` | int[] | Não | IDs dos dispositivos a incluir |

**Resposta de sucesso — 201 Created:**
```json
{
  "success": true,
  "message": "Grupo criado com sucesso",
  "id": 1
}
```

---

## Módulo: Listas de Tarefas

Sistema de listas pessoais com itens que podem ser marcados como concluídos. Cada lista tem uma cor de identificação e pode conter múltiplos itens.

---

### GET /list/list.php

Retorna todas as listas do usuário com seus respectivos itens.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "titulo": "Verificações do Laboratório",
      "cor": "blue",
      "ativo": true,
      "total_itens": 3,
      "concluidos": 1,
      "itens": [
        {
          "id": 1,
          "texto": "Verificar projetores",
          "concluido": true,
          "ordem": 1,
          "data_criacao": "2025-06-01 08:00:00",
          "data_conclusao": "2025-06-01 09:30:00"
        },
        {
          "id": 2,
          "texto": "Testar computadores",
          "concluido": false,
          "ordem": 2,
          "data_criacao": "2025-06-01 08:00:00",
          "data_conclusao": null
        }
      ],
      "data_criacao": "2025-06-01 08:00:00"
    }
  ]
}
```

---

### POST /list/create.php

Cria uma nova lista e opcionalmente já adiciona itens a ela.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "titulo": "Verificações do Laboratório",
  "cor": "blue",
  "itens": [
    "Verificar projetores",
    "Testar computadores",
    "Checar internet"
  ]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `titulo` | string | Sim | Título da lista |
| `cor` | string | Não | Identificação visual (padrão: `blue`) |
| `itens` | string[] | Não | Textos dos itens a criar junto com a lista |

**Resposta de sucesso — 201 Created:**
```json
{
  "success": true,
  "message": "Lista criada com sucesso",
  "data": {
    "id": 1,
    "titulo": "Verificações do Laboratório",
    "cor": "blue",
    "ativo": true,
    "itens": [
      { "id": 1, "texto": "Verificar projetores", "concluido": false, "ordem": 1 },
      { "id": 2, "texto": "Testar computadores", "concluido": false, "ordem": 2 }
    ],
    "data_criacao": "2025-06-13 09:00:00"
  }
}
```

---

### PUT /list/update.php

Atualiza o título e/ou a cor de uma lista existente.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 1,
  "titulo": "Verificações Semanais",
  "cor": "green"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | int | Sim | ID da lista |
| `titulo` | string | Sim | Novo título |
| `cor` | string | Não | Nova cor (mantém a atual se omitida) |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Lista atualizada com sucesso",
  "data": { ... }
}
```

---

### DELETE /list/delete.php

Remove uma lista e todos os seus itens permanentemente.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 1
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Lista deletada com sucesso"
}
```

---

### POST /list/itens.php

Adiciona um novo item ao final de uma lista existente.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id_lista": 1,
  "texto": "Novo item da lista"
}
```

**Resposta de sucesso — 201 Created:**
```json
{
  "success": true,
  "message": "Item adicionado com sucesso",
  "data": {
    "id": 10
  }
}
```

---

### PUT /list/itens.php

Atualiza o texto e/ou o status de conclusão de um item.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 10,
  "id_lista": 1,
  "texto": "Texto atualizado",
  "concluido": true
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `id` | int | Sim | ID do item |
| `id_lista` | int | Sim | ID da lista pai |
| `texto` | string | Não | Novo texto do item |
| `concluido` | bool | Não | Status de conclusão |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Item atualizado com sucesso"
}
```

---

### DELETE /list/itens.php

Remove um item de uma lista.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Query string:**
```
DELETE /api/list/itens.php?id=10&id_lista=1
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `id` | int | Sim | ID do item |
| `id_lista` | int | Sim | ID da lista pai |

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Item deletado com sucesso"
}
```

---

### POST /list/toggle_item.php

Alterna o status de conclusão de um item entre concluído e não concluído.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 10,
  "id_lista": 1
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Status do item atualizado"
}
```

---

## Módulo: Notificações

Notificações são geradas automaticamente pelo sistema em eventos como login, criação de usuário, atualização de perfil e erro em dispositivos. O usuário não cria notificações manualmente.

**Tipos de notificação:**

| Tipo | Ícone | Cor | Uso |
|------|-------|-----|-----|
| `info` | info | `#3B82F6` (azul) | Informações gerais, login |
| `sucesso` | check_circle | `#10B981` (verde) | Ações concluídas com êxito |
| `aviso` | warning | `#F59E0B` (amarelo) | Alertas e mudanças importantes |
| `erro` | error | `#EF4444` (vermelho) | Falhas e problemas |

---

### GET /notifications/read.php

Retorna as últimas 50 notificações do usuário, ordenadas da mais recente para a mais antiga, com tempo decorrido calculado.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "tipo": "sucesso",
      "titulo": "Perfil Atualizado",
      "mensagem": "Suas informações foram atualizadas com sucesso.",
      "lida": false,
      "tempo": "2 min atrás",
      "data_criacao": "2025-06-13 09:10:00",
      "data_leitura": null,
      "dispositivo": null,
      "dispositivo_tipo": null,
      "icon": "check_circle",
      "color": "#10B981"
    }
  ],
  "nao_lidas": 3,
  "total": 15
}
```

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `tempo` | string | Tempo decorrido legível (`Agora`, `5 min atrás`, `2 dias atrás`) |
| `lida` | bool | Se o usuário já visualizou |
| `nao_lidas` | int | Total de notificações não lidas (útil para badges) |
| `dispositivo` | string\|null | Nome do dispositivo relacionado, quando aplicável |
| `icon` | string | Nome do ícone Material Design |
| `color` | string | Cor hex do ícone |

---

### PUT /notifications/mark_read.php

Marca uma notificação específica como lida, ou marca **todas** as notificações não lidas de uma vez.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Opção 1 — Marcar uma notificação específica:**
```json
{
  "id": 42
}
```

**Opção 2 — Marcar todas como lidas:**
```json
{
  "mark_all": true
}
```

**Resposta de sucesso — marcar uma (200 OK):**
```json
{
  "success": true,
  "message": "Notificação marcada como lida"
}
```

**Resposta de sucesso — marcar todas (200 OK):**
```json
{
  "success": true,
  "message": "Todas as notificações foram marcadas como lidas",
  "affected": 3
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | ID da notificação ou mark_all é obrigatório | Nenhum dos campos enviado |
| `404` | Notificação não encontrada | ID inválido ou pertence a outro usuário |

---

### DELETE /notifications/delete.php

Remove permanentemente uma notificação.

**Autenticação:** Requerida (Bearer Token)

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Body:**
```json
{
  "id": 42
}
```

**Resposta de sucesso — 200 OK:**
```json
{
  "success": true,
  "message": "Notificação removida com sucesso"
}
```

**Erros:**

| Código | Mensagem | Causa |
|--------|----------|-------|
| `400` | ID da notificação é obrigatório | Campo `id` ausente |
| `404` | Notificação não encontrada | ID inválido ou pertence a outro usuário |

---

## Referência Rápida

### Todos os Endpoints

| Método | Endpoint | Autenticação | Descrição |
|--------|----------|:------------:|-----------|
| `POST` | `/auth/login.php` | ❌ | Login |
| `POST` | `/auth/register.php` | ❌ | Registro |
| `POST` | `/auth/logout.php` | ✅ | Logout |
| `POST` | `/auth/verify-token.php` | ❌ | Verificar validade do token |
| `GET` | `/user/get_profile.php` | ✅ | Obter perfil próprio |
| `PUT` | `/user/update_profile.php` | ✅ | Atualizar nome e e-mail |
| `PUT` | `/user/update_password.php` | ✅ | Trocar senha |
| `POST` | `/user/verify_password.php` | ✅ | Verificar senha atual |
| `POST` | `/user/update_photo.php` | ✅ | Upload de foto de perfil |
| `POST` | `/user/validate_password.php` | ❌ | Avaliar força de senha |
| `GET` | `/devices/list.php` | ✅ | Listar dispositivos |
| `POST` | `/devices/create.php` | ✅ | Criar dispositivo |
| `PUT` | `/devices/update.php` | ✅ | Atualizar dispositivo |
| `DELETE` | `/devices/delete.php` | ✅ | Remover dispositivo |
| `POST` | `/devices/toggle.php` | ✅ | Alternar status de dispositivo |
| `GET` | `/devices/rooms.php` | ✅ | Listar salas com estatísticas |
| `POST` | `/devices/toggle_group.php` | ✅ | Alternar grupo de dispositivos por sala |
| `GET` | `/routine/list.php` | ✅ | Listar rotinas |
| `POST` | `/routine/create.php` | ✅ | Criar rotina |
| `PUT` | `/routine/update.php` | ✅ | Atualizar rotina |
| `DELETE` | `/routine/delete.php` | ✅ | Remover rotina |
| `POST` | `/routine/toggle.php` | ✅ | Ativar/desativar rotina |
| `POST` | `/routine/execute.php` | ✅ | Executar rotina manualmente |
| `GET` | `/group/list.php` | ✅ | Listar grupos |
| `POST` | `/group/create.php` | ✅ | Criar grupo |
| `GET` | `/list/list.php` | ✅ | Listar listas de tarefas |
| `POST` | `/list/create.php` | ✅ | Criar lista |
| `PUT` | `/list/update.php` | ✅ | Atualizar lista |
| `DELETE` | `/list/delete.php` | ✅ | Remover lista |
| `POST` | `/list/itens.php` | ✅ | Adicionar item a lista |
| `PUT` | `/list/itens.php` | ✅ | Atualizar item de lista |
| `DELETE` | `/list/itens.php` | ✅ | Remover item de lista |
| `POST` | `/list/toggle_item.php` | ✅ | Marcar/desmarcar item |
| `GET` | `/notifications/read.php` | ✅ | Listar notificações |
| `PUT` | `/notifications/mark_read.php` | ✅ | Marcar notificação(ões) como lida(s) |
| `DELETE` | `/notifications/delete.php` | ✅ | Remover notificação |
