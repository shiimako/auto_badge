# Auto Badge (local_auto_badge) for Moodle

Plugin lokal ini mengotomatiskan pembuatan dan pemberian lencana (badges) pencapaian untuk siswa berprestasi di dalam kursus Moodle.

## ✅ Fitur Utama

* **Pembuatan Lencana Otomatis**: Secara otomatis membuat 2 kerangka lencana (`Course Legend` & `Course Hero`) setiap kali sebuah kursus baru dibuat.
* **Sinkronisasi Terjadwal**: Menjalankan tugas per jam (cron job) untuk secara berkala mengevaluasi ulang peringkat dan memberikan lencana kepada siswa yang berhak.
* **Pembaruan Nama Dinamis**: Jika nama kursus atau grup diubah, nama lencana yang terkait akan diperbarui secara otomatis untuk menjaga konsistensi.
* **Pencabutan Lencana Dinamis (Opsional)**: Jika diaktifkan, lencana akan secara otomatis dicabut dari pemegang sebelumnya jika ada siswa baru yang berhasil meraih peringkat teratas, memastikan lencana selalu dipegang oleh juara bertahan.
* **Sistem Peringkat Fleksibel**: Menggunakan sistem peringkat 3-lapis untuk menentukan pemenang secara adil.

## 🏆 Bagaimana Cara Pemilihan Juara?

Plugin ini menggunakan sistem peringkat yang sangat detail untuk menentukan pemenang secara adil, terutama saat ada nilai yang sama. Peringkat ditentukan berdasarkan urutan prioritas berikut:

1.  **Nilai Akhir Kursus (Gradebook)**: Jika plugin Level Up! tidak terinstal, atau jika ada dua atau lebih siswa dengan poin XP yang sama persis, plugin akan menggunakan **nilai akhir kursus** (`finalgrade`) sebagai penentu kedua.
2.  **Waktu Pencapaian**: Jika poin XP dan nilai akhir sama, lencana akan diberikan kepada siswa yang **mencapai nilai tersebut paling awal** (`timemodified`).
3.  **Poin XP (Level Up!)**: Kriteria utama adalah total poin dari plugin **Level Up!** (`block_xp`). Siswa dengan poin XP tertinggi akan menjadi juara.

> **Catatan Penting**: Berkat penggunaan `DENSE_RANK()` dalam query, jika ada beberapa siswa yang terikat di peringkat pertama dengan semua kriteria yang sama, **semua siswa tersebut akan menerima lencana**.

## ⚙️ Persyaratan

* Moodle 5.0
* (Opsional) Plugin **Level Up!** (versi gratis) untuk pemeringkatan berbasis Poin Pengalaman (XP). Jika tidak terinstal, plugin akan otomatis menggunakan nilai akhir kursus.

## 💾 Instalasi

#### Metode 1: Instalasi via Git
1.  Buka terminal di dalam folder root Moodle Anda.
2.  Jalankan perintah: `git clone https://github.com/shiimako/auto_badge.git local/auto_badge`
3.  Masuk ke Moodle sebagai admin dan pergi ke `Site administration → Notifications` untuk menyelesaikan proses instalasi.

#### Metode 2: Instalasi Manual
1.  Unduh file ZIP plugin ini.
2.  Ekstrak isinya ke dalam folder `[folder-moodle-anda]/local/`.
3.  Pastikan nama folder hasil ekstraksi adalah `auto_badge`. Path akhirnya harus: `local/auto_badge/`.
4.  Masuk ke Moodle sebagai admin dan pergi ke `Site administration → Notifications` untuk menyelesaikan proses instalasi.

## 🛠️ Pengaturan

Anda dapat menemukan halaman pengaturan di:
`Site administration → Plugins → Local plugins → Auto Badge`

* **Dynamic revoke (on/off)**: Aktifkan untuk secara otomatis mencabut lencana dari pemegang sebelumnya jika ada juara baru.
* **Minimum XP for Legend/Hero**: Atur batas minimal poin XP yang harus dimiliki siswa agar memenuhi syarat menjadi pemenang. Setel ke 0 untuk menonaktifkan.
* **Minimum Grade for Legend/Hero**: Atur batas minimal nilai akhir kursus yang harus dimiliki siswa agar memenuhi syarat. Setel ke 0 untuk menonaktifkan.

## 🎨 Kustomisasi

* Untuk mengubah gambar lencana, cukup ganti file gambar di dalam folder `pix/` dengan gambar Anda sendiri. Pastikan nama file tetap sama (`legend.png` dan `hero.png`) untuk menghindari pengeditan kode.

## 📄 Lisensi

Plugin ini dirilis di bawah lisensi GNU GPL v3 atau yang lebih baru.