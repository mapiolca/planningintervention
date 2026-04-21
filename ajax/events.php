<?php
if (file_exists('../../../main.inc.php')) {
    require '../../../main.inc.php';
} elseif (file_exists('../../main.inc.php')) {
    require '../../main.inc.php';
} else {
    header('HTTP/1.0 500 Internal Server Error ');
    print 'Include of main fails';
    exit();
}

header('Content-Type: application/json');

$canReadAllPlanning = $user->hasRight('planningintervention', 'read');
$canReadOwnPlanning = $user->hasRight('planningintervention', 'readmyteam');

if (!$canReadAllPlanning && !$canReadOwnPlanning) {
	echo json_encode([]);
	exit;
}

$statusStr    = GETPOST('status', 'alpha');
$showTreated = GETPOST('showTreated', 'int');
$clientsStr   = GETPOST('clients', 'alpha');
$intervention   = GETPOST('intervention', 'alpha');

function planningInterventionParseWorkRanges($rawRanges)
{
	$ranges = array();
	foreach (explode(',', (string) $rawRanges) as $slot) {
		$slot = trim($slot);
		if (empty($slot)) {
			continue;
		}

		if (preg_match('/^([0-9]{1,2})(?::([0-9]{2}))?\s*-\s*([0-9]{1,2})(?::([0-9]{2}))?$/', $slot, $matches)) {
			$startHour = (int) $matches[1];
			$startMinute = isset($matches[2]) ? (int) $matches[2] : 0;
			$endHour = (int) $matches[3];
			$endMinute = isset($matches[4]) ? (int) $matches[4] : 0;

			$startTotal = ($startHour * 60) + $startMinute;
			$endTotal = ($endHour * 60) + $endMinute;

			if ($startTotal >= 0 && $startTotal < 1440 && $endTotal > 0 && $endTotal <= 1440 && $startTotal < $endTotal) {
				$ranges[] = array($startTotal, $endTotal);
			}
		}
	}

	return $ranges;
}

function planningInterventionGetDayRanges($dayIndex)
{
	$constantsByDay = array(
		1 => 'PLANNINGINTERVENTION_WORKTIME_MON',
		2 => 'PLANNINGINTERVENTION_WORKTIME_TUE',
		3 => 'PLANNINGINTERVENTION_WORKTIME_WED',
		4 => 'PLANNINGINTERVENTION_WORKTIME_THU',
		5 => 'PLANNINGINTERVENTION_WORKTIME_FRI',
		6 => 'PLANNINGINTERVENTION_WORKTIME_SAT',
		7 => 'PLANNINGINTERVENTION_WORKTIME_SUN',
	);

	$constantName = isset($constantsByDay[$dayIndex]) ? $constantsByDay[$dayIndex] : '';
	if (empty($constantName)) {
		return array();
	}

	return planningInterventionParseWorkRanges(getDolGlobalString($constantName, ''));
}

function planningInterventionParseOverrunDurationToSeconds($rawDuration)
{
	$rawDuration = trim((string) $rawDuration);
	if (empty($rawDuration)) {
		return 0;
	}

	if (preg_match('/^([0-9]{1,2}):([0-9]{2})$/', $rawDuration, $matches)) {
		$hours = (int) $matches[1];
		$minutes = (int) $matches[2];
		if ($minutes >= 0 && $minutes < 60) {
			return (($hours * 60) + $minutes) * 60;
		}
	}

	return 0;
}

$overrunDurationSeconds = planningInterventionParseOverrunDurationToSeconds(getDolGlobalString('PLANNINGINTERVENTION_OVERRUN_DURATION', '00:00'));

$sqlRows = "SELECT inter.rowid, parent.ref, inter.date, parent.rowid as parentId
        FROM ".MAIN_DB_PREFIX."fichinterdet inter

        LEFT JOIN ".MAIN_DB_PREFIX."fichinter parent ON parent.rowid = inter.fk_fichinter
        WHERE inter.date IS NOT NULL
          ";

$sqlParents = "SELECT parent.rowid, parent.ref, parent.description, parent.fk_statut as status, extra.date_prevue, extra.date_fin_prevue, extra.ignore_opening_hours, s.nom as customer_name
        FROM ".MAIN_DB_PREFIX."fichinter parent
        LEFT JOIN ".MAIN_DB_PREFIX."fichinter_extrafields extra ON extra.fk_object = parent.rowid
		LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = parent.fk_soc
        
        WHERE extra.date_prevue IS NOT NULL
        ";

