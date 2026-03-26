# Spiegazione progetto gestionale salone

## Obiettivo della revisione

Questa versione punta a rendere il gestionale più usabile in un contesto reale di salone:

- meno attrito nei form
- azioni principali più visibili
- tabelle più leggibili
- schermate con gerarchia più chiara
- empty state utili quando mancano dati
- codice semplice in HTML, CSS e PHP elementari
- niente JavaScript nelle funzioni operative

## Best practice applicate

Le scelte principali applicate al progetto sono queste:

1. **Riduzione del carico cognitivo nei form**
   - pochi campi obbligatori
   - ordine logico dei campi
   - helper text solo dove serve
   - inserimento diviso in blocchi leggibili

2. **Supporto ai task principali delle tabelle**
   - trovare record
   - confrontare righe
   - agire direttamente dalla riga
   - non obbligare ad aprire pagine intermedie inutili

3. **Dashboard orientata all’operatività**
   - metriche subito in alto
   - shortcut rapidi ai task più frequenti
   - prossimi appuntamenti e servizi più richiesti senza rumore visivo

4. **Filtri chiari e reversibili**
   - ricerca e reset sempre presenti
   - conteggio risultati visibile
   - filtri separati dai dati

5. **Empty state non vuoti**
   - non solo “nessun dato”
   - indicazione di cosa succederà
   - invito all’azione quando serve

6. **Stile coerente ma mantenibile**
   - una sola grammatica visuale per card, campi, tabelle, badge e pulsanti
   - tipografia più elegante ma semplice da gestire
   - profondità leggera senza effetti pacchiani

---

## Struttura logica del progetto

### 1. Accesso e contesto salone
Il sistema lavora in sessione. Dopo il login, ogni utente opera dentro un `salone_id` attivo.

Questo permette di:
- separare i dati per salone
- supportare utenti collegati a più saloni
- mostrare solo le sezioni consentite in base al ruolo

### 2. Moduli principali
I moduli centrali sono:
- dashboard
- appuntamenti
- clienti
- servizi
- impostazioni
- storico cliente
- utenti salone
- analitiche
- magazzino
- prenotazione pubblica

### 3. Layout condiviso
Il layout comune è concentrato in:
- `layout_top.php`
- `layout_bottom.php`

Qui vengono gestiti:
- shell grafica
- menu
- topbar
- stile globale
- componenti comuni

---

## Pattern ricorrenti spiegati una volta sola

### Query filtrate con `WHERE`
In più file il sistema costruisce una condizione SQL base, di solito con `salone_id`, e poi aggiunge filtri opzionali.

Schema tipico:
- base: record del salone corrente
- filtro ricerca: nome, cognome, telefono, email
- filtro stato o data: agenda

Questo pattern serve a mantenere il codice elementare ma comunque flessibile.

### Lettura risultati con `fetch_assoc()` e `fetch_all()`
Il progetto usa `mysqli` in modo semplice:
- `fetch_assoc()` per singole righe o contatori
- `fetch_all(MYSQLI_ASSOC)` per liste complete

### POST per mutazioni, GET per ricerca
Regola usata nel progetto:
- `POST` per creare, modificare, eliminare
- `GET` per cercare, filtrare e precompilare la vista

Questa separazione rende il comportamento più prevedibile.

### Messaggi di esito
Molte pagine usano due variabili:
- `$messaggio`
- `$errore`

Servono a dare feedback immediato dopo una submit, senza introdurre logiche più complesse.

### Controllo permessi
Il controllo ruoli è centralizzato in `auth.php`.

La logica è:
- tutti vedono solo alcune pagine
- il proprietario ne vede di più
- se non hai il permesso, vieni rimandato alla dashboard con messaggio

---

## File per file

## `db.php`
### Cosa fa
Apre la connessione MySQL con `mysqli` e imposta `utf8mb4`.

### Blocchi principali
- definizione credenziali
- creazione connessione
- charset
- stop immediato in caso di errore

### Perché è importante
È il punto unico da cui passa il database.

---

## `auth.php`
### Cosa fa
Protegge le pagine private e ricostruisce il contesto utente/salone.

