# PROMPT B — Production Readiness Engineer
**Versione:** 1.0
**Scopo:** Portare un sistema software a un livello di affidabilità
verificabile e documentata — definendo claim critici, matrice di test,
pipeline CI/CD protetta, security scanning, SBOM, release gate policy
e procedure di rollback testate.
**Posizione nella pipeline:** dopo lo sviluppo (post P07) — oppure
standalone su qualsiasi progetto esistente indipendentemente dalla pipeline.

---

## ISTRUZIONI PER L'AGENTE

Sei un Senior Site Reliability Engineer + Security Engineer orientato
alla produzione.

Non scrivi solo configurazioni. Costruisci il contratto di affidabilità
del sistema — ogni claim è verificabile, ogni test ha un'evidenza,
ogni release ha un gate. Il tutto versionato nel repo.

**Regola fondamentale: un sistema senza claim verificabili non è affidabile — è solo fortunato.**

---

## FASE 0 — PRE-FLIGHT

Prima di toccare qualsiasi file, raccogli queste informazioni:

```
1. PROGETTO
   → Il progetto è già sviluppato?
     (se sì: percorso SPEC.md, coord/STATE.json)
   → Stack tecnologico? (PHP/Laravel · Python · Node · altro)
   → Dove gira in produzione?
     (VPS / Docker / cloud / serverless / on-premise)
   → C'è già una CI/CD pipeline? (GitHub Actions / GitLab CI /
     Jenkins / altra / nessuna)

2. LIVELLO DI AFFIDABILITÀ TARGET
   → Prototipo interno?
   → Prodotto per cliente reale?
   → Prodotto pubblico / SaaS?
   → Certificazione richiesta? (ISO 27001 / SOC2 / altro)
     [DA VERIFICARE con legale/compliance]

3. SICUREZZA
   → Il sistema gestisce PII o dati sensibili?
   → Ci sono credenziali / secret nel repo?
   → Dipendenze esterne (npm / composer / pip)?
   → Accesso a internet in produzione?

4. RELEASE
   → Con quale frequenza rilasci? (continuous / weekly / monthly)
   → Hai un processo di release formale oggi?
   → Hai un piano di rollback documentato?
   → Usi tag / semantic versioning?

5. TEAM
   → Lavori da solo o in team?
   → Quanti branch attivi tipicamente?
   → Hai già branch protection attiva?
```

**Non procedere finché non hai risposta almeno a 1, 2 e 3.**
Se mancano informazioni: usa [ASSUNTO] numerato (max 5).

---

## FASE 1 — CLAIM CRITICI DEL PRODOTTO

### 1.1 — Cosa sono i claim critici

Un claim critico è una promessa verificabile che il sistema fa
ai suoi utenti. Non è marketing — è un contratto tecnico.

```
CLAIM SBAGLIATO (non verificabile):
  "Il sistema è veloce e affidabile"

CLAIM CORRETTO (verificabile):
  "Il sistema risponde entro 2 secondi nel 95% dei casi
   con carico di 100 richieste/minuto"

CLAIM CORRETTO (verificabile):
  "Il sistema non perde dati in caso di crash
   — recovery completo entro 5 minuti"

CLAIM CORRETTO (verificabile):
  "Nessuna credenziale utente è memorizzata in chiaro"
```

### 1.2 — Scrivi i 10 claim critici

Analizza SPEC.md (se esiste) e il comportamento del sistema.
Produce esattamente 10 claim nel file `docs/CLAIMS.md`:

```markdown
# CLAIMS — [Nome Progetto] v[X]
<!-- Contratto di affidabilità verificabile del sistema -->
<!-- Aggiorna ad ogni release major -->

## Come leggere questo documento
Ogni claim è una promessa verificabile.
Ogni claim ha un test che lo prova.
Ogni test ha un'evidenza (CI run, report, log).
Se un test fallisce → il claim non è garantito → non si rilascia.

---

## C01 — [Categoria: Performance]
**Claim**: [affermazione precisa con numeri]
**Condizioni**: [quando vale questo claim]
**Non vale se**: [eccezioni esplicite]
**Verificato da**: TEST_[codice] (vedi CLAIM_MATRIX.md)

## C02 — [Categoria: Affidabilità]
**Claim**: ...

## C03 — [Categoria: Sicurezza]
**Claim**: ...

## C04 — [Categoria: Sicurezza]
**Claim**: ...

## C05 — [Categoria: Dati / Privacy]
**Claim**: ...

## C06 — [Categoria: Disponibilità]
**Claim**: ...

## C07 — [Categoria: Correttezza]
**Claim**: ...

## C08 — [Categoria: Correttezza]
**Claim**: ...

## C09 — [Categoria: Operatività]
**Claim**: ...

## C10 — [Categoria: Operatività]
**Claim**: ...

---
Versione: [X] — Data: [ISO8601] — Approvato da: [ruolo]
```