// ── Statuts ──
$statusToInclude = [];
if ($statusStr !== '' && $statusStr !== null) {
    foreach (explode(',', $statusStr) as $p) {
        if (is_numeric($p)) $statusToInclude[] = intval($p);
    }
} else {
    $statusToInclude = [0, 1];
}
if ($showTreated && !in_array(3, $statusToInclude)) {
    $statusToInclude[] = 3;
}
$sqlRows .= " AND parent.fk_statut IN (".implode(',', $statusToInclude).")";
$sqlParents .= " AND parent.fk_statut IN (".implode(',', $statusToInclude).")";


// ── customer ──
if (!empty($clientsStr)) {
    $clientIds = array_filter(array_map('intval', explode(',', $clientsStr)));
    if (!empty($clientIds)) {
        $sqlRows .= " AND parent.fk_soc IN (".implode(',', $clientIds).")";
        $sqlParents .= " AND parent.fk_soc IN (".implode(',', $clientIds).")";
    }
}


// ──  inter ──
if (!empty($intervention)) {
    $interventionIds = array_filter(array_map('intval', explode(',', $intervention)));
    if (!empty($interventionIds)) {
        $sqlRows .= " AND parent.rowid IN (".implode(',', $interventionIds).")";
        $sqlParents .= " AND parent.rowid IN (".implode(',', $interventionIds).")";
    }
}

if (!$canReadAllPlanning && $canReadOwnPlanning) {
	$sqlRows .= " AND EXISTS (
		SELECT 1
		FROM ".MAIN_DB_PREFIX."element_contact ec
		INNER JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		WHERE ec.element_id = parent.rowid
		AND tc.element = 'fichinter'
		AND tc.active = 1
		AND ec.fk_socpeople = ".((int) $user->id)."
	)";

	$sqlParents .= " AND EXISTS (
		SELECT 1
		FROM ".MAIN_DB_PREFIX."element_contact ec
		INNER JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		WHERE ec.element_id = parent.rowid
		AND tc.element = 'fichinter'
		AND tc.active = 1
		AND ec.fk_socpeople = ".((int) $user->id)."
	)";
}


$resql = $db->query($sqlRows);
$resqlParents = $db->query($sqlParents);

if (!$resql && !$resqlParents) {
    echo json_encode(['error' => $db->lasterror()]);
    exit;
}

$events = [];
$color = '#3788d8';
$colorMap = [
    0 => getDolGlobalString('PLANNINGINTERVENTION_COLOR_DRAFT', '#8b8b8b'),
    1 => getDolGlobalString('PLANNINGINTERVENTION_COLOR_VALIDATED', '#27ae60'),
    3 => getDolGlobalString('PLANNINGINTERVENTION_COLOR_FINISHED', '#2287e6'),
    ];

