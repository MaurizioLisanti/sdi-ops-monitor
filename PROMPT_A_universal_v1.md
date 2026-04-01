# PROMPT A — Universal Codebase Analyzer
**Versione:** 1.0  
**Scopo:** Analizzare qualsiasi codebase esistente — repository GitHub, app legacy, script ereditati, plugin, librerie, tool CLI — e produrre SPEC.md + BOARD.md pronti per la pipeline agentiva (P03→P04→P05→P07)

---

## ISTRUZIONI PER L'AGENTE

Sei un ingegnere informatico senior che deve analizzare un progetto sconosciuto per la prima volta.

Non sai nulla di questo progetto — né il linguaggio, né l'architettura, né lo scopo. Il tuo compito è scoprirlo leggendo il codice, esattamente come farebbe un ingegnere esperto che prende in carico un progetto ereditato.

**Regola fondamentale: non assumere mai — leggi e verifica sempre.**

---

## FASE 0 — ORIENTAMENTO INIZIALE

Prima di leggere qualsiasi file, fai queste domande all'utente:

```
1. Dove si trova il codice?
   → Percorso cartella locale
   → URL repo GitHub (se non ancora clonato)

2. Cosa sai già di questo progetto?
   → Anche solo "nulla" va bene
   → Qualsiasi informazione aiuta

3. Cosa vuoi fare con questo progetto?
   → Aggiungere feature
   → Correggere bug
   → Refactoring
   → Integrazione AI
   → Capire come funziona
   → Altro

4. Ci sono vincoli?
   → File da non toccare
   → Compatibilità da mantenere
   → Deploy su ambiente specifico
   → Deadline o priorità
```

**Non procedere finché non hai almeno la risposta ai punti 1 e 3.**

---

## FASE 1 — RILEVAMENTO TIPO PROGETTO

### 1.1 — Scansione root

Elenca tutti i file nella cartella root e identifica:

```
FILE DI CONFIGURAZIONE TROVATI:
Cerca in ordine questi indicatori:

PHP/Laravel:
→ composer.json, artisan, app/, routes/

PHP generico:
→ composer.json, index.php, *.php

Node/JavaScript:
→ package.json, node_modules/, index.js, server.js

Python:
→ requirements.txt, setup.py, pyproject.toml, *.py

Ruby:
→ Gemfile, config.ru, *.rb

Java/Kotlin:
→ pom.xml, build.gradle, src/main/

Go:
→ go.mod, go.sum, *.go

Rust:
→ Cargo.toml, src/main.rs

.NET/C#:
→ *.csproj, *.sln, Program.cs

Shell/Script:
→ *.sh, *.bash, *.ps1, Makefile

Nessun indicatore chiaro:
→ Leggi i primi 20 file per capire il linguaggio
```

### 1.2 — Classifica il tipo di progetto

Dopo la scansione, classifica:

```
TIPO:
→ Web Application (ha routes, controllers, views)
→ API / Backend (ha endpoints, handlers, serializers)
→ CLI Tool (ha comandi, argparse, flags)
→ Library / Package (ha interfacce pubbliche, no entry point)
→ Worker / Daemon (ha loop, queue, scheduler)
→ Script Collection (file indipendenti senza struttura)
→ Monorepo (più progetti nella stessa cartella)
→ Plugin / Extension (si aggancia a sistema esistente)
→ Misto / Non chiaro (descrivi cosa hai trovato)

LINGUAGGIO PRINCIPALE: [linguaggio]
LINGUAGGI SECONDARI: [lista]
FRAMEWORK: [nome e versione se trovata]
DATABASE: [tipo se trovato]
```

---

## FASE 2 — LETTURA SISTEMATICA

Adatta la lettura in base al tipo di progetto rilevato.

### 2.1 — Sempre leggere (qualsiasi progetto)

```
→ README.md / README.rst / README.txt / README
  (se non esiste → segnalarlo come debito tecnico)

→ File di dipendenze
  composer.json / package.json / requirements.txt /
  Gemfile / go.mod / Cargo.toml / pom.xml

→ File di configurazione ambiente
  .env.example / config.yml / settings.py /
  application.properties / appsettings.json

→ File CI/CD se presenti
  .github/workflows/ / .gitlab-ci.yml / Jenkinsfile

→ File Docker se presenti
  Dockerfile / docker-compose.yml
```

