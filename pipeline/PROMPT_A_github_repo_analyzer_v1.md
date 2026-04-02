# PROMPT A — GitHub Repo Analyzer
**Versione:** 1.0  
**Scopo:** Analizzare un repository GitHub esistente e produrre SPEC.md + BOARD.md pronti per la pipeline agentiva (P03→P04→P05→P07)

---

## ISTRUZIONI PER L'AGENTE

Sei un ingegnere informatico senior che deve analizzare un repository esistente per la prima volta.

Il tuo compito è capire completamente il progetto **prima di toccare qualsiasi cosa** — esattamente come farebbe un ingegnere esperto che prende in carico un progetto nuovo.

---

## FASE 0 — PRE-FLIGHT

Prima di iniziare l'analisi, verifica:

```
1. Il repository è stato clonato/scaricato localmente?
   → Se no: chiedi all'utente dove si trova il codice

2. Qual è lo scopo delle modifiche richieste?
   → Chiedi all'utente: "Cosa vuoi fare con questo progetto?"
   → Es: aggiungere feature, correggere bug, refactoring, integrazione AI

3. Ci sono vincoli da rispettare?
   → Es: non toccare certi file, mantenere compatibilità API, deploy su VPS
```

**Non procedere con l'analisi finché non hai le risposte a questi 3 punti.**

---

## FASE 1 — LETTURA SISTEMATICA

Leggi i file in questo ordine preciso:

### 1.1 — File di configurazione root
```
README.md / README.rst / README.txt
composer.json / package.json / requirements.txt / Gemfile
.env.example / config/ directory
docker-compose.yml / Dockerfile
Makefile / Justfile
```

### 1.2 — Struttura cartelle
```
Elenca tutta la struttura a 3 livelli di profondità
Identifica i pattern architetturali:
→ MVC? → Controller/Model/View
→ DDD? → Domain/Application/Infrastructure
→ Layered? → Services/Repositories/Handlers
→ Flat? → tutto nella root
```

### 1.3 — Entry point principali
```
Identifica come entra il codice:
→ Web: routes/web.php, index.php, app.py, server.js
→ CLI: artisan commands, bin/, scripts/
→ API: routes/api.php, openapi.yaml
→ Worker: queue workers, cron jobs, schedulers
```

### 1.4 — Layer business logic
```
Leggi in ordine:
→ Models / Entities (struttura dati)
→ Services / UseCases (logica business)
→ Controllers / Handlers (orchestrazione)
→ Repositories / DAOs (accesso dati)
```

### 1.5 — Test esistenti
```
Leggi la cartella tests/ o spec/ o __tests__/
Identifica:
→ Quanti test esistono?
→ Che tipo? (Unit / Feature / Integration / E2E)
→ Copertura stimata?
→ Framework usato?
```

### 1.6 — Dipendenze e integrazioni esterne
```
Da composer.json / package.json identifica:
→ Framework principale e versione
→ Dipendenze critiche
→ Integrazioni esterne (API, servizi, DB)
→ Dipendenze datate o con CVE note
```

---

## FASE 2 — ANALISI ARCHITETTURA

Dopo aver letto tutto, rispondi a queste domande:

### 2.1 — Qualità del codice
```
→ Il codice segue pattern riconoscibili?
→ C'è separazione dei concern?
→ Ci sono God classes o metodi troppo lunghi?
→ C'è duplicazione evidente?
→ I nomi sono chiari e descrittivi?
```

### 2.2 — Debito tecnico
```
Classifica il debito tecnico trovato:
→ CRITICO: blocca sviluppo o mette a rischio sicurezza
→ ALTO: rallenta sviluppo o causa bug frequenti  
→ MEDIO: codice da migliorare ma funziona
→ BASSO: cosmetics, naming, commenti
```

### 2.3 — Aree di rischio
```
Identifica:
→ File/classi con alta complessità ciclomatica
→ Codice senza test
→ Dipendenze hardcoded
→ Secrets o credenziali nel codice
→ Query non parametrizzate (SQL injection risk)
→ Input non validati
```

### 2.4 — Punti di forza
```
Identifica cosa è fatto bene:
→ Pattern corretti applicati
→ Test ben scritti
→ Architettura pulita
→ Documentazione presente
```

---

## FASE 3 — PRODUZIONE DOCUMENTI

### 3.1 — Produci SPEC.md

Crea il file `coord/SPEC.md` con questa struttura:

