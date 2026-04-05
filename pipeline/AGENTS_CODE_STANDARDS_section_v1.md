# AGENTS.md — Sezione aggiuntiva: CODE STANDARDS
# ═══════════════════════════════════════════════════════════
# DOVE INSERIRE: in AGENTS.md dopo la sezione "Error model"
# e prima di "Emergency stop"
# ═══════════════════════════════════════════════════════════
# Questa sezione va anche aggiunta in PROMPT_02_repo_seed_generator_v3.md
# nella parte A) AGENTS.md → "Contenuto invariato rispetto a v2"
# sostituendo con "Contenuto invariato rispetto a v2 + aggiungi CODE STANDARDS"
# ═══════════════════════════════════════════════════════════

---

## Code Standards (obbligatori — letti da Executor prima di scrivere codice)

Queste regole si applicano a OGNI file toccato da OGNI agente.
Non sono opzionali. Non hanno eccezioni.
L'Executor le legge in pre-flight al punto 2 (lettura AGENTS.md).
Il Reviewer le verifica in checklist [P1] Code quality.

---

### Lingua — regola assoluta

```
CODICE:
→ Inglese obbligatorio in tutto il codice
→ Variabili, funzioni, classi, costanti → inglese
→ Commenti inline → inglese
→ Docstring / PHPDoc / JSDoc → inglese
→ Messaggi di log → inglese
→ Messaggi di errore → inglese
→ Nomi di file e cartelle → inglese (snake_case o kebab-case)

VIETATO:
→ Commenti in italiano, spagnolo o qualsiasi altra lingua
→ Variabili o funzioni con nomi non inglesi
→ Messaggi di log misti (parte inglese, parte italiano)

SEGNALA NEL HANDOFF:
→ Se trovi codice esistente con commenti non in inglese
   → documenta in "Rischi / TODO residui"
   → NON modificare fuori Allowed Paths per fixarlo
   → Crea nota: [DOC_LANG: file, riga]
```

---

### Commenti inline — cosa scrivere

```
SCRIVI il PERCHÉ — non il COSA:

✅ CORRETTO — spiega una decisione non ovvia:
   // Skip soft-deleted records — hard delete handled by nightly job
   // Retry up to 3 times: external API rate limit is 100 req/min
   // Cast to int: legacy DB returns numeric fields as strings
   // Bypass cache intentionally — endpoint requires real-time data

❌ SBAGLIATO — descrive il codice già leggibile:
   // Loop through users
   // Increment counter
   // Return the result
   // Check if user exists

SCRIVI commenti per sezioni logiche complesse:
   // --- Input validation ---
   // --- Build response payload ---
   // --- Notify downstream services ---

SCRIVI TODO con owner e contesto:
   // TODO(@username): replace with async queue when vol > 10k/day
   // FIXME: race condition if two workers process same order simultaneously

NON SCRIVERE:
→ Commenti che replicano il nome della funzione
→ Commenti che spiegano sintassi ovvia del linguaggio
→ Commenti vuoti o placeholder ("// ...")
→ Codice commentato senza spiegazione del perché è lì
```

---

### Docstring / PHPDoc / JSDoc — standard per stack

Obbligatorio su OGNI funzione / metodo / classe pubblica.

```
PHP / Laravel → PHPDoc:
/**
 * [Descrizione breve — cosa fa, non come.]
 *
 * [Descrizione estesa se necessaria — contesto, comportamento
 *  speciale, casi limite. Ometti se la breve è sufficiente.]
 *
 * @param  [Tipo]   $[nome]   [Descrizione del parametro]
 * @param  [Tipo]   $[nome]   [Descrizione. Default: [valore]]
 * @return [Tipo]             [Descrizione del valore restituito]
 * @throws [ExceptionClass]   [Quando viene lanciata]
 *
 * @example
 * $result = $this->methodName($param1, $param2);
 * // → expected output or state change
 */

Python → Google Style:
def function_name(param1: Type, param2: Type = None) -> ReturnType:
    """Brief description — what it does, not how.

    Extended description if needed. Explain context, edge cases,
    or non-obvious behavior. Omit if brief is sufficient.

    Args:
        param1: Description of param1.
        param2: Description. Defaults to None.

    Returns:
        Description of what is returned.

    Raises:
        ExceptionClass: When and why this is raised.

    Example:
        >>> result = function_name(value1, value2)
        >>> print(result)
        'expected output'
    """

Node / TypeScript → JSDoc:
/**
 * Brief description — what it does, not how.
 *
 * Extended description if needed.
 *
 * @param {Type} paramName - Description of parameter
 * @param {Type} [optionalParam='default'] - Optional parameter
 * @returns {ReturnType} Description of returned value
 * @throws {ErrorClass} When and why this is thrown
 *
 * @example
 * const result = await functionName(param1, param2);
 * console.log(result); // → expected output
 */
```

---

### Quando la docstring è obbligatoria vs opzionale

