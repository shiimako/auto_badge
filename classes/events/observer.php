<?php

namespace local_auto_badge\events;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/auto_badge/lib.php');

use context_course;
use local_auto_badge\badge_utils;
use local_auto_badge\manager;

/**
 * Kelas observer untuk menangani event-event Moodle.
 */
class observer
{

    /**
     * Menangani event ketika sebuah course baru dibuat.
     * Fungsi ini memastikan badge terkait dibuat dan mencatatnya ke tabel histori.
     */
    public static function course_created(\core\event\course_created $event): bool
    {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            $courseid = $event->courseid;
            $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
            $ctx = context_course::instance($courseid);

            $newbadges = badge_utils::ensure_local_auto_badges($courseid, $course->fullname, $ctx);

            error_log("[DEBUG] Observer course_created: Hasil dari helper::ensure_local_auto_badges(): " . print_r($newbadges, true));

            if (empty($newbadges) || !isset($newbadges[manager::LEGEND])) {
                error_log("[ERROR] Observer course_created: Gagal membuat atau menemukan badge utama (Legend) untuk course baru ID {$courseid}");
                $DB->rollback_delegated_transaction($transaction); 
                return true; 
            }

            $mainbadge = $newbadges[manager::LEGEND];

            $newrecord = new \stdClass();
            $newrecord->courseid     = $course->id;
            $newrecord->name         = $course->fullname; 
            $newrecord->badgeid      = $mainbadge->id;   
            $newrecord->timemodified = time();

            $DB->insert_record('local_course_history', $newrecord);

            error_log("[SUCCESS] Observer course_created: Course ID {$courseid} dan Badge ID {$mainbadge->id} berhasil dilacak.");
            $DB->commit_delegated_transaction($transaction);
        } catch (\Exception $e) {
            error_log("[FATAL ERROR] Observer course_created: " . $e->getMessage() . "\n" . $e->getTraceAsString());

            if (isset($transaction) && $transaction->is_pending()) {
                $DB->rollback_delegated_transaction($transaction);
            }
        }

        return true;
    }

    /**
     * Menangani event ketika sebuah grup baru dibuat.
     */
    public static function group_created(\core\event\group_created $event): bool
    {
        global $DB;
        $transaction = $DB->start_delegated_transaction();

        try {

            $course = $DB->get_record('course', ['id' => $event->courseid], 'id, fullname', MUST_EXIST);
            $group = $DB->get_record('groups', ['id' => $event->objectid], 'id, name', MUST_EXIST);
            $ctx = context_course::instance($event->courseid);

            // Panggil helper untuk memastikan badge Hero dibuat.
            $newbadge = badge_utils::ensure_course_group_badge($event->courseid, $course->fullname, $ctx, $group->name);

            if (empty($newbadge) || !isset($newbadge[manager::HERO])) {
                $DB->rollback_delegated_transaction($transaction); 
                return true; 
            }

            $mainbadge = $newbadge[manager::HERO];

            $newrecord = new \stdClass();
            $newrecord->groupid      = $group->id;
            $newrecord->name         = $group->name;
            $newrecord->badgeid      = $mainbadge->id;
            $newrecord->timemodified = time();

            $DB->insert_record('local_group_history', $newrecord);
            $DB->commit_delegated_transaction($transaction);
        } catch (\Exception $e) {
            error_log("[ERROR] Observer group_created: " . $e->getMessage());
            if (isset($transaction) && $transaction->is_pending()) {
                $DB->rollback_delegated_transaction($transaction);
            }
        }

        return true;
    }

    /**
     * Menangani event ketika detail sebuah course diubah.
     * Menggunakan properti 'other' dari event yang lebih andal.
     */
    public static function course_updated(\core\event\course_updated $event): bool
    {
        global $DB;

        try {
            $oldcourse = $DB->get_record('local_course_history', ['courseid' => $event->courseid], 'name', MUST_EXIST);
            $oldname = $oldcourse->name;

            $newcourse = $DB->get_record('course', ['id' => $event->courseid], 'fullname', MUST_EXIST);
            $newname = $newcourse->fullname;


            if ($oldname == $newname) {
                error_log('[INFO] Observer course_updated: Nama course tidak berubah, tidak perlu memperbarui apa pun.');
                return true;
            } else {

                manager::on_course_updated($event, $oldname, $newname);

                $DB->set_field('local_course_history', 'name', $newname, ['courseid' => $event->courseid]);
                error_log("[SUCCESS] Observer course_updated: Nama course diubah dari '{$oldname}' menjadi '{$newname}' untuk course ID: {$event->courseid}");
                return true;
            }
        } catch (\Exception $e) {
            error_log("[ERROR] Observer course_updated: " . $e->getMessage());
        }
        return true;
    }

    /**
     * Menangani event ketika detail sebuah grup diubah.
     * Menggunakan properti 'other' dari event yang lebih andal.
     */
    public static function group_updated(\core\event\group_updated $event): bool
    {
        global $DB;

        try {
            $oldgroup = $DB->get_record('local_group_history', ['groupid' => $event->objectid], 'name', MUST_EXIST);
            $oldname = $oldgroup->name;

            $newgroup = $DB->get_record('groups', ['id' => $event->objectid], 'name', MUST_EXIST);
            $newname = $newgroup->name;

            if ($oldname == $newname) {
                error_log('[INFO] Observer group_updated: Nama grup tidak berubah, tidak perlu memperbarui apa pun.');
                return true; 
            } else {
                try {
                    manager::on_group_updated($event, $oldname, $newname);

                    $DB->set_field('local_group_history', 'name', $newname, ['groupid' => $event->objectid]);
                    error_log("[SUCCESS] Observer group_updated: Nama grup diubah dari '{$oldname}' menjadi '{$newname}' untuk grup ID: {$event->objectid}");
                } catch (\Exception $e) {
                    error_log("[ERROR] Observer group_updated (inner): " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("[ERROR] Observer group_updated: " . $e->getMessage());
        }
        return true;
    }

    /**
     * Membersihkan data histori saat sebuah course dihapus.
     *
     * @param \core\event\course_deleted $event Event yang dipicu.
     * @return bool
     */
    public static function course_deleted(\core\event\course_deleted $event): bool
    {
        global $DB;

        try {
            $DB->delete_records('local_course_history', ['courseid' => $event->objectid]);
            error_log("[CLEANUP] Menghapus data histori untuk course ID {$event->objectid} yang telah dihapus.");
        } catch (\Exception $e) {
            error_log("[ERROR] Gagal membersihkan histori untuk course ID {$event->objectid}: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Membersihkan data histori saat sebuah grup dihapus.
     *
     * @param \core\event\group_deleted $event Event yang dipicu.
     * @return bool
     */
    public static function group_deleted(\core\event\group_deleted $event): bool
    {
        global $DB;

        try {
            $DB->delete_records('local_group_history', ['groupid' => $event->objectid]);
            error_log("[CLEANUP] Menghapus data histori untuk grup ID {$event->objectid} yang telah dihapus.");
        } catch (\Exception $e) {
            error_log("[ERROR] Gagal membersihkan histori untuk grup ID {$event->objectid}: " . $e->getMessage());
        }

        return true;
    }
}
