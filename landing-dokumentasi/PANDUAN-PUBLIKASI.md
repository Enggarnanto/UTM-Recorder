# Publikasi dokumentasi di Blogger

Gunakan **blogger-page.html** untuk halaman Blogger. File ini merupakan potongan kode, tanpa tag html, head, atau body. CSS dibatasi pada kontainer dokumentasi agar tidak mengubah elemen tema blog. JavaScript juga bekerja pada kontainer tersebut.

1. Di Blogger, buka **Pages / Halaman** lalu buat halaman baru.
2. Beralih ke **HTML view / Tampilan HTML**.
3. Hapus potongan dokumentasi versi lama dari halaman, lalu salin seluruh isi blogger-page.html dan tempel sebagai penggantinya.
4. Isi judul halaman, misalnya **Dokumentasi UTM Traffic Recorder**, lalu pratinjau pada desktop dan ponsel.
5. Simpan atau publikasikan ketika tampilannya sesuai. Setelah tersimpan, periksa menu ponsel, tab penjelasan, contoh pesan, dan tombol salin di halaman publik.

Jangan beralih kembali ke mode Compose setelah menempelkan kode, karena editor visual dapat mengubah struktur HTML, CSS, atau JavaScript. Jika editor Blogger atau tema yang dipakai menghapus tag style/script, masukkan CSS melalui **Theme → Customize → Advanced → Add CSS** dan JavaScript melalui **Theme → Edit HTML** atau gadget HTML/JavaScript; elemen HTML tetap di halaman. Periksa perubahan pada tema di pratinjau sebelum dipublikasikan.

Font Sora dan Plus Jakarta Sans dimuat dari Google Fonts; bila tidak tersedia, font sistem dipakai. Ilustrasi dashboard dan contoh pesan bukan data nyata. Halaman ini menjelaskan plugin versi **2.6.4**.

Versi Blogger memakai logo dari URL Blogger yang diberikan, serta baris fitur berbasis elemen div agar editor tidak mengubah daftar dan menampilkan kode entitas seperti &amp;#10;.

File **index.html** tetap tersedia sebagai versi halaman mandiri. Jika kontennya diperbarui, jalankan **python buat-versi-blogger.py** dari folder ini untuk memperbarui blogger-page.html. Tidak ada ZIP plugin yang dibuat atau diubah.
