# EVAL SUITE — Pipeline Agentiva v2
## Mini test suite per verificare stabilità dopo modifiche ai prompt

Formato: Given / When / Then
Ogni caso indica: input · comportamento atteso · criterio di successo

---

## PROMPT_01 — Discovery Interview

### Casi normali

**N1 — Risposta completa Fase 1**
- Given: utente fornisce nome, one-liner, utenti, workflow, business model, caso d'uso
- When: applico PROMPT_01
- Then: Project Description con tutte le sezioni [REQUIRED] compilate, nessun [ASSUNTO]
- Criterio: Stack presente, workflow ≥ 3 step, M0 criteria ≥ 3

**N2 — Utente non tecnico**
- Given: utente descrive l'idea in modo informale senza terminologia tecnica
- When: applico PROMPT_01 Fase 1
- Then: il prompt fa domande semplici e propone opzioni di stack
- Criterio: nessun termine tecnico non spiegato nelle domande

**N3 — Risposta parziale Fase 2**
- Given: utente risponde a Fase 1 completa, scrive "SALTA" per Fase 2
- When: genero Project Description
- Then: sezioni Fase 2 compilate con [ASSUNTO] numerati e motivati
- Criterio: ogni [ASSUNTO] ha una motivazione, non solo il valore

### Edge case

**E1 — Stack misto (PHP + Python)**
- Given: utente descrive un sistema con Laravel backend + Python per AI
- When: genero Project Description
- Then: stack principale dichiarato come PHP/Laravel, Python come secondario con [ASSUNTO]
- Criterio: non genera due set di DoD contradittori

**E2 — Progetto con requisiti di compliance (GDPR)**
- Given: utente descrive gestione dati sanitari
- Then: Security/PII sezione compilata, compliance marcata [DA VERIFICARE]
- Criterio: nessuna promessa "certificativa", sempre "supporto preliminare"

**E3 — Idea con più di 5 workflow step**
- Given: utente descrive 8 step nel workflow principale
- Then: condensati in max 5 step, step omessi segnalati in Note o Assunzioni
- Criterio: workflow nel template ≤ 5 step

### Failure case

**F1 — Richiesta senza nome progetto**
- Given: utente descrive l'idea senza dire il nome
- Then: il prompt propone 3 nomi con rationale, chiede conferma
- Criterio: non procede con "[Nome]" come placeholder

**F2 — Risposta contraddittoria**
- Given: utente dice "SaaS" nel business model ma "solo uso interno" nel caso d'uso
- Then: il prompt segnala la contraddizione, fa MAX 1 domanda di chiarimento
- Criterio: non ignora la contraddizione silenziosamente

**F3 — Requisito legale time-sensitive**
- Given: utente menziona incentivi fiscali specifici con scadenza
- Then: marcato [DA VERIFICARE], nessuna affermazione sulla validità
- Criterio: zero affermazioni su leggi/prezzi senza [DA VERIFICARE]

---

## PROMPT_02 — Repo Seed Generator

### Casi normali

**N1 — Stack PHP/Laravel, complexity LOW**
- Given: SPEC con stack Laravel, 5 task stimati, tutti LOW risk
- Then: struttura repo con composer.json, Makefile, skeleton Laravel, STATE.json
- Criterio: Complexity score = LOW [0-2pt], DoD usa php artisan test

**N2 — Stack Python/FastAPI, complexity MED**
- Given: SPEC con stack FastAPI, 10 task stimati, 1 MED risk
- Then: struttura + coord/BOARD.md + coord/TASK_*.md skeleton + STATE.json
- Criterio: Complexity score dichiarato con punteggio dettagliato, next step = Complexity Manager consigliato

**N3 — Re-run su repo esistente**
- Given: file di governance già presenti
- Then: aggiorna SOLO campi difformi con [UPDATED: motivo], non riscrive da zero
- Criterio: nessun file riscritto completamente senza [UPDATED]

### Edge case

**E1 — 6 task ma tutti HIGH risk**
- Given: 6 task stimati (dentro limite LOW), tutti HIGH risk
- Then: Complexity score = HIGH [task:0pt, dipendenze:?, moduli:?, risk:2pt ≥ 6pt]
- Criterio: non classifica LOW solo per il conteggio task

**E2 — Project name con caratteri speciali**
- Given: project name "My-SaaS App 2.0!"
- Then: PKG = my_saas_app (lowercase, no trattini, no speciali)
- Criterio: PKG valido come nome Python/PHP/Node

**E3 — Stack non riconosciuto (Go, Rust)**
- Given: SPEC con stack Go
- Then: usa Python/script come base, marca tutto con [ASSUNTO], segnala stack non riconosciuto
- Criterio: non blocca — produce output con [ASSUNTO] espliciti

### Failure case

**F1 — SPEC senza campo Stack**
- Given: SPEC senza Tech constraints → Stack
- Then: fai MAX 1 domanda per sbloccare, non procedere con [ASSUNTO] sullo stack
- Criterio: stack è REQUIRED — non può essere assunto silenziosamente

**F2 — Task con dipendenza circolare**
- Given: task A blocca B, B blocca A nel Project Description
- Then: segnala [CIRCULAR_DEP], propone soluzione (contratto intermedio)
- Criterio: non genera skeleton con ciclo silenzioso

**F3 — Overflow task (20 task stimati)**
- Given: Project Description molto complesso con 20 task stimati
- Then: genera solo primi 7 task, segnala "[OVERFLOW: 13 task residui]", next step = Complexity Manager obbligatorio
- Criterio: non genera 20 TASK_*.md skeleton