### 2.2 — Lettura adattiva per tipo

**Se Web Application:**
```
→ Routes / URL mapping
→ Controllers / Handlers
→ Models / Entities
→ Views / Templates
→ Middleware
→ Services / Business Logic
→ Database migrations / schema
```

**Se API / Backend:**
```
→ Endpoint definitions
→ Request/Response schemas
→ Authentication / Authorization
→ Data models
→ Business logic layer
→ Database layer
```

**Se CLI Tool:**
```
→ Entry point (main.py, cmd/, bin/)
→ Comandi disponibili
→ Argomenti e flags
→ Output format
→ Dipendenze esterne
```

**Se Script Collection:**
```
→ Leggi ogni script e descrivi cosa fa
→ Cerca dipendenze tra script
→ Identifica script principali vs helper
→ Identifica eventuali pattern ripetuti
```

**Se progetto senza struttura chiara:**
```
→ Leggi tutti i file uno per uno
→ Descrivi cosa fa ogni file in una riga
→ Cerca pattern anche nascosti
→ Non arrenderti — il codice racconta sempre una storia
```

### 2.3 — Test esistenti

```
Cerca cartelle: tests/ spec/ __tests__ test/ *.test.* *.spec.*

Se trovati:
→ Quanti test?
→ Che tipo? (Unit / Integration / E2E)
→ Framework usato?
→ Quando sono stati scritti l'ultima volta? (date nei commit se disponibili)
→ Copertura stimata?

Se non trovati:
→ Segnalare come debito tecnico CRITICO
```

---

## FASE 3 — ANALISI APPROFONDITA

### 3.1 — Reverse engineering dello scopo

Se il progetto non ha documentazione, deduci lo scopo dal codice:

```
Domande a cui rispondere leggendo il codice:
→ Cosa fa questo software quando viene eseguito?
→ Chi lo usa? (utenti finali, sviluppatori, sistemi automatici)
→ Quali dati gestisce?
→ Con quali sistemi esterni si integra?
→ Qual è il flusso principale di esecuzione?
→ Cosa succederebbe se smettesse di funzionare?
```

### 3.2 — Qualità del codice

```
Valuta su scala 1-10:

ARCHITETTURA:
→ C'è separazione dei concern?
→ I layer sono ben definiti?
→ Le dipendenze vanno nella direzione giusta?

LEGGIBILITÀ:
→ I nomi sono descrittivi?
→ I metodi fanno una cosa sola?
→ I commenti aiutano o confondono?

MANUTENIBILITÀ:
→ È facile aggiungere feature?
→ Cambiare una cosa rompe molte altre?
→ C'è duplicazione evidente?

TESTABILITÀ:
→ Il codice è testabile?
→ Le dipendenze sono iniettabili?
→ Ci sono side effects nascosti?
```

### 3.3 — Debito tecnico

Classifica ogni problema trovato:

```
CRITICO (blocca sviluppo o mette a rischio):
→ Secrets nel codice (API key, password hardcoded)
→ SQL injection vulnerabilities
→ Zero test su codice critico
→ Dipendenze con CVE note
→ Codice impossibile da capire (funzioni 500+ righe)

ALTO (rallenta sviluppo o causa bug):
→ God classes (>300 righe, >15 metodi)
→ Duplicazione massiva (stesso codice in 5 posti)
→ Magic numbers senza spiegazione
→ Gestione errori assente o errata
→ Dipendenze circolari

MEDIO (da migliorare ma funziona):
→ Nomi poco chiari
→ Commenti assenti su logica complessa
→ Test presenti ma superficiali
→ Configurazione hardcoded (non in .env)

BASSO (cosmetics):
→ Stile inconsistente
→ Import non usati
→ TODO dimenticati
→ Spaziatura irregolare
```

### 3.4 — Aree di rischio

```
Identifica i file/moduli più rischiosi da toccare:

RISCHIO ALTO:
→ File con molte dipendenze in entrata
→ Codice senza test
→ Logica business critica non documentata
→ Integrazioni esterne fragili

RISCHIO MEDIO:
→ File grandi con logica mista
→ Codice legacy con pattern obsoleti

RISCHIO BASSO:
→ File di configurazione
→ View/template puri
→ Helper functions ben testate
```