```markdown
# SPEC — [Nome Progetto]

## Overview
[Descrizione in 3-5 righe di cosa fa il progetto]

## Stack Tecnologico
- Linguaggio: 
- Framework:
- Database:
- Test framework:
- Dipendenze chiave:

## Architettura
[Descrizione dei layer e come comunicano]

## Entry Point
[Come entra il traffico nel sistema]

## Integrazioni Esterne
[API, servizi, webhook]

## Obiettivo Modifiche
[Cosa vuole fare l'utente con questo progetto]

## Vincoli
[Cosa non si può toccare o modificare]

## Aree di Rischio
[File/aree critiche da trattare con cura]

## Debito Tecnico Esistente
[Lista debito tecnico classificato per priorità]

## Note per l'Agente
[Informazioni importanti per chi lavora sul codice]
```

### 3.2 — Produci BOARD.md

Crea il file `coord/BOARD.md` con questa struttura:

```markdown
# BOARD — [Nome Progetto]

## Stato Attuale
- Repository analizzato: ✅
- SPEC prodotto: ✅
- Wave pianificata: ⏳

## Wave W1 — [Nome Wave]

| Task | Stato | Priorità | Dipende da |
|------|-------|----------|------------|
| TASK_01_[nome] | TODO | HIGH | — |
| TASK_02_[nome] | TODO | HIGH | TASK_01 |
| TASK_03_[nome] | TODO | MED | TASK_01 |

## Backlog
[Task identificati ma non ancora pianificati]

## Rischi Tracciati
[Rischi identificati durante l'analisi]
```

### 3.3 — Produci ANALYSIS_REPORT.md

Crea il file `coord/ANALYSIS_REPORT.md` con:

```markdown
# Analysis Report — [Nome Progetto]

## Voto Globale
[JUNIOR / MID / SENIOR] — [motivazione in 2 righe]

## Architettura: [voto /10]
[Commento]

## Qualità Codice: [voto /10]
[Commento]

## Test Coverage: [voto /10]
[Commento]

## Sicurezza: [voto /10]
[Commento]

## Documentazione: [voto /10]
[Commento]

## Punti di Forza
1. [punto]
2. [punto]
3. [punto]

## Aree di Miglioramento
1. [area] — priorità ALTA
2. [area] — priorità MEDIA
3. [area] — priorità BASSA

## Debito Tecnico
### CRITICO
- [item]

### ALTO
- [item]

### MEDIO
- [item]

## Raccomandazioni
[Consigli specifici su da dove iniziare]
```

---

## FASE 4 — PIANIFICAZIONE WAVE

Dopo aver prodotto i 3 documenti, proponi:

```
Wave W1 — [nome descrittivo]
Obiettivo: [cosa si raggiunge alla fine della wave]
Durata stimata: [X giorni]
Task: [lista task in ordine logico con dipendenze]

Criteri di successo:
→ [criterio 1]
→ [criterio 2]
→ [criterio 3]
```

**Aspetta conferma dell'utente prima di procedere con la Wave W1.**

---

## FASE 5 — HANDOFF

Produci `coord/HANDOFF_repo_analysis.md`:

```markdown
# HANDOFF — Repo Analysis

## Repository
- URL: [url GitHub]
- Branch analizzato: [branch]
- Commit analizzato: [hash o "ultimo"]
- Data analisi: [data]

## Documenti Prodotti
- coord/SPEC.md ✅
- coord/BOARD.md ✅  
- coord/ANALYSIS_REPORT.md ✅

## Prossimo Passo
Applicare PROMPT_03_planner su Wave W1
Task iniziale: TASK_01_[nome]

## Note Importanti
[Qualsiasi cosa l'agente successivo deve sapere]
```

---

## REGOLE FONDAMENTALI

```
1. MAI modificare codice durante l'analisi
   → Solo lettura, zero modifiche

2. MAI assumere — sempre leggere il codice reale
   → Non "probabilmente usa X" — leggi e conferma

3. MAI saltare file perché "sembra irrilevante"
   → Un file ignorato può nascondere dipendenze critiche

4. SEMPRE segnalare secrets o credenziali trovati
   → API key, password, token nel codice = segnalare subito

5. SEMPRE aspettare conferma prima di Wave W1
   → L'utente deve validare SPEC.md e BOARD.md
```

---

## PROMPT DI AVVIO

Quando l'utente vuole analizzare un repo GitHub, usa questo prompt in Claude Code:

```
Leggi PROMPT_A_github_repo_analyzer_v1.md
dalla cartella pipeline-agentiva e applicalo
al repository in [percorso cartella].

Lo scopo delle modifiche è: [descrizione obiettivo]

Produci:
- coord/SPEC.md
- coord/BOARD.md  
- coord/ANALYSIS_REPORT.md
- coord/HANDOFF_repo_analysis.md

Non toccare nulla finché non hai prodotto
tutti e 4 i documenti e ricevuto la mia conferma.
```

---

*Prompt A — GitHub Repo Analyzer v1.0*  
*Compatibile con pipeline agentiva 7 prompt*  
*Usa dopo: P03 Planner → P04 Executor → P05 Reviewer → P07 Integration*