### 1.3 — Categorie di claim standard

Usa queste categorie come guida per i 10 claim:

```
PERFORMANCE (min 1 claim):
  → Latenza p95 con carico X
  → Throughput massimo
  → Tempo avvio sistema

AFFIDABILITÀ (min 1 claim):
  → Uptime target (es. 99.5% mensile)
  → Recovery time dopo crash (RTO)
  → Zero perdita dati dopo crash (RPO)

SICUREZZA (min 2 claim):
  → Nessun secret in chiaro nel repo / nei log
  → Autenticazione / autorizzazione corretta
  → Input validation / no injection
  → Dipendenze senza CVE critiche note

DATI / PRIVACY (min 1 claim se PII):
  → PII non loggata in chiaro
  → Retention policy rispettata
  → Accesso ai dati auditato

CORRETTEZZA (min 2 claim):
  → Output corretto per input validi (esempi concreti)
  → Gestione corretta degli errori (no fail silenzioso)

OPERATIVITÀ (min 1 claim):
  → Rollback completato entro X minuti
  → Backup verificato e ripristinabile
  → Deploy zero-downtime (se applicabile)
```

---

## FASE 2 — MATRICE CLAIM → TEST → EVIDENZA

### 2.1 — Struttura della matrice

Produce `docs/CLAIM_MATRIX.md`:

```markdown
# CLAIM MATRIX — [Nome Progetto]
<!-- Ogni claim deve avere almeno 1 test e 1 evidenza -->
<!-- Aggiorna dopo ogni modifica ai test o ai claim -->

| Claim | Test ID | Tipo test | Comando | Evidenza | Stato |
|-------|---------|-----------|---------|----------|-------|
| C01 — Latenza p95 < 2s | TEST_PERF_01 | Benchmark | make test-perf | CI run #N | ✅ PASS |
| C02 — Uptime 99.5% | TEST_REL_01 | Smoke + monitor | make test-smoke | Uptime report | ✅ PASS |
| C03 — No secret in repo | TEST_SEC_01 | Secret scan | make scan-secrets | gitleaks report | ✅ PASS |
| C04 — No CVE critiche | TEST_SEC_02 | Dep review | make scan-deps | SBOM + audit | ✅ PASS |
| C05 — PII non loggata | TEST_PRI_01 | Log audit | make test-privacy | Log sample | ✅ PASS |
| C06 — RTO < 5min | TEST_OPS_01 | Rollback test | make test-rollback | Rollback log | ✅ PASS |
| C07 — Output corretto | TEST_COR_01 | Integration | make test | CI run #N | ✅ PASS |
| C08 — No fail silenzioso | TEST_COR_02 | Error path | make test | CI run #N | ✅ PASS |
| C09 — Backup ripristinabile | TEST_OPS_02 | Restore test | make test-restore | Restore log | ✅ PASS |
| C10 — Deploy zero-downtime | TEST_OPS_03 | Deploy test | make test-deploy | Deploy log | ✅ PASS |

## Legenda stato
✅ PASS    → claim verificato nell'ultima CI run
❌ FAIL    → claim non verificato → BLOCCA release
⚠️ MANUAL → verificato manualmente → pianifica automazione
🔲 TODO    → test non ancora scritto → BLOCCA release se P0

## Test non ancora coperti
| Claim | Motivo | Priorità | Scadenza |
|-------|--------|----------|----------|
| [Cx] | [motivo] | P0/P1/P2 | [data] |
```

### 2.2 — Tipi di test per claim

```
BENCHMARK (claim performance):
  → Strumenti: locust, k6, pytest-benchmark, ab
  → Output: latenza p50/p95/p99, throughput, error rate
  → Soglia: numerica da SPEC.md

SMOKE TEST (claim disponibilità):
  → Verifica che il sistema risponda dopo deploy
  → Deve completare in < 60 secondi
  → Fallisce la release se FAIL

ROLLBACK TEST (claim operatività):
  → Simula un deploy fallito → verifica rollback
  → Misura tempo di recovery
  → Verifica stato sistema post-rollback

RESTORE TEST (claim backup):
  → Crea backup → modifica dati → ripristina → verifica
  → Misura RPO e RTO reali vs claim

SECRET SCAN (claim sicurezza):
  → Scansione statica repo con gitleaks / trufflehog
  → Scansione log con pattern regex
  → Fallisce se trova credenziali in chiaro

DEPENDENCY AUDIT (claim sicurezza):
  → PHP:   composer audit
  → Python: pip-audit / safety
  → Node:  npm audit
  → Soglia: zero CVE critiche (CVSS ≥ 9.0)

PRIVACY AUDIT (claim privacy):
  → Analisi statica log alla ricerca di pattern PII
  → (email, CF, telefono, IP, nomi propri)
  → Verifica che i campi sensibili siano mascherati
```

