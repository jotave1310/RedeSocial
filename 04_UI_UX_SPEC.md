# CARVASILVA — UI/UX Specification

## DESIGN SYSTEM

### Color Palette
```css
:root {
  --bg-primary:      #0D0D0D;
  --bg-surface:      #1A1A2E;
  --bg-elevated:     #16213E;
  --bg-hover:        #22223E;

  --accent-gold:     #F5C518;
  --accent-gold-dim: #B8922E;
  --accent-blue:     #4B9EFF;
  --accent-blue-dim: #2E5E99;
  --accent-green:    #2ECC71;
  --accent-red:      #E74C3C;

  --text-primary:    #E8E8E8;
  --text-secondary:  #9999AA;
  --text-muted:      #555566;

  --border:          #2A2A3E;
  --border-gold:     #F5C51850;
  
  --credits-color:   var(--accent-gold);
  --anon-color:      #9B59B6;
}
```

### Typography
```css
/* Import in <head> */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap');

body { font-family: 'Inter', sans-serif; }
h1, h2, h3, .logo { font-family: 'Poppins', sans-serif; }
```

### Spacing & Radius
- Base unit: 4px
- Card border-radius: 12px
- Button border-radius: 8px
- Avatar border-radius: 50%

---

## LAYOUT STRUCTURE

### Desktop (≥768px) — Twitter-style 3-column
```
┌────────────────────────────────────────────────┐
│  [SIDEBAR NAV]  │  [MAIN FEED]  │  [RIGHT PANEL] │
│   200px         │   600px       │   320px        │
└────────────────────────────────────────────────┘
```

### Mobile (<768px) — Bottom tab bar
```
┌─────────────────────┐
│   [TOP HEADER]      │  ← Logo + Credits + Notif bell
├─────────────────────┤
│                     │
│   [CONTENT AREA]    │
│                     │
├─────────────────────┤
│ 🏠 | 🎰 | 👁 | 🏆 | 👤 │  ← Bottom nav tabs
└─────────────────────┘
```

---

## COMPONENT SPECIFICATIONS

### POST CARD
```
┌─────────────────────────────────────────────┐
│ [Avatar 40px]  Username_Sala  · 2h atrás    │
│                ₢ 4.500 💛                   │
│                                              │
│  Texto do post aqui, até 280 caracteres...  │
│                                              │
│  [Imagem se houver — max 500px height]      │
│                                              │
│  ❤️ 12   💬 3   🔁 1   💰 Tip   ···         │
└─────────────────────────────────────────────┘
```

**CREDIT FLEX variant** — gold border + gold background tint:
```
┌─────────────────────────────────────────────┐  ← border: 2px solid #F5C518
│ [Avatar]  Lucas_3B  · agora                 │
│                                              │
│  💰 Lucas_3B está flexando seus créditos!   │
│                                              │
│  ┌──────────────────────┐                   │
│  │  Saldo Atual         │  ← gold card      │
│  │  ₢ 28.450            │                   │
│  └──────────────────────┘                   │
│                                              │
│  #CarvasilvaFlex                             │
└─────────────────────────────────────────────┘
```

**ANONYMOUS variant** — purple border + masked identity:
```
┌─────────────────────────────────────────────┐  ← border: 2px solid #9B59B6
│ 👤 Anônimo Misterioso  · 45min atrás        │
│                                              │
│  Texto do post anônimo aqui...               │
│                                              │
│  ❤️ 8   💬 2   (sem tip, sem repost)        │
└─────────────────────────────────────────────┘
```

---

### SIDEBAR NAVIGATION
```
┌─────────────────┐
│  ⚡ CARVASILVA  │  ← logo
├─────────────────┤
│  🏠 Feed        │
│  🎰 BetHouse    │
│  👁  Anônimo    │
│  🏆 Ranking     │
│  💬 Mensagens   │
│  🔔 Notificações│
│  👤 Perfil      │
├─────────────────┤
│  [Avatar 32px]  │
│  Lucas_3B       │
│  ₢ 4.500 💛    │
└─────────────────┘
```

---

### PROFILE PAGE
```
┌────────────────────────────────────────────────┐
│  [Cover/gradient bar — accent color]            │
│                                                 │
│  [Avatar 80px]  Lucas_3B                        │
│                 Lucas Ferreira  |  Turma 3B     │
│                 Bio aqui, max 160 chars          │
│                                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐      │
│  │ ₢ 4.500 │  │ 23 posts │  │ 12 segui.│      │
│  │ Créditos│  │          │  │          │      │
│  └──────────┘  └──────────┘  └──────────┘      │
│                                                 │
│  [Badges row: 🌟 🎰 📢]                         │
│                                                 │
│  [Tab: Posts | Apostas | Créditos]              │
├────────────────────────────────────────────────┤
│  [Posts list...]                                │
└────────────────────────────────────────────────┘
```

---

