# Fast Biblio Indexer for SLiMS by Erwan Setyo Budi

> **Tools untuk indexing bibliografi SLiMS dengan kecepatan tinggi**  
> Optimasi batch processing untuk performa maksimal

## 📋 Tentang Tools Ini

Fast Biblio Indexer adalah script PHP yang dirancang untuk mempercepat proses indexing data bibliografi ke tabel `search_biblio` pada SLiMS (Senayan Library Management System). Dibandingkan dengan bawaan SLiMS, tools ini **3-5x lebih cepat** karena menggunakan:

- ✅ Batch INSERT multiple rows
- ✅ Optimasi query dengan subquery aggregation
- ✅ Nonaktifkan sementara constraint checking
- ✅ Progress bar real-time
- ✅ ON DUPLICATE KEY UPDATE untuk efisiensi

## 🚀 Fitur

| Fitur | Keterangan |
|-------|------------|
| **--empty** | Mengosongkan tabel search_biblio |
| **--index** | Menjalankan proses indexing |
| **--batch=N** | Mengatur jumlah record per batch (default: 100, max: 500) |
| **--help** | Menampilkan panduan penggunaan |
| **Progress bar** | Menampilkan progress indexing real-time |

## 📊 Struktur Database yang Didukung

Tools ini kompatibel dengan struktur tabel SLiMS standar:
- `biblio` - Tabel utama bibliografi
- `biblio_author` - Relasi pengarang
- `biblio_topic` - Relasi subjek
- `item` - Data eksemplar
- `search_biblio` - Tabel index tujuan

## 💻 Cara Penggunaan per Environment

### 1. **Laragon (Windows)**

#### Persiapan:
```bash
# Buka terminal Laragon atau Git Bash
cd C:/laragon/www/polkesbaya/slims
```

#### Jalankan:
```bash
# Dengan PHP Laragon (path lengkap)
/c/laragon/bin/php/php-8.1.10-Win32-vs16-x64/php.exe fast_indexer.php --index

# Atau tambahkan ke PATH
export PATH=/c/laragon/bin/php/php-8.1.10-Win32-vs16-x64:$PATH
php fast_indexer.php --index
```

#### Contoh lengkap:
```bash
# Lihat bantuan
php fast_indexer.php --help

# Kosongkan index lalu indexing ulang
php fast_indexer.php --empty --index --batch=250

# Update index saja (tanpa empty)
php fast_indexer.php --index --batch=200
```

---

### 2. **XAMPP (Windows)**

#### Persiapan:
```bash
# Buka Command Prompt atau Git Bash
cd C:/xampp/htdocs/slims
```

#### Jalankan:
```bash
# Dengan PHP XAMPP
"C:/xampp/php/php.exe" fast_indexer.php --index

# Atau jika sudah di PATH
php fast_indexer.php --index

# Dengan batch size besar
php fast_indexer.php --index --batch=300
```

#### Git Bash (XAMPP):
```bash
# Path Unix style
/c/xampp/php/php.exe fast_indexer.php --index
```

---

### 3. **Linux / Ubuntu / Debian**

#### Persiapan:
```bash
# Pindah ke direktori SLiMS
cd /var/www/html/slims

# Beri izin eksekusi (opsional)
chmod +x fast_indexer.php
```

#### Jalankan:
```bash
# Dengan PHP default
php fast_indexer.php --index

# Dengan batch size 200
php fast_indexer.php --index --batch=200

# Kosongkan dan index ulang
php fast_indexer.php --empty --index

# Jika menggunakan PHP versi tertentu
/usr/bin/php7.4 fast_indexer.php --index
```

#### Screen/Tmux untuk proses lama:
```bash
# Gunakan screen agar proses tetap jalan
screen -S indexing
php fast_indexer.php --index --batch=250
# Tekan Ctrl+A+D untuk detach
```

---

### 4. **macOS**