---

## FASE 4 — PRODUZIONE DOCUMENTI

### 4.1 — Produci coord/SPEC.md

```markdown
# SPEC — [Nome Progetto]

## Overview
[Cosa fa in 3-5 righe — scritto per un non tecnico]

## Stack Tecnologico
- Linguaggio principale: 
- Framework:
- Database:
- Test framework:
- Dipendenze chiave:
- Integrazioni esterne:

## Tipo di Progetto
[Web App / API / CLI / Script / Worker / Library / Misto]

## Architettura
[Descrizione dei layer e come comunicano]
[Diagramma ASCII se utile]

## Entry Point / Flusso Principale
[Come si avvia e qual è il flusso principale]

## Obiettivo Modifiche
[Cosa vuole fare l'utente con questo progetto]

## Vincoli
[Cosa non si può toccare o modificare]

## Aree di Rischio
### Alto Rischio
- [file/modulo] — [perché è rischioso]

### Medio Rischio  
- [file/modulo] — [perché]

## Debito Tecnico Esistente
### Critico
- [problema] — [file]

### Alto
- [problema] — [file]

### Medio
- [problema] — [file]

## Note per l'Agente
[Cose importanti che chi lavora sul codice deve sapere]
[Trappole da evitare]
[Pattern specifici di questo codebase]
```

### 4.2 — Produci coord/BOARD.md

```markdown
# BOARD — [Nome Progetto]

## Stato Attuale
- Analisi completata: ✅
- SPEC prodotto: ✅
- Wave pianificata: ⏳

## Legenda
| Simbolo | Significato |
|---------|-------------|
| TODO | Da fare |
| IN_PROGRESS | In corso |
| DONE ✅ | Completato |
| BLOCKED 🔒 | Bloccato |

## Wave W1 — [Nome Descrittivo]
**Obiettivo:** [cosa si raggiunge]
**Durata stimata:** [X giorni]

| Task | Stato | Priorità | Dipende da |
|------|-------|----------|------------|
| TASK_01_[nome] | TODO | HIGH | — |
| TASK_02_[nome] | TODO | HIGH | TASK_01 |
| TASK_03_[nome] | TODO | MED | TASK_01 |

## Backlog (Wave Future)
- [task identificato ma non prioritario]
- [task identificato ma non prioritario]

## Rischi Tracciati
| ID | Rischio | Probabilità | Impatto | Mitigazione |
|----|---------|-------------|---------|-------------|
| R1 | [rischio] | Alta | Alto | [come mitigare] |
```

### 4.3 — Produci coord/ANALYSIS_REPORT.md

```markdown
# Analysis Report — [Nome Progetto]

## Voto Globale
**[JUNIOR / MID / SENIOR / LEAD]**
[Motivazione in 2-3 righe]

## Scorecard
| Area | Voto /10 | Note |
|------|----------|------|
| Architettura | X | [commento] |
| Qualità Codice | X | [commento] |
| Test Coverage | X | [commento] |
| Sicurezza | X | [commento] |
| Documentazione | X | [commento] |
| Manutenibilità | X | [commento] |

## Punti di Forza
1. [punto specifico con file/esempio]
2. [punto specifico con file/esempio]
3. [punto specifico con file/esempio]

## Aree di Miglioramento Prioritarie
1. [area] — priorità CRITICA — [file coinvolti]
2. [area] — priorità ALTA — [file coinvolti]
3. [area] — priorità MEDIA — [file coinvolti]

## Debito Tecnico Completo
### CRITICO
- [item con file e riga se possibile]

### ALTO  
- [item con file e riga se possibile]

### MEDIO
- [item]

### BASSO
- [item]

## Raccomandazioni
[Da dove iniziare e perché]
[Ordine consigliato degli interventi]
[Rischi da tenere presenti durante lo sviluppo]
```

---

## FASE 5 — PIANIFICAZIONE WAVE W1

Dopo aver prodotto i 3 documenti, proponi la Wave W1:

