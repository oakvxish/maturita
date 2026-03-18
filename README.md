# Gestionale RBAC per Saloni

Gestionale web per saloni di bellezza progettato per semplificare l’operatività quotidiana, organizzare il lavoro del team e controllare l’accesso alle funzionalità tramite **RBAC** (*Role-Based Access Control*).

L’obiettivo del progetto è offrire una base solida, chiara ed estendibile per la gestione di attività come appuntamenti, clienti, servizi, magazzino e utenti interni, mantenendo una separazione netta dei permessi in base al ruolo.

---

## Panoramica

Questo gestionale nasce per rispondere a esigenze concrete di un salone:

- gestione dei clienti
- gestione appuntamenti
- organizzazione interna del personale
- controllo accessi per ruolo
- gestione base del magazzino
- riduzione degli errori operativi
- maggiore ordine nei flussi quotidiani

Il sistema utilizza un modello **RBAC**, così ogni utente vede e utilizza solo le sezioni che gli competono.

---

## Obiettivi del progetto

- Fornire un’interfaccia semplice e rapida da usare in salone
- Limitare l’accesso alle funzionalità sensibili
- Migliorare la sicurezza dei dati
- Rendere il sistema facilmente scalabile
- Mantenere una struttura backend elementare ma solida
- Preparare il terreno per future integrazioni

---

## Funzionalità principali

### 1. Gestione utenti
- creazione utenti interni
- login protetto
- gestione ruoli
- abilitazione/disabilitazione account
- modifica credenziali e dati profilo

### 2. RBAC - Role-Based Access Control
Ogni utente appartiene a un ruolo che definisce i permessi disponibili.

Esempi di restrizioni:
- solo il proprietario può creare nuovi account
- i dipendenti vedono solo le aree operative consentite
- le sezioni amministrative non sono accessibili a chi non ha permessi adeguati

### 3. Gestione clienti
- anagrafica clienti
- contatti principali
- note operative
- storico base delle attività
- ricerca rapida

### 4. Gestione appuntamenti
- creazione appuntamenti
- modifica e aggiornamento stato
- visualizzazione agenda
- associazione cliente / servizio / operatore
- supporto ai flussi quotidiani del salone

### 5. Gestione servizi
- catalogo servizi
- durata stimata
- prezzo
- note operative interne

### 6. Magazzino
- inserimento prodotti
- quantità disponibili
- aggiornamento stock
- controllo disponibilità
- gestione minima ma utile per l’operatività

### 7. Dashboard operativa
- accesso rapido alle funzioni principali
- visione sintetica del lavoro giornaliero
- sezioni mostrate in base al ruolo

---

## Architettura logica

Il sistema è diviso in moduli funzionali separati, in modo da mantenere il codice leggibile e facilmente manutenibile:

- **Autenticazione**
- **Autorizzazione RBAC**
- **Utenti**
- **Clienti**
- **Appuntamenti**
- **Servizi**
- **Magazzino**
- **Dashboard**

Il controllo permessi viene eseguito lato backend prima di consentire l’accesso a pagine, azioni o record sensibili.

---

## Modello RBAC

Il cuore del progetto è il sistema di autorizzazione basato su ruoli.

### Principio
Un utente:
- ha un account
- appartiene a un ruolo
- eredita i permessi previsti da quel ruolo

### Vantaggi
- maggiore sicurezza
- minore rischio di errori operativi
- interfaccia più pulita
- gestione utenti più ordinata
- controllo centralizzato degli accessi

---

## Ruoli previsti

### Proprietario
Ruolo con privilegi completi.

Permessi tipici:
- gestione utenti
- creazione account dipendenti
- accesso completo a clienti, appuntamenti, servizi e magazzino
- configurazioni di sistema
- visualizzazione delle sezioni amministrative

### Dipendente
Ruolo operativo con accesso limitato.

Permessi tipici:
- visualizzazione e gestione delle sezioni autorizzate
- consultazione clienti
- gestione appuntamenti assegnati o operativi
- uso di specifiche aree del gestionale
- nessun accesso alla gestione account o ad aree riservate

> I permessi reali dipendono dalla configurazione implementata nel progetto.

---

## Sicurezza

Il progetto è pensato con una logica orientata alla sicurezza applicativa di base.

### Misure consigliate / implementate
- validazione input lato server
- escaping output
- controllo sessione
- verifica permessi a ogni accesso sensibile
- password hashate
- accesso a sezioni protette solo dopo autenticazione
- separazione tra autenticazione e autorizzazione
- query parametrizzate per evitare SQL injection

### Nota importante
Nascondere un pulsante nel frontend **non equivale** a proteggere una funzione.  
Ogni controllo critico deve essere verificato lato backend.

---

## Stack tecnico

> Personalizza questa sezione in base al tuo progetto reale.

Esempio stack:

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Autenticazione:** sessioni server-side
- **Autorizzazione:** RBAC custom
- **Ambiente:** Apache / Nginx / XAMPP / hosting LAMP

---

## Struttura del progetto

Esempio di organizzazione cartelle:

```text
/
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
├── config/
│   └── config.php
├── includes/
│   ├── auth.php
│   ├── permissions.php
│   ├── db.php
│   └── helpers.php
├── modules/
│   ├── users/
│   ├── clients/
│   ├── appointments/
│   ├── services/
│   └── inventory/
├── dashboard/
├── login.php
├── logout.php
└── index.php```