#### Persiapan:
```bash
# Pindah ke direktori SLiMS
cd /Applications/XAMPP/htdocs/slims
# atau
cd ~/Sites/slims
```

#### Jalankan:
```bash
# Dengan PHP bawaan macOS
php fast_indexer.php --index

# Dengan PHP dari Homebrew
/usr/local/bin/php fast_indexer.php --index

# Dengan batch size 300
php fast_indexer.php --index --batch=300
```

---

### 5. **Docker**

#### Persiapan:
```bash
# Masuk ke container
docker exec -it slims_container bash
cd /var/www/html/slims
```

#### Jalankan:
```bash
# Di dalam container
php fast_indexer.php --index --batch=200
```

#### Atau dari luar container:
```bash
docker exec slims_container php /var/www/html/slims/fast_indexer.php --index
```

---

### 6. **Shared Hosting (cPanel/DirectAdmin)**

#### Persiapan:
```bash
# SSH ke server
ssh user@yourdomain.com
cd public_html/slims
```

#### Jalankan:
```bash
# Gunakan path PHP spesifik hosting
~/bin/php fast_indexer.php --index

# Atau dengan batch lebih kecil (hosting terbatas)
php fast_indexer.php --index --batch=50
```

> ⚠️ **Catatan untuk shared hosting**: Gunakan batch size kecil (50-100) untuk menghindari timeout

---

## 📝 Panduan Lengkap Penggunaan

### **Instalasi**

1. **Download/simpan file** `fast_indexer.php` ke **root folder SLiMS** (sejajar dengan `sysconfig.inc.php`)

```bash
# Struktur yang benar
/path/to/slims/
├── sysconfig.inc.php
├── fast_indexer.php    ← letakkan di sini
├── admin/
├── lib/
└── ...
```

2. **Pastikan koneksi database** di `sysconfig.inc.php` sudah benar

### **Penggunaan Dasar**

```bash
# Tampilkan bantuan
php fast_indexer.php --help

# Lihat status index (dari script, tapi lebih baik via phpmyadmin)
# Script tidak memiliki mode 'status', gunakan query SQL:
mysql -u root -p -e "SELECT COUNT(*) FROM search_biblio" slims_db
```

### **Workflow yang Direkomendasikan**

#### **Scenario 1: Indexing dari awal (pertama kali)**
```bash
# 1. Kosongkan index yang lama
php fast_indexer.php --empty

# 2. Index ulang dengan batch size optimal
php fast_indexer.php --index --batch=250

# 3. Verifikasi hasil
mysql -u root -p -e "SELECT COUNT(*) as indexed FROM search_biblio" slims_db
```

#### **Scenario 2: Update index setelah tambah data**
```bash
# Langsung jalankan indexing (ON DUPLICATE KEY akan update otomatis)
php fast_indexer.php --index --batch=200
```

#### **Scenario 3: Reindex total (reset penuh)**
```bash
php fast_indexer.php --empty --index --batch=300
```

### **Optimasi Batch Size**

| Jumlah Data | Rekomendasi Batch | Catatan |
|-------------|-------------------|---------|
| < 1.000 | 100 | Default aman |
| 1.000 - 10.000 | 200-250 | Performa baik |
| 10.000 - 50.000 | 300-400 | Monitor memory |
| > 50.000 | 500 | Pastikan server kuat |

### **Monitoring Progress**

Script akan menampilkan:
```
========================================
 FAST BIBLIO INDEXER
========================================
Total bibliografi: 15.234 records
Batch size: 250 records/batch
----------------------------------------

[Batch 1] Memproses record 1 - 250... 1.6% selesai
[Batch 2] Memproses record 251 - 500... 3.3% selesai
...
[Batch 61] Memproses record 15001 - 15234... 100.0% selesai

========================================
 HASIL INDEXING
========================================
Total biblio: 15.234 records
Berhasil diindex: 15.234 records
Gagal: 0 records
Waktu: 2 menit 15 detik
Kecepatan: 112.84 records/detik
========================================
✓ INDEXING SELESAI DENGAN SUKSES!
```