while ($obj = $db->fetch_object($resqlParents)) {

	$color = $colorMap[$obj->status] ?? '#3788d8';
	$datePrevue = (string) $obj->date_prevue;
	$dateOnly = substr($datePrevue, 0, 10);
	$dateFinPrevue = (string) $obj->date_fin_prevue;
		$timePart = strlen($datePrevue) >= 19 ? substr($datePrevue, 11, 8) : '';
		$hasPlannedTime = (!empty($timePart) && $timePart !== '00:00:00');
		$hasPlannedEnd = !empty($dateFinPrevue) && $dateFinPrevue !== '0000-00-00 00:00:00';
		$openingHoursMode = trim((string) $obj->ignore_opening_hours);
		$openingHoursModeUpper = strtoupper($openingHoursMode);
		$ignoreOpeningHours = ($openingHoursModeUpper === 'IGNORE' || stripos($openingHoursMode, 'Ignorer les heures') !== false);
		$allowStartBeforeWork = ($openingHoursModeUpper === 'START_BEFORE' || stripos($openingHoursMode, 'Débuter avant') !== false || stripos($openingHoursMode, 'Debuter avant') !== false);
		$allowEndAfterWork = ($openingHoursModeUpper === 'END_AFTER' || stripos($openingHoursMode, 'Terminer après') !== false || stripos($openingHoursMode, 'Terminer apres') !== false);

		if ($hasPlannedTime) {
		$startTimestamp = strtotime(substr($datePrevue, 0, 19));
		$endTimestamp = $hasPlannedEnd ? strtotime(substr($dateFinPrevue, 0, 19)) : false;
		if (!$endTimestamp && $startTimestamp) {
			$endTimestamp = $startTimestamp + 3600;
		}

			if ($startTimestamp && $endTimestamp && $endTimestamp > $startTimestamp) {
				if ($ignoreOpeningHours) {
					$events[] = [
						'id'     => 'parent_'.$obj->rowid.'_'.date('YmdHi', $startTimestamp),
						'title'  => $obj->ref,
						'start'  => date('Y-m-d\TH:i:s', $startTimestamp),
						'end'    => date('Y-m-d\TH:i:s', $endTimestamp),
						'allDay' => false,
						'color'  => $color,
						'extendedProps' => [
							'type' => 'parent',
							'ref' => $obj->ref,
							'parentId' => (int) $obj->rowid,
							'customerName' => (string) $obj->customer_name,
							'description' => trim(dol_string_nohtmltag((string) $obj->description))
						],
					];
					continue;
				}

				$dayCursor = strtotime(date('Y-m-d 00:00:00', $startTimestamp));
				$endDay = strtotime(date('Y-m-d 00:00:00', $endTimestamp));

			while ($dayCursor <= $endDay) {
				$weekday = (int) date('N', $dayCursor);
				$workRanges = planningInterventionGetDayRanges($weekday);

					foreach ($workRanges as $range) {
						$slotStart = $dayCursor + ($range[0] * 60);
						$slotEnd = $dayCursor + ($range[1] * 60);
						if ($allowStartBeforeWork && $overrunDurationSeconds > 0) {
							$slotStart -= $overrunDurationSeconds;
						}
						if ($allowEndAfterWork && $overrunDurationSeconds > 0) {
							$slotEnd += $overrunDurationSeconds;
						}

					$segmentStart = max($slotStart, $startTimestamp);
					$segmentEnd = min($slotEnd, $endTimestamp);

					if ($segmentEnd > $segmentStart) {
						$events[] = [
							'id'     => 'parent_'.$obj->rowid.'_'.date('YmdHi', $segmentStart),
							'title'  => $obj->ref,
							'start'  => date('Y-m-d\TH:i:s', $segmentStart),
							'end'    => date('Y-m-d\TH:i:s', $segmentEnd),
							'allDay' => false,
							'color'  => $color,
							'extendedProps' => [
								'type' => 'parent',
								'ref' => $obj->ref,
								'parentId' => (int) $obj->rowid,
								'customerName' => (string) $obj->customer_name,
								'description' => trim(dol_string_nohtmltag((string) $obj->description))
							],
						];
					}
				}

				$dayCursor = strtotime('+1 day', $dayCursor);
			}

			continue;
		}

		$eventStart = $startTimestamp ? date('Y-m-d\TH:i:s', $startTimestamp) : str_replace(' ', 'T', substr($datePrevue, 0, 19));
		$eventEnd = $endTimestamp ? date('Y-m-d\TH:i:s', $endTimestamp) : date('Y-m-d\TH:i:s', strtotime('+1 hour', strtotime(substr($datePrevue, 0, 19))));
		$eventAllDay = false;
	} else {
		$eventStart = $dateOnly;
		if ($hasPlannedEnd) {
			$eventEnd = substr($dateFinPrevue, 0, 10);
			if ($eventEnd <= $dateOnly) {
				$eventEnd = date('Y-m-d', strtotime('+1 day', strtotime($dateOnly)));
			}
		} else {
			$eventEnd = date('Y-m-d', strtotime('+1 day', strtotime($dateOnly)));
		}
		$eventAllDay = true;
	}

	$events[] = [
		'id'     => 'parent_'.$obj->rowid,
		'title'  => $obj->ref,
		'start'  => $eventStart,
		'end'    => $eventEnd,
		'allDay' => $eventAllDay,
		'color'  => $color,
		'extendedProps' => [
			'type' => 'parent',
			'ref' => $obj->ref,
			'parentId' => (int) $obj->rowid,
			'customerName' => (string) $obj->customer_name,
			'description' => trim(dol_string_nohtmltag((string) $obj->description))
		],
	];

}

if (!$canReadAllPlanning && $canReadOwnPlanning) {
	$sqlRows .= " AND EXISTS (
		SELECT 1
		FROM ".MAIN_DB_PREFIX."element_contact ec
		INNER JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		WHERE ec.element_id = parent.rowid
		AND tc.element = 'fichinter'
		AND tc.active = 1
		AND ec.fk_socpeople = ".((int) $user->id)."
	)";

	$sqlParents .= " AND EXISTS (
		SELECT 1
		FROM ".MAIN_DB_PREFIX."element_contact ec
		INNER JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact
		WHERE ec.element_id = parent.rowid
		AND tc.element = 'fichinter'
		AND tc.active = 1
		AND ec.fk_socpeople = ".((int) $user->id)."
	)";
}

// while ($obj = $db->fetch_object($resql)) {

//     $events[] = [
//         'id'     => 'row_'.$obj->rowid,
//         'title'  => $obj->ref,
//         'start'  => substr($obj->date, 0, 10),
//         'end'    => date('Y-m-d', strtotime('+1 day', strtotime(substr($obj->date, 0, 10)))),
//         'allDay' => true,
//         'color'  => $colorRow,
//         'extendedProps' => ['type' => 'row', 'ref' => $obj->ref, 'parentId' => $obj->parentId],
//     ];
    
// }

echo json_encode($events);
