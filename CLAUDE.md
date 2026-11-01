# doliField – indicazioni per Claude

Modulo esterno Dolibarr per i dati di campo: sensori, meteo, satellite e videocamere, collegati ad appezzamenti, fabbricati e silos di doliFarm. Fa parte della suite doliFarm insieme a doliFarm (fascicolo e anagrafiche), doliTrace (tracciabilità e quaderno di campagna), doliAgroPass e doliGas.

**Stato: in sviluppo.** La versione 0.1.0 ha solo la pagina dimostrativa `dolifieldindex.php` con valori di esempio scritti nel codice: nessun sensore è collegato.

## Regole fondamentali

- **Il core di Dolibarr non si modifica mai.** Tutto passa da classi, hook, trigger e file del modulo.
- **Il nome della cartella è `dolifield`, tutto minuscolo.** Clonare con `git clone git@github.com:doliFarm/doliField.git dolifield`: su Linux una cartella `doliField` impedisce l'attivazione.
- **doliField dipende da doliFarm** (`$this->depends` contiene `modDolifarm`): i dati di campo appartengono agli appezzamenti, ai fabbricati e ai silos di doliFarm, di cui doliField non crea le tabelle. Il codice che usa doliTrace va protetto con `isModEnabled('dolitrace')`.
- **Ambiti:** doliField raccoglie e mostra i dati di campo (dispositivi, letture, soglie, meteo, indici satellitari). Le registrazioni del quaderno di campagna restano in doliTrace; le anagrafiche in doliFarm.

## Ambienti e compatibilità

| | Sviluppo (Docker sulla WS) | VPS (Linux, Docker, test) |
|---|---|---|
| URL | http://localhost:8089 | — |
| Dolibarr | 24.0.2 | 24.0.2 |
| PHP | 8.4 | 8.4 / 8.5 |
| Database | MySQL 8.4 | MySQL 8.x |

- **Unica piattaforma di sviluppo e di prova: l'istanza Docker** in `C:\dolibarr\docker-php8` (vedi il README lì). Monta `C:\dolibarr\www\dolibarr\htdocs`; database e documenti sono suoi. L'installazione DoliWamp della WS (porta 8088, PHP 7.4, MariaDB) **non si usa più** (deciso il 01/11/2026): niente prove, reinizializzazioni o controlli lì.

- Requisito minimo: **Dolibarr 23**, come doliFarm.
- PHP **8.2 o successivo** (`$this->phpmin = array(8, 2)`), provato su 8.4; la VPS può passare a 8.5:
  - la sintassi di PHP 8 è ammessa (`match`, `?->`, `str_contains()`, ...); non serve più la compatibilità con PHP 7;
  - niente deprecazioni PHP 8.x: dichiarare sempre le proprietà delle classi, non passare `null` alle funzioni native, nullable esplicito (`?Tipo $x = null`), niente `curl_close()`.
- SQL per **MySQL 8**, provato nell'istanza Docker. Restare su SQL semplice e portabile (niente funzioni solo MySQL se c'è un'alternativa standard), così da non escludere MariaDB. Nomi di tabelle e colonne in minuscolo: sulla VPS i nomi sono case-sensitive.
- Verifica della sintassi (Git Bash): `cd /c/dolibarr/docker-php8 && MSYS_NO_PATHCONV=1 docker compose exec -T web php -l /var/www/html/custom/<modulo>/<file>`.
- Script di prova da riga di comando nel container: `MSYS_NO_PATHCONV=1 docker compose exec -T -w /var/www/html web php < script.php` (percorsi `/var/www/html/...`).

## Licenza e intestazioni

- Licenza del modulo: **AGPL v3 o successiva** (`COPYING`, `LICENSE`). Titolare: Luigi Grillo - doliFarm.com <info@dolifarm.com>.
- Ogni file nuovo ha l'intestazione AGPL con `Copyright (C) <anno> Luigi Grillo - doliFarm.com <info@dolifarm.com>`.
- I file derivati dai modelli di ModuleBuilder con il copyright di autori di Dolibarr mantengono la loro intestazione **GPL v3 o successiva** (testo in `COPYING-GPL-3.0`): non cambiarla, aggiungere solo la riga del titolare.

## Struttura

- `core/modules/modDoliField.class.php`: descrittore (numero 500030, permesso `read`, menu delle aree).
- `dolifieldindex.php`: pagina dell'area (dimostrativa).
- `admin/`: setup (ancora senza opzioni) e informazioni. `lib/dolifield.lib.php`: schede del setup.
- `langs/{it_IT,en_US,fr_FR}/dolifield.lang`: le chiavi sono in inglese; il testo italiano va solo nei valori.

## Database e SQL

- Dolibarr esegue `sql/llx_*.sql`, poi `sql/llx_*.key.sql`, poi `sql/data*.sql`, poi `sql/update_*.sql` **a ogni attivazione del modulo**. Gli script devono essere **idempotenti e non distruttivi**: `CREATE TABLE IF NOT EXISTS`, niente `DROP TABLE`, `INSERT` che non duplicano.
- Le letture dei sensori crescono in fretta: prevedere aggregati giornalieri e una regola di conservazione fin dalla prima tabella.
- Gli indici unici sugli oggetti multi-entity devono includere `entity`.
- Prefisso: `llx_` nei file `.sql`; nel codice `MAIN_DB_PREFIX` o `$this->db->prefix()`.

## Convenzioni di codice

- Seguire lo stile del codice Dolibarr circostante: tab, `GETPOST()`/`GETPOSTINT()` per l'input, `$this->db->escape()` e cast `(int)` nelle query, `dol_syslog()` per i log.
- Permessi con `$user->hasRight('dolifield', ...)`, non `$user->rights->...`.
- Costanti con `getDolGlobalString()`/`getDolGlobalInt()` e `dolibarr_set_const()`, non `$conf->global->...`.
- Ogni pagina controlla permessi ed entity (`restrictedArea()` o equivalente).
- Chiavi di servizi esterni (meteo, satellite) nel setup, cifrate come la chiave EPPO di doliFarm.
- Niente file di debug, niente scritture in `/tmp`, niente log a livello WARNING per messaggi informativi.

## Git e deploy

- Lavoro su `develop`; `main` è ciò che va sulla VPS. I tag sono firmati: li crea l'utente.
- Fine riga LF nel repository (`.gitattributes`).
- I documenti di progettazione stanno in `../dolifarm/docs/` (esclusa da git).
