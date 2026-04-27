use salone_db;

/*
patch minima per installazioni vecchie.
aggiunge le tabelle usate dal recupero password e dall'audit,
senza toccare i dati gia esistenti.
*/

create table if not exists password_reset_requests (
  id int unsigned not null auto_increment,
  user_id int unsigned not null,
  reset_token varchar(64) not null,
  current_code varchar(6) not null,
  code_expires_at datetime not null,
  request_expires_at datetime not null,
  status enum('attiva','completata','scaduta','annullata') not null default 'attiva',
  created_at datetime not null default current_timestamp,
  updated_at datetime not null default current_timestamp on update current_timestamp,
  used_at datetime default null,
  primary key (id),
  unique key ux_password_reset_token (reset_token),
  key ix_password_reset_user_status (user_id, status),
  key ix_password_reset_request_expires (request_expires_at),
  constraint fk_password_reset_user
    foreign key (user_id) references userdata (id)
    on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists user_recovery_codes (
  id int unsigned not null auto_increment,
  user_id int unsigned not null,
  code_hash varchar(255) not null,
  generated_by_user_id int unsigned default null,
  created_at datetime not null default current_timestamp,
  used_at datetime default null,
  revoked_at datetime default null,
  primary key (id),
  key ix_user_recovery_codes_user (user_id),
  key ix_user_recovery_codes_generated_by (generated_by_user_id),
  key ix_user_recovery_codes_active (user_id, used_at, revoked_at),
  constraint fk_user_recovery_codes_user
    foreign key (user_id) references userdata (id)
    on delete cascade,
  constraint fk_user_recovery_codes_generated_by
    foreign key (generated_by_user_id) references userdata (id)
    on delete set null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists password_reset_audit (
  id int unsigned not null auto_increment,
  user_id int unsigned default null,
  event_type varchar(80) not null,
  event_status varchar(40) not null,
  detail text,
  ip_address varchar(45) default null,
  user_agent varchar(255) default null,
  created_at datetime not null default current_timestamp,
  primary key (id),
  key ix_password_reset_audit_user (user_id),
  key ix_password_reset_audit_created (created_at),
  constraint fk_password_reset_audit_user
    foreign key (user_id) references userdata (id)
    on delete set null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