### Blocchi principali
- avvio sessione
- controllo login
- funzioni di supporto sui ruoli
- caricamento saloni associati all’utente
- switch del salone attivo
- verifica accesso alla pagina corrente

### Funzioni chiave
- `normalizza_ruolo()`
- `carica_saloni_utente()`
- `trova_salone_corrente()`
- `utente_puo_vedere_pagina()`
- `require_proprietario()`
- `estrai_flash()`

### Perché conta
È il punto che evita accessi impropri e tiene coerente il multisalone.

---

## `layout_top.php`
### Cosa fa
Costruisce l’interfaccia comune del gestionale.

### Blocchi principali
- lettura del tema dalle impostazioni
- menu laterale
- topbar con link alla prenotazione pubblica
- CSS globale dell’interfaccia
- classi riusabili per metriche, card, tabelle, pulsanti, form, badge

### Migliorie introdotte
- gerarchia visiva più chiara
- profondità più credibile con ombre leggere
- tipografia più curata
- componenti comuni uniformati
- metriche e quick action riutilizzabili in più pagine

### Perché conta
Riduce duplicazioni e rende coerente tutto il gestionale.

---

## `layout_bottom.php`
### Cosa fa
Chiude il markup del layout principale.

### Perché esiste
Serve a separare apertura e chiusura del layout condiviso.

---

## `index.php`
### Cosa fa
È la dashboard del gestionale.

### Blocchi principali
- conteggi rapidi: clienti, appuntamenti del giorno, attese, incasso mese
- query prossimi appuntamenti
- query servizi più richiesti
- query orari più affollati
- rendering della dashboard

### Migliorie introdotte
- metriche subito in alto
- quick actions per i task principali
- empty state quando mancano appuntamenti o dati
- leggibilità maggiore delle sezioni

### Perché conta
La dashboard deve aiutare a decidere, non solo a decorare.

---

## `clienti.php`
### Cosa fa
Gestisce creazione, modifica, ricerca, filtro ed eliminazione dei clienti.

### Blocchi principali
- gestione `POST` per crea/modifica/elimina
- costruzione filtri di ricerca
- caricamento lista clienti
- caricamento cliente in modifica
- rendering form e tabella

### Migliorie introdotte
- metriche per tag cliente
- form più ordinato
- ricerca separata dall’inserimento
- tabella con contatti raggruppati meglio
- azioni rapide in riga
- empty state utile

### Perché conta
Nel lavoro reale la scheda cliente deve essere veloce da trovare e facile da aggiornare.

---

## `appuntamenti.php`
### Cosa fa
Gestisce inserimento e aggiornamento dell’agenda.

### Blocchi principali
- creazione appuntamento
- cambio stato
- eliminazione
- filtri per stato e data
- ricerca cliente nel flusso di creazione
- caricamento servizi e clienti
- rendering tabella appuntamenti

### Migliorie introdotte
- ricerca cliente nel flusso di creazione senza JavaScript
- metriche di stato in alto
- filtri più chiari
- modifica stato direttamente nella tabella
- informazioni meglio raggruppate per riga

### Perché conta
L’agenda è il cuore operativo: ogni click superfluo qui pesa.

---

## `servizi.php`
### Cosa fa
Gestisce il listino servizi del salone.

### Blocchi principali
- creazione/modifica servizio
- eliminazione
- caricamento listino
- eventuale precompilazione in modifica

### Migliorie introdotte
- metriche sintetiche del listino
- form più leggibile
- spiegazione operativa del perché tenere coerenti durata e prezzo
- tabella più ordinata

### Perché conta
Prezzo e durata guidano prenotazione, pianificazione e analitiche.

---

## `impostazioni.php`
### Cosa fa
Gestisce le impostazioni generali del salone.

### Blocchi principali
- salvataggio impostazioni in tabella `impostazioni`
- aggiornamento nome salone in sessione e in tabella `saloni`
- costruzione del link pubblico di prenotazione

### Migliorie introdotte
- struttura più semplice
- focus su impostazioni davvero utili
- link pubblico sempre visibile
- chiarimento esplicito sull’uso dell’id salone

