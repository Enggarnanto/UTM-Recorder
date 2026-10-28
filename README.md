# UTM Traffic Recorder

Plugin WordPress untuk mencatat kunjungan, parameter UTM, sesi browser, dan klik CTA WhatsApp. Versi saat ini: **2.6.16**.

## Isi proyek

- **utm-traffic-recorder/** — kode sumber plugin WordPress.
- **dist/** — ZIP instalasi hasil build, tidak dimasukkan ke Git. Paket terbaru: `dist/2.6.16/utm-traffic-recorder-2.6.16.zip` atau `dist/utm-traffic-recorder.zip`.
- **integrations/webhook/** — kontrak JSON API netral, skema database, dan contoh receiver PHP/CodeIgniter 3/4 untuk backend apa pun.
- **integrations/google-sheets/** — receiver Apps Script dan panduan sinkronisasi detail klik WhatsApp dari banyak website.
- **landing-dokumentasi/** — landing page dokumentasi dan potongan HTML untuk Blogger.
- **tests/fallback-smoke.ps1** — uji pilihan sumber tanpa UTM di browser headless.
- **tests/** — uji PHP, receiver, logger, serta simulasi penggantian instalasi lama tanpa folder duplikat.
- **tools/** — script build paket; **docs/** — catatan dan panduan rilis; **.github/** — workflow rilis.

## Instalasi

Di dashboard WordPress, buka **Plugins → Add New → Upload Plugin**, pilih **utm-traffic-recorder.zip**, lalu pilih **Replace current with uploaded** jika WordPress menampilkan opsi tersebut. Plugin memerlukan WordPress 6.0+, PHP 7.4+, JavaScript, dan WordPress REST API pada halaman publik.

Setelah aktif, buka **Traffic Recorder → Settings** untuk memilih UTM laporan, kemudian **Traffic Recorder → CTA WA** untuk menambahkan akun WhatsApp. Uji kunjungan dari jendela privat karena kunjungan pengguna yang login ke WordPress tidak dicatat.

Panduan fitur, pengaturan, penyimpanan data, dan batas pengukuran tersedia di [DOKUMENTASI.md](utm-traffic-recorder/DOKUMENTASI.md). Versi halaman Blogger berada di [blogger-page.html](landing-dokumentasi/blogger-page.html).

## Pembaruan dari GitHub

Updater tersedia sejak 2.6.15. Setelah versi yang lebih tinggi diterbitkan sebagai GitHub Release dengan asset `utm-traffic-recorder.zip`, halaman Plugins dapat menampilkan **Perbarui sekarang**. Tombol **Periksa pembaruan** ada di Settings. Lihat [panduan rilis](docs/RELEASING.md); push kode tanpa release belum membuat update tersedia. ZIP source-code otomatis GitHub bukan paket plugin.

## Webhook/API untuk berbagai backend

Di Settings, konfigurasi URL endpoint HTTPS, ID website, dan kunci API. Penerima harus mengikuti [kontrak JSON](integrations/webhook/README.md). Contoh adapter tersedia untuk PHP biasa dan CodeIgniter, tetapi backend bahasa lain dapat mengimplementasikan protokol yang sama. Antrean dan konfirmasi terpisah dari Google Sheets, sehingga kedua integrasi dapat berjalan bersamaan.

## Log aktivitas dan struktur repo

Menu **Traffic Recorder → Log aktivitas** tersedia sejak 2.6.16 untuk administrator, dengan filter, pagination, dan CSV. Cakupan meliputi tracking, pengaturan, integrasi, pembaruan, dan lifecycle plugin. Log menyamarkan kredensial dan dibersihkan harian (30 hari/maksimum 50.000 catatan setelah pemangkasan). Riwayat klik tetap terpisah.

```text
utm-traffic-recorder/   sumber plugin; nama folder instalasi tetap
integrations/          receiver Google Sheets dan Webhook/API
docs/                  panduan dan catatan rilis
tests/                 pengujian perilaku dan layout ZIP
tools/                 build paket portabel
.github/               workflow rilis
dist/                  hasil build lokal, diabaikan Git
```

Jalankan `python tools/build-release.py`; upload `dist/utm-traffic-recorder.zip` atau ZIP versi di `dist/2.6.16/`. Arsip keluaran tidak dimasukkan ke Git. Arsip lokal lama dipertahankan di `dist/archive/`. `.gitignore` mengecualikan build, ZIP, log, cache, dependensi lokal, dan kredensial; `.gitattributes` menyeragamkan akhir baris file sumber. File `.env.example` tetap boleh dilacak. Penghapusan arsip dari indeks Git tidak menulis ulang riwayat commit lama.

## Penulis dan lisensi

Galih Enggarnanto · [MTalkBlog](https://www.mtalkblog.com/p/utm-traffic-recorder-dokumentasi.html) · GPLv2 atau lebih baru.
