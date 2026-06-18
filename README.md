# Event Hub

Applicazione Laravel 12 per pubblicare eventi, gestire adesioni, lista d'attesa, notifiche, calendario, feedback e backoffice staff.

## Requisiti

- PHP 8.2+ con estensioni: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
- Composer.
- XAMPP con MySQL/MariaDB per sviluppo locale.
- Node/npm non sono necessari: il progetto usa asset statici versionati in `public/static`.

Su questa macchina Composer e disponibile come:

```powershell
php C:\tmp\composer.phar
```

## Setup locale

```powershell
php C:\tmp\composer.phar install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Il database locale predefinito e `event_hub_local` su MySQL/MariaDB di XAMPP. Non eseguire `php artisan migrate:fresh` dopo aver importato un dump reale: cancellerebbe tabelle e dati importati.

## Import database MySQL con XAMPP

1. Avvia MySQL dal pannello XAMPP.
2. Copia il dump `.sql` in una posizione locale, per esempio `C:\tmp\dump.sql`.
3. Importa il dump:

```powershell
.\scripts\import-mysql-dump.ps1 -DumpPath "C:\tmp\dump.sql"
```

Se il dump contiene dati non ordinati rispetto alle foreign key, riprova con:

```powershell
.\scripts\import-mysql-dump.ps1 -DumpPath "C:\tmp\dump.sql" -DisableForeignKeyChecks
```

Per verificare engine, charset, tabelle principali e relazioni:

```powershell
cmd /c """C:\xampp\mysql\bin\mysql.exe"" -u root --default-character-set=utf8mb4 event_hub_local < ""database\mysql\verify_import.sql"""
```

La connessione Laravel e configurata in `.env` con `DB_CONNECTION=mysql`, `DB_DATABASE=event_hub_local`, `DB_USERNAME=root` e password vuota, come da installazione XAMPP standard.

## Comandi utili

```powershell
php artisan test
php artisan route:list
php artisan schedule:list
php artisan notifications:send-deadline-reminders
php C:\tmp\composer.phar validate --strict
php C:\tmp\composer.phar audit --locked
```

## Asset frontend

Il layout carica solo:

- `public/static/vendor/bootstrap.min.css`
- `public/static/css/app.css`
- `public/static/vendor/jquery.min.js`
- `public/static/vendor/bootstrap.bundle.min.js`
- `public/static/js/app.js`

I file sono serviti con cache-busting basato su `filemtime()`. Non usare Vite/npm per questa versione del progetto.

## Produzione

Prima del deploy:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tuo-dominio.example
APP_TIMEZONE=Europe/Rome
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
```

Eseguire:

```powershell
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

Configurare il cron Laravel:

```text
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

## Checklist QA

- Guest: home, lista eventi, calendario, login, registrazione, API pubbliche JSON/XML.
- Utente: iscrizione, lista d'attesa, modifica nota, annullamento, feedback su eventi completati.
- Staff: CRUD eventi, cambio stato, gestione iscritti, storico modifiche.
- Sicurezza: `APP_DEBUG=false`, DB e backup fuori dal deploy pubblico, CSRF attivo sulle route web.
- Performance: verificare liste eventi con molti record e conteggi iscrizioni precaricati.
