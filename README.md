# 🏪 Sistem POS (Point of Sale) — Project PKL

Sistem Point of Sale (POS) berbasis web untuk manajemen toko retail/distributor, dibangun menggunakan **Laravel 13**, **Filament v5**, dan **PostgreSQL**.

---

## 📋 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [Tech Stack](#-tech-stack)
- [Struktur Modul](#-struktur-modul)
- [Instalasi & Setup](#-instalasi--setup)
- [Konfigurasi](#%EF%B8%8F-konfigurasi)
- [Penggunaan](#-penggunaan)
- [Skema Database](#-skema-database)

---

## ✨ Fitur Utama

### 🖥️ Halaman Kasir (`/kasir`)
- Antarmuka kasir modern untuk memproses transaksi penjualan
- Pencarian barang real-time
- Input jumlah & diskon per item
- Pilihan metode pembayaran (Tunai, QRIS, Transfer)
- Perhitungan otomatis subtotal, diskon, total neto, dan kembalian
- Cetak/preview struk transaksi

### 📊 Dashboard Admin (`/admin`)
- **4 Stat Cards**: Penjualan Hari Ini, Penjualan Bulan Ini, Pembelian Bulan Ini, Stok Menipis
- **Grafik Omset Penjualan** (7 hari terakhir) — Line chart hijau
- **Grafik Modal Pembelian** (7 hari terakhir) — Line chart oranye
- **Pop-up SweetAlert Stok Menipis** — Muncul otomatis sekali setelah login jika ada barang stok ≤ 5 pcs
- Klik card "Stok Menipis" untuk melihat rincian barang kapan saja

### 📦 Manajemen Data (CRUD)
| Modul | Deskripsi |
|---|---|
| **Barang** | Kelola data barang, harga jual, satuan, relasi jenis & gudang |
| **Jenis Barang** | Kategori/klasifikasi barang |
| **Gudang** | Data gudang penyimpanan |
| **Supplier** | Data pemasok barang |
| **Customer** | Data pelanggan (opsional, untuk transaksi member) |

### 📄 Laporan & Riwayat (Read-Only dengan Detail Modal)
| Modul | Deskripsi |
|---|---|
| **Barang Keluar (Penjualan)** | Riwayat transaksi penjualan dari kasir. Klik baris untuk lihat detail nota |
| **Barang Masuk (Pembelian)** | Riwayat & input transaksi pembelian dari supplier. Klik baris untuk lihat detail |
| **Perpindahan Barang** | Transfer stok antar gudang |
| **Kartu Stok** | Buku mutasi stok barang (masuk/keluar/pindah). Klik baris untuk lihat detail |
| **Riwayat Aktivitas** | Audit log perubahan data (via Spatie Activity Log) |

### 🔔 Fitur Tambahan
- **Zona waktu WIB** (Asia/Jakarta) di seluruh sistem
- **SPA Mode** — Navigasi halaman admin tanpa full reload
- **Dark Theme** pada seluruh modal detail
- **Badge warna** untuk jenis pembayaran (Tunai/QRIS/Transfer/Tempo)
- **Validasi stok otomatis** — Hapus pembelian/penjualan otomatis rollback stok

---

## 🛠️ Tech Stack

| Komponen | Teknologi |
|---|---|
| **Backend** | PHP 8.3+, Laravel 13 |
| **Admin Panel** | Filament v5 |
| **Frontend Kasir** | Vanilla JavaScript + Vite |
| **Database** | PostgreSQL |
| **Activity Log** | Spatie Laravel Activity Log v5 |
| **Pop-up Alert** | SweetAlert2 (CDN) |
| **Styling** | Filament UI (Admin), Custom CSS (Kasir) |

---

## 📁 Struktur Modul

```
app/
├── Filament/
│   ├── Resources/
│   │   ├── Activities/          # Riwayat Aktivitas
│   │   ├── Barangs/             # Master Barang
│   │   ├── Customers/           # Master Customer
│   │   ├── Gudangs/             # Master Gudang
│   │   ├── JenisBarangs/        # Master Jenis Barang
│   │   ├── KartuStoks/          # Kartu Stok (read-only)
│   │   ├── Pembelians/          # Barang Masuk (Pembelian)
│   │   ├── Penjualans/          # Barang Keluar (Penjualan)
│   │   ├── PerpindahanBarangs/  # Perpindahan Antar Gudang
│   │   └── Suppliers/           # Master Supplier
│   └── Widgets/
│       ├── StatsOverviewWidget.php      # 4 Stat Cards Dashboard
│       ├── PenjualanChartWidget.php     # Grafik Penjualan
│       └── PembelianChartWidget.php     # Grafik Pembelian
├── Http/Controllers/
│   └── KasirController.php     # Controller halaman kasir
├── Models/                      # 13 Eloquent Models
├── Services/
│   └── StokService.php         # Logic stok: catat kartu, validasi, rollback
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php  # Konfigurasi panel admin
```

---

## 🚀 Instalasi & Setup

### Prasyarat
- PHP 8.3+
- Composer
- Node.js & NPM
- PostgreSQL

### Langkah Instalasi

```bash
# 1. Clone repository
git clone <repository-url>
cd Project-PKL

# 2. Install dependensi PHP
composer install

# 3. Install dependensi JavaScript
npm install

# 4. Salin file environment
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Konfigurasi database di .env (lihat bagian Konfigurasi)

# 7. Jalankan migrasi database
php artisan migrate

# 8. (Opsional) Jalankan seeder jika tersedia
php artisan db:seed

# 9. Build asset frontend
npm run build

# 10. Jalankan server development
php artisan serve
```

---

## ⚙️ Konfigurasi

### File `.env` — Variabel Penting

```env
# Aplikasi
APP_NAME=Laravel
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=nama_database
DB_USERNAME=username
DB_PASSWORD=password
```

### Zona Waktu
Seluruh sistem menggunakan **WIB (Asia/Jakarta)**. Konfigurasi ini diatur di:
- `config/app.php` → `timezone` & `locale`
- Semua kolom tanggal di tabel Filament menggunakan `.timezone('Asia/Jakarta')`

---

## 💡 Penggunaan

### Akses Halaman

| Halaman | URL | Keterangan |
|---|---|---|
| **Admin Panel** | `/admin` | Login → Dashboard, kelola semua data |
| **Halaman Kasir** | `/kasir` | Interface kasir untuk transaksi penjualan |

### Alur Kerja Utama

1. **Setup Data Master** → Tambahkan Gudang, Supplier, Jenis Barang, lalu Barang
2. **Input Pembelian** → Tambah transaksi Barang Masuk dari Supplier (stok otomatis bertambah)
3. **Proses Penjualan** → Buka halaman Kasir (`/kasir`), scan/cari barang, proses pembayaran
4. **Monitor Dashboard** → Cek omset harian, grafik tren, dan peringatan stok menipis
5. **Audit Stok** → Buka Kartu Stok untuk melihat riwayat lengkap mutasi setiap barang

---

## 🗄️ Skema Database

### Tabel Utama

| Tabel | Deskripsi |
|---|---|
| `barang` | Master data barang |
| `jenis_barang` | Kategori/jenis barang |
| `gudang` | Data gudang penyimpanan |
| `barang_gudang` | Pivot: stok barang per gudang |
| `supplier` | Data pemasok |
| `customer` | Data pelanggan |
| `pembelian` | Header transaksi pembelian |
| `detail_beli` | Detail item per pembelian |
| `penjualan` | Header transaksi penjualan |
| `detail_jual` | Detail item per penjualan |
| `perpindahan_barang` | Header perpindahan antar gudang |
| `perpindahan_barang_detail` | Detail item perpindahan |
| `kartu_stok` | Log mutasi stok (masuk/keluar/pindah) |
| `activity_log` | Audit trail (Spatie) |
| `users` | Data pengguna/admin |

### Relasi Utama

```
Barang ──┬── belongsToMany ── Gudang (via barang_gudang + stok)
         ├── belongsTo ─── JenisBarang
         └── hasMany ───── KartuStok

Pembelian ──┬── belongsTo ── Supplier, Gudang, User
            └── hasMany ──── DetailBeli → belongsTo Barang

Penjualan ──┬── belongsTo ── Customer (nullable), Gudang, User
            └── hasMany ──── DetailJual → belongsTo Barang

KartuStok ── belongsTo ── Barang, Gudang
```

---

## 📝 Lisensi

Project ini dibuat untuk keperluan **Praktik Kerja Lapangan (PKL)**.