```
OBBLIGATORIA (P1 — il Reviewer verifica):
→ Ogni funzione / metodo pubblico
→ Ogni classe pubblica
→ Ogni interfaccia o tipo pubblico
→ Ogni costante non auto-esplicativa
→ Ogni modulo / file con logica business

OPZIONALE (non verificata dal Reviewer):
→ Funzioni private < 5 righe con nome auto-esplicativo
→ Getter / setter banali (es. getId(), setName())
→ Override semplici che non aggiungono comportamento

MAI (rumore — rimuovi se presente):
→ Docstring che ripetono solo il nome della funzione
→ Docstring vuote o con placeholder non compilati
→ @param senza descrizione ("@param $id")
```

---

### Naming conventions per stack

```
PHP / Laravel:
→ Classes:     PascalCase        UserController, PaymentService
→ Methods:     camelCase         processPayment(), getUserById()
→ Variables:   camelCase         $userId, $orderTotal
→ Constants:   UPPER_SNAKE_CASE  MAX_RETRY_COUNT, DEFAULT_TIMEOUT
→ Files:       PascalCase.php    UserController.php
→ DB columns:  snake_case        created_at, user_id

Python:
→ Classes:     PascalCase        UserController, PaymentService
→ Functions:   snake_case        process_payment(), get_user_by_id()
→ Variables:   snake_case        user_id, order_total
→ Constants:   UPPER_SNAKE_CASE  MAX_RETRY_COUNT, DEFAULT_TIMEOUT
→ Files:       snake_case.py     user_controller.py
→ Private:     _leading_underscore _validate_input()

Node / TypeScript:
→ Classes:     PascalCase        UserController, PaymentService
→ Functions:   camelCase         processPayment(), getUserById()
→ Variables:   camelCase         userId, orderTotal
→ Constants:   UPPER_SNAKE_CASE  MAX_RETRY_COUNT, DEFAULT_TIMEOUT
→ Files:       kebab-case.js     user-controller.js
→ Interfaces:  IPascalCase       IUserService (TypeScript)

REGOLA UNIVERSALE:
→ Nomi descrittivi — mai abbreviazioni non standard
→ getUserById() ✅   getUsrById() ❌   get() ❌
→ isUserActive() ✅  check() ❌        flag() ❌
→ MAX_RETRY_COUNT ✅ MRC ❌            max ❌
```

---

### Checklist pre-consegna per l'Executor

Prima di produrre il HANDOFF, verifica:

```
[ ] Tutti i commenti nel codice modificato sono in inglese?
[ ] Ogni nuova funzione pubblica ha docstring completa?
[ ] La docstring segue lo standard dello stack (PHPDoc/Google/JSDoc)?
[ ] I nomi di variabili e funzioni sono in inglese e descrittivi?
[ ] I commenti spiegano il PERCHÉ e non il COSA?
[ ] Nessun commento inutile (che replica il codice)?
[ ] I TODO hanno owner e contesto?

SE UN CHECK FALLISCE:
→ Correggilo prima di produrre HANDOFF
→ Se il file è fuori Allowed Paths: documenta in Rischi/TODO residui
   con tag [DOC_LANG] o [DOC_MISSING] — non modificare fuori scope
```

---

### Checklist per il Reviewer

Aggiunta alla sezione [P1] Code quality di PROMPT_05:

```
[P1] Code documentation standards:
  ✓ Tutti i commenti nel diff sono in inglese?
  ✓ Ogni nuova funzione pubblica ha docstring?
  ✓ La docstring segue lo standard dello stack?
  ✓ I nomi sono in inglese e descrittivi?
  ✓ I commenti spiegano PERCHÉ non COSA?
  → FAIL → verdict: NEEDS_CHANGES (P1)
     Fix richiesto: [lista file e funzioni non conformi]
```

---

# ═══════════════════════════════════════════════════════════
# ISTRUZIONI DI INTEGRAZIONE
# ═══════════════════════════════════════════════════════════
#
# 1. AGENTS.md (generato dal Repo Seed per ogni nuovo progetto)
#    → Aggiungi questa sezione dopo "Error model"
#      e prima di "Emergency stop"
#    → Il Repo Seed Generator v3 deve includerla sempre,
#      indipendentemente dalla complexity (LOW/MED/HIGH)
#
# 2. PROMPT_02_repo_seed_generator_v3.md
#    → Nella sezione A) AGENTS.md sostituisci:
#      "Contenuto invariato rispetto a v2."
#      con:
#      "Contenuto invariato rispetto a v2 + sezione CODE STANDARDS
#       (includi sempre — anche per complexity LOW)"
#
# 3. PROMPT_04_executor_agent_v3.md
#    → Nel PRE-FLIGHT al punto 3 (Leggi AGENTS.md) aggiungi:
#      "→ leggi e applica la sezione CODE STANDARDS"
#
# 4. PROMPT_05_reviewer_agent_v3.md
#    → Nel check [P1] Code quality aggiungi i 5 check
#      della sezione "Checklist per il Reviewer" sopra
#
# 5. Per progetti ESISTENTI senza AGENTS.md:
#    → Crea AGENTS.md con almeno questa sezione CODE STANDARDS
#    → Non serve la struttura completa di AGENTS.md
#      se il progetto non usa la pipeline completa
# ═══════════════════════════════════════════════════════════
