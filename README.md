# Setup Native PHP (XAMPP + PHP 8.2)

## Jalankan aplikasi

1. Buat database dan tabel:
   - Import `/sql/schema.sql` ke MySQL (phpMyAdmin atau CLI).
2. Isi data dummy dashboard (opsional tapi direkomendasikan untuk testing cepat):
   - Import `/sql/seed_dummy_dashboard.sql`.
3. Jalankan dari XAMPP (Apache + MySQL aktif), lalu akses:
   - `http://localhost/monev-pegawai/public/index.php`

> Konfigurasi DB dibaca dari environment variable (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`). Default: `127.0.0.1:3306`, DB `monev_pegawai`, user `root`, password kosong.

## Patch Follow-up (GPS, Dashboard, KPI)

### 1) GPS auto capture (petugas input hasil)

- Form `public/input_hasil.php` otomatis meminta geolocation browser (`navigator.geolocation.getCurrentPosition`) saat halaman dibuka.
- Latitude/longitude dibuat **readonly** dan diisi otomatis (tanpa input manual).
- Tombol submit dikunci sampai koordinat valid didapat.
- Jika izin lokasi ditolak/tidak tersedia, UI menampilkan pesan jelas dan submit diblokir.
- Server-side di `public/save_hasil.php` menolak submit jika latitude/longitude kosong atau di luar rentang valid.
- Metadata capture yang disimpan: `captured_at`, `accuracy` (jika tersedia), `user_agent`, `ip_address`.

### 2) Dashboard monitoring + data dummy

- Dashboard `public/index.php` menampilkan ringkasan, tabel monitoring, dan ranking KPI dari query join data tugas/hasil/KPI.
- Query sudah disusun agar record existing tetap muncul (LEFT JOIN untuk hasil/KPI).
- Jika data kosong, komponen menampilkan fallback text (tidak error/blank).
- Untuk langsung uji dashboard berisi data, import seed: `/sql/seed_dummy_dashboard.sql`.

### 3) KPI calculation process

Implementasi KPI ada di `src/kpi.php` dan dipanggil saat simpan hasil (`public/save_hasil.php`) serta rekalkulasi massal (`public/recalculate_kpi.php`).

Formula final score (0-100):

- `final_score = sum(component_score * weight) / sum(weight)`

Komponen dan normalisasi:

- `target_achievement = min(realisasi / target * 100, 100)`
- `collection_success = min(penagihan_berhasil / realisasi * 100, 100)`
- `verification_rate = 100 jika verval_complete, selain itu 0`
- `gps_validity = 100 jika gps_valid, selain itu 0`
- `timeliness = 100 jika on_time, selain itu 0`
- `document_completeness = clamp(nilai, 0..100)`

Default bobot (di tabel `kpi_weights`):

- target_achievement: 0.25
- collection_success: 0.25
- verification_rate: 0.15
- gps_validity: 0.10
- timeliness: 0.10
- document_completeness: 0.15

Kategori KPI:

- `A (Sangat Baik)` >= 85
- `B (Baik)` >= 70
- `C (Cukup)` >= 55
- `D (Perlu Pembinaan)` < 55

Hasil KPI tampil di:

- Dashboard monitoring (`public/index.php`) pada kolom skor/kategori
- Ranking KPI petugas (`public/index.php`)

---

# Sistem Penilaian Kinerja Petugas Penagihan Pajak

## 1. Login

```
START
   │
   ▼
Login
   │
   ▼
Validasi User
   │
   ├── Tidak Valid
   │      │
   │      └── Kembali ke Login
   │
   └── Valid
          │
          ▼
      Dashboard
```

---

# 2. Dashboard

Dashboard merupakan pusat navigasi seluruh aplikasi.

```
Dashboard
│
├── Master Data
├── Penugasan
├── Pelaksanaan Lapangan
├── Verifikasi
├── Perhitungan KPI
├── Ranking Pegawai
├── Insentif
└── Laporan
```

---

# 3. Master Data

Master Data digunakan sebagai referensi seluruh proses bisnis.

```
Master Data
│
├── Data Pegawai
│
├── Data Wajib Pajak
│
├── Jenis Pekerjaan
│
├── Bobot KPI
│
├── Setting Insentif
│
├── Wilayah
│
└── Target Periode
```

---

# 4. Penugasan

Admin membuat pekerjaan yang akan dilaksanakan petugas.

```
Dashboard
    │
    ▼
Penugasan
    │
    ▼
Input Target
    │
    ▼
Pilih Wajib Pajak
    │
    ▼
Pilih Pegawai
    │
    ▼
Pilih Jenis Pekerjaan
    │
    ▼
Tentukan Deadline
    │
    ▼
Simpan Penugasan
```

---

# 5. Pelaksanaan Lapangan

Petugas melaksanakan pekerjaan di lokasi wajib pajak.

```
Dashboard
    │
    ▼
Terima Tugas
    │
    ▼
Buka Detail Penugasan
    │
    ▼
Ambil GPS
    │
    ▼
Validasi Radius
    │
    ├── Tidak Valid
    │        │
    │        └── Ambil GPS Kembali
    │
    └── Valid
             │
             ▼
        Foto Lokasi
             │
             ▼
        Input Hasil
             │
             ▼
        Upload Bukti
             │
             ▼
           Submit
```

---

# 6. Verifikasi

Admin melakukan pemeriksaan terhadap hasil pekerjaan.

```
Submit Hasil
      │
      ▼
Admin Review
      │
      ▼
Pemeriksaan Kelengkapan
      │
      ├── Ditolak
      │
      └── Disetujui
               │
               ▼
        Lanjut Perhitungan KPI
```

---

# 7. Perhitungan KPI

Seluruh indikator dinormalisasi kemudian dihitung berdasarkan bobot yang ditentukan.

```
Perhitungan KPI
│
├── Target
├── Realisasi
├── Penagihan Berhasil
├── Verval
├── GPS Valid
├── Ketepatan Waktu
├── Kelengkapan Dokumen
└── Bobot KPI
        │
        ▼
 Normalisasi Nilai
        │
        ▼
 Hitung Nilai KPI
```

---

# 8. Ranking Pegawai

Nilai KPI digunakan sebagai dasar perangkingan pegawai.

```
Nilai KPI
     │
     ▼
Sorting Nilai
     │
     ▼
Ranking Pegawai
```

---

# 9. Insentif

Insentif dihitung berdasarkan proporsi nilai KPI.

```
Ranking Pegawai
        │
        ▼
Input Dana Mingguan
        │
        ├──────────────┐
        │              │
        ▼              ▼
Input Dana Bulanan   Total KPI Pegawai
        │              │
        └──────┬───────┘
               ▼
      Hitung Proporsi
               │
               ▼
      Insentif Pegawai
```

---

# 10. Laporan

Seluruh proses menghasilkan laporan dan dashboard.

```
Dashboard Laporan
│
├── Dashboard Monitoring
│
├── Laporan KPI
│
├── Laporan Penagihan
│
├── Laporan GPS
│
├── Laporan Insentif
│
├── Grafik Mingguan
│
├── Grafik Bulanan
│
├── Export PDF
│
└── Export Excel
```

---

# 11. Alur Sistem Secara Keseluruhan

```
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

---

# 12. Siklus Operasional

```
Master Data
      │
      ▼
Penugasan
      │
      ▼
Pelaksanaan
      │
      ▼
Verifikasi
      │
      ▼
Perhitungan KPI
      │
      ▼
Ranking
      │
      ▼
Insentif
      │
      ▼
Laporan
      │
      ▼
Periode Berikutnya
      │
      └─────────────── kembali ke Penugasan
```
