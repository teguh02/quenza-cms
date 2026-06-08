# Quenza CMS

Security-first CMS engine untuk ekosistem Quenza.

## Local Manual Testing

Mode default development adalah `sqlite`.

### 1. Siapkan environment

Salin `.env.example` menjadi `.env`.

Konfigurasi default yang dipakai:

```env
DB_DRIVER=sqlite
DB_SQLITE_PATH=storage/database/quenza.sqlite
```

Jika ingin bootstrap admin saat seeding, isi juga:

```env
QZ_ADMIN_NAME="Admin Quenza"
QZ_ADMIN_EMAIL="admin@example.com"
QZ_ADMIN_PASSWORD="password-kuat"
```

### 2. Jalankan smoke test lokal

```bash
composer smoke
```

Script ini akan:

- reset database aktif
- jalankan seluruh migration
- jalankan seeder inti
- validasi tabel inti dan seed data dasar

### 3. Command manual yang sering dipakai

```bash
composer db:status
composer db:migrate
composer db:fresh
composer serve
```

Atau langsung lewat CLI:

```bash
php bin/qz migrate:status
php bin/qz migrate --seed
php bin/qz migrate:fresh --seed
php -S 127.0.0.1:8000 -t public
```

### 4. Verifikasi database SQLite dengan sqlite3

```bash
sqlite3 storage/database/quenza.sqlite
```

Command yang berguna:

```sql
.headers on
.mode column
.tables
.schema qz_users
SELECT * FROM qz_roles;
SELECT option_name, option_value FROM qz_options;
SELECT scope, package, migration, batch FROM qz_migrations;
```

### 5. Manual HTTP testing dengan php -S dan curl

Jalankan server lokal:

```bash
composer serve
```

Contoh alur register publik:

```bash
curl.exe -c tmp-cookies.txt http://127.0.0.1:8000/register -o register.html
```

Ambil token `_token` dari HTML, lalu submit:

```bash
curl.exe -b tmp-cookies.txt -c tmp-cookies.txt -X POST http://127.0.0.1:8000/register \
  --data-urlencode "_token=TOKEN_DARI_HTML" \
  --data-urlencode "full_name=Manual Tester" \
  --data-urlencode "email=manual.tester@example.com" \
  --data-urlencode "password=Testing123!" \
  --data-urlencode "password_confirmation=Testing123!"
```

Lalu verifikasi dashboard:

```bash
curl.exe -b tmp-cookies.txt http://127.0.0.1:8000/admin
```

Verifikasi DB:

```sql
SELECT id, full_name, email, status, last_login_at FROM qz_users;
SELECT ur.user_id, r.slug FROM qz_user_roles ur INNER JOIN qz_roles r ON r.id = ur.role_id;
```

Uji brute force login:

1. logout dulu
2. kirim password salah berulang kali dengan token baru setiap request POST
3. cek hasil di SQLite

```sql
SELECT email, failed_login_attempts, locked_until FROM qz_users;
SELECT action, ip_address, identifier, attempts, locked_until FROM qz_auth_attempts;
```

Jika konten belum dibuat dari admin, artikel publik bisa diinjeksi manual via DB helper lalu diverifikasi dengan slug:

```bash
curl.exe http://127.0.0.1:8000/articles/artikel-manual-quenza
```

### 6. Mode Docker default

Default `docker-compose.yml` juga memakai `sqlite`.

```bash
docker compose up -d
```

Jika ingin MySQL, aktifkan profile `mysql` dan ubah `.env`:

```bash
docker compose --profile mysql up -d
```

Lalu set:

```env
DB_DRIVER=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=quenza_cms
DB_USERNAME=quenza
DB_PASSWORD=quenza_secret
```

## Automated Testing

### PHPUnit

Quenza CMS sekarang memiliki suite test otomatis untuk:

- unit test `Security`, `Translator`, dan `SessionManager`
- integration test layer database
- integration test registrasi publik dan login/logout
- integration test route publik dan dashboard

Suite ini dipakai sebagai **white-box testing** untuk memastikan behavior internal (service, database layer, auth flow, dan routing) tetap konsisten.

Command yang tersedia:

```bash
composer test
composer test:unit
composer test:integration
composer test:ci
```

Default test database adalah SQLite terpisah:

```env
DB_DRIVER=sqlite
DB_SQLITE_PATH=storage/database/quenza_phpunit.sqlite
```

### Playwright E2E

Playwright dipakai sebagai **black-box testing** untuk memverifikasi alur dari sudut pandang user/browser.

Command yang tersedia:

```bash
npm ci
npm run e2e
```

Skenario utama yang diuji:

- first run diarahkan ke wizard instalasi (`/install`)
- instalasi SQLite sampai selesai (`language -> database -> site/admin`)
- setelah instalasi, homepage publik terbuka
- artikel hasil seeding installer tampil di homepage dan detail artikel dapat dibuka
- validasi negatif untuk field instalasi site/admin yang tidak valid

### GitHub Actions

Workflow CI tersedia di:

```text
.github/workflows/tests.yml
```

Job yang dijalankan:

- `sqlite-tests` untuk smoke test dan PHPUnit dengan SQLite
- `e2e-playwright` untuk browser black-box test menggunakan Playwright + artifact report/log
- `mysql-tests` untuk regression test pada MySQL
