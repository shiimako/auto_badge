<?php

namespace local_auto_badge\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/auto_badge/lib.php');

use core\task\scheduled_task;
use local_auto_badge\manager;

/**
 * Scheduled task untuk menyinkronkan badge dinamis (Legend & Hero).
 *
 * Tugas ini berjalan secara berkala (misalnya, setiap hari) untuk
 * menghitung ulang peringkat teratas di setiap course dan grup, lalu
 * memberikan atau mencabut badge sesuai dengan data terbaru dari
 * plugin Level Up! atau gradebook.
 *
 * @package    local_auto_badge
 */
class update_badges_task extends scheduled_task
{

    /**
     * Mendapatkan nama tugas yang akan ditampilkan di antarmuka admin.
     *
     * @return string Nama tugas.
     */
    public function get_name(): string
    {
        return get_string('task_updatebadges', 'local_auto_badge');
    }

    /**
     * Method utama yang akan dieksekusi oleh Moodle cron.
     *
     * Method ini memanggil manajer leaderboard untuk melakukan semua
     * pekerjaan berat.
     */
    public function execute()
    {
        error_log('[local_auto_badge] Memulai sinkronisasi badge dinamis...');

        manager::cleanup_orphaned_badges();
        error_log('--> Proses pembersihan data terlantar selesai.');
        
        manager::run_scheduled_sync();

        error_log('[local_auto_badge] Sinkronisasi badge dinamis selesai.');
    }
}
