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
 * \file       dolifield_plot_weather.php
 * \ingroup    dolifield
 * \brief      Tab "Weather" of a plot of doliFarm: weather point, precipitation compared with the climate normal,
 *             last days, last months, years
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

dol_include_once('/dolifarm/class/dolifarmplot.class.php');
dol_include_once('/dolifarm/lib/dolifarm_plot.lib.php');
dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');
dol_include_once('/dolifield/class/dolifieldweather.class.php');

$langs->loadLangs(array('dolifarm@dolifarm', 'dolifield@dolifield'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');

if (!isModEnabled('dolifarm') || !isModEnabled('dolifield')) {
	accessforbidden();
}
if (!$user->hasRight('dolifarm', 'dolifarmplot', 'read') || !$user->hasRight('dolifield', 'read')) {
	accessforbidden();
}

$object = new DoliFarmPlot($db);
if ($object->fetch($id, $ref) <= 0) {
	accessforbidden();
}
// Farm of the plot: users restricted to their third parties
dolifarmCheckSocAccess($user, $object);

$weather = new DoliFieldWeather($db);


/*
 * View
 */

$title = $langs->trans('DoliFarmPlot').' - '.$langs->trans('Weather');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-dolifield page-plot-weather');

$head = dolifarmplotPrepareHead($object);
print dol_get_fiche_head($head, 'dolifieldweather', $langs->trans('DoliFarmPlot'), -1, $object->picto);
$linkback = '<a href="'.dol_buildpath('/dolifarm/dolifarmplot_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', '<div class="refidno"></div>');
print '<div class="fichecenter"><div class="underbanner clearboth"></div>';

$hascoords = ($object->geo_lat !== null && $object->geo_lat !== '' && $object->geo_lon !== null && $object->geo_lon !== '' && ((float) $object->geo_lat != 0 || (float) $object->geo_lon != 0));
$points = $weather->getPlotStations((int) $object->id);
$station = (count($points) ? $points[0] : null);

if (!$hascoords) {
	print '<br><div class="warning">'.$langs->trans('WeatherPlotNoCoords').'</div>';
} elseif ($station === null) {
	print '<br><div class="info">'.$langs->trans('WeatherPlotNoPoint').'</div>';
}

if ($station !== null) {
	// Weather point
	print '<br><table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans('WeatherPoint').'</td><td>'.dol_escape_htmltag($station['label']);
	print ' <span class="opacitymedium">('.$langs->trans('WeatherDistance', price(round($station['distance_km'], 1))).')</span></td></tr>';
	print '<tr><td>'.$langs->trans('WeatherSource').'</td><td>'.$langs->trans('WeatherSource_'.$station['source']).'</td></tr>';
	print '<tr><td>'.$langs->trans('WeatherDataPeriod').'</td><td>';
	if ($station['data_to'] !== '') {
		print dol_print_date(dol_stringtotime($station['data_from']), 'day').' - '.dol_print_date(dol_stringtotime($station['data_to']), 'day');
	} elseif ($station['history_status'] == DoliFieldWeather::HISTORY_REQUESTED) {
		print '<span class="opacitymedium">'.$langs->trans('WeatherHistoryRequested').'</span>';
	} elseif ($station['history_status'] == DoliFieldWeather::HISTORY_ERROR) {
		print '<span class="error">'.$langs->trans('WeatherHistoryError').'</span>';
	} else {
		print '<span class="opacitymedium">'.$langs->trans('WeatherHistoryTodo').'</span>';
	}
	print '</td></tr>';
	print '</table>';
}

if ($station !== null && $station['data_to'] !== '') {
	$stationid = (int) $station['id'];
	$lastday = $station['data_to'];
	$lastts = dol_stringtotime($lastday.' 00:00:00', 1);
	$normfrom = 1991;
	$normto = 2020;
	$monthly = $weather->getMonthly($stationid);

	// Precipitation of the last days, compared with the same days of the years of the climate normal
	print '<br>';
	print load_fiche_titre($langs->trans('WeatherPrecipTitle', dol_print_date($lastts, 'day', 'gmt')), '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Period').'</th><th class="right">'.$langs->trans('WeatherPrecip').'</th>';
	print '<th class="right">'.$langs->trans('WeatherNormal', $normfrom, $normto).'</th><th class="right">'.$langs->trans('WeatherDifference').'</th></tr>';
	foreach (array(30, 90, 180, 365) as $nbdays) {
		$from = dol_print_date(dol_time_plus_duree($lastts, -($nbdays - 1), 'd'), '%Y-%m-%d', 'gmt');
		$period = $weather->getPeriodPrecip($stationid, $from, $lastday, $normfrom, $normto);
		print '<tr class="oddeven"><td>'.$langs->trans('WeatherLastDays', $nbdays).'</td>';
		print '<td class="right">'.($period['precip'] === null ? '' : price(round($period['precip'])).' mm').'</td>';
		print '<td class="right">'.($period['normal'] === null ? '' : price(round($period['normal'])).' mm').'</td>';
		print '<td class="right">';
		if ($period['precip'] !== null && $period['normal'] !== null && $period['normal'] > 0) {
			$diff = ($period['precip'] - $period['normal']) / $period['normal'] * 100;
			print '<span class="'.($diff < -20 ? 'error' : ($diff > 20 ? 'ok' : '')).'">'.($diff > 0 ? '+' : '').price(round($diff)).' %'.'</span>';
		}
		print '</td></tr>';
	}
	print '</table></div>';

	// Last days
	print '<br>';
	print load_fiche_titre($langs->trans('WeatherLastDaysTitle'), '', '');
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th>';
	print '<th class="right">'.$langs->trans('WeatherTmin').'</th><th class="right">'.$langs->trans('WeatherTmax').'</th>';
	print '<th class="right">'.$langs->trans('WeatherRh').'</th><th class="right">'.$langs->trans('WeatherPrecip').'</th>';
	print '<th class="right">'.$langs->trans('WeatherWind').'</th><th class="right">'.$langs->trans('WeatherRadiation').'</th>';
	print '<th class="right">'.$langs->trans('WeatherSoilWater').'</th></tr>';
	foreach ($weather->getLastDays($stationid, 14) as $day) {
		print '<tr class="oddeven"><td>'.dol_print_date(dol_stringtotime($day['day'].' 00:00:00', 1), 'daytext', 'gmt').'</td>';
		print '<td class="right">'.($day['tmin'] === null ? '' : price(round((float) $day['tmin'], 1)).' °C').'</td>';
		print '<td class="right">'.($day['tmax'] === null ? '' : price(round((float) $day['tmax'], 1)).' °C').'</td>';
		print '<td class="right">'.($day['rh_mean'] === null ? '' : price(round((float) $day['rh_mean'])).' %').'</td>';
		print '<td class="right">'.($day['precip'] === null ? '' : price(round((float) $day['precip'], 1)).' mm').'</td>';
		print '<td class="right">'.($day['wind_mean'] === null ? '' : price(round((float) $day['wind_mean'], 1)).' m/s').'</td>';
		print '<td class="right">'.($day['radiation'] === null ? '' : price(round((float) $day['radiation'], 1)).' MJ/m²').'</td>';
		print '<td class="right">'.($day['soil_water'] === null ? '' : price(round((float) $day['soil_water'] * 100)).' %').'</td>';
		print '</tr>';
	}
	print '</table></div>';

	// Last 12 months, compared with the climate normal of the month
	$normals = array();
	foreach ($monthly as $ym => $m) {
		$y = (int) substr($ym, 0, 4);
		$mo = (int) substr($ym, 5, 2);
		$daysinmonth = (int) gmdate('t', gmmktime(0, 0, 0, $mo, 1, $y));
		if ($y < $normfrom || $y > $normto || $m['days'] < $daysinmonth) {
			continue;
		}
		if (!isset($normals[$mo])) {
			$normals[$mo] = array('precip' => 0.0, 'np' => 0, 'tmin' => 0.0, 'tmax' => 0.0, 'nt' => 0);
		}
		if ($m['precip'] !== null && $m['precipdays'] >= $daysinmonth) {
			$normals[$mo]['precip'] += $m['precip'];
			$normals[$mo]['np']++;
		}
		if ($m['tmin'] !== null && $m['tmax'] !== null) {
			$normals[$mo]['tmin'] += $m['tmin'];
			$normals[$mo]['tmax'] += $m['tmax'];
			$normals[$mo]['nt']++;
		}
	}
	print '<br>';
	print load_fiche_titre($langs->trans('WeatherMonthsTitle'), '', '');
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Month').'</th>';
	print '<th class="right">'.$langs->trans('WeatherPrecip').'</th><th class="right">'.$langs->trans('WeatherNormalShort').'</th>';
	print '<th class="right">'.$langs->trans('WeatherTminMean').'</th><th class="right">'.$langs->trans('WeatherNormalShort').'</th>';
	print '<th class="right">'.$langs->trans('WeatherTmaxMean').'</th><th class="right">'.$langs->trans('WeatherNormalShort').'</th></tr>';
	$lastmonths = array_slice(array_keys($monthly), -12);
	foreach (array_reverse($lastmonths) as $ym) {
		$m = $monthly[$ym];
		$mo = (int) substr($ym, 5, 2);
		$n = $normals[$mo] ?? null;
		$daysinmonth = (int) gmdate('t', gmmktime(0, 0, 0, $mo, 1, (int) substr($ym, 0, 4)));
		$partial = ($m['days'] < $daysinmonth);
		print '<tr class="oddeven"><td>'.dol_print_date(dol_mktime(12, 0, 0, $mo, 1, (int) substr($ym, 0, 4)), '%B %Y').($partial ? ' <span class="opacitymedium small">('.$langs->trans('WeatherPartialMonth', $m['days']).')</span>' : '').'</td>';
		print '<td class="right">'.($m['precip'] === null ? '' : price(round($m['precip'])).' mm').'</td>';
		print '<td class="right opacitymedium">'.($n && $n['np'] ? price(round($n['precip'] / $n['np'])).' mm' : '').'</td>';
		print '<td class="right">'.($m['tmin'] === null ? '' : price(round($m['tmin'], 1)).' °C').'</td>';
		print '<td class="right opacitymedium">'.($n && $n['nt'] ? price(round($n['tmin'] / $n['nt'], 1)).' °C' : '').'</td>';
		print '<td class="right">'.($m['tmax'] === null ? '' : price(round($m['tmax'], 1)).' °C').'</td>';
		print '<td class="right opacitymedium">'.($n && $n['nt'] ? price(round($n['tmax'] / $n['nt'], 1)).' °C' : '').'</td>';
		print '</tr>';
	}
	print '</table></div>';

	// Years
	$years = array();
	foreach ($monthly as $ym => $m) {
		$y = (int) substr($ym, 0, 4);
		if (!isset($years[$y])) {
			$years[$y] = array('precip' => 0.0, 'precipdays' => 0, 'days' => 0, 'tmin' => 0.0, 'tmax' => 0.0, 'nt' => 0);
		}
		$years[$y]['precip'] += (float) $m['precip'];
		$years[$y]['precipdays'] += $m['precipdays'];
		$years[$y]['days'] += $m['days'];
		if ($m['tmin'] !== null && $m['tmax'] !== null) {
			$years[$y]['tmin'] += $m['tmin'] * $m['days'];
			$years[$y]['tmax'] += $m['tmax'] * $m['days'];
			$years[$y]['nt'] += $m['days'];
		}
	}
	krsort($years);
	print '<br>';
	print load_fiche_titre($langs->trans('WeatherYearsTitle'), '', '');
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Year').'</th><th class="right">'.$langs->trans('WeatherPrecip').'</th>';
	print '<th class="right">'.$langs->trans('WeatherTminMean').'</th><th class="right">'.$langs->trans('WeatherTmaxMean').'</th><th></th></tr>';
	foreach ($years as $y => $yd) {
		$daysinyear = ((int) gmdate('t', gmmktime(0, 0, 0, 2, 1, $y)) == 29 ? 366 : 365);
		print '<tr class="oddeven"><td>'.$y.'</td>';
		print '<td class="right">'.($yd['precipdays'] ? price(round($yd['precip'])).' mm' : '').'</td>';
		print '<td class="right">'.($yd['nt'] ? price(round($yd['tmin'] / $yd['nt'], 1)).' °C' : '').'</td>';
		print '<td class="right">'.($yd['nt'] ? price(round($yd['tmax'] / $yd['nt'], 1)).' °C' : '').'</td>';
		print '<td class="opacitymedium small">'.($yd['days'] < $daysinyear ? $langs->trans('WeatherPartialYear', $yd['days']) : '').'</td>';
		print '</tr>';
	}
	print '</table></div>';
}

print '<br><div class="opacitymedium small">'.$langs->trans('WeatherDaysUtc').' '.$langs->trans('WeatherAttributionEra5').'</div>';

print '</div>';
print dol_get_fiche_end();

llxFooter();
$db->close();
