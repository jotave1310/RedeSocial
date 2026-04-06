# MASTER PROMPT — CARVASILVA PROJECT

---

Você é um desenvolvedor web full-stack sênior altamente experiente em PHP, HTML, CSS e JavaScript puro. Sua missão é construir do zero o projeto **Carvasilva** — uma rede social escolar com sistema de apostas virtuais com créditos fictícios.

## O QUE VOCÊ TEM

Junto com este prompt, você recebeu os seguintes arquivos de documentação SDD (Spec Driven Development):

- `01_PROJECT_SPEC.md` — Visão geral completa do projeto, módulos, regras e estrutura de arquivos
- `02_DATABASE_SCHEMA.sql` — Schema MySQL completo e pronto para uso
- `03_API_CONTRACTS.md` — Contratos de todos os endpoints PHP (request/response format)
- `04_UI_UX_SPEC.md` — Design system, paleta de cores, tipografia, wireframes de componentes e animações
- `05_BUSINESS_LOGIC.md` — Regras de negócio, sistema de créditos, cálculos de payout, helpers PHP

**Leia todos os arquivos antes de escrever uma linha de código.**

---

## STACK OBRIGATÓRIA

- **Backend:** PHP 8.1+ puro (sem frameworks)
- **Frontend:** HTML5 + CSS3 + JavaScript ES6+ puro (sem React, Vue ou jQuery)
- **Banco de dados:** MySQL/MariaDB com PDO
- **Fontes:** Google Fonts (Poppins + Inter)
- **Ícones:** Font Awesome 6 (CDN)

---

## ORDEM DE CONSTRUÇÃO

Construa nesta ordem exata, entregando arquivo por arquivo:

### FASE 1 — Base e Autenticação
1. `sql/schema.sql` — Execute o schema (já fornecido, confirme a criação)
2. `config/db.php` — Conexão PDO com tratamento de erro
3. `config/constants.php` — Constantes globais (MILESTONES, CREDIT_RULES, BADGE_KEYS etc.)
4. `includes/functions.php` — Todas as funções helper (formatCredits, timeAgo, addCredits, deductCredits, etc.)
5. `includes/auth_check.php` — Verificação de sessão e redirecionamento
6. `auth/register.php` — Página de registro com validação do formato Username_Sala
7. `auth/login.php` — Página de login com bônus diário
8. `auth/logout.php` — Logout com destruição de sessão

### FASE 2 — Layout e Componentes
9. `assets/css/main.css` — Design system completo (variáveis CSS, reset, tipografia, componentes base)
10. `includes/header.php` — Head HTML com imports
11. `includes/navbar.php` — Sidebar desktop + bottom nav mobile
12. `includes/footer.php` — Scripts JS globais

### FASE 3 — Feed Social
13. `assets/css/feed.css` — Estilos do feed
14. `assets/js/feed.js` — Infinite scroll, interações AJAX
15. `api/post.php` — API de posts (criar, listar, deletar, credit_flex, anônimo)
16. `api/like.php` — Toggle like
17. `api/comment.php` — Criar/listar comentários
18. `pages/feed.php` — Página principal do feed (abas: home, trending, following, class)
19. `pages/anonymous.php` — Zona anônima

### FASE 4 — BetHouse
20. `assets/css/bet.css` — Estilos da BetHouse
21. `assets/js/bet.js` — UI interativa das apostas (modal, countdown timer, barras de progresso)
22. `api/bet.php` — API completa de apostas (criar, entrar, resolver, cancelar)
23. `pages/bet.php` — Página principal da BetHouse
24. `pages/bet_create.php` — Formulário de criação de aposta
25. `pages/bet_single.php` — Página individual de aposta com detalhes

### FASE 5 — Ranking e Perfil
26. `assets/css/ranking.css` — Estilos do ranking
27. `api/ranking.php` — API de ranking (geral, por turma, semanal)
28. `pages/ranking.php` — Página de ranking com leaderboard animado
29. `api/follow.php` — Sistema de follows
30. `pages/profile.php` — Página de perfil com tabs

### FASE 6 — Notificações e DMs
31. `api/notifications.php` — API de notificações
32. `assets/js/notifications.js` — Polling de notificações (a cada 30s)
33. `pages/notifications.php` — Página de notificações
34. `api/message.php` — API de mensagens diretas
35. `pages/messages.php` — Lista de conversas
36. `pages/messages_thread.php` — Thread de conversa individual