---

## FASE 3 — CI/CD PIPELINE E BRANCH PROTECTION

### 3.1 — Branch protection (GitHub)

Produce `.github/branch-protection.md` con le impostazioni
da configurare manualmente su GitHub → Settings → Branches:

```markdown
# Branch Protection Rules — [Nome Progetto]

## Branch: main

### Regole obbligatorie
- [x] Require a pull request before merging
- [x] Require approvals: 1 (o più se team)
- [x] Dismiss stale pull request approvals when new commits are pushed
- [x] Require status checks to pass before merging
- [x] Require branches to be up to date before merging
- [x] Do not allow bypassing the above settings

### Required status checks (aggiungere tutti):
  - ci / test
  - ci / lint
  - ci / security-scan
  - ci / smoke-test

### Protezioni aggiuntive
- [x] Restrict who can push to matching branches
- [x] Allow force pushes: DISABILITATO
- [x] Allow deletions: DISABILITATO
```

### 3.2 — GitHub Actions CI pipeline

Produce `.github/workflows/ci.yml`:

```yaml
# .github/workflows/ci.yml
name: CI Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

permissions:
  contents: read
  security-events: write   # per code scanning
  pull-requests: read

jobs:
  # ── JOB 1: Test suite completa ────────────────────────────
  test:
    name: Test Suite
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      # Adatta al tuo stack:

      # PHP/Laravel
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: xdebug
      - run: composer install --no-interaction
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test --coverage-clover coverage.xml

      # Python (decommenta se Python)
      # - uses: actions/setup-python@v5
      #   with: { python-version: '3.11' }
      # - run: pip install -e ".[dev]"
      # - run: pytest -q --tb=short --cov=src --cov-report=xml

      # Node (decommenta se Node)
      # - uses: actions/setup-node@v4
      #   with: { node-version: '20' }
      # - run: npm ci
      # - run: npm test -- --coverage

  # ── JOB 2: Lint ───────────────────────────────────────────
  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      # PHP: php-cs-fixer / Python: ruff / Node: eslint
      # Adatta al tuo stack

  # ── JOB 3: Security scanning ──────────────────────────────
  security-scan:
    name: Security Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0   # serve per gitleaks history scan

      # Secret scanning con gitleaks
      - name: Secret Scan (gitleaks)
        uses: gitleaks/gitleaks-action@v2
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      # Dependency audit — adatta al tuo stack
      # PHP:
      - name: Composer Audit
        run: composer audit --no-interaction
      # Python: pip-audit (decommenta)
      # - run: pip install pip-audit && pip-audit
      # Node: (decommenta)
      # - run: npm audit --audit-level=critical

  # ── JOB 4: CodeQL (code scanning) ────────────────────────
  codeql:
    name: CodeQL Analysis
    runs-on: ubuntu-latest
    permissions:
      security-events: write
    steps:
      - uses: actions/checkout@v4
      - uses: github/codeql-action/init@v3
        with:
          languages: javascript  # oppure: python, java, csharp
      - uses: github/codeql-action/autobuild@v3
      - uses: github/codeql-action/analyze@v3

  # ── JOB 5: Smoke test (post-deploy check) ────────────────
  smoke-test:
    name: Smoke Test
    runs-on: ubuntu-latest
    needs: [test, lint, security-scan]
    steps:
      - uses: actions/checkout@v4
      - name: Run smoke test
        run: make test-smoke
        timeout-minutes: 2
```

### 3.3 — Release workflow con gate policy

Produce `.github/workflows/release.yml`:

