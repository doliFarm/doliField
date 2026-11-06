<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    dolifield/admin/setup.php
 * \ingroup dolifield
 * \brief   DoliField setup page.
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/dolifield.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "dolifield@dolifield"));

$hookmanager->initHooks(array('dolifieldsetup', 'globalsetup'));

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

if (!$user->admin) {
	accessforbidden();
}

dol_include_once('/dolifield/class/dolifieldweather.class.php');

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Weather: history from Copernicus ERA5-Land through the API of the Climate Data Store
$formSetup->newItem('WeatherSetup')->setAsTitle();

// Key of the Climate Data Store: never printed in the page, checked before it is saved (constant of entity 0,
// stored encrypted by dolibarr_set_const because its name ends with _KEY). An empty field keeps the key.
$item = $formSetup->newItem('DOLIFIELD_CDS_API_KEY');
$item->nameText = $langs->transnoentities('WeatherCdsKey');
$item->helpText = $langs->transnoentities('WeatherCdsKeyHelp');
$cdskeyset = (getDolGlobalString('DOLIFIELD_CDS_API_KEY') !== '');
$item->fieldInputOverride = '<input type="password" class="flat minwidth300" name="DOLIFIELD_CDS_API_KEY" value="" autocomplete="new-password" placeholder="'.dol_escape_htmltag($cdskeyset ? $langs->transnoentities('WeatherCdsKeyIsSet') : '').'">';
if ($cdskeyset) {
	$item->fieldInputOverride .= ' <label><input type="checkbox" name="DOLIFIELD_CDS_API_KEY_remove" value="1"> '.$langs->trans('WeatherCdsKeyRemove').'</label>';
}
$item->fieldOutputOverride = ($cdskeyset ? img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('WeatherCdsKeySet') : '<span class="opacitymedium">'.$langs->trans('WeatherCdsKeyNotSet').'</span>');
$item->setValueFromPostCallBack(function () use ($db, $langs, $conf) {
	if (GETPOST('DOLIFIELD_CDS_API_KEY_remove', 'int')) {
		dolibarr_del_const($db, 'DOLIFIELD_CDS_API_KEY', 0);
		setEventMessages($langs->trans('WeatherCdsKeyRemoved'), null, 'mesgs');
		return 0;
	}
	$apikey = trim(GETPOST('DOLIFIELD_CDS_API_KEY', 'password'));
	// The header copies the posted values in the link of the print view: the key must not be there
	unset($_POST['DOLIFIELD_CDS_API_KEY'], $_GET['DOLIFIELD_CDS_API_KEY']);
	if ($apikey === '') {
		return 0;
	}
	$previous = getDolGlobalString('DOLIFIELD_CDS_API_KEY');
	$conf->global->DOLIFIELD_CDS_API_KEY = $apikey;
	$weather = new DoliFieldWeather($db);
	$check = $weather->checkKey();
	if ($check >= 0) {
		dolibarr_set_const($db, 'DOLIFIELD_CDS_API_KEY', $apikey, 'chaine', 0, 'Personal access token of the Climate Data Store', 0);
		setEventMessages($langs->trans('WeatherCdsKeySaved'), null, 'mesgs');
		if ($check == 0) {
			setEventMessages($weather->error, null, 'warnings');
		}
	} else {
		$conf->global->DOLIFIELD_CDS_API_KEY = $previous;
		setEventMessages($weather->error, null, 'errors');
	}
	// The constant is written here (entity 0), not by the form
	return 0;
});

$item = $formSetup->newItem('DOLIFIELD_WEATHER_START_YEAR');
$item->nameText = $langs->transnoentities('WeatherStartYear');
$item->helpText = $langs->transnoentities('WeatherStartYearHelp');
$item->defaultFieldValue = '1991';
$item->cssClass = 'maxwidth75';

$item = $formSetup->newItem('DOLIFIELD_WEATHER_HOURLY_MONTHS');
$item->nameText = $langs->transnoentities('WeatherHourlyMonths');
$item->helpText = $langs->transnoentities('WeatherHourlyMonthsHelp');
$item->defaultFieldValue = '13';
$item->cssClass = 'maxwidth75';


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// Numbers in a valid range
if ($action == 'update') {
	if (GETPOSTISSET('DOLIFIELD_WEATHER_START_YEAR')) {
		$year = GETPOSTINT('DOLIFIELD_WEATHER_START_YEAR');
		dolibarr_set_const($db, 'DOLIFIELD_WEATHER_START_YEAR', ($year >= 1950 && $year <= (int) date('Y') ? $year : 1991), 'chaine', 0, '', $conf->entity);
	}
	if (GETPOSTISSET('DOLIFIELD_WEATHER_HOURLY_MONTHS')) {
		dolibarr_set_const($db, 'DOLIFIELD_WEATHER_HOURLY_MONTHS', max(0, GETPOSTINT('DOLIFIELD_WEATHER_HOURLY_MONTHS')), 'chaine', 0, '', $conf->entity);
	}
}

// Check of the key and of the licence of the dataset
$cdscheck = null;
$cdscheckerror = '';
if ($action == 'checkcds' && getDolGlobalString('DOLIFIELD_CDS_API_KEY') !== '') {
	$weather = new DoliFieldWeather($db);
	$cdscheck = $weather->checkKey();
	$cdscheckerror = $weather->error;
}


/*
 * View
 */

$title = "DoliFieldSetup";

llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-dolifield page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = dolifieldAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "dolifield@dolifield");

print '<span class="opacitymedium">'.$langs->trans("DoliFieldSetupPage").'</span><br><br>';

print $formSetup->generateOutput(true);
print '<br>';

// Account of the Climate Data Store: licence of the dataset, accepted once on the site by the owner of the account
print '<div class="info">'.$langs->trans('WeatherCdsHowTo', DoliFieldWeather::CDS_DATASET_PAGE).'</div>';
if ($cdscheck !== null) {
	if ($cdscheck > 0) {
		print '<div class="ok">'.img_picto('', 'tick', 'class="pictofixedwidth"').$langs->trans('WeatherCdsCheckOk').'</div>';
	} else {
		print '<div class="error">'.dol_escape_htmltag($cdscheckerror).'</div>';
	}
}
if (getDolGlobalString('DOLIFIELD_CDS_API_KEY') !== '') {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?action=checkcds&token='.newToken()).'">'.$langs->trans('WeatherCdsCheck').'</a>';
	print '<a class="butAction" href="'.dol_buildpath('/dolifield/dolifield_weather.php', 1).'">'.$langs->trans('WeatherPointsList').'</a>';
	print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
