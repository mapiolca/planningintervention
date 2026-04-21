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

$statusStr    = GETPOST('status', 'alpha');
$showTreated = GETPOST('showTreated', 'int');
$clientsStr   = GETPOST('clients', 'alpha');
$intervention   = GETPOST('intervention', 'alpha');

$sqlRows = "SELECT inter.rowid, parent.ref, inter.date, parent.rowid as parentId
        FROM ".MAIN_DB_PREFIX."fichinterdet inter

        LEFT JOIN ".MAIN_DB_PREFIX."fichinter parent ON parent.rowid = inter.fk_fichinter
        WHERE inter.date IS NOT NULL
          ";

$sqlParents = "SELECT parent.rowid, parent.ref, parent.fk_statut as status, extra.date_prevue
        FROM ".MAIN_DB_PREFIX."fichinter parent
        LEFT JOIN ".MAIN_DB_PREFIX."fichinter_extrafields extra ON extra.fk_object = parent.rowid
        
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
	$timePart = strlen($datePrevue) >= 19 ? substr($datePrevue, 11, 8) : '';
	$hasPlannedTime = (!empty($timePart) && $timePart !== '00:00:00');

	if ($hasPlannedTime) {
		$startTimestamp = strtotime(substr($datePrevue, 0, 19));
		$endTimestamp = $startTimestamp ? ($startTimestamp + 3600) : false;

		$eventStart = $startTimestamp ? date('Y-m-d\TH:i:s', $startTimestamp) : str_replace(' ', 'T', substr($datePrevue, 0, 19));
		$eventEnd = $endTimestamp ? date('Y-m-d\TH:i:s', $endTimestamp) : date('Y-m-d\TH:i:s', strtotime('+1 hour', strtotime(substr($datePrevue, 0, 19))));
		$eventAllDay = false;
	} else {
		$eventStart = $dateOnly;
		$eventEnd = date('Y-m-d', strtotime('+1 day', strtotime($dateOnly)));
		$eventAllDay = true;
	}

	$events[] = [
		'id'     => 'parent_'.$obj->rowid,
		'title'  => $obj->ref,
		'start'  => $eventStart,
		'end'    => $eventEnd,
		'allDay' => $eventAllDay,
		'color'  => $color,
		'extendedProps' => ['type' => 'parent', 'ref' => $obj->ref],
	];

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