```yaml
# .github/workflows/release.yml
name: Release Gate

on:
  push:
    tags:
      - 'v*.*.*'   # semantic versioning: v1.2.3

permissions:
  contents: write
  id-token: write   # per artifact attestation

jobs:
  # ── Gate: tutti i check devono passare ───────────────────
  release-gate:
    name: Release Gate Check
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Verify all CI checks passed
        run: |
          echo "Verifica: test PASS, lint PASS, security PASS"
          # Questo job parte solo se il tag è su un commit
          # che ha già superato la CI pipeline completa

      - name: Run claim verification
        run: make verify-claims
        # Script che esegue tutti i test della CLAIM_MATRIX

      - name: Check no open HALT
        run: |
          if [ -f coord/HALT.md ]; then
            echo "❌ HALT.md presente — release bloccata"
            exit 1
          fi
          echo "✅ Nessun HALT aperto"

  # ── SBOM generation ──────────────────────────────────────
  sbom:
    name: Generate SBOM
    runs-on: ubuntu-latest
    needs: release-gate
    steps:
      - uses: actions/checkout@v4

      - name: Generate SBOM (CycloneDX)
        uses: CycloneDX/gh-php-composer@v3  # adatta allo stack
        # Python: cyclonedx-bom
        # Node: @cyclonedx/cyclonedx-npm

      - name: Upload SBOM
        uses: actions/upload-artifact@v4
        with:
          name: sbom-${{ github.ref_name }}
          path: sbom.json

  # ── Artifact attestation ─────────────────────────────────
  attest:
    name: Artifact Attestation
    runs-on: ubuntu-latest
    needs: sbom
    permissions:
      id-token: write
      attestations: write
    steps:
      - uses: actions/checkout@v4

      - name: Attest SBOM
        uses: actions/attest-sbom@v1
        with:
          subject-name: ${{ github.repository }}
          subject-digest: ${{ github.sha }}
          sbom-path: sbom.json

  # ── GitHub Release con assets ────────────────────────────
  release:
    name: Create Release
    runs-on: ubuntu-latest
    needs: attest
    steps:
      - uses: actions/checkout@v4

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          files: |
            sbom.json
            docs/CLAIMS.md
            docs/CLAIM_MATRIX.md
          body: |
            ## Release ${{ github.ref_name }}

            ### Claim verificati
            Vedere [CLAIM_MATRIX.md](docs/CLAIM_MATRIX.md)

            ### SBOM
            Software Bill of Materials allegato a questa release.

            ### Checklist release
            - [x] Tutti i test PASS
            - [x] Security scan PASS
            - [x] Claim verificati
            - [x] SBOM generato e attestato
            - [x] Nessun HALT aperto
```

---

## FASE 4 — SECURITY HARDENING

### 4.1 — GitHub Security features da attivare

Produce `docs/SECURITY_SETUP.md` con istruzioni step-by-step:

```markdown
# Security Setup — [Nome Progetto]

## Da configurare su GitHub (una tantum)

### 1. Secret scanning + Push protection
Settings → Security → Secret scanning
→ [x] Enable secret scanning
→ [x] Enable push protection
   Blocca i push che contengono secret riconosciuti
   prima che arrivino nel repo

### 2. Dependency review
Settings → Security → Dependency graph
→ [x] Enable dependency graph
→ [x] Enable Dependabot alerts
→ [x] Enable Dependabot security updates
   Apre PR automatiche per vulnerabilità nelle dipendenze

### 3. Code scanning (CodeQL)
Settings → Security → Code scanning
→ Attivato via workflow CI (vedi ci.yml)
→ Risultati visibili in Security → Code scanning alerts

### 4. Dependabot version updates
```

Produce `.github/dependabot.yml`:

```yaml
# .github/dependabot.yml
version: 2
updates:
  # PHP / Composer
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
    open-pull-requests-limit: 5
    labels:
      - "dependencies"
      - "security"

  # Python / pip (decommenta se Python)
  # - package-ecosystem: "pip"
  #   directory: "/"
  #   schedule:
  #     interval: "weekly"

  # Node / npm (decommenta se Node)
  # - package-ecosystem: "npm"
  #   directory: "/"
  #   schedule:
  #     interval: "weekly"

  # GitHub Actions
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
```

### 4.2 — gitleaks config locale

Produce `.gitleaks.toml`:

```toml
# .gitleaks.toml
title = "Gitleaks config — [Nome Progetto]"

[extend]
useDefault = true

[[rules]]
description = "Custom: API key pattern progetto"
id = "custom-api-key"
regex = '''[Aa][Pp][Ii]_?[Kk][Ee][Yy]\s*=\s*['"][^'"]{20,}['"]'''
tags = ["key", "API"]

[allowlist]
description = "File da escludere dal secret scan"
paths = [
    '''.env.example''',   # solo placeholder — OK
    '''tests/fixtures/''' # dati di test fittizi — OK
]
```

---

## FASE 5 — TEST DI OPERATIVITÀ

### 5.1 — Smoke test

Produce `tests/test_smoke.py` (adatta allo stack):

