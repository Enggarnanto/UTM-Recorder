# Panduan UTM Traffic Recorder

**Versi plugin:** 2.6.14

Tampilan dashboard dan CTA mengambang memakai Deep Navy, Electric Blue, dan aksen Electric Lime. Heading menggunakan Sora dan teks menggunakan Plus Jakarta Sans, dengan font sistem sebagai cadangan bila Google Fonts tidak tersedia. Warna akun WhatsApp yang sudah diatur sendiri tetap dipertahankan; warna bawaan akun baru adalah Electric Blue.

Di **CTA WA → Tombol mengambang & tautan WhatsApp → Tampilan tombol floating**, Anda dapat memilih warna tombol, warna teks dan ikon, warna titik aksen, serta ukuran 80–140%. Pratinjau berubah langsung saat pengaturan digeser. Klik **Simpan pengaturan CTA WA** agar perubahan berlaku di situs. Warna tombol akun di daftar chat diatur terpisah pada masing-masing akun.
**Penulis:** Galih Enggarnanto  
**Website:** [www.mtalkblog.com](https://www.mtalkblog.com)

Plugin ini mencatat kunjungan website, parameter UTM, dan klik WhatsApp. Laporannya tersedia di dashboard WordPress. Anda juga bisa membuat satu tombol WhatsApp mengambang yang menampilkan beberapa admin.

## Mulai cepat

1. Di WordPress, buka **Plugins → Add New → Upload Plugin**. Unggah `utm-traffic-recorder.zip`, lalu pilih **Replace current with uploaded** jika ditawarkan untuk memperbarui versi lama. Aktifkan plugin bila perlu.
2. Buka **Traffic Recorder → Settings**. Pilih UTM yang ingin dicatat, lalu klik **Simpan pengaturan**. Kelima UTM aktif secara bawaan.
3. Buka **Traffic Recorder → CTA WA** untuk menambahkan akun admin WhatsApp, nomor, avatar, pesan, dan jadwal aktifnya.
4. Buka website sebagai pengunjung yang **tidak login**, memakai tautan seperti:

   ```text
   https://example.com/halaman/?utm_source=GADS&utm_medium=CPC&utm_campaign=PROMO_A&utm_content=GRUP_A&utm_term=KURSUS
   ```

5. Periksa hasilnya di **Dashboard Trafik & UTM** dan **UTM Manager**. Klik tombol WhatsApp untuk menguji pesan dan **Dashboard Chat WA**.

Pengujian sebaiknya dilakukan di jendela privat. Kunjungan dan klik dari pengguna yang sedang login ke WordPress tidak masuk laporan.

## Integrasi Google Sheets

Di **Traffic Recorder → Settings → Integrasi Google Sheets**, isi URL Web app Apps Script yang berakhir `/exec`, ID website unik, dan kunci sesuai Value Script Property `SITE_KEY_<ID website>`. Simpan integrasi, lalu klik **Tes koneksi**. Receiver versi terbaru mendukung tes berkunci tanpa menambah klik contoh; setelah memperbarui kode receiver, deploy **New version** pada deployment yang sama.

Aktifkan sinkronisasi lalu simpan. Seluruh field klik WA yang masih tersimpan dikirim dari server dalam batch maksimal 100 klik tiap 5 menit melalui WP-Cron. Klik **Sinkronkan sekarang** untuk menjalankan satu batch segera; sisanya dilanjutkan otomatis. Pageview tidak dikirim. Satu klik menjadi satu baris, bersama identitas website, ID browser/sesi lengkap, akun dan nomor WA tujuan, jenis link, halaman, seluruh UTM yang dicatat, serta sumber pesan. Waktu klik di Sheets menggunakan UTC.

Receiver mencegah duplikat berdasarkan ID website dan ID klik; jangan gunakan ID website sama pada dua instalasi. Jika database direset dan ID klik dimulai lagi, pakai ID website baru. Pengiriman gagal tidak memajukan posisi sinkronisasi dan akan dicoba ulang. **Sinkronkan ulang riwayat** mengirim kembali data yang masih ada tanpa menghapus riwayat. Penggantian URL penerima atau ID website memulai ekspor dari awal; perubahan kunci saja tidak.

Kunci yang sudah tersimpan tidak ditampilkan di form. Biarkan kolom kunci kosong untuk mempertahankannya. Jangan bagikan kunci. Matikan checkbox dan simpan untuk menghentikan pengiriman otomatis. Data klik dikirim ke Google hanya bila integrasi diaktifkan; tombol tes koneksi mengirim identitas website dan kunci untuk memeriksa akses.

Jadwal WP-Cron bergantung pada aktivitas website; gunakan cron hosting untuk jadwal konsisten. Retensi lokal tetap 90 hari, termasuk data yang belum tersinkron. Riwayat yang sudah dihapus tidak dapat dipulihkan melalui fitur ini. Kuota Apps Script/Sheets berlaku; spreadsheet sangat besar memerlukan pengarsipan.

## Persyaratan

- WordPress 6.0 atau lebih baru dan PHP 7.4 atau lebih baru.
- JavaScript dan WordPress REST API harus dapat berjalan pada halaman publik.
- Penghapusan data otomatis memakai WP-Cron, sehingga prosesnya berjalan saat WordPress menerima kunjungan.

## Mengenal menu

| Menu | Kegunaan |
| --- | --- |
| **Dashboard Trafik & UTM** | Melihat ID browser unik, sesi unik, tampilan halaman, kunjungan ber-UTM, klik WA, sumber UTM teratas, dan klik WA terbaru. |
| **Dashboard Chat WA** | Melihat jumlah klik, sesi yang mengklik, akun atau nomor teratas, serta rincian UTM tiap klik. |
| **UTM Manager** | Melihat ID browser dan jumlah sesinya, lima peringkat UTM, detail kunjungan, filter, ekspor CSV, serta toggle Clean URL. |
| **CTA WA** | Mengelola akun admin, avatar, pesan, jadwal, halaman penayangan, urutan akun, shortcode, tombol mengambang, serta seluruh format sumber pesan WA. |
| **Sidebar editor Post / Page → Shortcode WA** | Menyalin shortcode akun untuk konten Post, Page, atau SEO Writer. |
| **Settings** | Memilih kolom UTM yang dicatat dalam laporan kunjungan dan klik WA, pelacakan tautan WA yang sudah ada, dan Clean URL. |

## Mengatur pencatatan UTM

Di **Traffic Recorder → Settings**, centang parameter yang dibutuhkan:

- `utm_source`: asal trafik, misalnya `GADS`.
- `utm_medium`: jenis kanal, misalnya `CPC`.
- `utm_campaign`: nama kampanye.
- `utm_content`: variasi iklan atau grup iklan.
- `utm_term`: kata kunci.
- `utm_match_type`: jenis kecocokan kata kunci. Tautan dengan `utm_matchtype` juga diterima dan disimpan sebagai `utm_match_type`.

Parameter yang tidak dicentang tidak dicatat sebagai UTM pada laporan baru. Pilihan untuk teks pesan WA diatur terpisah di CTA WA dan tersedia di penyimpanan browser. Anda boleh mengosongkan semuanya; jumlah trafik dan sesi tetap dicatat. Data lama yang sudah masuk tidak diubah ketika pilihan ini diganti.

Saat pengunjung membuka tautan dengan UTM baru, plugin mengganti **seluruh set UTM aktif**. Parameter yang tidak ada pada tautan baru menjadi kosong. Contohnya, setelah `GADS-CPC`, pengunjung membuka `?utm_source=EMAIL`; atribusi berikutnya menjadi `EMAIL`, tanpa mewarisi `CPC` dari tautan sebelumnya.

## ID browser, sesi, dan tempat data disimpan

| Lokasi | Isi | Kapan berubah |
| --- | --- | --- |
| `localStorage`, kunci `utr_visitor_v1` | ID browser dan semua UTM aktif, termasuk nilai untuk teks WA. | UTM baru mengganti UTM aktif. ID bertahan di browser dan domain yang sama sampai data situs dihapus. |
| Cookie pihak pertama `utr_vid_v1` | Hanya ID browser sebagai cadangan jika localStorage tidak bisa dibaca. | Disamakan dengan ID di localStorage; berlaku maksimal satu tahun, tanpa nilai UTM. |
| `sessionStorage`, kunci `utr_session_v2` | ID sesi tab, halaman pertama, domain dan URL perujuk bila diberikan browser, serta semua UTM pada sesi aktif. | Sesi tab baru mendapat ID sesi baru. UTM baru dalam tab memperbarui UTM sesi. |
| Database WordPress | Riwayat kunjungan dan klik WA beserta ID browser, ID sesi, dan UTM saat peristiwa terjadi. | Setiap kunjungan atau klik yang berhasil dicatat menambah baris laporan. |

Satu ID browser bisa memiliki **beberapa ID sesi**. Di **UTM Manager → ID browser & sesi**, klik sebuah ID untuk memfilter rincian kunjungannya. Data sebelum versi 1.9.0 belum memiliki ID browser.

Tabel **ID browser & sesi** menampilkan 5 baris secara bawaan. Isi **Jumlah baris** dengan angka 1–500 lalu klik **Tampilkan** untuk melihat lebih banyak atau lebih sedikit. Klik judul kolom untuk mengurutkan baris yang terlihat. Tabel lain juga dapat diurutkan melalui judul kolom; di layar kecil gunakan pilihan urut di atas tabel. Pada tabel yang memiliki pagination, pengurutan berlaku untuk halaman yang sedang dibuka. Di tabel akun WA, pengurutan kolom hanya untuk melihat data; kembali ke urutan awal sebelum menggeser prioritas akun.

Plugin hanya menulis ulang dua snapshot browser jika isinya berubah dan memeriksa apakah penyimpanannya berhasil. Key lama `utr_wa_utm_v1` dan `utr_wa_session_utm_v1` digabungkan ke key utama lalu dihapus pada kunjungan publik berikutnya. Pilihan UTM di Settings membatasi data laporan WordPress, bukan nilai UTM untuk teks WA di browser. UTM baru memperbarui seluruh parameter yang didukung meskipun sebagian kolom laporan dimatikan. Nilai UTM dipotong hingga 191 karakter sebelum disimpan. Pesan WA membaca nilai `localStorage` terbaru saat tautan diklik, termasuk bila UTM diperbarui di tab lain. Jika penulisan storage gagal, pesan pada halaman itu memakai nilai UTM di memori; cookie hanya membantu mempertahankan ID browser. ID sesi tetap mengikuti tab. Jika browser memblokir atau menghapus semua penyimpanan situs, ID dan atribusi lintas halaman tidak dapat dijamin tetap sama.

Tabel database memakai awalan tabel WordPress Anda, misalnya `wp_utm_traffic_recorder` untuk kunjungan dan `wp_utm_traffic_wa_clicks` untuk klik WA. Catatan di database dibersihkan setelah berumur lebih dari **90 hari**. Plugin tidak menyimpan alamat IP, user agent, atau teks utama pesan WhatsApp dalam catatan klik; baris sumber yang ditambahkan tersimpan agar riwayat tetap akurat. Riwayat yang sudah tersimpan tetap mempertahankan UTM saat peristiwa terjadi.

ID browser mengacu pada penyimpanan di browser dan domain tersebut. Browser lain, perangkat lain, mode privat, atau data situs yang dihapus dapat menghasilkan ID baru.

Mulai versi 2.4.2, permintaan pencatatan kunjungan dan klik WA hanya diterima bila **ID browser dan ID sesi sama-sama berupa UUID valid**. Tautan WA tetap berfungsi jika pencatatan ditolak. Pastikan cache halaman dan JavaScript diperbarui setelah memasang versi baru; skrip lama yang belum mengirim ID browser tidak lagi masuk laporan. Baris lama dengan ID browser kosong tetap kosong bila tidak ada data untuk mengenali browser aslinya.

## Mengatur sumber pada pesan WhatsApp

Di **CTA WA → Format sumber dalam pesan WA**, pilih sumber UTM untuk pesan:

| Pilihan | Perilaku |
| --- | --- |
| **Local storage (disarankan)** | Memakai UTM aktif terbaru, termasuk saat pengunjung kembali pada sesi berikutnya di browser yang sama. Ini pilihan bawaan. |
| **Sesi aktif** | Memakai UTM yang diperoleh dalam sesi tab tersebut. Sesi baru tanpa UTM memakai URL halaman sebagai sumber. |

Pilihan ini mengatur **teks sumber pada pesan WA**. UTM yang dicentang di CTA WA digunakan meskipun tidak dipilih untuk laporan di Settings. Kolom UTM pada laporan kunjungan dan klik tetap mengikuti pilihan Settings dan atribusi terbaru di `localStorage`. Jika pesan memakai mode sesi, UTM di pesan dan kolom laporan klik bisa berbeda. Jika parameter hanya aktif untuk pesan WA, nilainya tetap terlihat dalam baris **Sumber** pada riwayat klik, tetapi kolom UTM terkait akan kosong. Kedua menu menampilkan penanda untuk pilihan seperti ini. Untuk pengunjung yang sudah membuka situs sebelum pembaruan ini, UTM yang sebelumnya dinonaktifkan di Settings baru tersedia setelah mereka membuka tautan UTM lagi.

Contoh pesan dengan UTM:

```text
Halo,
saya ingin bertanya tentang program ini.

Sumber: GADS+a1b2c3d4
```

Format bawaan memakai `utm_source` dan delapan karakter awal ID sesi, sama seperti kolom **Sesi** di UTM Manager. Saat tidak ada UTM sama sekali, pengaturan bawaan memakai URL halaman sebagai sumber:

```text
Halo,
saya ingin bertanya tentang program ini.

Sumber: https://example.com/halaman/
```

Baris baru dalam pesan akun atau pesan default tetap dipertahankan. Baris sumber ditambahkan setelah satu baris kosong.

Di **CTA WA → Format sumber dalam pesan WA**, Anda dapat mengubah:

- **Kata sebelum nilai sumber**, misalnya `Asal` agar pesan menampilkan `Asal:`.
- **Gaya huruf nilai**: sesuai nilai asli, UPPERCASE, Title Case, atau lowercase. Pilihan ini berlaku untuk nilai UTM dan potongan ID, bukan kata label.
- **Nilai yang ditambahkan**: Source, Medium, Campaign, Content, Term, Match Type, ID sesi, dan ID browser. Urutannya mengikuti daftar pilihan. ID memakai delapan karakter awal; nilai UTM tetap disimpan lengkap di laporan jika diaktifkan pada Settings.
- **Pemisah antar nilai**, misalnya `+`, `-`, atau ` | `. Pemisah kosong menggabungkan nilai tanpa tanda. Jika semua pilihan nilai tidak dicentang, plugin tidak menambah baris sumber.

Di bagian **Saat tidak ada UTM**, gunakan sakelar untuk mengaktifkan atau menonaktifkan baris sumber pengganti. Atur kata sebelum URL secara terpisah dari label UTM. Pilih **URL halaman pengunjung** agar link mengikuti halaman yang diklik, atau **Link khusus** untuk memakai satu URL tetap. Anda juga dapat mencentang **ID sesi**, **ID browser**, keduanya, atau tidak satu pun. ID yang dipilih ditambahkan setelah URL dengan pemisah dan gaya huruf pada format sumber di atas; masing-masing memakai delapan karakter awal. Saat fitur dimatikan, pesan tanpa UTM tidak mendapat baris sumber. Pratinjau pada panel akun berubah langsung saat Anda mengatur format. Simpan pengaturan agar berlaku di halaman publik. Riwayat klik baru menyimpan baris sumber yang benar-benar ditambahkan, sehingga perubahan format berikutnya tidak mengubah tampilan sumber untuk klik lama.

## Membuat CTA WhatsApp

Di **Traffic Recorder → CTA WA**, isi panel **Tambah akun CTA**:

1. Masukkan **nama akun**, misalnya `Admin Penjualan`.
2. Masukkan nomor dengan kode negara tanpa tanda `+`, spasi, atau angka `0` di depan. Pola `62XXXXXXXXXX` hanya penanda tempat untuk nomor Anda.
3. Isi judul, avatar, dan pesan sesuai kebutuhan.
4. Pilih hari aktif serta semua halaman atau halaman tertentu. Jika memakai **Hanya halaman yang dipilih** dan ingin tombol muncul di URL utama situs, centang **Beranda / homepage**. Ini juga berlaku bila beranda menampilkan posting, tanpa halaman statis.
5. Simpan akun. Tambahkan akun lain bila perlu.

Geser baris akun pada tabel untuk mengubah prioritas di daftar chat. Sakelar **Daftar chat** pada setiap baris langsung menyimpan apakah akun tampil di daftar mengambang; shortcode tetap bisa dipakai saat sakelar mati. Tombol mengambang hanya ada **satu**; ketika dibuka, tombol itu menampilkan daftar akun yang tersedia. Posisi kiri atau kanan, salam pembuka, serta tombol mengambang aktif atau tidak diatur pada bagian **Tombol mengambang & tautan WhatsApp**.

Anda juga bisa memasang tautan WhatsApp akun tertentu pada konten halaman dengan shortcode:

```text
[utr_whatsapp id="1"]
```

Ganti `1` dengan ID pada kolom **Shortcode** di tabel akun, atau salin dari panel **Shortcode WA** di sidebar saat mengedit Post atau Page. Shortcode tampil sebagai pil kecil satu baris berisi gambar dan nama akun, mengikuti ukuran huruf konten. Jika akun belum memiliki gambar, ikon WhatsApp digunakan. Pengaturan hari aktif dan halaman berlaku untuk tombol mengambang maupun shortcode.

## Tautan WhatsApp yang sudah ada

### Variabel dalam Pesan akun

Di **CTA WA → Pesan akun**, pilih variabel lalu klik **Sisipkan variabel** untuk menambahkannya pada posisi kursor. Pilih salah satu nilai UTM, ID Sesi, ID Browser, atau Custom. Isi **Nilai custom** untuk akun tersebut jika menggunakan `{custom}`.

Contoh pesan: `Halo, saya dari {utm_source}. Kode sesi: {session_id}. Promo: {custom}`. UTM mengikuti pilihan sumber localStorage/sessionStorage di CTA WA; ID menggunakan 8 karakter pertama. Variabel yang tidak tersedia menjadi kosong. Pratinjau menggunakan contoh UTM dan ID, serta nilai custom yang sedang diisi. Variabel juga diganti dalam pesan pada parameter `text` tautan lama; isi pesan lainnya tetap dipertahankan. Format `[{utm_source}]` menghasilkan, misalnya, `[gads]`.

Variabel juga menerima kurung ganda (`{{utm_source}}`), spasi di dalam kurung, dan huruf besar/kecil. `{browser_id}` adalah alias `{visitor_id}`, sedangkan `{utm_matchtype}` adalah alias `{utm_match_type}`. Gaya huruf UTM dan ID mengikuti pengaturan Format sumber; nilai custom dipertahankan sesuai isian.

Pesan pada tautan diprioritaskan, lalu pesan akun, lalu pesan default jika keduanya kosong. Tautan WA yang sudah ada diproses saat opsi **Tambahkan sumber ke tautan WA yang sudah ada** aktif. Tombol akun dan shortcode selalu diproses.

Untuk mengganti salam atau judul daftar admin, gunakan **Simpan posisi, salam & tampilan tombol**. Tombol ini menyimpan tampilan tanpa memerlukan validasi URL sumber. Tombol **Simpan pengaturan CTA WA** menyimpan seluruh pengaturan, termasuk posisi tombol, salam, pesan default, dan sumber. Perubahan pengaturan CTA WA serta versi plugin memicu pembersihan cache LiteSpeed bila tersedia. Versi aktif ditampilkan pada header halaman plugin.

Plugin mengenali tautan seperti:

```text
https://api.whatsapp.com/send?phone=62XXXXXXXXXX
https://wa.me/send?phone=62XXXXXXXXXX
https://wa.me/62XXXXXXXXXX
```

`62XXXXXXXXXX` adalah pola penanda, bukan nomor yang dapat dihubungi. Ganti dengan nomor WhatsApp milik Anda saat memasang tautan.

Ada **dua pengaturan terpisah**:

- Di **CTA WA**, aktifkan **Tambahkan sumber ke tautan WA yang sudah ada** bila pesan pada tautan tersebut perlu ditambah `Sumber:`. Nomor tujuan dan pesan yang sudah terisi tetap digunakan.
- Di **Settings**, aktifkan **Catat klik tautan WhatsApp yang sudah ada** bila kliknya perlu masuk ke Dashboard Chat WA. Mematikan pilihan ini tidak mematikan pencatatan klik tombol akun dan shortcode buatan plugin.

Keduanya dapat diaktifkan atau dimatikan sendiri-sendiri. Jika tautan lama sudah memiliki parameter `text`, plugin mempertahankan pesan itu lalu menambahkan sumber setelahnya. Jika belum ada pesan, plugin memakai pesan akun yang nomornya cocok atau pesan default.

## Clean URL di address bar

Aktifkan **Clean URL** di **Settings** atau **UTM Manager**. Skrip di bagian head halaman segera menghapus parameter UTM yang didukung, termasuk `utm_matchtype`, `utm_match_type`, dan variasi huruf besar/kecil, dari URL yang terlihat di address bar. Parameter URL lain dan bagian `#` tetap dipertahankan.

Contoh:

```text
Sebelum: https://example.com/kelas/?utm_source=GADS&utm_medium=CPC&kupon=OK#daftar
Sesudah: https://example.com/kelas/?kupon=OK#daftar
```

UTM dari tautan awal ditangkap sebelum URL dibersihkan, lalu dipakai untuk pencatatan dan pesan WA. Tautan kampanye yang dibagikan **tetap harus memuat UTM**. Clean URL berjalan di browser, sehingga URL awal masih dapat terlihat sebentar dan tetap terkirim dalam permintaan halaman pertama.

## Membaca dan mengekspor laporan

- **Sesi unik** dihitung dari ID sesi. Satu ID browser bisa menghasilkan beberapa sesi.
- **Tampilan halaman** menghitung halaman yang dibuka, sehingga satu sesi dapat memiliki beberapa tampilan halaman.
- **Top Source/Medium/Campaign/Content/Terms** di UTM Manager menghitung sesi unik sesuai filter tanggal dan sumber.
- **Top Akun/Nomor** di Dashboard Chat WA diurutkan berdasarkan sesi unik yang mengklik.
- UTM Manager dan Dashboard Chat WA menyediakan periode cepat **Hari ini**, **7 hari**, **12 hari**, **1 bulan** (30 hari), dan **3 bulan** (90 hari). Hari ini termasuk dalam setiap periode; filter sumber, ID browser (UTM Manager), dan akun (Chat WA) tetap dipertahankan.
- Filter di UTM Manager dapat dibatasi menurut tanggal, `utm_source`, dan ID browser. Klik **Ekspor CSV** untuk mengunduh baris sesuai filter. Waktu dalam CSV memakai UTC; tampilan dashboard memakai zona waktu WordPress.
- Kolom **URL perujuk** menampilkan URL lengkap bila browser memberikannya. Kebijakan perujuk situs asal dapat membatasi nilainya menjadi domain saja atau kosong. Baris sebelum versi 2.1.0 tetap menampilkan domain yang sebelumnya tercatat; URL lengkapnya tidak dapat dipulihkan. CSV menyertakan domain dan URL perujuk sebagai kolom terpisah.
- Kolom **Match Type** memuat nilai dari `utm_match_type` atau `utm_matchtype`. Dashboard Chat WA juga menampilkan ID sesi agar kode pada pesan dapat dicocokkan dengan klik dan kunjungan.

Jika ingin melihat beberapa sesi dari satu browser, pilih ID di bagian **ID browser & sesi**, lalu periksa kolom **Sesi** pada tabel detail. ID pada tabel detail ditampilkan singkat; arahkan kursor ke ID untuk melihat nilainya lengkap.

## Memeriksa instalasi

1. Keluar dari akun WordPress atau buka jendela privat.
2. Buka halaman dengan tautan UTM contoh di atas. Pastikan kunjungan muncul di Dashboard Trafik & UTM dan detail UTM Manager.
3. Buka tautan kedua dalam tab yang sama dengan `?utm_source=EMAIL`. Pastikan kunjungan baru memakai `EMAIL` dan parameter UTM lain yang tidak ada menjadi kosong.
4. Klik tombol WA. Periksa teks `Sumber:` sebelum mengirim pesan dan pastikan kliknya muncul di Dashboard Chat WA.
5. Bila Clean URL aktif, pastikan UTM hilang dari address bar sementara parameter lain tetap ada.

Jika data belum tampil, periksa pilihan UTM di Settings, JavaScript, WordPress REST API, plugin cache, serta pemblokir privasi browser. Halaman yang dilihat saat login sebagai admin tidak dicatat. Sesudah mengganti pengaturan, bersihkan cache halaman atau CDN bila situs Anda memakainya agar pengunjung menerima konfigurasi terbaru.

## Memperbarui dan menghapus plugin

Untuk memperbarui, unggah ZIP versi baru melalui WordPress dan pertahankan plugin serta data yang sudah ada. Pembaruan skema database dijalankan oleh plugin saat versi berubah. **Menghapus plugin melalui WordPress menjalankan `uninstall.php`**, yang menghapus tabel laporan dan opsi plugin. Buat cadangan database sebelum menghapus plugin jika riwayat masih diperlukan.
