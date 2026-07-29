# Sistem Penilaian Kinerja Petugas Penagihan Pajak

## Setup & Running

This application is implemented in **Python/Flask** (SQLAlchemy + SQLite, Flask-Login,
ReportLab, OpenPyXL). Follow these steps to run it locally:

```
pip install -r requirements.txt
python run.py
```

The first time it runs, `run.py` will automatically:
1. Create the SQLite database and all tables (`monev.db`).
2. Seed demo/master data (employees, taxpayers, job types, KPI weights, sample
   assignments in various stages, etc.) if the database is empty.
3. Start the development server at **http://localhost:5000**.

### Demo credentials

| Role    | Username   | Password     |
|---------|------------|--------------|
| Admin   | `admin`    | `admin123`   |
| Petugas | `petugas1` | `petugas123` |
| Petugas | `petugas2` | `petugas123` |
| Petugas | `petugas3` | `petugas123` |

Log in as **admin** to manage master data, penugasan, verifikasi, KPI,
ranking, insentif, and laporan. Log in as **petugas** to view assigned tasks
and submit field execution (pelaksanaan lapangan) reports.

---

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
