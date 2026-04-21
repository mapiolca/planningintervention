<?php
/* Copyright (C) 2004       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2026		ForLead 				<contact@forlead.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if (file_exists('../main.inc.php')) {
    require '../main.inc.php';
} elseif (file_exists('../../main.inc.php')) {
    require '../../main.inc.php';
} else {
    header('HTTP/1.0 500 Internal Server Error ');
    print 'Include of main fails';
    exit();
}

if (!$user->rights->planningintervention->read && !$user->rights->planningintervention->readmyteam) {
    accessforbidden();
}

print '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/custom/planningintervention/css/planning.css">';

//  customers
$sqlClients = "SELECT s.rowid, s.nom FROM ".MAIN_DB_PREFIX."societe s 
               INNER JOIN ".MAIN_DB_PREFIX."fichinter e ON e.fk_soc = s.rowid
               WHERE s.entity IN (".getEntity('societe').")
               GROUP BY s.rowid, s.nom ORDER BY s.nom";
$resClients = $db->query($sqlClients);
$clients = [];
if ($resClients) {
    while ($obj = $db->fetch_object($resClients)) { 
        $clients[] = $obj;
    }
}

// inter
$sqlInterventions = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."fichinter
                   WHERE entity IN (".getEntity('fichinter').")
                   ORDER BY ref";
$resInterventions = $db->query($sqlInterventions);
$interventions = [];
if ($resInterventions) {
    while ($obj = $db->fetch_object($resInterventions)) {
        $interventions[] = $obj;
    }
}

llxHeader('', $langs->trans('PLANNING_INTERVENTION'));
?>

<?php
$holidayColor = getDolGlobalString('PLANNINGINTERVENTION_COLOR_HOLIDAY', '#eca76a');
?>

<style>
    :root {
        --color-holiday: <?php echo $holidayColor; ?>;
    }
</style>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<title>Planning des Interventions</title>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" />
<script src="https://unpkg.com/@popperjs/core@2"></script>
<script src="https://unpkg.com/tippy.js@6"></script>
<link rel="stylesheet" href="node_modules/toastify-js/src/toastify.css">
<script src="node_modules/toastify-js/src/toastify.js"></script>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

<script>
    var DOL_TOKEN = '<?php echo $_SESSION['newtoken']; ?>';
    var DOL_URL_ROOT = '<?php echo DOL_URL_ROOT; ?>';
    var DOL_SCREENWIDTH_SESSION = '<?php echo isset($_SESSION['dol_screenwidth']) ? (int) $_SESSION['dol_screenwidth'] : 0; ?>';

    const USER_LANG = "<?php echo $langs->defaultlang; ?>";

    const LANGS = {
        today: "<?php echo $langs->transnoentities("Today"); ?>",
        month: "<?php echo $langs->transnoentities("Month"); ?>",
        week: "<?php echo $langs->transnoentities("Week"); ?>",
        day: "<?php echo $langs->transnoentities("Day"); ?>",
        list: "<?php echo $langs->transnoentities("List"); ?>",
        intervention: "<?php echo $langs->transnoentities("Intervention"); ?>",
        listThirdParty: "<?php echo $langs->transnoentities("PLANNINGINTERVENTION_LIST_THIRD_PARTY"); ?>",
        listDescription: "<?php echo $langs->transnoentities("PLANNINGINTERVENTION_LIST_DESCRIPTION"); ?>",
        filter: "<?php echo $langs->transnoentities("Filter"); ?>",
        selectDate: "<?php echo $langs->transnoentities("SelectDate"); ?>",
        dateSaved: "<?php echo $langs->transnoentities("DateSaved"); ?>",
    };
</script>

<div class="fiche">

    <div class="planning-filters">

        <div class="planning-title" style="font-size:18px!important; width:100%;">
            <h1>  <?php echo $langs->trans("PLANNING_INTERVENTION"); ?></h1>
        </div>

        <div class="filter-group">
            <!-- <label class="filter-label" for="filterStatus">Statut</label> -->
            <select id="filterStatus" class="filter-multi" data-label="<?php echo $langs->trans("Status"); ?>" multiple name="status[]">
                <option value="0"><?php echo $langs->trans("Draft"); ?></option>
                <option value="1"><?php echo $langs->trans("Validated"); ?></option>
                <option value="3"><?php echo $langs->trans("Done"); ?></option>
            </select>
        </div>

        <div class="filter-group">
            <!-- <label class="filter-label" for="filterClient">Client</label> -->
            <select id="filterClient" class="filter-multi" data-label="<?php echo $langs->trans("Customer"); ?>" multiple name="client[]">
                <?php foreach ($clients as $c): ?>
                    <option value="<?php echo $c->rowid; ?>"><?php echo dol_htmlentities($c->nom); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        

        <div class="filter-group">
            <!-- <label class="filter-label" for="filterIntervention">Intervention</label> -->
            <select id="filterIntervention" class="filter-multi" data-label="<?php echo $langs->trans("Intervention"); ?>" multiple name="intervention[]">
                <?php foreach ($interventions as $e): ?>
                    <option value="<?php echo $e->rowid; ?>"><?php echo dol_htmlentities($e->ref); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
         <div class="filter-divider"></div>

         
        

        <div class="filter-toggles" title="Afficher les interventions traitées">
            <label class="toggle-pill">
                <input type="checkbox" id="filterShowTreated">
                <span class="toggle-track">
                    <span class="toggle-thumb"></span>
                </span>
                <span class="toggle-label"><?php echo $langs->trans("Done"); ?></span>
            </label>
        </div>

        

    </div>

    <div id="calendar"></div>

</div>

<script src="js/planning.js"></script>

<?php
llxFooter();
