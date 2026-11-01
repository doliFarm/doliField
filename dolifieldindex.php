<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		Luigi Grillo
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

/**
 *	\file       dolifield/dolifieldindex.php
 *	\ingroup    dolifield
 *	\brief      Home page of dolifield top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

// Load translation files required by the page
$langs->loadLangs(array("dolifield@dolifield"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array
//$hookmanager->initHooks(array($object->element.'index'));

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('dolifield')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('dolifield', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'dolifield', 0, 'dolifield_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("DoliFieldArea"), '', '', 0, 0, '', '', '', 'mod-dolifield page-index');

print load_fiche_titre($langs->trans("DoliFieldArea"), '', 'dolifield.png@dolifield');

print '<div class="fichecenter">';


/* BEGIN MODULEBUILDER DRAFT MYOBJECT
// Draft MyObject
if (isModEnabled('dolifield') && $user->hasRight('dolifield', 'read')) {
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.ref_client, c.total_ht, c.tva as total_tva, c.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql.= ", s.code_client";
	$sql.= " FROM ".$db->prefix()."commande as c";
	$sql.= ", ".$db->prefix()."societe as s";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.fk_statut = 0";
	$sql.= " AND c.entity IN (".getEntity('commande').")";
	if ($socid)	$sql.= " AND c.fk_soc = ".((int) $socid);

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="3">'.$langs->trans("DraftMyObjects").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th></tr>';

		$var = true;
		if ($num > 0)
		{
			$i = 0;
			while ($i < $num)
			{

				$obj = $db->fetch_object($resql);
				print '<tr class="oddeven"><td class="nowrap">';

				$myobjectstatic->id=$obj->rowid;
				$myobjectstatic->ref=$obj->ref;
				$myobjectstatic->ref_client=$obj->ref_client;
				$myobjectstatic->total_ht = $obj->total_ht;
				$myobjectstatic->total_tva = $obj->total_tva;
				$myobjectstatic->total_ttc = $obj->total_ttc;

				print $myobjectstatic->getNomUrl(1);
				print '</td>';
				print '<td class="nowrap">';
				print '</td>';
				print '<td class="right" class="nowrap">'.price($obj->total_ttc).'</td></tr>';
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0)
			{

				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" class="right">'.price($total)."</td></tr>";
			}
		}
		else
		{

			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoOrder").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}
END MODULEBUILDER DRAFT MYOBJECT */



/* BEGIN MODULEBUILDER LASTMODIFIED MYOBJECT
// Last modified myobject
if (isModEnabled('dolifield') && $user->hasRight('dolifield', 'read')) {
	$sql = "SELECT s.rowid, s.ref, s.label, s.date_creation, s.tms";
	$sql.= " FROM ".$db->prefix()."dolifield_myobject as s";
	$sql.= " WHERE s.entity IN (".getEntity($myobjectstatic->element).")";
	//if ($socid)	$sql.= " AND s.rowid = $socid";
	$sql .= " ORDER BY s.tms DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="2">';
		print $langs->trans("BoxTitleLatestModifiedMyObjects", $max);
		print '</th>';
		print '<th class="right">'.$langs->trans("DateModificationShort").'</th>';
		print '</tr>';
		if ($num)
		{
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$myobjectstatic->id=$objp->rowid;
				$myobjectstatic->ref=$objp->ref;
				$myobjectstatic->label=$objp->label;
				$myobjectstatic->status = $objp->status;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$myobjectstatic->getNomUrl(1).'</td>';
				print '<td class="right nowrap">';
				print "</td>";
				print '<td class="right nowrap">'.dol_print_date($db->jdate($objp->tms), 'day')."</td>";
				print '</tr>';
				$i++;
			}

			$db->free($resql);
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
		}
		print "</table><br>";
	}
}
*/
// --- BANNER IN COSTRUZIONE ---
print '<div class="warning" style="margin-bottom: 20px; padding: 15px; border-left: 5px solid #e67e22; background-color: #fdf5e6; border-radius: 4px;">';
print '  <h3 style="margin: 0 0 5px 0; color: #d35400;"><i class="fa fa-info-circle"></i> Modulo in Costruzione</h3>';
print '  <p style="margin: 0; font-size: 13px; color: #555;">';
print '    Il modulo completo e integrato con la sensoristica di campo è attualmente in fase di sviluppo e il rilascio ufficiale è previsto per <strong>Settembre 2026</strong>.';
print '  </p>';
print '</div>';

