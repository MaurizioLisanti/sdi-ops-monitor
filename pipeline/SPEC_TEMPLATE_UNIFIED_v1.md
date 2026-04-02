# SPEC — [Nome Progetto]
<!-- 
  SPEC_TEMPLATE_UNIFIED v1
  Usato da: PROMPT_01, PROMPT_02, PROMPT_03, PROMPT_A_github, PROMPT_A_universal
  Sezioni [REQUIRED] → obbligatorie per la pipeline
  Sezioni [OPT] → opzionali, usa [ASSUNTO] se mancano
  Non rimuovere sezioni — lascia "N/A" se non applicabile
-->

---

## [REQUIRED] Overview
<!-- 3-5 righe. Cosa fa, per chi, quale beneficio immediato. -->
[descrizione]

---

## [REQUIRED] Stack Tecnologico
<!-- Questo campo sblocca TUTTO il resto della pipeline -->
- **Linguaggio principale**: [es. PHP / Python / Node]
- **Framework**: [es. Laravel 11 / FastAPI / Express]
- **Database**: [es. MySQL / PostgreSQL / SQLite / N/A]
- **Test framework**: [es. PHPUnit / pytest / jest]
- **Dipendenze chiave**: [lista]
- **Runtime / Deploy**: [local / Docker / cloud / VPS]
- **OS target**: [Linux / macOS / Windows / cross]

---

## [REQUIRED] Primary Workflow
<!-- Min 3 step. Formato: trigger → azione → output atteso -->
1. [trigger] → [azione] → [output]
2. ...
3. ...
4. ...
5. ...

---

## [REQUIRED] MVP Acceptance Criteria (M0)
<!-- Min 3. Ogni criterio PASS/FAIL verificabile con demo o script in ≤ 30 min -->
- [ ] [criterio 1 — PASS/FAIL]
- [ ] [criterio 2 — PASS/FAIL]
- [ ] [criterio 3 — PASS/FAIL]
- [ ] [criterio 4 — PASS/FAIL]
- [ ] [criterio 5 — PASS/FAIL]

---

## [REQUIRED] Non-Goals (scope esplicito)
<!-- Min 3. Protezione contro scope creep. Sii specifico. -->
- NON farà: [...]
- NON farà: [...]
- NON farà: [...]
- NON farà: [...]
- NON farà: [...]

---

## [OPT] Target Users
- **Primary**: [ruolo] — [frequenza] — [livello tecnico]
- **Secondary**: [ruolo] — [frequenza] — [livello tecnico]

---

## [OPT] Inputs
- **Sources**: [es. form / API / file / webhook]
- **Formats**: [es. JSON / CSV / PDF / immagine]
- **Example**:
```json
{
  "example": "inserisci esempio realistico"
}
```

---

## [OPT] Outputs
- **Destinations**: [es. dashboard / email / PDF / API response]
- **Formats**: [es. JSON / HTML / PDF]
- **Example**:
```json
{
  "example": "inserisci esempio realistico"
}
```

---

## [OPT] Integrazioni Esterne
<!-- API di terze parti, servizi, webhook -->
- [integrazione 1] — [scopo] — [limiti noti]
- [integrazione 2]

---

## [OPT] Security & PII
- **Dati PII gestiti**: [SÌ/NO — lista se SÌ]
- **Confidentiality level**: [pubblico / interno / confidenziale]
- **Compliance**: [GDPR / altro / N/A] [DA VERIFICARE se necessario]

---

## [OPT] Observability
- **Log format**: [JSON strutturato SÌ/NO]
- **correlation_id**: [SÌ/NO]
- **Audit trail**: [SÌ/NO]
- **Metrics chiave**: [es. latenza p95, error rate]

---

## [OPT] Performance / Cost Budget
- **Latency p95**: [es. < 2s]
- **Volume atteso**: [es. 1000 req/giorno]
- **Costo per chiamata stimato**: [DA VERIFICARE]

---

## [OPT] Architettura
<!-- Per progetti esistenti (Prompt A). Descrizione dei layer. -->
[descrizione architettura — diagramma ASCII se utile]

---

## [OPT] Entry Point / Flusso Principale
<!-- Per progetti esistenti. Come si avvia il sistema. -->
[descrizione entry point]

---

## [OPT] Aree di Rischio
<!-- Per progetti esistenti. File/aree critiche da trattare con cura. -->
### Alto Rischio
- [file/modulo] — [perché]

### Medio Rischio
- [file/modulo] — [perché]

---

## [OPT] Debito Tecnico Esistente
<!-- Per progetti esistenti. Compilato da Prompt A. -->
### Critico
- [problema] — [file]

### Alto
- [problema] — [file]

### Medio
- [problema] — [file]

---

## [OPT] Milestones
- **M0** (demoabile): [criteri PASS/FAIL]
- **M1** (usable): [criteri PASS/FAIL]
- **M2** (prod-lite): [criteri PASS/FAIL]

---

## [OPT] Risks / Unknowns
<!-- Scala: Probabilità (A=Alta / M=Media / B=Bassa) × Impatto (A/M/B) -->
- R1 [P:? / I:?]: [...] → Mitigazione: [...]
- R2 [P:? / I:?]: [...] → Mitigazione: [...]

---

## Assunzioni
<!-- Numerate. Ogni [An] referenziata nei file dove è usata. -->
- [A1] ...
- [A2] ...

---

## Note per l'Agente
<!-- Cose importanti per chi lavora sul codice. Trappole da evitare. -->
[note]

---

## [REQUIRED] Obiettivo Modifiche
<!-- Obbligatorio per progetti esistenti (Prompt A). -->
<!-- Per progetti nuovi: coincide con One-liner. -->
[descrizione obiettivo]

---

## [REQUIRED] Vincoli
<!-- Cosa non si può toccare o modificare. -->
- [vincolo 1]
- [vincolo 2]

---
<!-- Fine SPEC_TEMPLATE_UNIFIED v1 -->
<!-- Versione: 1.0 — Compatibile con pipeline agentiva v2 -->
