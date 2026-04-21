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

if (!$user->rights->planningintervention->write || !$user->rights->ficheinter->creer) {
    echo json_encode([
        'error' => true,
        'message' => "Vous n'avez pas les droits nécessaires pour modifier les interventions."
    ]);
    exit;
}

$id    = GETPOST('id', 'int');
$start = GETPOST('start', 'alpha');
$end = GETPOST('end', 'alpha');
$type = GETPOST('type', 'alpha');


$sql = "SELECT date_prevue, date_fin_prevue FROM ".MAIN_DB_PREFIX."fichinter_extrafields WHERE fk_object = ".((int)$id);



$resql = $db->query($sql);

if (!$resql || !$db->num_rows($resql)) {
    echo json_encode(['error' => true, 'message' => 'Intervention introuvable']);
    exit;
}

$obj = $db->fetch_object($resql);

$old_ts = $db->jdate($obj->date_prevue);

$old_hour   = date('H', $old_ts);
$old_minute = date('i', $old_ts);
$old_second = date('s', $old_ts);

$new_date_ts = strtotime($start);

$new_ts = mktime(
    $old_hour,
    $old_minute,
    $old_second,
    date('m', $new_date_ts),
    date('d', $new_date_ts),
    date('Y', $new_date_ts)
);

$new_end_ts = null;
if (!empty($end)) {
	$new_end_date_ts = strtotime($end);
	if ($new_end_date_ts) {
		$new_end_ts = mktime(
			date('H', $new_end_date_ts),
			date('i', $new_end_date_ts),
			date('s', $new_end_date_ts),
			date('m', $new_end_date_ts),
			date('d', $new_end_date_ts),
			date('Y', $new_end_date_ts)
		);
	}
}

if ($new_end_ts) {
	$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter_extrafields
		SET date_prevue = '".$db->idate($new_ts)."', date_fin_prevue = '".$db->idate($new_end_ts)."'
		WHERE fk_object = ".((int)$id);
} else {
	$sql = "UPDATE ".MAIN_DB_PREFIX."fichinter_extrafields
		SET date_prevue = '".$db->idate($new_ts)."'
		WHERE fk_object = ".((int)$id);
}

$resql = $db->query($sql);

echo json_encode(['success' => $resql ? true : false]);
exit;