// --- BOX 1: KPI WIDGETS (Stile Carte di Monitoraggio) ---
print '<div class="box" style="margin-bottom: 20px;">';
print '  <div class="boxheader font-weight-bold"><i class="fa fa-microchip"></i> Stato Sensori in Tempo Reale</div>';
print '  <div class="boxcontents" style="padding: 15px; display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-around; background: #fff;">';


// Sensore 1
print '    <div style="flex: 1; min-width: 180px; border-left: 4px solid #2ecc71; padding-left: 10px;">';
print '      <small style="color:#777; font-weight:bold;">SUOLO_01</small><br>';
print '      <span style="font-size: 14px;">Umidità Terreno</span><br>';
print '      <strong style="font-size: 24px; color: #2b5d8f;">42%</strong><br>';
print '      <small style="color: #2ecc71;"><i class="fa fa-check-circle"></i> Regolare</small>';
print '    </div>';

// Sensore 2
print '    <div style="flex: 1; min-width: 180px; border-left: 4px solid #f1c40f; padding-left: 10px;">';
print '      <small style="color:#777; font-weight:bold;">TEMP_02</small><br>';
print '      <span style="font-size: 14px;">Temperatura Serra</span><br>';
print '      <strong style="font-size: 24px; color: #2b5d8f;">28.4°C</strong><br>';
print '      <small style="color: #f1c40f;"><i class="fa fa-exclamation-triangle"></i> Soglia Critica</small>';
print '    </div>';

// Sensore 3
print '    <div style="flex: 1; min-width: 180px; border-left: 4px solid #3498db; padding-left: 10px;">';
print '      <small style="color:#777; font-weight:bold;">SILO_03</small><br>';
print '      <span style="font-size: 14px;">Livello Silo</span><br>';
print '      <strong style="font-size: 24px; color: #2b5d8f;">85%</strong><br>';
print '      <small style="color: #3498db;"><i class="fa fa-sync"></i> In riempimento</small>';
print '    </div>';

print '  </div>';
print '</div>';


// --- BOX 2: GRAFICO DEMO (Canvas HTML5 + Script Chart.js) ---
print '<div class="box" style="margin-bottom: 20px;">';
print '  <div class="boxheader font-weight-bold"><i class="fa fa-chart-line"></i> Storico Rilevazioni Giornaliere</div>';
print '  <div class="boxcontents" style="padding: 15px; background: #fff;">';
print '    <canvas id="demoIotChart" style="max-height: 220px; width: 100%;"></canvas>';
print '  </div>';
print '</div>';


// --- BOX 3: TABELLA ULTIMI LOG ---
print '<div class="box">';
print '  <div class="boxheader font-weight-bold"><i class="fa fa-list"></i> Ultimi Dati Ricevuti dal Campo</div>';
print '  <div class="boxcontents">';
print '    <table class="noborder lists" width="100%">';
print '      <tr class="liste_titre">';
print '        <td>Ora Ricezione</td><td>ID Nodo</td><td>Tipo</td><td>Valore</td><td>Stato</td>';
print '      </tr>';
print '      <tr class="oddeven"><td>23:30:12</td><td><i class="fa fa-broadcast-tower"></i> SUOLO_01</td><td>Umidità</td><td>42%</td><td class="text-success">OK</td></tr>';
print '      <tr class="oddeven"><td>23:28:45</td><td><i class="fa fa-broadcast-tower"></i> TEMP_02</td><td>Temperatura</td><td style="color:#f1c40f; font-weight:bold;">28.4°C</td><td class="text-warning">WARN</td></tr>';
print '      <tr class="oddeven"><td>23:25:00</td><td><i class="fa fa-broadcast-tower"></i> SILO_03</td><td>Livello</td><td>85%</td><td class="text-success">OK</td></tr>';
print '    </table>';
print '  </div>';
print '</div>';


// --- INIEZIONE JAVASCRIPT PER IL GRAFICO ---
// Sfrutta l'istanza globale di Chart.js già caricata nell'head di Dolibarr
print '
<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("demoIotChart");
    if (ctx && typeof Chart !== "undefined") {
        new Chart(ctx.getContext("2d"), {
            type: "line",
            data: {
                labels: ["08:00", "10:00", "12:00", "14:00", "16:00", "18:00", "20:00"],
                datasets: [{
                    label: "Temperatura Serra 1 (°C)",
                    data: [19.5, 22.1, 26.4, 28.4, 27.8, 24.2, 21.0],
                    borderColor: "#2b5d8f",
                    backgroundColor: "rgba(43, 93, 143, 0.1)",
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: false } }
            }
        });
    }
});
</script>
';




print '</div>';

// End of page
llxFooter();
$db->close();