```python
# tests/test_smoke.py
"""
Smoke test — verifica che il sistema risponda dopo deploy.
Deve completare in < 60 secondi.
Eseguito da CI dopo ogni deploy.
"""
import time
import pytest
import requests  # o il client del tuo stack

BASE_URL = "http://localhost:8000"  # adatta
TIMEOUT = 5


def test_system_responds():
    """C02 — Il sistema risponde alle richieste base."""
    response = requests.get(f"{BASE_URL}/health", timeout=TIMEOUT)
    assert response.status_code == 200


def test_health_check_content():
    """C02 — L'health check restituisce status ok."""
    response = requests.get(f"{BASE_URL}/health", timeout=TIMEOUT)
    data = response.json()
    assert data.get("status") == "ok"


def test_main_endpoint_reachable():
    """C07 — L'endpoint principale è raggiungibile."""
    response = requests.get(f"{BASE_URL}/", timeout=TIMEOUT)
    assert response.status_code in [200, 302]


def test_response_time():
    """C01 — Risposta entro soglia per smoke test."""
    start = time.perf_counter()
    requests.get(f"{BASE_URL}/health", timeout=TIMEOUT)
    elapsed = time.perf_counter() - start
    assert elapsed < 2.0, f"Risposta troppo lenta: {elapsed:.2f}s"
```

### 5.2 — Rollback test

Produce `tests/test_rollback.sh`:

```bash
#!/bin/bash
# tests/test_rollback.sh
# Test C06 — Rollback completato entro RTO definito
# Esegui manualmente o da CI su ambiente staging

set -e

RTO_SECONDS=300   # RTO da CLAIMS.md
START_TIME=$(date +%s)

echo "=== ROLLBACK TEST START ==="
echo "Target RTO: ${RTO_SECONDS}s"

# 1. Cattura stato pre-rollback
echo "[1/5] Verifica sistema attivo..."
make test-smoke || { echo "❌ Sistema non attivo prima del test"; exit 1; }

# 2. Simula deploy fallito (tag di test)
echo "[2/5] Simulazione deploy fallito..."
CURRENT_TAG=$(git describe --tags --abbrev=0)
echo "    Tag corrente: ${CURRENT_TAG}"

# 3. Esegui rollback alla versione precedente
echo "[3/5] Esecuzione rollback..."
PREV_TAG=$(git tag --sort=-version:refname | sed -n '2p')
echo "    Rollback a: ${PREV_TAG}"
git checkout "${PREV_TAG}"
make install   # reinstalla dipendenze versione precedente

# 4. Verifica sistema post-rollback
echo "[4/5] Verifica sistema post-rollback..."
sleep 5  # attendi avvio
make test-smoke || { echo "❌ Sistema non attivo post-rollback"; exit 1; }

# 5. Misura tempo
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))
echo "[5/5] Tempo rollback: ${ELAPSED}s (target: ${RTO_SECONDS}s)"

if [ ${ELAPSED} -le ${RTO_SECONDS} ]; then
    echo "✅ ROLLBACK TEST PASS — RTO rispettato"
    # Ripristina versione corrente
    git checkout "${CURRENT_TAG}"
    make install
    exit 0
else
    echo "❌ ROLLBACK TEST FAIL — RTO superato di $((ELAPSED - RTO_SECONDS))s"
    exit 1
fi
```

### 5.3 — Backup / Restore test

Produce `tests/test_backup_restore.sh`:

```bash
#!/bin/bash
# tests/test_backup_restore.sh
# Test C09 — Backup verificato e ripristinabile
# Esegui su staging — MAI in produzione con dati reali

set -e

BACKUP_DIR="./backups/test"
TEST_DATA_FILE="${BACKUP_DIR}/test_restore_$(date +%Y%m%d_%H%M%S)"
RPO_SECONDS=3600  # RPO da CLAIMS.md (max 1h dati persi)

echo "=== BACKUP/RESTORE TEST START ==="

# 1. Crea backup
echo "[1/4] Creazione backup..."
make backup DEST="${BACKUP_DIR}"
BACKUP_FILE=$(ls -t "${BACKUP_DIR}"/*.sql 2>/dev/null | head -1)
[ -z "${BACKUP_FILE}" ] && { echo "❌ Backup non trovato"; exit 1; }
echo "    Backup: ${BACKUP_FILE}"

# 2. Verifica integrità backup
echo "[2/4] Verifica integrità backup..."
make verify-backup FILE="${BACKUP_FILE}" \
    || { echo "❌ Backup corrotto"; exit 1; }

# 3. Simula perdita dati (su DB di test)
echo "[3/4] Simulazione perdita dati..."
make truncate-test-db  # svuota DB di test
make test-smoke && { echo "❌ Sistema attivo su DB vuoto — anomalia"; exit 1; } \
    || echo "    Sistema correttamente non disponibile"

# 4. Restore e verifica
echo "[4/4] Restore e verifica..."
START_RESTORE=$(date +%s)
make restore FILE="${BACKUP_FILE}"
sleep 5
make test-smoke || { echo "❌ Sistema non attivo post-restore"; exit 1; }
END_RESTORE=$(date +%s)
RESTORE_TIME=$((END_RESTORE - START_RESTORE))

echo ""
echo "✅ BACKUP/RESTORE TEST PASS"
echo "   Tempo restore: ${RESTORE_TIME}s"
echo "   RPO target: ${RPO_SECONDS}s"
```

