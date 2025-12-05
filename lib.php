<?php

/**
 * Pustaka fungsi inti ("Mesin") untuk plugin local_auto_badge.
 *
 * @package    local_auto_badge
 * @copyright  2025 Miika
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_auto_badge;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/badges/lib.php');
require_once($CFG->dirroot . '/lib/badgeslib.php');

use core_badges\badge;
use context_course;
use moodle_exception;
use stdClass;


// =========================================================================
// CLASS 1: MANAGER - Koordinator & Logika Peringkat
// =========================================================================

/**
 * Class Manager
 * Bertanggung jawab atas alur kerja tingkat tinggi seperti sinkronisasi
 * dan menentukan siapa pemenangnya.
 */
class manager
{

    // Konstanta tipe badge untuk referensi yang mudah.
    public const LEGEND = 'Course Legend';
    public const HERO   = 'Course Hero';

    // =========================================================================
    // BAGIAN 1: FUNGSI KOORDINATOR UTAMA 
    // =========================================================================

    /**
     * Fungsi utama yang akan dipanggil oleh Cron Job.
     * Mengoordinasikan seluruh proses sinkronisasi untuk semua course.
     */
    public static function run_scheduled_sync(): void
    {
        global $DB;
        $courses = $DB->get_records('course', [], '', 'id, fullname');
        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }
            // Panggil fungsi sinkronisasi untuk setiap course.
            self::sync_for_course((int)$course->id);
        }
    }

    /**
     * Menjalankan proses sinkronisasi untuk satu course tunggal.
     *
     * @param int $courseid ID dari course yang akan disinkronkan.
     */
    public static function sync_for_course(int $courseid): void
    {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);
        $ctx = \context_course::instance($courseid);
        error_log("Memproses badge dinamis untuk course: {$course->fullname} (ID: {$courseid})");

        badge_utils::ensure_local_auto_badges($courseid, $course->fullname, $ctx);

        $config = get_config('local_auto_badge');
        $dynamicrevoke = !empty($config->dynamicrevoke);
        $minpoints = (int)($config->minpoints ?? 0);
        $mingrade = (float)($config->mingrade ?? 0.0);

        self::_sync_legend_badge($courseid, $ctx, $course->fullname, $dynamicrevoke, $minpoints, $mingrade);
        self::_sync_hero_badges($courseid, $ctx, $course->fullname, $dynamicrevoke, $minpoints, $mingrade);
    }

    /**
     * Method untuk tugas terjadwal (cron) untuk membersihkan badge dari course yang sudah terhapus.
     */
    public static function cleanup_orphaned_badges(): void
    {
        global $DB;
        $sql = "SELECT b.id, b.name FROM {badge} b
                 WHERE b.type = :type AND b.courseid > 0
                   AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = b.courseid)";
        $params = ['type' => \BADGE_TYPE_COURSE];
        $orphans = $DB->get_records_sql($sql, $params);

        foreach ($orphans as $orphan) {
            try {
                (new badge($orphan->id))->delete();
                error_log("[MANAGER: ORPHAN CLEANUP] Menghapus badge yatim ID: {$orphan->id}, Nama: {$orphan->name}");
            } catch (moodle_exception $e) {
                error_log("[MANAGER: ORPHAN CLEANUP ERROR] Gagal menghapus badge {$orphan->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Memperbarui nama badge terkait saat nama course berubah.
     */
    public static function on_course_updated(\core\event\course_updated $event, string $oldname, string $newname): void
    {
        global $DB;

        $courseid = $event->courseid;

        //Cari semua badge (Legend & Hero) yang terkait dengan NAMA LAMA course ini.
        $sql = "SELECT id, name, description FROM {badge}
            WHERE courseid = :courseid
              AND name LIKE :oldnamepattern";
        $params = [
            'courseid'       => $courseid,
            'oldnamepattern' => '% - ' . $oldname . ' %'
        ];

        $badges_to_update = $DB->get_records_sql($sql, $params);

        if (empty($badges_to_update)) {
            error_log("[MANAGER:ONCOURSE_UPDATED] Tidak ada badge yang perlu diupdate untuk course '{$oldname}'.");
            return;
        }

        foreach ($badges_to_update as $oldbadge) {
            try {
                $new_badge_name = str_replace($oldname, $newname, $oldbadge->name);
                $new_badge_desc = str_replace($oldname, $newname, $oldbadge->description);

                $updatedbadge = new \stdClass();
                $updatedbadge->id          = $oldbadge->id;
                $updatedbadge->name        = $new_badge_name;
                $updatedbadge->description = $new_badge_desc;

                $DB->update_record('badge', $updatedbadge);
            } catch (\Exception $e) {
                error_log("[MANAGER:ONCOURSE_UPDATED] [ERROR] Gagal update badge ID {$oldbadge->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Memperbarui nama badge Hero saat nama grup berubah.
     */
    public static function on_group_updated(\core\event\group_updated $event, string $oldgroupname, string $newgroupname): void
    {
        global $DB;

        $courseid   = $event->courseid;
        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid], MUST_EXIST);
        $periode    = badge_utils::get_academic_year();

        error_log("[HELPER:ONGROUP_UPDATED] Dipicu oleh group update | CourseID: {$courseid}, Course: '{$coursename}', OldGroup: '{$oldgroupname}', NewGroup: '{$newgroupname}'");

        $expected_old_name = manager::HERO . ' - ' . $coursename . ' - ' . $oldgroupname . ' (' . $periode . ')';
        error_log("[HELPER:ONGROUP_UPDATED] Mencari badge lama bernama '{$expected_old_name}'");

        if (!$badge = badge_utils::find_badge_by_name($courseid, $expected_old_name)) {
            error_log("[HELPER:ONGROUP_UPDATED] [WARNING] Tidak bisa menemukan badge lama dengan nama '{$expected_old_name}' untuk diupdate.");
            return;
        }

        $new_badge_name = manager::HERO . ' - ' . $coursename . ' - ' . $newgroupname . ' (' . $periode . ')';
        $new_badge_desc = 'Peringkat 1 di leaderboard grup ' . $newgroupname . ' dalam course ' . $coursename . ' pada tahun ajaran ' . $periode;

        try {
            $updatedbadge = new \stdClass();
            $updatedbadge->id          = $badge->id;
            $updatedbadge->name        = $new_badge_name;
            $updatedbadge->description = $new_badge_desc;

            $DB->update_record('badge', $updatedbadge);

            error_log("[HELPER:ONGROUP_UPDATED] [SUCCESS] Badge ID {$badge->id} diperbarui menjadi '{$new_badge_name}'");
        } catch (\Exception $e) {
            error_log("[HELPER:ONGROUP_UPDATED] [ERROR] Gagal update badge ID {$badge->id}: " . $e->getMessage());
        }
    }

    // =========================================================================
    // BAGIAN 2: LOGIKA INTERNAL SINKRONISASI BADGE
    // =========================================================================

    /**
     * Menyinkronkan lencana 'Course Legend'.
     */
    private static function _sync_legend_badge(int $courseid, context_course $ctx, string $coursename, bool $revoke, int $minpoints, float $mingrade): void
    {
        $badgename = manager::LEGEND . ' - ' . $coursename . ' (' . badge_utils::get_academic_year() . ')';

        if (!$badge = badge_utils::find_badge_by_name($courseid, $badgename)) {
            error_log("  Peringatan: Badge Legend '{$badgename}' tidak ditemukan.");
            return;
        }

        $topusers = self::_get_top_users_for_course($courseid);
        $winnerids = [];

        if (!empty($topusers)) {
            foreach ($topusers as $winner) {
                $winner = (object)$winner;
                if (self::_is_winner_valid($winner, $minpoints, $mingrade)) {
                    $winnerids[] = $winner->userid;
                    $evidence = "Peringkat teratas di course '{$coursename}' dengan skor {$winner->score} ({$winner->source})";
                    badge_utils::issue_badge_with_evidence($badge->id, $winner->userid, '', $evidence);
                    error_log("  SUCCESS: Badge Legend diberikan ke pengguna {$winner->userid} (Skor: {$winner->score} {$winner->source})");
                } else {
                    error_log("  INFO: Pemenang Legend (user {$winner->userid}) tidak memenuhi syarat skor minimal.");
                }
            }
        } else {
            error_log("  INFO: Tidak ada data leaderboard untuk menentukan pemenang Legend.");
        }

        if ($revoke) {
            badge_utils::revoke_from_non_winners($badge->id, $winnerids);
        }
    }

    /**
     * Menyinkronkan lencana 'Course Hero'.
     */
    private static function _sync_hero_badges(int $courseid, context_course $ctx, string $coursename, bool $revoke, int $minpoints, float $mingrade): void
    {
        $groupwinners = self::_get_top_users_per_group($courseid);
        if (empty($groupwinners)) {
            error_log("  INFO: Tidak ada data leaderboard grup untuk menentukan pemenang Hero.");
            return;
        }

        $winnersbygroup = [];
        foreach ($groupwinners as $winner) {
            // UBAH: Konversi ke object agar konsisten
            $winner = (object)$winner;
            $winnersbygroup[$winner->groupid][] = $winner;
        }

        foreach ($winnersbygroup as $groupid => $winners) {
            $groupname = $winners[0]->groupname;
            $badgename = manager::HERO . ' - ' . $coursename . ' - ' . $groupname . ' (' . badge_utils::get_academic_year() . ')';
            badge_utils::ensure_course_group_badge($courseid, $coursename, $ctx, $groupname);

            if (!$badge = badge_utils::find_badge_by_name($courseid, $badgename)) {
                error_log("  Peringatan: Badge Hero '{$badgename}' tidak ditemukan.");
                continue;
            }

            $validwinnerids = [];
            foreach ($winners as $winner) {
                if (self::_is_winner_valid($winner, $minpoints, $mingrade)) {
                    $validwinnerids[] = $winner->userid;
                    $evidence = "Peringkat teratas di grup '{$groupname}' dengan skor {$winner->score} ({$winner->source})";
                    badge_utils::issue_badge_with_evidence($badge->id, $winner->userid, '', $evidence);
                    error_log("  SUCCESS: Badge Hero grup '{$groupname}' diberikan ke pengguna {$winner->userid}");
                } else {
                    error_log("  INFO: Pemenang Hero grup '{$groupname}' (user {$winner->userid}) tidak memenuhi syarat skor.");
                }
            }

            if ($revoke) {
                badge_utils::revoke_from_non_winners($badge->id, $validwinnerids);
            }
        }
    }


    // =========================================================================
    // BAGIAN 3: LOGIKA PENCARIAN PEMENANG (QUERY SQL)
    // =========================================================================

    /**
     * Mengambil pengguna peringkat teratas untuk sebuah course.
     */
    private static function _get_top_users_for_course(int $courseid): array
    {
        global $DB;

        // Cek dulu apakah plugin XP versi gratis terinstall.
        if (!$DB->get_manager()->table_exists('block_xp')) {
            // Jika tidak, jalankan query simpel yang hanya berdasarkan Nilai + Waktu.
            $sql = "SELECT userid, score, source
                FROM (
                    SELECT
                        gg.userid,
                        gg.finalgrade AS score,
                        'grade' as source,
                        DENSE_RANK() OVER(ORDER BY gg.finalgrade DESC, gg.timemodified ASC) as dr
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id
                    WHERE gi.courseid = :courseid AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                ) as ranked
                WHERE dr = 1 AND score > 0";
            $params = ['courseid' => $courseid];
            $winners = $DB->get_records_sql($sql, $params);
            return array_values(array_map(fn($w) => (array)$w, $winners));
        }

        // Jika plugin XP ada, jalankan query 3-lapis
        $sql = "SELECT userid, score, source
            FROM (
                SELECT
                    u.id as userid,
                    COALESCE(xp.xp, 0) as total_xp,
                    COALESCE(gg.finalgrade, 0) as final_grade,
                    gg.timemodified as grade_time,
                    DENSE_RANK() OVER(
                        ORDER BY 
                            COALESCE(gg.finalgrade, 0) DESC, 
                            gg.timemodified ASC,
                            COALESCE(xp.xp, 0) DESC 
                    ) as dr,
                    CASE 
                        WHEN xp.xp IS NOT NULL THEN xp.xp 
                        ELSE COALESCE(gg.finalgrade, 0) 
                    END as score,
                    CASE 
                        WHEN xp.xp IS NOT NULL THEN 'xp' 
                        ELSE 'grade' 
                    END as source
                
                FROM (
                    SELECT DISTINCT userid 
                    FROM {block_xp} 
                    WHERE courseid = :courseid1
                    
                    UNION
                    
                    SELECT DISTINCT gg.userid
                    FROM {grade_grades} gg
                    JOIN {grade_items} gi ON gg.itemid = gi.id
                    WHERE gi.courseid = :courseid2 AND gi.itemtype = 'course'
                ) course_users
                
                JOIN {user} u ON u.id = course_users.userid
                LEFT JOIN {block_xp} xp ON u.id = xp.userid AND xp.courseid = :courseid3
                LEFT JOIN {grade_grades} gg ON u.id = gg.userid
                LEFT JOIN {grade_items} gi ON gg.itemid = gi.id
                    AND gi.courseid = :courseid4 
                    AND gi.itemtype = 'course'
            ) as ranked
            WHERE dr = 1 AND score > 0";

        $params = [
            'courseid1' => $courseid,
            'courseid2' => $courseid,
            'courseid3' => $courseid,
            'courseid4' => $courseid
        ];

        $winners = $DB->get_records_sql($sql, $params);
        return array_values(array_map(fn($w) => (array)$w, $winners));
    }

    /**
     * Mengambil pengguna peringkat teratas untuk setiap grup.
     */
    private static function _get_top_users_per_group(int $courseid): array
    {
        global $DB;

        // Cek dulu apakah plugin XP versi gratis terinstall.
        if (!$DB->get_manager()->table_exists('block_xp')) {
            // Jika tidak, jalankan query simpel yang hanya berdasarkan Nilai + Waktu.
            $sql = "SELECT uniqueid, groupid, groupname, userid, score, source
                FROM (
                    SELECT
                        CONCAT(m.groupid, '-', m.userid) as uniqueid,
                        m.groupid,
                        g.name as groupname,
                        m.userid,
                        gg.finalgrade as score,
                        'grade' as source,
                        DENSE_RANK() OVER(PARTITION BY m.groupid ORDER BY gg.finalgrade DESC, gg.timemodified ASC) as dr
                    FROM {groups_members} m
                    JOIN {groups} g ON m.groupid = g.id
                    JOIN {grade_items} gi ON g.courseid = gi.id AND g.courseid = gi.courseid
                    JOIN {grade_grades} gg ON gi.itemid = gg.itemid AND m.userid = gg.userid
                    WHERE g.courseid = :courseid AND gi.itemtype = 'course' AND gg.finalgrade IS NOT NULL
                ) as ranked
                WHERE dr = 1 AND score > 0";
            $params = ['courseid' => $courseid];
            $winners = $DB->get_records_sql($sql, $params);
            return array_values(array_map(fn($w) => (array)$w, $winners));
        }

        // Jika plugin XP ada, jalankan query 3-lapis.
        $sql = "SELECT uniqueid, groupid, groupname, userid, score, source
            FROM (
                SELECT
                    CONCAT(u.id, '-', m.groupid) as uniqueid,
                    m.groupid,
                    g.name as groupname,
                    u.id as userid,
                    COALESCE(xp.xp, 0) as total_xp,
                    COALESCE(gg.finalgrade, 0) as final_grade,
                    gg.timemodified as grade_time,
                    DENSE_RANK() OVER(
                        PARTITION BY m.groupid 
                        ORDER BY 
                            COALESCE(gg.finalgrade, 0) DESC, 
                            gg.timemodified ASC,
                            COALESCE(xp.xp, 0) DESC 
                    ) as dr,
                    CASE 
                        WHEN xp.xp IS NOT NULL THEN xp.xp 
                        ELSE COALESCE(gg.finalgrade, 0) 
                    END as score,
                    CASE 
                        WHEN xp.xp IS NOT NULL THEN 'xp' 
                        ELSE 'grade' 
                    END as source
                FROM {groups_members} m
                JOIN {user} u ON m.userid = u.id
                JOIN {groups} g ON m.groupid = g.id
                LEFT JOIN {block_xp} xp ON u.id = xp.userid AND g.courseid = xp.courseid
                LEFT JOIN {grade_grades} gg ON u.id = gg.userid
                LEFT JOIN {grade_items} gi ON gg.itemid = gi.id
                WHERE g.courseid = :courseid AND gi.courseid = :courseid2 AND gi.itemtype = 'course'
            ) as ranked
            WHERE dr = 1 AND score > 0";

        $params = [
            'courseid' => $courseid,
            'courseid2' => $courseid
        ];

        $winners = $DB->get_records_sql($sql, $params);
        return array_values(array_map(fn($w) => (array)$w, $winners));
    }

    // =========================================================================
    // BAGIAN 4: FUNGSI UTILITAS INTERNAL
    // =========================================================================

    /**
     * Memeriksa apakah seorang pemenang memenuhi syarat skor minimal.
     */
    private static function _is_winner_valid(stdClass $winner, int $minpoints, float $mingrade): bool
    {
        if ($winner->source === 'xp' && $minpoints > 0 && $winner->score < $minpoints) {
            return false;
        }
        if ($winner->source === 'grade' && $mingrade > 0 && $winner->score < $mingrade) {
            return false;
        }
        return true;
    }
}

// =========================================================================
// CLASS 2: BADGE UTILS - Eksekusi Tugas Spesifik
// =========================================================================

/**
 * Class Badge Utils
 * Menyediakan fungsi-fungsi spesifik dan bisa dipakai ulang untuk
 * semua operasi yang berhubungan dengan lencana.
 */
class badge_utils
{
    /**
     * Memastikan lencana standar (Legend) ada untuk sebuah course.
     * Akan membuatnya jika belum ada.
     */
    public static function ensure_local_auto_badges(int $courseid, string $coursename, context_course $ctx)
    {
        $periode = self::get_academic_year();


        $createdbadges = [];

        $badges_to_create =
            [
                manager::LEGEND => [
                    'name'        => manager::LEGEND . ' - ' . $coursename . ' (' . $periode . ')',
                    'description' => get_string('legend_badge_description', 'local_auto_badge', [
                        'coursename' => $coursename,
                        'periode' => $periode,
                    ]),
                    'pixpath'     => 'pix/legend.png',
                    'criteria'    => \BADGE_CRITERIA_TYPE_MANUAL,
                ],
            ];


        foreach ($badges_to_create as $badgetype => $params) {
            $badgeobj = self::_create_badge_if_not_exists($courseid, $ctx, $params);

            if ($badgeobj) {
                $createdbadges[$badgetype] = $badgeobj;
            }
        }

        return $createdbadges;
    }

    /**
     * Memastikan badge spesifik grup (Hero) ada untuk sebuah grup di dalam course.
     */
    public static function ensure_course_group_badge(int $courseid, string $coursename, context_course $ctx, string $groupname)
    {
        $periode = self::get_academic_year();

        $createdbadges = [];

        $params = [
            'name'        => manager::HERO . ' - ' . $coursename . ' - ' . $groupname . ' (' . $periode . ')',
            'description' => get_string('hero_badge_description', 'local_auto_badge', [
                'coursename' => $coursename,
                'periode' => $periode,
            ]),
            'pixpath'     => 'pix/hero.png',
            'criteria'    => \BADGE_CRITERIA_TYPE_MANUAL,
        ];

        $badgeobj = self::_create_badge_if_not_exists($courseid, $ctx, $params);
        if ($badgeobj) {
            $createdbadges[manager::HERO] = $badgeobj;
        }
        return $createdbadges;
    }

    /**
     * Memberikan badge ke pengguna beserta bukti kustom, jika belum pernah diberikan.
     */
    public static function issue_badge_with_evidence(int $badgeid, int $userid, string $evidenceurl = '', string $evidencetext = ''): void
    {
        global $DB;

        try {
            $badge = new badge($badgeid);
            if ($badge->is_issued($userid)) {
                return; 
            }

            $badge->issue($userid);

 
            if (!empty($evidenceurl) || !empty($evidencetext)) {
                $evidence = new stdClass();
                $evidence->badgeid      = $badgeid;
                $evidence->userid       = $userid;
                $evidence->evidenceurl  = $evidenceurl;
                $evidence->evidencetext = $evidencetext;
                $evidence->timecreated  = time();
                $evidence->timemodified = $evidence->timecreated;
                $DB->insert_record('local_auto_badge_evidence', $evidence);
            }
            error_log("[BADGE] Memberikan badge {$badgeid} ke pengguna {$userid}");
        } catch (moodle_exception $e) {
            error_log("[BADGE ERROR] Gagal memberikan badge {$badgeid} ke pengguna {$userid}: " . $e->getMessage());
        }
    }

    /**
     * Mencabut badge dari pengguna dan menghapus bukti kustom yang terkait.
     */
    public static function revoke_badge_with_evidence(int $badgeid, int $userid): void
    {
        global $DB;
        try {
            $badge = new badge($badgeid);
            if (!$badge->is_issued($userid)) {
                return; 
            }

            $badge->revoke($userid);
            $DB->delete_records('local_auto_badge_evidence', ['badgeid' => $badgeid, 'userid' => $userid]);
            error_log("[BADGE] Mencabut badge {$badgeid} dari pengguna {$userid} dan menghapus bukti");
        } catch (moodle_exception $e) {
            error_log("[BADGE ERROR] Gagal mencabut badge {$badgeid} dari pengguna {$userid}: " . $e->getMessage());
        }
    }

    /**
     * Mencabut lencana dari pengguna yang tidak lagi menjadi pemenang.
     */
    public static function revoke_from_non_winners(int $badgeid, array $winnerids): void
    {
        global $DB;

        error_log("    == [DEBUG REVOKE] == Memulai proses revoke untuk Badge ID: {$badgeid}");
        error_log("    == [DEBUG REVOKE] == Daftar pemenang yang SAH saat ini adalah: [" . implode(', ', $winnerids) . "]");

        $winnerids = array_filter(array_map('intval', $winnerids));
        if (empty($winnerids)) {
            $winnerids = [0];
        }

        list($insql, $params) = $DB->get_in_or_equal($winnerids, SQL_PARAMS_NAMED, 'userid', false);
        $params['badgeid'] = $badgeid;

        $sql = "SELECT userid FROM {badge_issued} WHERE badgeid = :badgeid AND userid {$insql}";
        $torevoke = $DB->get_records_sql($sql, $params);

        foreach ($torevoke as $record) {
            badge_utils::revoke_badge_with_evidence($badgeid, (int)$record->userid);
            error_log("    Mencabut badge {$badgeid} dari pengguna {$record->userid}");
        }
    }

    /**
     * Mencari badge berdasarkan nama persisnya di dalam sebuah course.
     */
    public static function find_badge_by_name(int $courseid, string $name): ?badge
    {
        global $DB;

        // Gunakan get_records() yang mengembalikan array, bukan get_record()
        $records = $DB->get_records('badge', [
            'courseid' => $courseid,
            'name'     => $name,
            'type'     => \BADGE_TYPE_COURSE
        ], 'id', '*', 0, 1); 

        if (empty($records)) {
            return null; 
        }

        $firstrecord = reset($records);
        return new badge($firstrecord->id);
    }

    /**
     * Method internal untuk membuat satu badge jika belum ada.
     * Menangani penyisipan DB, pemrosesan gambar, dan pembuatan kriteria.
     */
    private static function _create_badge_if_not_exists(int $courseid, context_course $ctx, array $params): ?badge
    {
        global $DB, $USER, $CFG;

        if ($existingbadge = $DB->get_record('badge', ['courseid' => $courseid, 'name' => $params['name']])) {
            error_log("[DEBUG] Helper: Badge '{$params['name']}' sudah ada. Mengembalikan data yang ada.");
            $resultbadge = new badge($existingbadge->id);
        } else {

            $tempimagepath = null; // Variabel untuk menyimpan path gambar sementara

            try {
                $data = new stdClass();
                $data->name           = $params['name'];
                $data->description    = $params['description'];
                $data->courseid       = $courseid;
                $data->type           = \BADGE_TYPE_COURSE;
                $data->status         = \BADGE_STATUS_ACTIVE;
                $data->timecreated    = time();
                $data->timemodified   = $data->timecreated;
                $data->usercreated    = $USER->id;
                $data->usermodified   = $USER->id;
                $data->issuername     = fullname($USER);
                $data->issuerurl      = $CFG->wwwroot;
                $data->issuercontact  = $USER->email;
                $data->notification   = 1;
                $data->language       = 'en';
                $data->messagesubject = get_string('badgeawardedsubject', 'local_auto_badge');
                $data->message        = get_string('badgeawarded', 'local_auto_badge');

                $badgeid = $DB->insert_record('badge', $data);
                $badge = new badge($badgeid);

                $sourcefile = $CFG->dirroot . '/local/auto_badge/' . $params['pixpath'];
                if (file_exists($sourcefile)) {
                    // Buat nama unik untuk file salinan di direktori temp Moodle
                    $tempimagepath = tempnam($CFG->tempdir, 'badgeimg_');

                    // Salin gambar asli ke lokasi sementara
                    if (copy($sourcefile, $tempimagepath)) {
                        badges_process_badge_image($badge, $tempimagepath);
                    } else {
                        error_log("[ERROR] Helper : Gagal membuat salinan backup untuk gambar: " . $sourcefile);
                    }
                }

                self::_create_badge_criteria($badge, $ctx, $params['criteria']);

                $overallcriteria = new stdClass();
                $overallcriteria->badgeid = $badge->id;
                $overallcriteria->criteriatype = \BADGE_CRITERIA_TYPE_OVERALL;
                $overallcriteria->description = $params['description'];
                $overallcriteria->descriptionformat = FORMAT_HTML;
                $DB->insert_record('badge_criteria', $overallcriteria);

                error_log("[SUCCESS] Helper : Berhasil membuat badge '{$badge->name}' (ID: {$badgeid})");

                $resultbadge = $badge;
            } catch (\Exception $e) {

                error_log("[ERROR] Helper : Gagal saat proses pembuatan badge untuk '{$params['name']}': " . $e->getMessage());
                return null;

            } finally {
                if ($tempimagepath && file_exists($tempimagepath)) {
                    unlink($tempimagepath);
                }
            }
        }
        return $resultbadge;
    }

    /**
     * Membuat kriteria untuk sebuah badge dan memicu event Moodle yang diperlukan.
     */
    private static function _create_badge_criteria(badge $badge, context_course $ctx, int $criteriatype): void
    {
        global $DB, $USER;

        $criteria = new stdClass();
        $criteria->badgeid      = $badge->id;
        $criteria->criteriatype = $criteriatype;
        $criteria->method       = \BADGE_CRITERIA_AGGREGATION_ALL;


        if ($criteriatype === \BADGE_CRITERIA_TYPE_MANUAL) {
            $criteria->description = 'Penghargaan diberikan secara manual oleh peran yang berwenang.';
            $criteria->descriptionformat = FORMAT_HTML;
        }


        $criteriaid = $DB->insert_record('badge_criteria', $criteria);

        if ($criteriatype === \BADGE_CRITERIA_TYPE_MANUAL) {
            error_log("[DEBUG] Helper : Masuk ke blok pembuatan kriteria TIPE MANUAL.");

            $role_shortname_to_find = 'manager';
            $managerrole = $DB->get_record('role', ['shortname' => $role_shortname_to_find]);

            if ($managerrole) {
                error_log("[DEBUG] Helper : SUKSES menemukan peran '{$role_shortname_to_find}'. ID: {$managerrole->id}, Nama: {$managerrole->name}");

                $param = new stdClass();
                $param->critid = $criteriaid;
                $param->name   = 'role_' . $managerrole->id;
                $param->value  = $managerrole->id;

                $paramid = $DB->insert_record('badge_criteria_param', $param);
                error_log("[DEBUG] Helper : Hasil insert_record ke badge_criteria_param. ID baru: " . ($paramid ? $paramid : 'GAGAL'));
            } else {
                error_log("[ERROR] Helper : GAGAL menemukan peran '{$role_shortname_to_find}'. Padahal data ada di database. Cek cache atau typo.");
            }
        }

        $event = \core\event\badge_criteria_created::create([
            'objectid'      => $criteriaid,
            'context'       => $ctx,
            'relateduserid' => $USER->id,
            'other'         => ['badgeid' => $badge->id]
        ]);
        $event->trigger();
    }

    /**
     * Menghitung string tahun ajaran saat ini (contoh: "2024/2025").
     */
    public static function get_academic_year(): string
    {
        $year = (int)date('Y');
        $month = (int)date('n');

        // Semester ganjil (Juli–Desember) memulai tahun ajaran baru.
        if ($month >= 7) {
            return $year . '/' . ($year + 1);
        }

        // Semester genap (Januari–Juni) adalah bagian dari tahun ajaran sebelumnya.
        return ($year - 1) . '/' . $year;
    }
}
