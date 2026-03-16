create database if not exists salone_db character set utf8mb4 collate utf8mb4_unicode_ci;
use salone_db;

create table clienti (
  id int auto_increment primary key,
  nome varchar(100) not null,
  cognome varchar(100) not null,
  telefono varchar(30),
  email varchar(150),
  tag varchar(50) default 'normale',
  note text,
  whatsapp_chat_id varchar(60),
  created_at timestamp default current_timestamp
);

create table servizi (
  id int auto_increment primary key,
  nome varchar(100) not null,
  categoria varchar(60),
  durata_minuti int default 60,
  prezzo decimal(8,2) default 0.00
);

create table appuntamenti (
  id int auto_increment primary key,
  cliente_id int not null,
  servizio_id int not null,
  data_ora datetime not null,
  stato enum('attesa','confermato','completato','annullato') default 'attesa',
  note text,
  created_at timestamp default current_timestamp,
  foreign key (cliente_id) references clienti(id) on delete cascade,
  foreign key (servizio_id) references servizi(id) on delete cascade
);

create table magazzino (
  id int auto_increment primary key,
  nome varchar(100) not null,
  categoria varchar(60),
  quantita int default 0,
  soglia_minima int default 5,
  unita varchar(20) default 'pz',
  prezzo_acquisto decimal(8,2) default 0.00
);

create table whatsapp_log (
  id int auto_increment primary key,
  cliente_id int,
  chat_id varchar(60),
  messaggio text,
  tipo enum('reminder','broadcast','coupon','manuale') default 'manuale',
  stato enum('inviato','errore','in_coda') default 'in_coda',
  inviato_il timestamp default current_timestamp,
  foreign key (cliente_id) references clienti(id) on delete set null
);

create table impostazioni (
  chiave varchar(60) primary key,
  valore text
);

insert into impostazioni (chiave, valore) values
  ('nome_salone', 'Il Mio Salone'),
  ('iva', '22'),
  ('valuta', 'EUR'),
  ('whatsapp_api_url', ''),
  ('whatsapp_api_token', ''),
  ('reminder_24h', '1'),
  ('reminder_2h', '1'),
  ('tema', 'chiaro');

insert into servizi (nome, categoria, durata_minuti, prezzo) values
  ('Taglio capelli', 'capelli', 45, 25.00),
  ('Piega', 'capelli', 30, 20.00),
  ('Colorazione', 'capelli', 90, 60.00),
  ('Manicure', 'unghie', 45, 25.00),
  ('Pedicure', 'unghie', 60, 30.00),
  ('Pulizia viso', 'viso', 60, 40.00),
  ('Ceretta', 'corpo', 30, 20.00);

insert into clienti (nome, cognome, telefono, email, tag) values
  ('Maria', 'Rossi', '3331234567', 'maria@email.it', 'vip'),
  ('Laura', 'Bianchi', '3449876543', 'laura@email.it', 'normale'),
  ('Anna', 'Verdi', '3557654321', '', 'normale');
