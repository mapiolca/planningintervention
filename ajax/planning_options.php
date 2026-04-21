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

echo json_encode([
	'publicHolidays' => $publicHolidays,
	'greyWeekend'    => (bool) getDolGlobalInt('PLANNINGINTERVENTION_GREY_WEEKEND'),
	'hideWeekends'   => (bool) getDolGlobalInt('PLANNINGINTERVENTION_HIDE_WEEKENDS'),
	'dayViewShowCustomer' => (bool) getDolGlobalInt('PLANNINGINTERVENTION_DAY_VIEW_SHOW_CUSTOMER'),
	'rights' => [
		"readPlanning" => $user->hasRight('planningintervention', 'read'),
		"writePlanning" => $user->hasRight('planningintervention', 'write'),
		"readExp" => $user->hasRight('ficheinter', 'lire'),
		"writeExp" => $user->hasRight('ficheinter', 'creer'),

	]

]);
