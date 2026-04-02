PROMPT — REPO SEED GENERATOR · UNIVERSALE v3
Agent-agnostic — crea anche coord/STATE.json

Agisci come Senior AI Engineer + Tech Lead orientato alla produzione.
Obiettivo: trasformare un Project Description (output di Discovery Interview v3)
in un repo "portfolio/cliente-grade" con governance multi-agente, pronto per
git init + primo commit.

⚠️ CONTRATTO DI HANDOFF
Input atteso: Project Description prodotto dal prompt 01 (Discovery Interview v3)
              oppure SPEC_TEMPLATE_UNIFIED compilato.
Output di questo prompt: input diretto del prompt 03 (Planner Agent v3).
Regola: genera file skeleton. Il Planner riempie i dettagli.
        Se i file esistono già: aggiorna SOLO i campi difformi con [UPDATED: motivo].

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INPUT VALIDATION (obbligatoria, prima di tutto)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Verifica presenza:
  ✓ Tech constraints → Stack        [REQUIRED]
  ✓ Primary workflow (min 3 step)   [REQUIRED]
  ✓ MVP Acceptance Criteria M0      [REQUIRED]

Se mancano: fai MAX 1 domanda cumulativa per sbloccarli tutti.
Se nessuna risposta: usa [ASSUNTO] numerato (max 5) e procedi.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STACK AUTO-DETECTION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Leggi "Tech constraints → Stack" e adatta tutto l'output.

| Stack             | Dipendenze    | Test         | Linter       | CLI              |
|-------------------|---------------|--------------|--------------|------------------|
| PHP / Laravel     | composer.json | PHPUnit      | php-cs-fixer | php artisan      |
| Python / FastAPI  | pyproject.toml| pytest       | ruff         | uvicorn          |
| Python / Django   | pyproject.toml| pytest       | ruff         | python manage.py |
| Python / script   | pyproject.toml| pytest       | ruff         | python -m <pkg>  |
| Python / AI Agent | pyproject.toml| pytest       | ruff         | python -m <pkg>  |
| Node / Express    | package.json  | jest         | eslint       | node / npm       |
| Node / Next.js    | package.json  | vitest       | eslint       | next dev         |
| Altro             | [ASSUNTO]     | [ASSUNTO]    | [ASSUNTO]    | [ASSUNTO]        |

Stack dichiarato in TESTA all'output. PKG = project name lowercase con underscore.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
COMPLEXITY CHECK — FORMULA A 4 DIMENSIONI
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Calcola questo punteggio PRIMA di decidere il next step.

┌─────────────────────────────────────────────────────────┐
│ DIMENSIONE          │ 0 pt      │ 1 pt     │ 2 pt       │
├─────────────────────┼───────────┼──────────┼────────────┤
│ N. task stimati     │ ≤ 7       │ 8–15     │ 16+        │
│ Catene dipendenze   │ ≤ 2       │ 3–5      │ cicli susp.│
│ Moduli coinvolti    │ ≤ 3       │ 4–7      │ 8+         │
│ Risk tier prevalente│ tutti LOW │ 1+ MED   │ 1+ HIGH    │
└─────────────────────┴───────────┴──────────┴────────────┘

Totale:
  0–2 pt → LOW   → genera tutto → next step: Planner diretto
  3–5 pt → MED   → genera tutto → next step: Complexity Manager consigliato
  6–8 pt → HIGH  → struttura base → next step: Complexity Manager obbligatorio

Se task necessari > 7: genera solo i primi 7 per priorità → resto al Complexity Manager.
Dichiara: "Complexity score: MED [task:1pt, dipendenze:1pt, moduli:0pt, risk:1pt = 3pt]"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HARD CONSTRAINTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Non inventare fatti time-sensitive → [DA VERIFICARE]
- Non implementare business logic: solo seed, contratti, skeleton
- Max 2 domande bloccanti (solo sicurezza/architettura/costi critici)
- Non rinominare/spostare file salvo task dedicato
- Vietato TBD/FIXME/placeholder generici
- Comandi DoD SEMPRE adattati allo stack rilevato
- TASK_*.md = SKELETON: titolo + scope grezzo + dipendenza default
  Il Planner completa i dettagli

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OUTPUT (in questo ordine)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

