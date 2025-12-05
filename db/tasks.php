<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\local_auto_badge\\task\\update_badges_task',
        'blocking'  => 0,
        // Default: berjalan setiap jam.
        'minute'    => 'R',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
