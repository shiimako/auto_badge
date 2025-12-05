<?php
/**
 * File pengaturan untuk plugin lokal 'Auto Badge'.
 *
 * @package    local_auto_badge
 * @copyright  2025 Miika
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Pastikan hanya admin yang bisa mengakses.
if ($hassiteconfig) {

    // Membuat halaman pengaturan baru.
    $settings = new admin_settingpage('local_auto_badge', get_string('pluginname', 'local_auto_badge'));

    // --- PENGATURAN UMUM ---

    // --- KEPALA BAGIAN: PENGATURAN UMUM ---
    $settings->add(new admin_setting_heading(
        'local_auto_badge/generalheading',
        get_string('generalsettings', 'local_auto_badge'),
        ''
    ));

    // Opsi untuk Dynamic Revoke.
    $settings->add(new admin_setting_configcheckbox(
        'local_auto_badge/dynamicrevoke',
        get_string('dynamicrevoke', 'local_auto_badge'),
        get_string('dynamicrevoke_desc', 'local_auto_badge'),
        0
    ));

    // --- KEPALA BAGIAN: PENGATURAN LENCANA 'LEGEND' & 'HERO' ---
    // Pengelompokan ini membuat admin paham pengaturan mana untuk lencana mana.
    $settings->add(new admin_setting_heading(
        'local_auto_badge/legendheroheading',
        get_string('legendherosettings', 'local_auto_badge'),
        get_string('legendherosettings_desc', 'local_auto_badge')
    ));

    // Dibuat lebih spesifik: batas minimal XP untuk Legend/Hero.
    $settings->add(new admin_setting_configtext(
        'local_auto_badge/minpoints_legendhero',
        get_string('minpoints_legendhero', 'local_auto_badge'),
        get_string('minpoints_legendhero_desc', 'local_auto_badge'),
        '0',
        PARAM_INT
    ));

    // Dibuat lebih spesifik: batas minimal nilai untuk Legend/Hero.
    $settings->add(new admin_setting_configtext(
        'local_auto_badge/mingrade_legendhero',
        get_string('mingrade_legendhero', 'local_auto_badge'),
        get_string('mingrade_legendhero_desc', 'local_auto_badge'),
        '0',
        PARAM_FLOAT
    ));
    
    // --- AKHIR PENGATURAN UMUM ---
    // Tambahkan halaman pengaturan ini ke kategori 'Local plugins'.
    $ADMIN->add('localplugins', $settings);
}