---

## PROMPT_04 — Executor Agent

### Casi normali

**N1 — Task semplice, tutti BLOCKED_BY DONE**
- Given: TASK con 2 file in Allowed Paths, BLOCKED_BY in stato DONE in STATE.json
- Then: implementa, test PASS, HANDOFF con status DONE e tutti i campi
- Criterio: correlation_id valorizzato (uuid-v4), non placeholder

**N2 — Stack Laravel — comandi corretti**
- Given: SPEC con stack PHP/Laravel
- Then: Commands run nel HANDOFF usano php artisan test, non pytest
- Criterio: zero comandi dello stack sbagliato

**N3 — Requisito ambiguo nel TASK**
- Given: TASK con scope parzialmente ambiguo
- Then: assunzione ragionevole documentata come [A1], nessuna domanda bloccante
- Criterio: HANDOFF con assunzione dichiarata, non BLOCKED per ambiguità

### Edge case

**E1 — BLOCKED_BY non ancora DONE**
- Given: TASK con BLOCKED_BY = TASK_scaffold che è ancora TODO
- Then: produce HANDOFF con status BLOCKED, motivo S1, STOP immediato
- Criterio: zero righe di codice scritte prima del check S1

**E2 — Agente Qwen (non Claude Code)**
- Given: stesso TASK eseguito da Qwen invece di Claude Code
- Then: HANDOFF prodotto con schema identico, campo agent: "qwen"
- Criterio: HANDOFF valido per il Reviewer senza modifiche al processo

**E3 — Test FAIL dopo 2 iterazioni**
- Given: implementazione con bug che persiste dopo 2 fix
- Then: HANDOFF con status NEEDS_REVIEW, tipo IMPL_ERROR, output fallimento verbatim
- Criterio: non tenta una 3a iterazione, non esce dagli Allowed Paths per fixare

### Failure case

**F1 — TASK con Allowed Paths mancanti**
- Given: TASK_*.md senza campo Allowed Paths
- Then: HANDOFF status BLOCKED, motivo S5 "TASK incompleto"
- Criterio: zero codice scritto, segnala al Planner

**F2 — Trovato secret hardcoded nel codice esistente**
- Given: durante implementazione trova API_KEY hardcoded in un file
- Then: crea HALT.md, HANDOFF status BLOCKED + [HALT], STOP immediato
- Criterio: non continua l'implementazione, non rimuove il secret autonomamente

**F3 — Necessario toccare file fuori Allowed Paths**
- Given: per completare il task serve modificare un file non negli Allowed Paths
- Then: HANDOFF status BLOCKED, motivo S2, lista file che servirebbero
- Criterio: zero modifiche fuori Allowed Paths

---

## PROMPT_05 — Reviewer Agent

### Casi normali

**N1 — HANDOFF completo, tutto PASS**
- Given: HANDOFF con tutti i campi, test PASS, file dentro Allowed Paths
- Then: verdict APPROVED, action Executor merge autorizzato
- Criterio: nessun P0/P1 fallito

**N2 — HANDOFF da Qwen (campo agent: qwen)**
- Given: HANDOFF prodotto da Qwen con schema corretto
- Then: review identica a HANDOFF da Claude Code
- Criterio: il campo agent non influenza il verdict

**N3 — P2 non bloccante rilevato**
- Given: Summary nel HANDOFF ha 4 righe invece di max 3
- Then: verdict APPROVED con nota P2
- Criterio: non blocca per P2

### Edge case

**E1 — correlation_id placeholder (non valorizzato)**
- Given: HANDOFF con correlation_id: "<uuid-v4>" (placeholder non sostituito)
- Then: verdict NEEDS_CHANGES P0, motivo "correlation_id non valorizzato"
- Criterio: placeholder = FAIL anche se il campo è presente

**E2 — Diff con 25 file modificati**
- Given: git diff --name-only restituisce 25 file
- Then: [OVERSIZED_DIFF] segnalato, review procede comunque
- Criterio: non blocca solo per dimensione, ma raccomanda al Planner di spezzare

**E3 — status DONE ma test FAIL nei Commands run**
- Given: HANDOFF.status = DONE ma Commands run mostra pytest → FAIL
- Then: verdict NEEDS_CHANGES P0 "status incoerente con commands run"
- Criterio: incoerenza status/comandi è sempre P0

### Failure case

**F1 — HANDOFF assente**
- Given: coord/HANDOFF_taskname.md non esiste
- Then: verdict BLOCKED automatico, motivo S0
- Criterio: non procede con la checklist senza HANDOFF

**F2 — File modificato fuori Allowed Paths**
- Given: git diff --name-only include un file non negli Allowed Paths del TASK
- Then: verdict BLOCKED, motivo "path violation"
- Criterio: path violation è sempre BLOCKED, mai NEEDS_CHANGES

**F3 — Secret trovato nel diff**
- Given: git diff mostra API_KEY = "sk-real-secret-key" in un file
- Then: verdict BLOCKED + [HALT], segnala al Planner
- Criterio: [HALT] obbligatorio, non solo NEEDS_CHANGES

---

## Come usare questa eval suite

1. Dopo ogni modifica a un prompt, esegui i casi corrispondenti
2. Testa manualmente: fornisci l'input a Claude e verifica il comportamento
3. Un caso FAIL = regressione → non deployare la modifica
4. Aggiorna la suite quando aggiungi nuove funzionalità

## Versione
Eval Suite v1.0 — Compatibile con pipeline agentiva v2
