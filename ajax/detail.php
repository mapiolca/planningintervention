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

$langs->load("sendings");

$idraw = (string) GETPOST('id', 'restricthtml');
$parentidraw = (string) GETPOST('parentId', 'restricthtml');
$type = GETPOST('type', 'alpha');

if (preg_match('/(?:row_|parent_)?([0-9]+)/', $idraw, $matchesid)) {
	$id = (int) $matchesid[1];
} else {
	$id = 0;
}

if (preg_match('/(?:row_|parent_)?([0-9]+)/', $parentidraw, $matchesparentid)) {
	$parentid = (int) $matchesparentid[1];
} else {
	$parentid = 0;
}

if (empty($parentid)) {
	$parentid = $id;
}

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

if (file_exists(DOL_DOCUMENT_ROOT.'/fichinter/class/fichinterligne.class.php')) {
	require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinterligne.class.php';
}

$inter    = new Fichinter($db);
$row  = new FichinterLigne($db);
$formfile = new FormFile($db);
$resInter = $inter->fetch($parentid);
$resRow = 0;
if ($type === 'row') {
    $resRow = $row->fetch($id);
}

if ($resInter > 0 ) {


    // Client
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $soc = new Societe($db);
    if (!empty($inter->socid)) {
        $soc->fetch($inter->socid);
    }

    $sql = "SELECT
            ec.rowid              AS link_id,
            ec.statut,
            tc.source,
            tc.code,
            tc.libelle,

            sp.rowid              AS contact_id,
            sp.firstname          AS contact_firstname,
            sp.lastname           AS contact_lastname,
            sp.email              AS contact_email,
            sp.phone              AS contact_phone,
            sp.phone_mobile       AS contact_mobile,

            u.rowid               AS user_id,
            u.login               AS user_login,
            u.firstname           AS user_firstname,
            u.lastname            AS user_lastname,
            u.email               AS user_email
        FROM ".MAIN_DB_PREFIX."element_contact ec
        JOIN ".MAIN_DB_PREFIX."c_type_contact tc
             ON tc.rowid = ec.fk_c_type_contact
        LEFT JOIN ".MAIN_DB_PREFIX."socpeople sp
             ON sp.rowid = ec.fk_socpeople
        LEFT JOIN ".MAIN_DB_PREFIX."user u
             ON u.rowid = ec.fk_socpeople
        WHERE ec.element_id = ".((int) $inter->id)."
          AND tc.element = 'fichinter'
          AND tc.active = 1
        ORDER BY tc.source, tc.libelle";

        $resql = $db->query($sql);


    // PDF
    $pdfUrl = DOL_URL_ROOT.'/document.php?modulepart=fichinter&attachment=0&file='.urlencode($inter->ref.'/'.$inter->ref.'.pdf').'&entity='.$conf->entity;

    print '<div style="font-size:12px; line-height:1.3; min-width:220px;">';
    print '<h2 style="font-size:14px; margin:0 0 4px 0;">'.$inter->ref.'</h2>';
    print '<p style="margin:2px 0;"><strong>'.$langs->trans('Status').' :</strong> '.$inter->getLibStatut(5).'</p>';
    if (!empty($soc->nom)) {
        print '<p style="margin:2px 0;"><strong>'.$langs->trans('Customer').' :</strong> <a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$inter->socid.'" target="_blank">'.$soc->nom.'</a></p>';
    }
    $description = ($type === 'row') ? $row->desc : $inter->description;
    $duration = ($type === 'row') ? $row->duration : $inter->duration;

    print '<p style="margin:6px 0;"><strong>Description :</strong> '
    .dol_htmlentitiesbr($inter->description);

    if ($type === 'row' && !empty($row->desc)) {
        print '<br><span style="opacity:.75;">'.$langs->trans('Row').' :</span> '.dol_htmlentitiesbr($row->desc);
    }
    print '</p>';

    print '<p style="margin:6px 0;"><strong>'.$langs->trans('Duration').' :</strong> '
        .'<span style="opacity:.75;">Total :</span> '.convertSecondToTime((int) $inter->duration);

    if ($type === 'row' && !empty($row->duration)) {
        print ' <span style="opacity:.75;">| '.$langs->trans('Row').' :</span> '.convertSecondToTime((int) $row->duration);
    }
    print '</p>';

    if ($resql && $db->num_rows($resql) > 0) {
    print '<p style="margin:6px 0;"><strong>Contacts :</strong><br>';

    while ($obj = $db->fetch_object($resql)) {
        if (!empty($obj->contact_id)) {
            $name = trim($obj->contact_firstname.' '.$obj->contact_lastname);
            $mail = $obj->contact_email;
        } else {
            $name = trim($obj->user_firstname.' '.$obj->user_lastname);
            $mail = $obj->user_email;
        }

        $type = dol_escape_htmltag($obj->libelle);
        $name = dol_escape_htmltag($name);

        print '• '.$type.' : '.$name.'<br>';
    }

    print '</p>';
}

    
    $filedir = $conf->ficheinter->dir_output.'/'.dol_sanitizeFileName($inter->ref);
    $filename = dol_sanitizeFileName($inter->ref);

    $object_file_path = $filedir . '/' . $filename . '.pdf';

    print '<br><div style="display:flex; gap:8px; margin-top:8px;">';
    print '<a style="font-size:12px; padding:4px 10px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; text-decoration:none; color:#475569;" href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.$parentid.'" target="_blank">📋 '.$langs->trans('INTERVENTION_CARD_TITLE').'</a>';
    
    if (file_exists($object_file_path)) {
        print '<a style="font-size:12px; padding:4px 10px; background:#fee2e2; border:1px solid #fecaca; border-radius:6px; text-decoration:none; color:#dc2626;" href="'.$pdfUrl.'" mime="application/pdf" target="_blank">📄 PDF</a>';
    }
    
    print '</div>';

    print '</div>';

} else {
    print "Intervention introuvable.";
}
