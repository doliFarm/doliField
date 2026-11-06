<?php
/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       dolifield_weather.php
 * \ingroup    dolifield
 * \brief      Weather points of the plots: source, plots, history loaded, requests in progress, errors
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
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

dol_include_once('/dolifield/class/dolifieldweather.class.php');

$langs->loadLangs(array('dolifield@dolifield'));

$action = GETPOST('action', 'aZ09');

if (!isModEnabled('dolifield') || !$user->hasRight('dolifield', 'read') || $user->socid > 0) {
	accessforbidden();
}
$permissiontowrite = $user->hasRight('dolifield', 'weather', 'write');

$weather = new DoliFieldWeather($db);


/*
 * Actions
 */

// Same work as the scheduled job, run now: links of the plots, requests, load of the results ready
if ($action == 'runnow' && $permissiontowrite) {
	@set_time_limit(900);
	$result = $weather->cronUpdate();
	setEventMessages(dol_nl2br(dol_escape_htmltag($weather->output)), null, ($result == 0 ? 'mesgs' : 'errors'));
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}
if ($action == 'syncplots' && $permissiontowrite) {
	$result = $weather->syncPlots();
	if ($result < 0) {
		setEventMessages($weather->error, null, 'errors');
	} else {
		setEventMessages($langs->trans('WeatherPlotsLinked', $result), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$title = $langs->trans('WeatherPointsList');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifield page-weather');

$morehtmlright = '';
if ($permissiontowrite) {
	$morehtmlright .= dolGetButtonTitle($langs->trans('WeatherSyncPlots'), '', 'fa fa-link', $_SERVER['PHP_SELF'].'?action=syncplots&token='.newToken());
	$morehtmlright .= dolGetButtonTitle($langs->trans('WeatherRunNow'), '', 'fa fa-sync', $_SERVER['PHP_SELF'].'?action=runnow&token='.newToken());
}
print load_fiche_titre($title, $morehtmlright, 'fa-cloud-sun-rain');

print '<p class="opacitymedium">'.$langs->trans('WeatherPointsHelp').'</p>';
if (getDolGlobalString('DOLIFIELD_CDS_API_KEY') === '') {
	print '<div class="warning">'.$langs->trans('WeatherCdsKeyMissing');
	if ($user->admin) {
		print ' <a href="'.dol_buildpath('/dolifield/admin/setup.php', 1).'">'.$langs->trans('Setup').'</a>';
	}
	print '</div>';
}

$stations = $weather->getStations();
if ($stations === false) {
	setEventMessages($weather->error, null, 'errors');
	$stations = array();
}

$statuslabels = array(
	DoliFieldWeather::HISTORY_TODO => array('WeatherHistoryTodo', 'status0'),
	DoliFieldWeather::HISTORY_REQUESTED => array('WeatherHistoryRequested', 'status3'),
	DoliFieldWeather::HISTORY_LOADED => array('WeatherHistoryLoaded', 'status4'),
	DoliFieldWeather::HISTORY_ERROR => array('WeatherHistoryError', 'status8'),
);

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('WeatherPoint').'</th>';
print '<th>'.$langs->trans('WeatherSource').'</th>';
print '<th class="right">'.$langs->trans('WeatherLatitude').'</th>';
print '<th class="right">'.$langs->trans('WeatherLongitude').'</th>';
print '<th class="right">'.$langs->trans('WeatherPlots').'</th>';
print '<th class="center">'.$langs->trans('WeatherDataFrom').'</th>';
print '<th class="center">'.$langs->trans('WeatherDataTo').'</th>';
print '<th class="center">'.$langs->trans('WeatherLastLoad').'</th>';
print '<th>'.$langs->trans('Status').'</th>';
print '</tr>';
if (!count($stations)) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('WeatherNoPoints').'</span></td></tr>';
}
foreach ($stations as $station) {
	print '<tr class="oddeven'.($station['status'] ? '' : ' opacitymedium').'">';
	print '<td>'.dol_escape_htmltag($station['label']).'</td>';
	print '<td>'.$langs->trans('WeatherSource_'.$station['source']).'</td>';
	print '<td class="right">'.price2num($station['lat'], 4).'</td>';
	print '<td class="right">'.price2num($station['lon'], 4).'</td>';
	print '<td class="right">'.$station['nb_plots'].'</td>';
	print '<td class="center">'.($station['data_from'] ? dol_print_date(dol_stringtotime($station['data_from']), 'day') : '').'</td>';
	print '<td class="center">'.($station['data_to'] ? dol_print_date(dol_stringtotime($station['data_to']), 'day') : '').'</td>';
	print '<td class="center">'.($station['date_last_load'] ? dol_print_date($station['date_last_load'], 'dayhour') : '').'</td>';
	$st = $statuslabels[$station['history_status']] ?? array('Unknown', 'status0');
	$label = $langs->trans($st[0]);
	if ($station['history_status'] == DoliFieldWeather::HISTORY_REQUESTED) {
		$label .= ' ('.dol_print_date(dol_stringtotime($station['history_job_from']), 'day').' - '.dol_print_date(dol_stringtotime($station['history_job_to']), 'day').')';
	}
	print '<td>'.dolGetStatus($label, $label, '', $st[1], 5);
	if (!$station['status']) {
		print ' <span class="opacitymedium">'.$langs->trans('WeatherPointUnused').'</span>';
	}
	if ($station['history_status'] == DoliFieldWeather::HISTORY_ERROR && $station['last_error'] !== '') {
		print '<br><span class="error small">'.dol_escape_htmltag($station['last_error']).'</span>';
	}
	print '</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

print '<br><div class="opacitymedium small">'.$langs->trans('WeatherAttributionEra5').'</div>';

llxFooter();
$db->close();
