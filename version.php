<?php
/**
 * File informasi versi untuk plugin lokal 'Course Badge'.
 *
 * File ini dibaca oleh Moodle untuk mengidentifikasi plugin,
 * mengelola proses instalasi dan upgrade.
 *
 * @package    auto_badge
 * @copyright  2025 Miika
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Nama komponen plugin. Format: 'plugintype_pluginname'.
// Ini adalah ID unik pluginmu di seluruh sistem Moodle.
$plugin->component = 'local_auto_badge';

// Versi plugin dalam format YYYYMMDDHH (TahunBulanTanggalJam).
// NAIKKAN angka ini setiap kali kamu membuat perubahan di db/upgrade.php.
// Inilah pemicu utama Moodle untuk menjalankan skrip upgrade.
$plugin->version   = 2025101604;

// Versi Moodle minimal yang dibutuhkan agar plugin ini bisa berjalan.
// Angka ini bisa ditemukan di version.php utama Moodle.
// 2024052700 adalah Moodle 5.0.
$plugin->requires  = 2024052700;

// Tingkat kestabilan plugin. Pilihan:
// MATURITY_ALPHA: Versi awal, mungkin banyak bug.
// MATURITY_BETA: Fitur sudah lengkap, masih dalam tahap tes.
// MATURITY_RC: Release Candidate, hampir stabil.
// MATURITY_STABLE: Rilis stabil dan siap untuk production.
$plugin->maturity  = MATURITY_RC;

// Nama rilis yang mudah dibaca manusia.
// Ditampilkan di halaman admin plugin.
$plugin->release   = 'v1.0.0-releasecandidate';