<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 *	\brief      Area page of the field data (demonstration values until the sensors are connected)
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
$langs->loadLangs(array("dolifield@dolifield"));

if (!isModEnabled('dolifield') || !$user->hasRight('dolifield', 'read')) {
	accessforbidden();
}


/*
 * View
 *
 * Demonstration page: the values are examples written here, no sensor is read yet.
 */

llxHeader("", $langs->trans("DoliFieldArea"), '', '', 0, 0, '', '', '', 'mod-dolifield page-index');

print load_fiche_titre($langs->trans("DoliFieldArea"), '', 'fa-satellite-dish');

print '<div class="fichecenter">';

print '<div class="warning" style="margin-bottom: 20px;">';
print '<strong><span class="fa fa-info-circle paddingright"></span>'.$langs->trans('DoliFieldInDevelopment').'</strong><br>';
print $langs->trans('DoliFieldInDevelopmentHelp');
print '</div>';

// Examples of live values
$sensors = array(
	array('node' => 'SUOLO_01', 'label' => 'DoliFieldDemoSoilMoisture', 'value' => '42%', 'state' => 'ok', 'color' => '#2ecc71'),
	array('node' => 'TEMP_02', 'label' => 'DoliFieldDemoGreenhouseTemp', 'value' => '28.4 °C', 'state' => 'warn', 'color' => '#f1c40f'),
	array('node' => 'SILO_03', 'label' => 'DoliFieldDemoSiloLevel', 'value' => '85%', 'state' => 'filling', 'color' => '#3498db'),
);
$states = array(
	'ok' => array('fa-check-circle', 'DoliFieldDemoStateOk'),
	'warn' => array('fa-exclamation-triangle', 'DoliFieldDemoStateWarn'),
	'filling' => array('fa-sync', 'DoliFieldDemoStateFilling'),
);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="3"><span class="fa fa-microchip paddingright"></span>'.$langs->trans('DoliFieldDemoLiveTitle').' <span class="badge badge-warning">'.$langs->trans('DoliFieldDemoData').'</span></th></tr>';
print '<tr class="oddeven">';
foreach ($sensors as $sensor) {
	print '<td style="border-left: 4px solid '.$sensor['color'].';">';
	print '<span class="opacitymedium small">'.dol_escape_htmltag($sensor['node']).'</span><br>';
	print $langs->trans($sensor['label']).'<br>';
	print '<strong style="font-size: 1.8em;">'.dol_escape_htmltag($sensor['value']).'</strong><br>';
	print '<span class="small" style="color: '.$sensor['color'].';"><span class="fa '.$states[$sensor['state']][0].' paddingright"></span>'.$langs->trans($states[$sensor['state']][1]).'</span>';
	print '</td>';
}
print '</tr>';
print '</table>';
print '</div>';
print '<br>';

// Example of daily history
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th><span class="fa fa-chart-line paddingright"></span>'.$langs->trans('DoliFieldDemoHistoryTitle').' <span class="badge badge-warning">'.$langs->trans('DoliFieldDemoData').'</span></th></tr>';
print '<tr class="oddeven"><td><div style="height: 220px;"><canvas id="dolifieldDemoChart"></canvas></div></td></tr>';
print '</table>';
print '</div>';
print '<br>';

// Example of last readings
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="5"><span class="fa fa-list paddingright"></span>'.$langs->trans('DoliFieldDemoLastTitle').' <span class="badge badge-warning">'.$langs->trans('DoliFieldDemoData').'</span></th></tr>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('DoliFieldDemoTime').'</td><td>'.$langs->trans('DoliFieldDemoNode').'</td><td>'.$langs->trans('Type').'</td><td>'.$langs->trans('Value').'</td><td>'.$langs->trans('Status').'</td>';
print '</tr>';
$readings = array(
	array('23:30:12', 'SUOLO_01', 'DoliFieldDemoSoilMoisture', '42%', 'ok'),
	array('23:28:45', 'TEMP_02', 'DoliFieldDemoGreenhouseTemp', '28.4 °C', 'warn'),
	array('23:25:00', 'SILO_03', 'DoliFieldDemoSiloLevel', '85%', 'ok'),
);
foreach ($readings as $reading) {
	print '<tr class="oddeven">';
	print '<td>'.$reading[0].'</td>';
	print '<td><span class="fa fa-broadcast-tower paddingright"></span>'.dol_escape_htmltag($reading[1]).'</td>';
	print '<td>'.$langs->trans($reading[2]).'</td>';
	print '<td>'.dol_escape_htmltag($reading[3]).'</td>';
	print '<td>'.($reading[4] == 'warn' ? '<span class="badge badge-status1">WARN</span>' : '<span class="badge badge-status4">OK</span>').'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

// Chart.js is loaded by Dolibarr on the pages that use it: the chart is drawn only when it is available
$chartlabel = dol_escape_js($langs->transnoentitiesnoconv('DoliFieldDemoGreenhouseTemp').' (°C)');
print '<script>
document.addEventListener("DOMContentLoaded", function() {
	var ctx = document.getElementById("dolifieldDemoChart");
	if (ctx && typeof Chart !== "undefined") {
		new Chart(ctx.getContext("2d"), {
			type: "line",
			data: {
				labels: ["08:00", "10:00", "12:00", "14:00", "16:00", "18:00", "20:00"],
				datasets: [{
					label: "'.$chartlabel.'",
					data: [19.5, 22.1, 26.4, 28.4, 27.8, 24.2, 21.0],
					borderColor: "#2b5d8f",
					backgroundColor: "rgba(43, 93, 143, 0.1)",
					borderWidth: 2,
					tension: 0.3,
					fill: true
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
		});
	}
});
</script>';

print '</div>';

llxFooter();
$db->close();
