# UTM Traffic Recorder

Plugin WordPress untuk mencatat kunjungan, parameter UTM, sesi browser, dan klik CTA WhatsApp. Versi saat ini: **2.6.14**.

## Isi proyek

- **utm-traffic-recorder/** — kode sumber plugin WordPress.
- **utm-traffic-recorder.zip** — paket siap unggah ke WordPress (versi 2.6.14).
- **integrations/google-sheets/** — receiver Apps Script dan panduan sinkronisasi detail klik WhatsApp dari banyak website.
- **landing-dokumentasi/** — landing page dokumentasi dan potongan HTML untuk Blogger.
- **tests/fallback-smoke.ps1** — uji pilihan sumber tanpa UTM di browser headless.
- **tests/smoke.ps1** — uji perilaku JavaScript di browser headless.

## Instalasi

Di dashboard WordPress, buka **Plugins → Add New → Upload Plugin**, pilih **utm-traffic-recorder.zip**, lalu pilih **Replace current with uploaded** jika WordPress menampilkan opsi tersebut. Plugin memerlukan WordPress 6.0+, PHP 7.4+, JavaScript, dan WordPress REST API pada halaman publik.

Setelah aktif, buka **Traffic Recorder → Settings** untuk memilih UTM laporan, kemudian **Traffic Recorder → CTA WA** untuk menambahkan akun WhatsApp. Uji kunjungan dari jendela privat karena kunjungan pengguna yang login ke WordPress tidak dicatat.

Panduan fitur, pengaturan, penyimpanan data, dan batas pengukuran tersedia di [DOKUMENTASI.md](utm-traffic-recorder/DOKUMENTASI.md). Versi halaman Blogger berada di [blogger-page.html](landing-dokumentasi/blogger-page.html).

## Penulis dan lisensi

Galih Enggarnanto · [MTalkBlog](https://www.mtalkblog.com/p/utm-traffic-recorder-dokumentasi.html) · GPLv2 atau lebih baru.