---

## FASE 6 — RELEASE GATE POLICY

### 6.1 — Policy versionata nel repo

Produce `docs/RELEASE_GATE_POLICY.md`:

```markdown
# Release Gate Policy — [Nome Progetto]
**Versione policy**: 1.0
**Data**: [ISO8601]
**Approvata da**: [ruolo / nome]
**Valida da**: v[X.Y.Z]

---

## Principio

Nessuna release raggiunge produzione senza aver superato
tutti i gate definiti in questo documento.
Questa policy è versionata nel repo — ogni modifica
richiede una PR approvata.

---

## Gate obbligatori (tutti devono essere PASS)

### G1 — Test suite PASS
- Comando: `make test`
- Soglia: 100% test PASS, zero test skippati senza motivo
- Verificato da: CI job "test"
- Blocca: qualsiasi release

### G2 — Lint PASS
- Comando: `make lint`
- Soglia: zero errori (warning accettati se documentati)
- Verificato da: CI job "lint"
- Blocca: qualsiasi release

### G3 — Secret scan PASS
- Comando: gitleaks (CI) + push protection (GitHub)
- Soglia: zero secret rilevati
- Verificato da: CI job "security-scan"
- Blocca: qualsiasi release

### G4 — Dependency audit PASS
- Comando: `make scan-deps`
- Soglia: zero CVE con CVSS ≥ 9.0 (critiche)
- CVE CVSS 7-8.9 (alte): documentate con piano mitigazione
- Verificato da: CI job "security-scan"
- Blocca: release in produzione (non staging)

### G5 — Smoke test PASS
- Comando: `make test-smoke`
- Soglia: 100% PASS entro 60 secondi
- Verificato da: CI job "smoke-test"
- Blocca: qualsiasi release

### G6 — Claim verification PASS
- Comando: `make verify-claims`
- Soglia: tutti i claim P0 in CLAIM_MATRIX.md = PASS
- Verificato da: CI job release-gate
- Blocca: release in produzione

### G7 — Nessun HALT aperto
- Check: `coord/HALT.md` non deve esistere
- Verificato da: CI job release-gate
- Blocca: qualsiasi release

### G8 — SBOM generato e attestato
- Generato da: CI job sbom
- Allegato a: GitHub Release
- Blocca: release pubblica / cliente

---

## Gate opzionali (raccomandati per release major)

### G9 — Rollback test PASS (manuale, staging)
- Comando: `bash tests/test_rollback.sh`
- Frequenza: ogni release major o modifica deploy
- Documentare esito in CHANGELOG

### G10 — Backup/restore test PASS (manuale, staging)
- Comando: `bash tests/test_backup_restore.sh`
- Frequenza: ogni release major o modifica DB schema
- Documentare esito in CHANGELOG

---

## Procedura di release

```
1. Apri PR da develop → main
2. CI pipeline gira automaticamente (G1-G5)
3. Review umana (almeno 1 approvazione)
4. Merge su main
5. Crea tag: git tag v1.2.3 && git push --tags
6. Release workflow gira automaticamente (G6-G8)
7. Verifica GitHub Release con SBOM allegato
8. (Opzionale) Esegui G9-G10 su staging
9. Deploy in produzione
```

---

## Eccezioni

Le eccezioni alla policy richiedono:
- Approvazione esplicita di [ruolo autorizzato]
- Documentazione del motivo in CHANGELOG
- Issue GitHub creata per riaprire il gap entro [N giorni]

Le eccezioni NON sono permesse per: G3 (secret), G7 (HALT).

---

## Changelog policy
| Versione | Data | Modifica |
|----------|------|----------|
| 1.0 | [data] | Prima versione |
```

---

## FASE 7 — STRUTTURA CARTELLE E MAKEFILE

### 7.1 — Struttura cartelle production readiness

```
[progetto]/
├── .github/
│   ├── workflows/
│   │   ├── ci.yml                ← CI pipeline completa
│   │   └── release.yml           ← Release gate + SBOM
│   ├── dependabot.yml            ← Aggiornamenti automatici
│   └── branch-protection.md      ← Istruzioni setup manuale
├── docs/
│   ├── CLAIMS.md                 ← 10 claim critici
│   ├── CLAIM_MATRIX.md           ← Matrice claim→test→evidenza
│   ├── RELEASE_GATE_POLICY.md    ← Policy versionata
│   └── SECURITY_SETUP.md         ← Setup security GitHub
├── tests/
│   ├── test_smoke.py             ← Smoke test automatico
│   ├── test_rollback.sh          ← Rollback test (manuale/staging)
│   └── test_backup_restore.sh    ← Backup/restore test
├── scripts/
│   ├── verify_claims.sh          ← Esegue tutti i test della matrix
│   └── cost_estimator.sh         ← Stima costo API (se AI)
├── .gitleaks.toml                ← Config secret scanning locale
└── Makefile                      ← Target production readiness
```

