# Sistem Penilaian Kinerja Petugas Penagihan Pajak

Aplikasi ini sudah diimplementasikan ulang sebagai **native PHP** dan ditujukan untuk **XAMPP + PHP 8.2** tanpa framework.

## Fitur yang tersedia

Aplikasi mengikuti alur pada README awal:

1. **Login** admin / petugas.
2. **Dashboard** monitoring proses utama.
3. **Master Data**
   - Data Pegawai
   - Data Wajib Pajak
   - Jenis Pekerjaan
   - Bobot KPI
   - Setting Insentif
   - Wilayah
   - Target Periode
4. **Penugasan**
   - Admin membuat target, memilih wajib pajak, pegawai, jenis pekerjaan, deadline, dan menyimpan penugasan.
5. **Pelaksanaan Lapangan**
   - Petugas menerima tugas.
   - Petugas input GPS.
   - Sistem memvalidasi radius terhadap lokasi wajib pajak / wilayah.
   - Petugas upload foto lokasi, input hasil, upload bukti, dan submit.
6. **Verifikasi**
   - Admin review kelengkapan hasil.
   - Admin dapat menyetujui atau menolak hasil.
7. **Perhitungan KPI**
   - Menghitung target, realisasi, penagihan berhasil, verval, GPS valid, ketepatan waktu, kelengkapan dokumen.
   - Normalisasi dan perhitungan skor berdasarkan bobot KPI.
8. **Ranking Pegawai** berdasarkan skor KPI.
9. **Insentif**
   - Input dana mingguan dan bulanan.
   - Perhitungan proporsi berdasarkan skor KPI.
10. **Laporan**
   - Dashboard monitoring
   - Laporan KPI
   - Laporan penagihan
   - Laporan GPS
   - Laporan insentif
   - Grafik mingguan
   - Grafik bulanan
   - Export PDF
   - Export Excel

---

## Struktur folder

```text
monev-pegawai/
├── app/
│   ├── AppService.php
│   ├── Auth.php
│   ├── Database.php
│   ├── bootstrap.php
│   ├── controllers.php
│   ├── helpers.php
│   └── views.php
├── config/
│   ├── app.php
│   └── config.sample.php
├── database/
│   ├── schema.sql
│   └── seed.sql
├── public/
│   ├── assets/style.css
│   └── uploads/
├── .htaccess
├── index.php
└── README.md
```

- `index.php` adalah **front controller / routing entrypoint** untuk Apache XAMPP.
- `.htaccess` me-rewrite request ke `index.php` agar URL tetap rapi saat dijalankan di Apache.
- `config/config.sample.php` adalah template konfigurasi database.

---

## Persiapan XAMPP + PHP 8.2

### 1. Letakkan project di htdocs

Letakkan folder repository ini ke:

```text
C:\xampp\htdocs\monev-pegawai
```

Akses aplikasi nantinya melalui:

```text
http://localhost/monev-pegawai/
```

### 2. Aktifkan Apache dan MySQL

Buka XAMPP Control Panel lalu jalankan:
- Apache
- MySQL

### 3. Buat database dan import SQL

Buka `phpMyAdmin`, lalu jalankan SQL berikut secara berurutan:

1. Import `/database/schema.sql`
2. Import `/database/seed.sql`

Atau lewat terminal XAMPP/MySQL:

```bash
mysql -u root -p < C:/xampp/htdocs/monev-pegawai/database/schema.sql
mysql -u root -p monev_pegawai < C:/xampp/htdocs/monev-pegawai/database/seed.sql
```

> Default template menggunakan database `monev_pegawai`, user `root`, password kosong.

### 4. Konfigurasi database

Copy file template:

```text
config/config.sample.php -> config/config.php
```

Lalu sesuaikan isi koneksi database jika konfigurasi MySQL lokal berbeda.

Contoh default XAMPP lokal:

```php
<?php
return [
    'app' => [
        'name' => 'Sistem Penilaian Kinerja Petugas Penagihan Pajak',
        'timezone' => 'Asia/Jakarta',
        'upload_max_size_mb' => 5,
        'default_radius_meters' => 500,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'monev_pegawai',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
```

Jika tidak membuat `config/config.php`, aplikasi tetap akan membaca `config/config.sample.php` sebagai default lokal.

### 5. Pastikan permission upload bisa ditulis

Folder berikut harus bisa ditulis Apache/PHP:

```text
public/uploads/field-photos
public/uploads/evidence
```

### 6. Buka aplikasi

Akses:

```text
http://localhost/monev-pegawai/
```

---

## Akun login seed

### Admin
- Username: `admin`
- Password: `password123`

### Petugas
- Username: `petugas`
- Password: `password123`

Akun tambahan:
- Username: `petugas2`
- Password: `password123`

---

## Alur uji cepat

### Sebagai admin
1. Login sebagai `admin`.
2. Cek menu **Master Data** untuk melihat data contoh.
3. Buka **Penugasan** untuk melihat tugas yang sudah disiapkan.
4. Buka **Verifikasi** untuk review tugas yang sudah disubmit.
5. Buka **Perhitungan KPI** lalu klik hitung ulang.
6. Buka **Ranking Pegawai**.
7. Buka **Insentif**, simpan dana, lalu hitung insentif.
8. Buka **Laporan** lalu coba export PDF / Excel.

### Sebagai petugas
1. Login sebagai `petugas`.
2. Buka **Tugas Saya / Pelaksanaan**.
3. Terima tugas dengan status `assigned`.
4. Buka **Input Hasil**.
5. Isi GPS yang masih dalam radius lokasi wajib pajak.
6. Upload foto lokasi dan file bukti.
7. Submit hasil untuk diverifikasi admin.

---

## Validasi GPS

- Sistem membandingkan GPS yang diinput petugas dengan koordinat wajib pajak.
- Jika koordinat wajib pajak kosong, sistem memakai koordinat pusat wilayah.
- Submit hanya berhasil jika jarak petugas masih di dalam `radius_meters` wilayah / default aplikasi.

---

## Catatan implementasi

- Tanpa framework (bukan Laravel / CI).
- Menggunakan **PDO MySQL** dan kompatibel dengan **PHP 8.2**.
- Export Excel menggunakan CSV yang dapat dibuka di Excel.
- Export PDF menggunakan generator PDF sederhana bawaan aplikasi.
- Upload bukti mendukung `jpg`, `jpeg`, `png`, `pdf`, `doc`, `docx`.

---

## Ringkasan alur sistem

```text
START
│
▼
Login
│
▼
Dashboard
│
├─────────────── Master Data
│
├─────────────── Penugasan
│                     │
│                     ▼
│              Pelaksanaan Lapangan
│                     │
│                     ▼
│                Verifikasi
│                     │
│                     ▼
│              Perhitungan KPI
│                     │
│                     ▼
│              Ranking Pegawai
│                     │
│                     ▼
│                 Insentif
│                     │
│                     ▼
└──────────────► Laporan
                      │
                      ▼
                  SELESAI
```
