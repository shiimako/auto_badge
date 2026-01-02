<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_auto_badge_upgrade($oldversion)
{
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025101604) {
        // mendefinisikan tabel local_auto_badge_evidence untuk dibuat.
        $table = new xmldb_table('local_auto_badge_evidence');

        // menambahkan fields ke tabel local_auto_badge_evidence.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('evidenceurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('evidencetext', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // menambahkan keys ke tabel local_auto_badge_evidence.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('badgeuser', XMLDB_KEY_UNIQUE, ['badgeid', 'userid']);

        // menambahkan indexes ke tabel local_auto_badge_evidence.
        $table->add_index('badgeid', XMLDB_INDEX_NOTUNIQUE, ['badgeid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        // kondisional membuat tabel local_auto_badge_evidence.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // mendefinisikan tabel local_group_history untuk dibuat.
        $table = new xmldb_table('local_group_history');

        // menambahkan fields ke tabel local_group_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // menambahkan keys ke tabel local_group_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // menambahkan indexes ke tabel local_group_history.
        $table->add_index('groupid', XMLDB_INDEX_NOTUNIQUE, ['groupid']);
        $table->add_index('badgeid', XMLDB_INDEX_NOTUNIQUE, ['badgeid']);

        // kondisional membuat tabel local_group_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // mendefinisikan tabel local_course_history untuk dibuat.
        $table = new xmldb_table('local_course_history');

        // menambahkan fields ke tabel local_course_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('badgeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // menambahkan keys ke tabel local_course_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // menambahkan indexes ke tabel local_course_history.
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('badgeid', XMLDB_INDEX_NOTUNIQUE, ['badgeid']);

        // kondisional membuat tabel local_course_history.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Auto savepoint tercapai.
        upgrade_plugin_savepoint(true, 2025101604, 'local', 'auto_badge');
    }

    return true;
}