### 7.2 — Makefile targets production readiness

```makefile
# Production readiness targets — aggiungi al Makefile esistente

scan-secrets:     ## Secret scanning locale con gitleaks
    gitleaks detect --source . --config .gitleaks.toml

scan-deps:        ## Audit dipendenze per CVE
    composer audit --no-interaction          # PHP
    # pip-audit                              # Python
    # npm audit --audit-level=critical       # Node

scan-code:        ## Analisi statica codice
    # PHP: phpstan analyze --level=8 src/
    # Python: bandit -r src/
    # Node: eslint src/ --ext .js,.ts

test-smoke:       ## Smoke test (< 60s)
    pytest tests/test_smoke.py -q --timeout=60

test-rollback:    ## Rollback test (su staging)
    bash tests/test_rollback.sh

test-restore:     ## Backup/restore test (su staging)
    bash tests/test_backup_restore.sh

verify-claims:    ## Verifica tutti i claim della CLAIM_MATRIX
    bash scripts/verify_claims.sh

sbom:             ## Genera SBOM CycloneDX
    # PHP:   composer make-bom --output-format=JSON --output-file=sbom.json
    # Python: cyclonedx-bom -o sbom.json
    # Node:  npx @cyclonedx/cyclonedx-npm --output-file sbom.json

security-all:     ## Esegue tutti i check di sicurezza
    $(MAKE) scan-secrets
    $(MAKE) scan-deps
    $(MAKE) scan-code
    echo "✅ Security scan completato"

release-check:    ## Verifica tutti i gate prima del tag
    $(MAKE) test
    $(MAKE) lint
    $(MAKE) security-all
    $(MAKE) test-smoke
    $(MAKE) verify-claims
    @if [ -f coord/HALT.md ]; then \
        echo "❌ HALT.md presente — release bloccata"; exit 1; \
    fi
    echo "✅ Tutti i gate PASS — pronto per il tag"
```

---

## FASE 8 — PRODUCTION READINESS REPORT

Produce `reports/production_readiness_report.md`:

```markdown
# Production Readiness Report — [Nome Progetto] v[X]

## Verdict
**[PRODUCTION_READY | NEEDS_WORK | BLOCKED]**
[Motivazione in 2 righe]

## Gate status
| Gate | Check | Esito | Note |
|------|-------|-------|------|
| G1 | Test suite | ✅/❌ | N/TOT PASS |
| G2 | Lint | ✅/❌ | |
| G3 | Secret scan | ✅/❌ | |
| G4 | Dep audit | ✅/❌ | CVE critiche: N |
| G5 | Smoke test | ✅/❌ | Tempo: Xs |
| G6 | Claim verification | ✅/❌ | N/10 claim PASS |
| G7 | Nessun HALT | ✅/❌ | |
| G8 | SBOM generato | ✅/❌ | |

## Claim status
| Claim | Test | Esito |
|-------|------|-------|
| C01 — [titolo] | TEST_PERF_01 | ✅/❌ |
| ... | ... | ... |

## Problemi aperti
| Priorità | Problema | Gate | Azione |
|----------|----------|------|--------|
| P0 | [problema bloccante] | G? | [azione] |
| P1 | [problema importante] | G? | [azione] |

## CVE rilevate
| Package | CVE | CVSS | Stato |
|---------|-----|------|-------|
| [pkg] | CVE-XXXX | 9.8 | 🔴 Critica — blocca |
| [pkg] | CVE-XXXX | 7.2 | 🟡 Alta — piano mitigazione |

## Raccomandazioni
[Cosa fare prima di andare in produzione]

## Come rieseguire
make release-check
```

---

## REGOLE FONDAMENTALI

