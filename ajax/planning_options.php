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

$publicHolidays = [];

function planningInterventionParseOverrunDurationToMinutes($rawDuration)
{
	$rawDuration = trim((string) $rawDuration);
	if (empty($rawDuration)) {
		return 0;
	}

	if (preg_match('/^([0-9]{1,2}):([0-9]{2})$/', $rawDuration, $matches)) {
		$hours = (int) $matches[1];
		$minutes = (int) $matches[2];
		if ($minutes >= 0 && $minutes < 60) {
			return ($hours * 60) + $minutes;
		}
	}

	return 0;
}

$countryCode = getDolGlobalString('PLANNINGINTERVENTION_HOLIDAY_COUNTRY', 'FR');

$sqlCountry = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE code = '".$db->escape($countryCode)."' AND active = 1";
$resCountry = $db->query($sqlCountry);

if (!$resCountry || $db->num_rows($resCountry) == 0) {
    echo json_encode(['error' => 'Country not found: '.$countryCode]);
    exit;
}

$objCountry = $db->fetch_object($resCountry);
$fkCountry = $objCountry->rowid;

$tablePublicHoliday = MAIN_DB_PREFIX.'c_hrm_public_holiday';
$resqlCheck = $db->query("SHOW TABLES LIKE '".$tablePublicHoliday."'");

if ($resqlCheck && $db->num_rows($resqlCheck) > 0) {
    $sqlPH = "SELECT day, month FROM ".$tablePublicHoliday." WHERE (fk_country = ".((int) $fkCountry)." OR fk_country = 0) AND active = 1";
    $resqlPH = $db->query($sqlPH);

    if ($resqlPH) {
        while ($obj = $db->fetch_object($resqlPH)) {
            $publicHolidays[] = sprintf('%02d-%02d', $obj->month, $obj->day);
        }
	}
}

$workTimesByDay = array(
	0 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_SUN', ''),
	1 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_MON', ''),
	2 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_TUE', ''),
	3 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_WED', ''),
	4 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_THU', ''),
	5 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_FRI', ''),
	6 => getDolGlobalString('PLANNINGINTERVENTION_WORKTIME_SAT', ''),
);

echo json_encode([
	'publicHolidays' => $publicHolidays,
	'greyWeekend'    => (bool) getDolGlobalInt('PLANNINGINTERVENTION_GREY_WEEKEND'),
	'hideWeekends'   => (bool) getDolGlobalInt('PLANNINGINTERVENTION_HIDE_WEEKENDS'),
	'dayViewShowCustomer' => (bool) getDolGlobalInt('PLANNINGINTERVENTION_DAY_VIEW_SHOW_CUSTOMER'),
	'hideNonWorkingHours' => (bool) getDolGlobalInt('PLANNINGINTERVENTION_HIDE_NON_WORKING_HOURS'),
	'overrunDurationMinutes' => planningInterventionParseOverrunDurationToMinutes(getDolGlobalString('PLANNINGINTERVENTION_OVERRUN_DURATION', '00:00')),
	'workTimesByDay' => $workTimesByDay,
	'rights' => [
		"readPlanning" => $user->hasRight('planningintervention', 'read'),
		"readMyTeamPlanning" => $user->hasRight('planningintervention', 'readmyteam'),
		"writePlanning" => $user->hasRight('planningintervention', 'write'),
		"readExp" => $user->hasRight('ficheinter', 'lire'),
		"writeExp" => $user->hasRight('ficheinter', 'creer'),

	]

]);