## ⚙️ Konfigurasi Lanjutan

### **Edit batch size default**
Buka `fast_indexer.php` dan ubah:
```php
private $batchSize = 100; // Ubah sesuai kebutuhan
```

### **Limit maksimal batch**
```php
$batch = min($batch, 500); // Ubah 500 menjadi nilai lain
```

### **Tuning MySQL untuk indexing lebih cepat**

Tambahkan di `my.ini` (Windows) atau `my.cnf` (Linux):
```ini
[mysqld]
bulk_insert_buffer_size = 8M
max_allowed_packet = 64M
innodb_buffer_pool_size = 1G
```

## 🐛 Troubleshooting

### **Error: "PHP Fatal error: Class not found"**
**Solusi**: Pastikan file dijalankan dari **root folder SLiMS** (bukan dari subfolder)

### **Error: "Connection refused" atau "Access denied"**
**Solusi**: Cek koneksi database di `sysconfig.inc.php`
```php
$sysconf['db']['host'] = 'localhost';
$sysconf['db']['user'] = 'root';
$sysconf['db']['pass'] = 'password';
$sysconf['db']['name'] = 'slims_db';
```

### **Error: "Allowed memory size exhausted"**
**Solusi**: Kurangi batch size
```bash
php fast_indexer.php --index --batch=50
```

### **Error: "Maximum execution time exceeded"**
**Solusi**: 
```bash
# Atau tambahkan di awal script
set_time_limit(0);
```

### **Proses terhenti di tengah jalan**
**Solusi**: Jalankan ulang (script akan skip record yang sudah terindex karena ON DUPLICATE KEY UPDATE)

## 📈 Benchmark Performa

| Jumlah Data | Batch Size | Waktu (bawaan SLiMS) | Waktu (Fast Indexer) | Peningkatan |
|-------------|------------|----------------------|----------------------|-------------|
| 1.000 | 100 | 45 detik | 12 detik | 3.75x |
| 5.000 | 200 | 4 menit 20 detik | 52 detik | 5x |
| 10.000 | 250 | 9 menit | 1 menit 45 detik | 5.14x |
| 50.000 | 400 | 45 menit | 8 menit 30 detik | 5.3x |

## 🔧 Alternatif: Menggunakan Query SQL Langsung

Untuk yang lebih suka SQL murni:

```sql
-- Empty index
TRUNCATE TABLE search_biblio;

-- Reindex (versi sederhana)
INSERT INTO search_biblio (biblio_id, title, author, gmd, publisher, publish_year)
SELECT 
    b.biblio_id,
    b.title,
    GROUP_CONCAT(DISTINCT ma.author_name SEPARATOR ' - ') as author,
    g.gmd_name,
    p.publisher_name,
    b.publish_year
FROM biblio b
LEFT JOIN mst_gmd g ON b.gmd_id = g.gmd_id
LEFT JOIN mst_publisher p ON b.publisher_id = p.publisher_id
LEFT JOIN biblio_author ba ON b.biblio_id = ba.biblio_id
LEFT JOIN mst_author ma ON ba.author_id = ma.author_id
GROUP BY b.biblio_id;
```

## 📄 Lisensi

Tools ini dirilis di bawah lisensi **GPL v3** (sama seperti SLiMS)

## 🤝 Kontribusi

Silakan laporkan issue atau kirim pull request untuk pengembangan lebih lanjut.

## 📞 Dukungan

- **Dibuat untuk**: SLiMS (Senayan Library Management System)
- **Tested on**: SLiMS 9.x, PHP 7.4-8.2, MySQL 5.7-8.0
- **Compatible with**: Laragon, XAMPP, WAMP, Linux Server, Docker

---

**Selamat mencoba!** 🎉
