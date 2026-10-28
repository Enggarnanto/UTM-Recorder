# Sinkronisasi klik WA ke Google Sheets — plugin 2.6.12

Satu baris adalah satu klik WhatsApp yang berhasil dicatat plugin. Semua field tabel klik dikirim, ditambah identitas website dan waktu penerimaan. Pageview tidak dikirim. Nomor WA adalah nomor tujuan/admin, bukan nomor pengunjung. Waktu klik tetap UTC; ID browser dan sesi dikirim lengkap.

## Google Sheets dan Apps Script

1. Buka spreadsheet tujuan, lalu **Extensions > Apps Script**.
2. Ganti seluruh isi editor dengan `Code.gs` dalam folder panduan ini. Jangan membungkusnya dalam `myFunction`. Simpan.
3. Di **Project Settings > Script Properties**, isi `SPREADSHEET_ID` dengan bagian URL spreadsheet di antara `/d/` dan `/edit`.
4. Untuk tiap website, buat property `SITE_KEY_<ID website>`. Contoh: `SITE_KEY_web_a`. Value adalah kunci acak unik sepanjang 32–512 karakter tanpa spasi. Gunakan password generator. Jangan masukkan kunci asli ke kode, GitHub, atau halaman publik.
5. Dari editor, jalankan `setupSheet` dan berikan izin. Tab **Data Klik WA** dibuat otomatis. Header lama dari receiver awal diperbarui tanpa mengubah data klik. Jangan mengubah urutan atau nama kolom secara manual.
6. **Deploy > New deployment > Web app**. Pilih **Execute as: Me** dan **Who has access: Anyone**. Spreadsheet tetap privat. Salin URL yang berakhir `/exec`.
7. Jika sudah pernah deploy: **Deploy > Manage deployments > Edit > Version: New version > Deploy**. Simpan deployment yang sama supaya URL tidak berubah. Receiver versi 2 mendukung tes koneksi berkunci tanpa menambahkan klik contoh.
8. Membuka URL `/exec` menampilkan JSON dengan `ok: true` dan `version: 2`. Ini hanya tes alamat aktif. Tes izin spreadsheet dan kunci dilakukan dari plugin.

## Tiap website WordPress

1. Upload `utm-traffic-recorder-2.6.12.zip` melalui **Plugins > Add New > Upload Plugin**, lalu ganti instalasi lama. Pengaturan dan riwayat lama tetap dipakai.
2. Buka **Traffic Recorder > Settings > Integrasi Google Sheets**.
3. Isi URL Apps Script `/exec`, ID website (contoh `web_a`), dan kunci yang sama dengan Value `SITE_KEY_web_a`. Website lain memakai ID dan kunci sendiri; URL penerimanya boleh sama.
4. Klik **Simpan integrasi**, lalu **Tes koneksi**. Tes memeriksa kunci serta akses spreadsheet; tidak menambah data klik. Tidak perlu mengaktifkan sinkronisasi untuk tes.
5. Centang **Aktifkan sinkronisasi klik WA**, lalu **Simpan integrasi**. Pengiriman pertama mencakup riwayat yang masih tersimpan, dari ID terkecil.
6. Klik **Sinkronkan sekarang** untuk mengirim satu batch segera. Periksa pesan konfirmasi, antrean, dan baris baru di Sheets. Maksimal 100 klik per batch; riwayat besar dilanjutkan otomatis tiap 5 menit, mengikuti WP-Cron. Hosting harus dapat mengakses `script.google.com` dan redirect Google melalui HTTPS.

## Pengiriman ulang dan batasnya

- Gagal kirim atau konfirmasi tidak lengkap tidak memajukan ID terakhir. Batch dicoba lagi, dan receiver mencegah duplikat memakai `ID website:ID klik`.
- **Sinkronkan ulang riwayat** memulai dari awal tanpa menghapus data WordPress atau Sheets. Gunakan jika baris di Sheets terhapus. Baris dengan ID unik yang sudah ada tidak ditimpa.
- Pertahankan ID website; jangan menggunakan ID yang sama pada dua instalasi. Jika database klik direset dan ID klik dimulai lagi dari 1, gunakan ID website baru.
- Mengganti URL penerima atau ID website memulai ekspor riwayat dari awal. Mengganti kunci saja mempertahankan posisi ekspor.
- Kunci yang sudah disimpan tidak ditampilkan dalam form. Biarkan kolom kunci kosong untuk mempertahankannya. Kunci disimpan sebagai opsi WordPress tanpa autoload; administrator database tetap dapat membacanya.
- Matikan checkbox sinkronisasi dan simpan untuk menghentikan pengiriman otomatis. Tes koneksi tetap mengirim permintaan jika tombol ditekan.
- WP-Cron dipicu aktivitas WordPress. Atur cron hosting untuk jadwal yang konsisten. Klik tetap dicatat secara lokal ketika Sheets tidak dapat diakses.
- Retensi WordPress tetap 90 hari. Riwayat yang sudah dihapus tidak dapat dipulihkan lewat sinkronisasi, dan antrean yang tertunda melewati masa retensi bisa hilang.
- Apps Script dan Sheets mempunyai kuota. Receiver memeriksa kolom ID unik sebelum menulis; spreadsheet sangat besar memerlukan arsip berkala atau penerima berbasis database.

## Jika tes gagal

- **Kunci atau ID tidak cocok**: ID `web_a` harus berpasangan dengan property `SITE_KEY_web_a`, dan Value sama persis.
- **Apps Script belum menerima data**: pastikan receiver terbaru ditempel, fungsi berada di tingkat atas, jalankan `setupSheet`, lalu deploy **New version** dengan akses **Anyone**.
- **Tidak dapat menghubungi Apps Script**: periksa HTTPS keluar di hosting. Jangan menonaktifkan verifikasi TLS.
- Header tidak sesuai: pulihkan urutan/header yang dibuat script. Receiver menolak menulis daripada menaruh data pada kolom yang salah.
