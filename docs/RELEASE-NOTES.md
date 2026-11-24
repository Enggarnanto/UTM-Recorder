# UTM Traffic Recorder 2.6.17

- Tambahkan pengaturan CTA WA untuk menampilkan URL halaman ketika tepat satu UTM terisi. Opsi nonaktif secara bawaan.
- Baris `Halaman` ditambahkan terpisah setelah format sumber. URL tidak membawa query parameter atau fragmen.
- Jumlah UTM dihitung sebelum checkbox nilai pesan diterapkan. UTM pada URL diprioritaskan, kemudian Local storage atau Sesi aktif. Kondisi tanpa UTM tetap mengikuti pengaturan fallback.
- Nilai UTM laporan, ID sesi/browser, kolom Sumber riwayat klik, dan struktur payload Google Sheets/Webhook tetap mengikuti mekanisme sebelumnya.
- Pratinjau pengaturan menyertakan contoh satu UTM; pengujian mencakup penyimpanan opsi, klik berulang, serta kesamaan payload saat fitur aktif dan nonaktif.

Gunakan asset `utm-traffic-recorder.zip` untuk instalasi atau pembaruan WordPress. Arsip Source code otomatis dari GitHub bukan paket instalasi plugin. `utm-webhook-receiver.zip` adalah paket backend terpisah; rilis ini tidak memerlukan perubahan receiver.

## Previous release: 2.6.16

- Verified canonical WordPress installation identity: `utm-traffic-recorder/utm-traffic-recorder.php`. The build rejects nested plugin folders, nonportable paths, unexpected entry points, and version mismatches. The ZIP filename may contain the version; the internal folder never does.
- New administrator-only Log aktivitas: tracking results, setting changes, Sheets/Webhook operations, plugin lifecycle, and GitHub checks. Includes level/category filters, pagination, and CSV export. Sensitive credentials and payloads are excluded/redacted. Daily cleanup keeps 30 days and trims to 50,000 entries.
- Build artifacts now go to ignored `dist/`; legacy release archives are retained locally but removed from Git tracking. Added `.gitignore` and `.gitattributes`. Source, integrations, docs, tests, and release tooling are separate.

- Native WordPress update notifications from published GitHub releases, plus a manual check in Settings. Install this version manually once; subsequent higher releases with a valid ZIP asset can be updated from Plugins.
- Generic Webhook/API integration in Settings, without organization-specific branding or endpoints. Works with any backend implementing the documented JSON/Bearer contract.
- Independent Google Sheets and webhook queues, retries, acknowledgement checks, and history replay.
- Backend developer package: language-independent API contract, plain PHP/PDO receiver, CodeIgniter 3/4 adapters, and MySQL/MariaDB schema.
- Existing Sheets synchronization and WIB receiver remain available. No change to local click retention.

The receiving API must be installed/configured by its maintainer. No external backend is deployed by installing the WordPress plugin. Use `utm-traffic-recorder.zip` for WordPress; `utm-webhook-receiver.zip` is a separate backend developer package. GitHub's generated source-code ZIP is not an installable plugin package.
