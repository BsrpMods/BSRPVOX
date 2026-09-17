# BSRP Vox — License & Account Management Server

Backend sistem lisensi dan manajemen akun untuk aplikasi **BSRP Vox**. Dibangun menggunakan PHP + MySQL dengan validasi lisensi berbasis RSA signature untuk mendukung verifikasi online maupun offline.

---

## ✨ Fitur Utama

- 🔐 **Autentikasi Pengguna** — Login berbasis username/password dengan session token 30 hari
- 🖥️ **Hardware Binding** — Setiap akun dibatasi ke jumlah perangkat tertentu berdasarkan plan
- 🔑 **Tanda Tangan Lisensi RSA** — Payload lisensi ditandatangani dengan RSA-SHA256 untuk verifikasi offline
- 🌐 **WebView2 Support** — Respons HTML khusus untuk aplikasi desktop C# yang menggunakan WebView2
- 🛠️ **Admin Dashboard** — Panel web untuk membuat, memantau, menangguhkan akun, dan mereset perangkat
- 📋 **Audit Log** — Semua aktivitas login dan perangkat tercatat di database

---

## 📋 Persyaratan Server

| Persyaratan | Versi Minimum |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Ekstensi PHP | `pdo_mysql`, `openssl`, `json` |
| Web Server | Apache / Nginx / LiteSpeed |

---

## 🚀 Langkah Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/username/vox-server.git
cd vox-server
```

### 2. Setup Konfigurasi Database

Salin file template konfigurasi dan isi dengan kredensial Anda:

```bash
cp config/db.php.example config/db.php
```

Edit `config/db.php`:

```php
$db_host = "localhost";
$db_user = "your_db_username";
$db_pass = "your_db_password";
$db_name = "your_db_name";
```

### 3. Import Skema Database

Import skema `database_v2.sql` ke MySQL Anda:

```bash
mysql -u your_db_username -p your_db_name < database_v2.sql
```

Ini akan membuat semua tabel dan mengisi data awal:
- Plan default (1 Device & 2 Devices)
- Akun admin default (`admin` / `admin123`) — **Segera ganti setelah login pertama!**

### 4. Generate Kunci RSA

Jalankan script helper yang sudah disediakan untuk men-generate pasangan kunci RSA:

```bash
php keys/generate_keys.php
```

Atau menggunakan OpenSSL langsung:

```bash
openssl genrsa -out keys/private_key.pem 2048
openssl rsa -in keys/private_key.pem -pubout -out keys/private_key.pem.pub
```

> ⚠️ **Jangan generate ulang kunci yang sudah ada di produksi.** Ini akan membuat semua tanda tangan lisensi yang sudah terbit menjadi tidak valid.

### 5. Ganti Password Admin

Buka browser dan akses:

```
https://yourdomain.com/admin/ganti-admin.php
```

Ubah username dan password admin, lalu **hapus file `ganti-admin.php`** setelah selesai.

---

## 📁 Struktur Proyek

```
Server/
├── .gitignore
├── README.md
├── database_v2.sql              # Skema database aktif
├── database.sql                 # Skema versi lama (referensi)
│
├── config/
│   ├── db.php                   # ⛔ Tidak di-commit (kredensial DB)
│   └── db.php.example           # ✅ Template aman
│
├── keys/
│   ├── private_key.pem          # ⛔ Tidak di-commit (kunci RSA privat)
│   ├── private_key.pem.pub      # ⛔ Tidak di-commit (kunci RSA publik)
│   ├── private_key.pem.example  # ✅ Panduan placeholder
│   └── generate_keys.php        # ✅ Script helper generate kunci
│
└── public_html/
    ├── admin/
    │   ├── index.html           # Admin dashboard (SPA)
    │   └── ganti-admin.php      # Utility ganti kredensial admin
    └── api/
        ├── login.php            # POST — Login pengguna
        ├── logout.php           # POST — Logout pengguna
        ├── verify.php           # POST — Verifikasi sesi aktif
        ├── activate.php         # POST — Aktivasi lisensi (versi lama)
        └── admin/
            ├── auth.php         # Middleware autentikasi admin
            ├── login.php        # GET — Login admin
            ├── create-license.php   # GET — Buat akun baru
            ├── list-licenses.php    # GET — Daftar semua akun
            ├── revoke-license.php   # GET — Suspend akun
            └── reset-device.php     # GET — Reset perangkat akun
```

---

## 🔌 API Endpoint Reference

### Endpoint Publik (Digunakan Aplikasi Klien)

#### `POST /api/login.php`
Login pengguna dan mendaftarkan perangkat.

**Request body (JSON atau form):**
```json
{
  "username": "user_xxxx",
  "password": "pass_xxxx",
  "device_id": "UNIQUE-HARDWARE-ID",
  "device_name": "Nama Komputer"
}
```

**Response sukses:**
```json
{
  "success": true,
  "account": { "id": 1, "username": "user_xxxx", "plan": "BSRP Vox 1 Device" },
  "device": { "id": 5, "status": "ACTIVE" },
  "license": {
    "status": "ACTIVE",
    "payload": "{...}",
    "signature": "base64..."
  },
  "session": { "credential": "session_hash_hex" }
}
```

> Tambahkan `?webview2` ke URL untuk mendapatkan respons HTML (untuk aplikasi C# WebView2).

---

#### `POST /api/verify.php`
Memverifikasi apakah sesi masih aktif dan perangkat tidak diblokir.

**Request body (JSON):**
```json
{
  "session": "session_hash_hex",
  "device_id": "UNIQUE-HARDWARE-ID"
}
```

---

#### `POST /api/logout.php`
Mencabut sesi pengguna.

**Request body (JSON):**
```json
{
  "session": "session_hash_hex"
}
```

---

### Endpoint Admin (Dilindungi Session Admin)

Semua endpoint admin memerlukan login terlebih dahulu melalui:

```
GET /api/admin/login.php?username=admin&password=yourpassword
```

| Endpoint | Metode | Deskripsi |
|---|---|---|
| `/api/admin/login.php?action=check` | GET | Cek status login admin |
| `/api/admin/login.php?action=logout` | GET | Logout admin |
| `/api/admin/create-license.php?plan_id=1` | GET | Buat akun baru |
| `/api/admin/list-licenses.php` | GET | Tampilkan semua akun |
| `/api/admin/revoke-license.php?id={id}` | GET | Suspend akun |
| `/api/admin/reset-device.php?id={id}` | GET | Reset perangkat akun |

---

## 🛡️ Catatan Keamanan

- **Private Key RSA** — Simpan dengan aman, jangan pernah di-commit ke Git
- **`config/db.php`** — Berisi kredensial database, sudah di-exclude via `.gitignore`
- **`ganti-admin.php`** — Hapus file ini setelah digunakan untuk mencegah penyalahgunaan
- **Admin login via GET** — Untuk produksi, pertimbangkan mengubah ke POST dengan CSRF token
- **CORS** — Header `Access-Control-Allow-Origin: *` saat ini terbuka lebar; batasi ke domain spesifik di produksi

---

## 📦 Database

Skema database terdiri dari 6 tabel:

| Tabel | Deskripsi |
|---|---|
| `plans` | Paket berlangganan (jumlah maks perangkat) |
| `users` | Akun pengguna |
| `devices` | Perangkat yang terdaftar per pengguna |
| `sessions` | Token sesi aktif |
| `activation_logs` | Log semua aktivitas login/aktivasi |
| `admin_users` | Akun admin panel |

---

## 📄 Lisensi

Proyek ini bersifat privat. Hak cipta sepenuhnya milik tim BSRP.
