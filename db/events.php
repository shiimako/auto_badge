<?php

/**
 * Mendaftarkan observer untuk event-event Moodle.
 *
 * @package    local_auto_badge
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // --- Event untuk MEMBUAT kerangka badge ---
    [
        'eventname'   => '\core\event\course_created',
        'callback'    => '\local_auto_badge\events\observer::course_created',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback'  => '\local_auto_badge\events\observer::course_updated',
    ],
    [
        'eventname'   => '\core\event\course_deleted',
        'callback'    => '\local_auto_badge\events\observer::course_deleted',
    ],
    [
        'eventname'   => '\core\event\group_created',
        'callback'    => '\local_auto_badge\events\observer::group_created',
    ],
    [
        'eventname' => '\core\event\group_updated',
        'callback'  => '\local_auto_badge\events\observer::group_updated',
    ],
    [
        'eventname'   => '\core\event\group_deleted',
        'callback'    => '\local_auto_badge\events\observer::group_deleted',
    ],
];