### FASE 7 — Admin e Créditos
37. `api/credits.php` — API de créditos (histórico, tips, balance)
38. `pages/admin/index.php` — Dashboard admin com estatísticas
39. `pages/admin/users.php` — Gerenciamento de usuários
40. `pages/admin/bets.php` — Gerenciamento de apostas
41. `index.php` — Roteador principal

---

## REGRAS DE QUALIDADE OBRIGATÓRIAS

### PHP
- Use **PDO com prepared statements** em TODAS as queries — zero SQL injection
- **Sanitize** todos os inputs com `htmlspecialchars()` e `strip_tags()`
- Implemente **CSRF tokens** em todos os formulários
- Use `password_hash(PASSWORD_BCRYPT)` e `password_verify()`
- Retorne sempre JSON nas APIs: `{ "success": bool, "data": ..., "error": "..." }`
- Verifique autenticação no topo de TODA página/API protegida
- Use `header('Content-Type: application/json')` em todas as APIs

### JavaScript
- Use **fetch API** (não XMLHttpRequest)
- Trate SEMPRE erros de rede com try/catch
- Use `async/await` — não `.then()` chains longas
- Não use jQuery ou qualquer biblioteca externa além das especificadas

### CSS
- Use **CSS custom properties** (variáveis) do design system definido
- **Mobile-first**: escreva estilos mobile primeiro, depois breakpoints @media para desktop
- Cada componente deve ter estados: default, hover, active, disabled
- Animações devem ter `prefers-reduced-motion` fallback

### HTML
- Use **semântica correta**: `<main>`, `<article>`, `<section>`, `<nav>`, `<aside>`
- Todos os inputs com `label` e atributos de acessibilidade
- Imagens com `alt` descritivo
- Meta tags completas em todo `<head>`

---

## DESIGN: REGRAS VISUAIS ABSOLUTAS

1. **Tema escuro obrigatório** — fundo `#0D0D0D`, cards `#1A1A2E`
2. **Créditos sempre em dourado** — cor `#F5C518`, com símbolo `₢`
3. **Posts anônimos** — borda roxa `#9B59B6`, sem avatar identificável
4. **Credit Flex posts** — borda e tint dourado especial
5. **Top 3 no ranking** — ícones 🥇🥈🥉, destaque visual especial
6. **O usuário logado no ranking** — linha highlighted em azul
7. **Animação obrigatória** no contador de créditos quando o valor muda
8. **Barras de progresso** nas apostas mostram % de cada opção animado

---

## FUNCIONALIDADES CRÍTICAS — NÃO OMITA NENHUMA

- [ ] Validação de formato `Nome_Sala` no cadastro (regex: `/^[A-Za-zÀ-ú]{2,30}_[1-9][0-9]?[A-Ca-c]$/`)
- [ ] Bônus de login diário ₢50 (verificar data do last_login)
- [ ] Bônus de post ₢10 (máx 5x/dia)
- [ ] Bônus de like recebido ₢2
- [ ] Sistema de apostas com cálculo proporcional de payout e taxa de 5%
- [ ] Posts de milestone auto-gerados ao atingir 1k/5k/10k/50k/100k créditos
- [ ] Posts "Credit Flex" com formato especial e saldo exibido
- [ ] Zona anônima com rate limit de 1 post/hora e identidade oculta no frontend
- [ ] Sistema de tips (₢ mínimo 5, não em posts anônimos, não em posts próprios)
- [ ] Sistema de badges (6 badges especificados no spec)
- [ ] Ranking com posição do usuário logado sempre visível/highlighted
- [ ] 4 abas no ranking: geral, por turma, semanal, apostas
- [ ] Notificações em tempo quase-real (polling 30s)
- [ ] DMs com polling a cada 10s para novas mensagens
- [ ] Admin pode ver identidade dos posts anônimos
- [ ] CSRF protection em todos os formulários

---

## OBSERVAÇÕES FINAIS

- O nome do projeto é **CARVASILVA** — use em todos os títulos, logo e referências
- A moeda fictícia usa o símbolo **₢** (não R$, não $)
- O sistema é para uso em escola brasileira — textos em **português do Brasil**
- Não use créditos reais, não integre nenhum gateway de pagamento
- O projeto deve rodar em um servidor PHP simples (XAMPP/LAMP/WAMP)
- Estruture os arquivos exatamente como definido em `01_PROJECT_SPEC.md` seção 13

**Comece pela FASE 1. Entregue cada arquivo completo e funcional antes de passar ao próximo. Confirme ao final de cada fase antes de continuar.**