### BETHOUSE PAGE
```
┌────────────────────────────────────────────────┐
│  🎰 BetHouse          [+ Criar Aposta]         │
│  Seu saldo: ₢ 4.500                            │
├────────────────────────────────────────────────┤
│  [Tab: Abertas | Minhas Apostas | Encerradas]  │
├────────────────────────────────────────────────┤
│  BET CARD:                                      │
│  ┌──────────────────────────────────────────┐  │
│  │  🔥 Qual turma vai ganhar o torneio?     │  │
│  │  Pool Total: ₢ 45.200  |  23 apostas    │  │
│  │  ⏱ Encerra em: 2h 15min                 │  │
│  │                                          │  │
│  │  [3A - 45%]████████░░░░░  ₢ 20.340     │  │
│  │  [3B - 32%]██████░░░░░░░  ₢ 14.400     │  │
│  │  [3C - 23%]████░░░░░░░░░  ₢ 10.460     │  │
│  │                                          │  │
│  │  [Apostar]                               │  │
│  └──────────────────────────────────────────┘  │
└────────────────────────────────────────────────┘
```

---

### RANKING PAGE
```
┌────────────────────────────────────────────────┐
│  🏆 Ranking de Créditos                         │
│  [Tab: Geral | Por Turma | Semanal | Apostas]  │
├────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────┐  │
│  │  🥇  1  [Av] Ana_2A          ₢ 95.000   │  │
│  │  🥈  2  [Av] Pedro_1B        ₢ 82.300   │  │
│  │  🥉  3  [Av] Maria_3A        ₢ 71.100   │  │
│  │     4  [Av] João_2C          ₢ 45.200   │  │
│  │     5  [Av] Lua_1A           ₢ 39.800   │  │
│  │  ...                                     │  │
│  ├──────────────────────────────────────────┤  │
│  │  ► 15  [Av] Você (Lucas_3B)  ₢  4.500  │  │← user highlighted
│  └──────────────────────────────────────────┘  │
└────────────────────────────────────────────────┘
```

---

### NOTIFICATION BELL DROPDOWN
```
🔔 [5]
┌──────────────────────────────┐
│  Notificações                │
├──────────────────────────────┤
│ ❤️ Ana_2A curtiu seu post    │  ← 2min
│ 🏆 Você ganhou ₢ 320!        │  ← 1h
│    (Aposta: Torneio 3B)      │
│ 💬 Pedro comentou no seu...  │  ← 3h
│ 🏅 Nova conquista: Apostador │  ← hoje
└──────────────────────────────┘
```

---

### BET MODAL (after clicking "Apostar")
```
┌─────────────────────────────────────┐
│  Apostar em: "Qual turma ganha?"    │
│                                     │
│  Opção: [3B ▾]                      │
│                                     │
│  Valor: [___________] ₢             │
│  Mínimo: ₢ 50                       │
│  Seu saldo: ₢ 4.500                 │
│                                     │
│  Retorno estimado: ₢ ~680           │
│  (se ganhar — baseado no pool atual)│
│                                     │
│  [Cancelar]  [Confirmar Aposta 🎰]  │
└─────────────────────────────────────┘
```

---

## ANIMATIONS & MICRO-INTERACTIONS

### Credit Counter Animation
When credits change, animate the counter rolling up/down:
```javascript
// Animate credit counter
function animateCredits(element, from, to, duration = 1000) {
    const start = performance.now();
    const update = (time) => {
        const elapsed = time - start;
        const progress = Math.min(elapsed / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        const current = Math.round(from + (to - from) * eased);
        element.textContent = '₢ ' + current.toLocaleString('pt-BR');
        if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
}
```

### Milestone Celebration
When user reaches a milestone, trigger a confetti animation overlay:
- Use `canvas-confetti` JS library (CDN)
- Show modal: "🏆 Nova Meta Atingida! ₢ 10.000"
- Auto-generate milestone post

### Like Button
- Animate heart with scale: 1 → 1.4 → 1 + color change
- CSS transition: `transform 0.15s ease, color 0.15s ease`

### Bet Progress Bars
- Animate on load: bars grow from 0% to final value
- CSS: `transition: width 0.8s ease`

---

## RESPONSIVE BREAKPOINTS

```css
/* Mobile first */
/* Default: mobile styles */

/* Tablet */
@media (min-width: 600px) {
    /* Adjust card padding, font sizes */
}

/* Desktop */
@media (min-width: 768px) {
    /* Sidebar + multi-column layout */
    .layout { display: grid; grid-template-columns: 200px 600px 320px; }
}

/* Wide */
@media (min-width: 1200px) {
    .layout { grid-template-columns: 240px 600px 360px; }
}
```

---

## LOADING STATES

- Post skeleton: gray animated shimmer cards
- Use CSS `@keyframes shimmer` with `background: linear-gradient(90deg, #1A1A2E 25%, #22223E 50%, #1A1A2E 75%)`
- Feed skeleton: show 3 skeleton cards while loading

## EMPTY STATES

- No posts: "Nenhuma postagem ainda. Seja o primeiro! ✨"
- No bets: "Nenhuma aposta aberta. Que tal criar uma? 🎰"
- No notifications: "Tudo tranquilo por aqui 🔔"
- No messages: "Nenhuma conversa ainda 💬"