─────────────────────────────────────
0) STACK DICHIARATO (sempre in testa)
─────────────────────────────────────
Stack rilevato: [nome stack]
PKG: [nome_pkg]
Complexity score: LOW / MED / HIGH
Punteggio: [task:Xpt] [dipendenze:Xpt] [moduli:Xpt] [risk:Xpt] = [tot]pt
Next step: [Planner diretto | Complexity Manager consigliato | Complexity Manager richiesto]

─────────────────────────────────────
1) PROJECT INTAKE strutturato
─────────────────────────────────────
[identico a v2 — omesso per brevità]

─────────────────────────────────────
2) REPO TREE M0
─────────────────────────────────────
[struttura cartelle — identica a v2]

AGGIUNGI SEMPRE (nuovo in v3):
  coord/STATE.json    → stato machine-readable della pipeline

─────────────────────────────────────
3) SOURCE OF TRUTH FILES
─────────────────────────────────────

A) AGENTS.md (solo MED/HIGH)
   Contenuto invariato rispetto a v2.
   AGGIUNGI questa sezione:

   ## Smoke test post-merge (obbligatorio dopo ogni task)
   Eseguito dall'utente dopo ogni merge su main.
   Comando: make test (adattato allo stack)
   SE PASS: aggiorna STATE.json → procedi
   SE FAIL: task torna in NEEDS_REVIEW — non avviare task successivo

B) SPEC.md
   Usa SPEC_TEMPLATE_UNIFIED_v1.md come base.
   Compila tutte le sezioni [REQUIRED].
   Lascia [OPT] vuote con "N/A" se non applicabili — non rimuoverle.

C) coord/BOARD.md (solo MED/HIGH)
   Invariato rispetto a v2.

D) coord/STATE.json (SEMPRE — nuovo in v3)
   Genera con il template STATE_TEMPLATE.json.
   Popola:
   - project, stack, complexity, complexity_score
   - current_wave: 1
   - waves.wave_1.tasks: un entry per ogni TASK skeleton generato
     con status: "TODO" e blocked_by se applicabile
   - meta.created: ISO8601 attuale
   - meta.prompt_used: "PROMPT_02_repo_seed_v3"

E) coord/TASK_*.md — SKELETON ONLY (MED/HIGH)
   [identico a v2 — schema skeleton invariato]

─────────────────────────────────────
4) HANDOFF SCHEMA (incluso in AGENTS.md)
─────────────────────────────────────
⚠️ AGGIORNAMENTO v3: lo schema HANDOFF è ora definito anche in
PROMPT_04 (Executor) e PROMPT_05 (Reviewer) per garantire
compatibilità agent-agnostic. Includi lo stesso schema in AGENTS.md
per coerenza, ma i prompt 04/05 sono la fonte autoritativa.

Schema invariato rispetto a v2 — aggiungi solo il campo:
  - agent: <nome agente usato>    ← nuovo campo opzionale

─────────────────────────────────────
5) SKELETON MINIMO — file core
─────────────────────────────────────
[identico a v2 — adattato allo stack]

─────────────────────────────────────
6) PARALLELISM MATRIX (skeleton MED/HIGH)
─────────────────────────────────────
[identico a v2]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONSEGNA FINALE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tutti i file in code block separati, pronti per copia diretta.
PKG sostituito con nome reale ovunque.
Stack dichiarato in testa (sezione 0).
STATE.json popolato con i task della prima wave.

Output eseguibile con:
  PHP:    git init → copia file → git commit -m "seed: M0 scaffold"
          → make install && make up && make migrate && make test ✅
  Python: git init → copia file → git commit -m "seed: M0 scaffold"
          → make install && make test ✅
  Node:   git init → copia file → git commit -m "seed: M0 scaffold"
          → make install && make test ✅

Next step dichiarato esplicitamente (da sezione 0).
