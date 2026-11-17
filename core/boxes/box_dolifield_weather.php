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
 * \file    core/boxes/box_dolifield_weather.php
 * \ingroup dolifield
 * \brief   Widget with the weather of the next 48 hours for the weather points of the plots: frost, heavy rain,
 *          strong wind, first window suited to the treatments
 */

include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";

/**
 * Widget of the weather of the next 48 hours
 */
class box_dolifield_weather extends ModeleBoxes
{
	/** Precipitation in 48 hours shown as heavy, mm */
	const HEAVY_RAIN_MM = 20;

	public $boxcode = "dolifieldweather";
	public $boximg = "fa-cloud-sun-rain";
	public $boxlabel = 'BoxWeather48h';
	public $lang = 'dolifield@dolifield';
	public $depends = array('dolifield', 'dolifarm');
	public $widgettype = '';

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	string	$param		More parameters
	 */
	public function __construct(DoliDB $db, $param = '')
	{
		global $user;

		parent::__construct($db, $param);
		$this->param = $param;
		$this->hidden = !($user->hasRight('dolifield', 'read') && $user->hasRight('dolifarm', 'dolifarmplot', 'read'));
	}

	/**
	 * Load data into info_box_contents array to show array later
	 *
	 * @param	int		$max	Maximum number of records to load
	 * @return	void
	 */
	public function loadBox($max = 10)
	{
		global $user, $langs, $conf;

		$langs->loadLangs(array('dolifield@dolifield', 'dolifarm@dolifarm'));
		dol_include_once('/dolifield/class/dolifieldweather.class.php');
		dol_include_once('/dolifarm/lib/dolifarm_access.lib.php');

		$this->max = $max;
		$this->info_box_head = array('text' => $langs->trans('BoxWeather48hTitle'), 'limit' => 0, 'graph' => 0);
		$this->info_box_contents = array();

		// Plots visible to the user, by weather point
		$sql = "SELECT p.fk_station, t.rowid, t.ref, t.label FROM ".$this->db->prefix()."dolifield_weather_plot as p";
		$sql .= " INNER JOIN ".$this->db->prefix()."dolifarm_plot as t ON t.rowid = p.fk_plot";
		$sql .= " WHERE p.entity = ".((int) $conf->entity);
		if (function_exists('dolifarmSqlSocFilter')) {
			$sql .= dolifarmSqlSocFilter($user, 't.fk_soc');
		}
		$sql .= " ORDER BY t.ref";
		$resql = $this->db->query($sql);
		$plots = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$plots[(int) $obj->fk_station][] = $obj;
			}
			$this->db->free($resql);
		}

		$weather = new DoliFieldWeather($this->db);
		$stations = $weather->getStations(true);
		$now = dol_now();
		$i = 0;
		foreach (($stations ?: array()) as $station) {
			if (!isset($plots[$station['id']]) || $i >= $max) {
				continue;
			}
			$hours = $weather->getBestHours($station['id'], (int) (ceil($now / 3600) * 3600), $now + 48 * 3600);
			$forecast = array_filter($hours, static function (array $h): bool {
				return $h['kind'] == DoliFieldWeather::KIND_FORECAST;
			});
			$names = array();
			foreach (array_slice($plots[$station['id']], 0, 3) as $plot) {
				$names[] = '<a href="'.dol_buildpath('/dolifield/dolifield_plot_weather.php', 1).'?id='.((int) $plot->rowid).'">'.dol_escape_htmltag($plot->ref).'</a>';
			}
			$more = count($plots[$station['id']]) - count($names);
			$this->info_box_contents[$i][] = array('td' => 'class="tdoverflowmax150"', 'text' => implode(', ', $names).($more > 0 ? ' +'.$more : ''), 'asis' => 1);
			if (!count($forecast)) {
				$this->info_box_contents[$i][] = array('td' => 'colspan="4" class="opacitymedium"', 'text' => $langs->trans('WeatherNoForecast'));
				$i++;
				continue;
			}
			$tmin = null;
			$tmax = null;
			$rain = 0.0;
			$wind = 0.0;
			foreach ($forecast as $h) {
				if ($h['temp'] !== null) {
					$tmin = ($tmin === null ? $h['temp'] : min($tmin, $h['temp']));
					$tmax = ($tmax === null ? $h['temp'] : max($tmax, $h['temp']));
				}
				$rain += (float) $h['precip'];
				$wind = max($wind, (float) $h['wind']);
			}
			$limit = DoliFieldWeather::thresholds();
			$temp = DoliFieldWeather::num((float) $tmin, 0).' / '.DoliFieldWeather::num((float) $tmax, 0).' °C';
			if ($tmin !== null && $tmin <= 0) {
				$temp = dolGetBadge($langs->trans('WeatherFrost'), '', 'danger').' '.$temp;
			}
			$raintext = DoliFieldWeather::num($rain).' mm';
			if ($rain >= self::HEAVY_RAIN_MM) {
				$raintext = '<span class="error">'.$raintext.'</span>';
			}
			$windtext = DoliFieldWeather::num($wind).' m/s';
			if ($wind > $limit['wind']) {
				$windtext = '<span class="warning">'.$windtext.'</span>';
			}
			$windows = $weather->getTreatmentWindows($station['id'], 48);
			$window = (count($windows) ? dol_print_date($windows[0]['from'], 'dayhourreduceformat', 'tzuserrel').' - '.dol_print_date($windows[0]['to'], 'hour', 'tzuserrel') : '<span class="opacitymedium">'.$langs->trans('WeatherNoWindow').'</span>');
			$this->info_box_contents[$i][] = array('td' => 'class="nowraponall"', 'text' => $temp, 'asis' => 1);
			$this->info_box_contents[$i][] = array('td' => 'class="right nowraponall"', 'text' => $raintext, 'asis' => 1);
			$this->info_box_contents[$i][] = array('td' => 'class="right nowraponall"', 'text' => $windtext, 'asis' => 1);
			$this->info_box_contents[$i][] = array('td' => 'class="right nowraponall" title="'.dol_escape_htmltag($langs->trans('WeatherWindowHelp')).'"', 'text' => $window, 'asis' => 1);
			$i++;
		}
		if ($i == 0) {
			$this->info_box_contents[0][] = array('td' => 'class="center"', 'text' => '<span class="opacitymedium">'.$langs->trans('WeatherNoPoints').'</span>', 'asis' => 1);
		} else {
			$this->info_box_contents[$i][] = array('td' => 'colspan="5" class="opacitymedium small"', 'text' => $langs->trans('BoxWeather48hLegend').' '.$langs->trans('WeatherAttributionMetno'));
		}
	}

	/**
	 * Method to show box
	 *
	 * @param	?array<array{text?:string,sublink?:string,subpicto:?string,nbcol?:int,limit?:int,subclass?:string,graph?:int,target?:string}>	$head		Array with properties of box title
	 * @param	?array<array<array{tr?:string,td?:string,target?:string,text?:string,text2?:string,textnoformat?:string,tooltip?:string,logo?:string,url?:string,maxlength?:int,asis?:int,asis2?:int}>>	$contents	Array with properties of box lines
	 * @param	int<0,1>	$nooutput	No print, only return string
	 * @return	string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
