<?php
function workdaysBetween($start, $end) {
    $startDate = new DateTime($start);
    $endDate = new DateTime($end);
    $workdays = 0;

    while ($startDate <= $endDate) {
        if (!in_array($startDate->format('N'), [6, 7])) {
            $workdays++;
        }
        $startDate->modify('+1 day');
    }

    return $workdays;
}
?>
