<?php
$ABSENCE_TYPES = [
    'period' => ['Urlaub', 'Krank', 'Kind Krank'],
    'time_point' => ['Kommt später', 'Geht eher'],
    'time_range' => ['Unterbrechung'],
];

$ABSENCE_TYPE_LABELS = [
    'Urlaub' => 'Urlaub',
    'Krank' => 'Krank',
    'Kind Krank' => 'Kind Krank',
    'Kommt später' => 'Kommt später',
    'Geht eher' => 'Geht eher',
    'Unterbrechung' => 'Abwesend über den Tag',
];

$ALL_ABSENCE_TYPES = array_merge(...array_values($ABSENCE_TYPES));
?>