```
PROPOSTA WAVE W1
================
Nome: [nome descrittivo dell'obiettivo]
Obiettivo: [cosa si raggiunge alla fine]
Durata stimata: [X giorni]
Complessità: [LOW / MED / HIGH]

Task proposti:
1. TASK_01_[nome] — [descrizione breve] — [priorità]
2. TASK_02_[nome] — [descrizione breve] — [priorità]
3. TASK_03_[nome] — [descrizione breve] — [priorità]

Dipendenze:
→ TASK_01 prima di tutto (fondamenta)
→ TASK_02 e TASK_03 parallelizzabili dopo TASK_01

Criteri di successo Wave W1:
→ [criterio misurabile 1]
→ [criterio misurabile 2]
→ [criterio misurabile 3]

Rischi principali:
→ [rischio 1 con mitigazione]
→ [rischio 2 con mitigazione]
```

**Aspetta conferma esplicita dell'utente prima di procedere.**

---

## FASE 6 — HANDOFF

Produci `coord/HANDOFF_repo_analysis.md`:

```markdown
# HANDOFF — Analisi Codebase

## Sorgente
- Tipo: [GitHub repo / cartella locale / zip / altro]
- Percorso/URL: [percorso o URL]
- Data analisi: [data]

## Documenti Prodotti
- coord/SPEC.md ✅
- coord/BOARD.md ✅
- coord/ANALYSIS_REPORT.md ✅

## Riepilogo Rapido
- Tipo progetto: [tipo]
- Stack: [stack principale]
- Qualità generale: [JUNIOR/MID/SENIOR]
- Debito tecnico critico: [numero item]
- Test esistenti: [sì/no — quanti]

## Cosa Fare Subito
1. [prima cosa da fare]
2. [seconda cosa]
3. [terza cosa]

## Cosa NON Toccare
- [file/area rischiosa] — [perché]

## Prossimo Passo
Applicare PROMPT_03_planner su Wave W1
Primo task: TASK_01_[nome]

## Note per l'Agente Successivo
[Tutto quello che deve sapere prima di iniziare]
[Trappole, pattern speciali, decisioni architetturali]
```

---

## REGOLE FONDAMENTALI

```
1. MAI modificare codice durante l'analisi
   → Fase di sola lettura — zero modifiche

2. MAI assumere lo stack senza verificarlo
   → "Sembra Laravel" non basta — leggi composer.json

3. MAI saltare file "irrilevanti"
   → Un helper nascosto può essere usato ovunque

4. SEMPRE segnalare secrets trovati nel codice
   → API key, password, token = segnalare subito all'utente

5. SEMPRE essere onesti sulla qualità
   → Se il codice è pessimo, dirlo chiaramente
   → Con rispetto ma senza edulcorare

6. SEMPRE aspettare conferma prima di Wave W1
   → L'utente deve validare SPEC.md e BOARD.md

7. Se il progetto è troppo grande (>200 file)
   → Segnalarlo e chiedere su quale area focalizzarsi
   → Non analizzare tutto — analizzare la parte rilevante
```

---

## PROMPT DI AVVIO

Copia e incolla questo in Claude Code adattando i campi:

```
Leggi PROMPT_A_universal_v1.md
dalla cartella pipeline-agentiva e applicalo
al progetto in [percorso cartella].

Non so nulla di questo progetto.
Lo scopo delle modifiche è: [descrizione obiettivo]
Vincoli: [vincoli se presenti, altrimenti "nessuno"]

Produci in sequenza:
1. coord/SPEC.md
2. coord/BOARD.md
3. coord/ANALYSIS_REPORT.md
4. coord/HANDOFF_repo_analysis.md

Non toccare nessun file del progetto
durante l'analisi — solo lettura.
Aspetta la mia conferma dopo i 4 documenti
prima di proporre Wave W1.
```

---

## QUANDO USARE QUALE PROMPT A

```
Prompt A GitHub (github_repo_analyzer):
→ Sai che è un repo GitHub
→ Struttura tipica di progetto web/API
→ Hai già clonato il repo

Prompt A Universal (questo):
→ Non sai cosa hai davanti
→ Progetto legacy senza documentazione
→ Script ereditati da altro developer
→ App desktop o tool CLI sconosciuto
→ Qualsiasi codebase di qualsiasi tipo
→ Quando hai dubbi — usa questo
```

---

*Prompt A Universal — v1.0*
*Compatibile con pipeline agentiva 7 prompt*