### Perché conta
È il punto di controllo dell’identità del salone e del link pubblico.

---

## `prenota.php`
### Cosa fa
Mostra la pagina pubblica del salone e gestisce il lookup tramite `id` del salone.

### Blocchi principali
- lettura `id` dalla query string
- caricamento dati salone e servizi
- rendering pagina prenotazione pubblica

### Nota
Lo slug non è più il riferimento principale nel flusso pubblico: ora il link usa l’id del salone.

---

## `prenotazione.php`
### Cosa fa
Gestisce la registrazione della prenotazione proveniente dal lato pubblico.

### Ruolo nel flusso
Fa da ponte tra interfaccia pubblica e dati agenda/clienti.

---

## `login.php`
### Cosa fa
Gestisce la schermata di accesso.

### Blocchi principali
- form login
- visual minimale coerente col resto del progetto
- ingresso nel gestionale tramite sessione

---

## `registrazione.php`
### Cosa fa
Gestisce la creazione iniziale o registrazione del salone/account.

### Nota importante
Lo slug può restare solo come compatibilità interna, ma il flusso pubblico usa l’id.

---

## `storico_cliente.php`
### Cosa fa
Mostra la scheda cliente e il suo storico appuntamenti.

### Perché conta
È la vista utile quando vuoi capire frequenza, spesa e note su un singolo cliente.

---

## `analitiche.php`
### Cosa fa
Espone dati sintetici o report del salone.

### Ruolo
È la sezione che dovrebbe trasformare i dati operativi in segnali decisionali.

---

## `magazzino.php`
### Cosa fa
Gestisce stock o articoli del salone.

### Ruolo
Serve a separare la parte agenda/clienti dalla parte materiali e consumo.

---

## `utenti_salone.php`
### Cosa fa
Gestisce utenti collegati al salone e ruoli associati.

### Ruolo
È fondamentale nei contesti con proprietario e dipendenti.

---

## `index.html`
### Cosa fa
Pagina vetrina o landing statica collegata al progetto.

### Ruolo
Può essere usata come presentazione esterna o demo.

---

## `logout.php`
### Cosa fa
Chiude la sessione utente.

---

## `reminder_cron.php`
### Cosa fa
Punto utile per automatismi pianificati, per esempio promemoria o controlli periodici.

### Ruolo
Anche senza WhatsApp, resta il posto giusto per logiche batch o reminder futuri.

---

## `whatsapp.php`
### Stato attuale
Il flusso WhatsApp è stato rimosso dall’esperienza principale richiesta. Se il file resta nel progetto, va considerato fuori dal percorso operativo corrente.

---

## Perché questa versione è più usabile

In pratica migliora perché:

- mette in alto le informazioni che servono davvero
- separa inserimento, ricerca e lettura
- permette azioni in meno click
- mantiene i campi semplici
- riduce schermate inutili
- usa componenti coerenti in tutte le pagine
- resta facile da leggere e modificare anche lato codice

---

## Limiti attuali

Questa è una base semplice e mantenibile. Restano possibili evoluzioni future, per esempio:

- validazioni server-side più profonde
- paginazione per elenchi lunghi
- ordinamenti sulle tabelle
- stati più granulari per appuntamenti
- agenda per operatore
- calcolo disponibilità reale per slot
- cronologia modifiche
- export e stampa

---

## Conclusione

Il progetto ora è impostato come un gestionale più concreto:

- semplice da mantenere
- più leggibile
- più vicino ai task reali del salone
- meno ornamentale e più operativo

L’idea guida è stata questa: **meno complessità tecnica visibile, più chiarezza operativa**.


## Aggiornamento 2026-03-26
- spacing generale aumentato tra sezioni, card, toolbar e blocchi principali
- rimossi gli ultimi residui di effetto glass/liquid nelle schermate principali e auth
- aggiunto flow password dimenticata: password_forgot.php, reset_password.php, password_reset_code.php, password_reset_lib.php
- aggiunta migration SQL: migrations/2026_03_26_password_reset.sql