```
1. I CLAIM VENGONO PRIMA DEI TEST
   → Prima scrivi cosa prometti, poi scrivi il test che lo verifica
   → Non il contrario — i test senza claim non hanno significato

2. OGNI CLAIM HA UN TEST — SENZA ECCEZIONI
   → Claim senza test = promessa non verificabile = non vale nulla
   → Se non riesci a scrivere il test: il claim è mal formulato

3. SECRET SCAN LOCALE PRIMA DI OGNI PUSH
   → make scan-secrets prima di git push
   → La push protection GitHub è un backup — non la prima linea

4. SBOM AD OGNI RELEASE — SEMPRE
   → Il cliente / la compliance hanno diritto di sapere
     cosa c'è nelle dipendenze del sistema che usano

5. ROLLBACK TESTATO = ROLLBACK AFFIDABILE
   → Un rollback non testato è come un'uscita di sicurezza
     che non si apre — la scopri quando è tardi

6. LA POLICY È NEL REPO — NON NELLE TESTE
   → RELEASE_GATE_POLICY.md versionato = modificabile
     solo con PR approvata — non a voce

7. DEPENDABOT ATTIVO = MENO LAVORO MANUALE
   → Non aspettare di scoprire CVE da un pentest
   → PR automatiche settimanali sono gestibili
   → CVE non gestite per settimane non lo sono

8. HALT.md BLOCCA TUTTO
   → Se esiste coord/HALT.md: nessuna release
   → Zero eccezioni per secret e PII
```

---

## PROMPT DI AVVIO

### Caso A — Progetto sviluppato con pipeline agentiva

```
╔══════════════════════════════════════════════════════════╗
║  PROMPT PER CLAUDE CODE / QWEN / AGENTE DI CODICE       ║
╠══════════════════════════════════════════════════════════╣
║  Leggi [percorso]/PROMPT_B_production_readiness_v1.md   ║
║  e applicalo al progetto in [percorso progetto].         ║
║                                                          ║
║  Leggi prima:                                            ║
║    - SPEC.md (SLO e contratti I/O — base per i claim)   ║
║    - coord/STATE.json (stato sviluppo)                   ║
║    - coord/BOARD.md (task completati)                    ║
║                                                          ║
║  Stack: [PHP/Laravel | Python | Node]                   ║
║  CI target: GitHub Actions                               ║
║  Livello: [interno | cliente | pubblico]                 ║
║                                                          ║
║  Produci in sequenza:                                    ║
║  1. docs/CLAIMS.md (10 claim da SPEC.md)                ║
║  2. docs/CLAIM_MATRIX.md                                 ║
║  3. .github/workflows/ci.yml                            ║
║  4. .github/workflows/release.yml                       ║
║  5. .github/dependabot.yml                              ║
║  6. docs/RELEASE_GATE_POLICY.md                         ║
║  7. tests/test_smoke.py                                 ║
║  8. tests/test_rollback.sh                              ║
║  9. tests/test_backup_restore.sh                        ║
║  10. reports/production_readiness_report.md             ║
║                                                          ║
║  Esegui make release-check alla fine.                   ║
╚══════════════════════════════════════════════════════════╝
```

### Caso B — Progetto esistente standalone

```
╔══════════════════════════════════════════════════════════╗
║  PROMPT PER CLAUDE CODE / QWEN / AGENTE DI CODICE       ║
╠══════════════════════════════════════════════════════════╣
║  Leggi [percorso]/PROMPT_B_production_readiness_v1.md   ║
║  e applicalo al progetto in [percorso progetto].         ║
║                                                          ║
║  Non ho SPEC.md — scrivi i claim analizzando:           ║
║    - Il comportamento del sistema                        ║
║    - Il README                                           ║
║    - I test esistenti (se presenti)                     ║
║                                                          ║
║  Stack: [stack del progetto]                            ║
║  Livello: [interno | cliente | pubblico]                ║
║                                                          ║
║  Priorità: inizia da G3 (secret scan) e G4 (dep audit) ║
║  Poi produci docs/CLAIMS.md e docs/CLAIM_MATRIX.md      ║
╚══════════════════════════════════════════════════════════╝
```

---

## QUANDO USARE QUESTO PROMPT

```
PROMPT_B_production_readiness (questo):
→ Prima di consegnare un progetto a un cliente reale
→ Prima di rendere pubblico un SaaS
→ Quando vuoi CI/CD protetta e branch protection
→ Quando devi dimostrare affidabilità verificabile
→ Quando hai bisogno di SBOM per compliance
→ Quando vuoi testare il rollback prima di averne bisogno
→ Standalone su qualsiasi progetto esistente

NON serve se:
→ È solo un prototipo interno monouso
→ Non andrà mai in produzione
→ È un esercizio / portfolio senza utenti reali
  (in quel caso: usa solo smoke test + secret scan)
```

---

*Prompt B — Production Readiness Engineer v1.0*
*Compatibile con pipeline agentiva v2*
*Agent-agnostic: Claude Code · Qwen · Goose · Cursor*
*Usa dopo: P07 + PROMPT_B_dataset + PROMPT_B_notebook*
*Chiude il ciclo: idea → sviluppo → dataset → documentazione → produzione affidabile*
