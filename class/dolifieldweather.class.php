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
 * \file        class/dolifieldweather.class.php
 * \ingroup     dolifield
 * \brief       Weather of the plots: weather points, history from Copernicus ERA5-Land, daily values
 *
 * The history comes from the dataset "ERA5-Land hourly time-series data from 1950 to present" of the Copernicus
 * Climate Data Store (licence CC-BY 4.0): one hourly series for the grid point (0.1 degrees, about 9 km) nearest to
 * the plot, requested as CSV through the API of the Climate Data Store (account and personal access token of
 * doliFarm, set in the setup). The request is asynchronous: the scheduled job sends it, checks it at the next runs,
 * downloads the file, keeps it in the documents and loads it.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Weather service of doliField
 */
class DoliFieldWeather
{
	/** Source: ERA5-Land of Copernicus (grid point) */
	const SOURCE_ERA5LAND = 'era5land';

	/** Kinds of values */
	const KIND_REANALYSIS = 1;
	const KIND_OBSERVED = 2;
	const KIND_FORECAST = 3;

	/** Status of the history of a weather point */
	const HISTORY_TODO = 0;
	const HISTORY_REQUESTED = 1;
	const HISTORY_LOADED = 2;
	const HISTORY_ERROR = 9;

	/** Climate Data Store */
	const CDS_URL = 'https://cds.climate.copernicus.eu/api';
	const CDS_DATASET = 'reanalysis-era5-land-timeseries';
	const CDS_DATASET_PAGE = 'https://cds.climate.copernicus.eu/datasets/reanalysis-era5-land-timeseries';
	const CDS_LICENCE = 'cc-by';

	/** ERA5-Land is published with a delay of about 6 days: requests stop 7 days before today */
	const ERA5LAND_DELAY_DAYS = 7;
	/** Days loaded again at each update (the last days of the previous load may be incomplete) */
	const ERA5LAND_OVERLAP_DAYS = 10;
	/** Hours between two updates of a weather point that is already loaded */
	const UPDATE_EVERY_HOURS = 20;
	/** New requests sent by one run of the scheduled job */
	const MAX_REQUESTS_PER_RUN = 5;

	/** Variables of ERA5-Land requested */
	const ERA5LAND_VARIABLES = array(
		'2m_temperature',
		'2m_dewpoint_temperature',
		'total_precipitation',
		'10m_u_component_of_wind',
		'10m_v_component_of_wind',
		'surface_solar_radiation_downwards',
		'volumetric_soil_water_level_1',
		'surface_pressure',
	);

	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Last error
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var string Output of the scheduled job
	 */
	public $output = '';

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/*
	 * Weather points
	 */

	/**
	 * Grid point of ERA5-Land (0.1 degrees) of a position
	 *
	 * @param	float	$lat	Latitude
	 * @param	float	$lon	Longitude
	 * @return	array{code:string,lat:float,lon:float}	Code of the point and its coordinates
	 */
	public static function era5GridPoint(float $lat, float $lon): array
	{
		$glat = round($lat * 10) / 10;
		$glon = round($lon * 10) / 10;
		return array('code' => sprintf('%.2f_%.2f', $glat, $glon), 'lat' => $glat, 'lon' => $glon);
	}

	/**
	 * Distance in km between two positions (haversine)
	 *
	 * @param	float	$lat1	Latitude 1
	 * @param	float	$lon1	Longitude 1
	 * @param	float	$lat2	Latitude 2
	 * @param	float	$lon2	Longitude 2
	 * @return	float			Distance in km
	 */
	public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
	{
		$dlat = deg2rad($lat2 - $lat1);
		$dlon = deg2rad($lon2 - $lon1);
		$a = sin($dlat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlon / 2) ** 2;
		return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
	}

	/**
	 * Create the weather points of the plots that have coordinates and link the plots to them
	 *
	 * Only the plots of the managed farms, not disabled. A plot that moved is linked to its new point; the points
	 * without plots are set as not used (their data are kept).
	 *
	 * @return	int		Number of plots linked, <0 if error
	 */
	public function syncPlots(): int
	{
		global $conf;

		dol_include_once('/dolifarm/lib/dolifarm_farm.lib.php');

		$sql = "SELECT t.rowid, t.geo_lat, t.geo_lon FROM ".$this->db->prefix()."dolifarm_plot as t";
		$sql .= " WHERE t.entity = ".((int) $conf->entity);
		$sql .= " AND (t.status IS NULL OR t.status <> 9)";
		$sql .= " AND t.geo_lat IS NOT NULL AND t.geo_lon IS NOT NULL AND (t.geo_lat <> 0 OR t.geo_lon <> 0)";
		if (function_exists('dolifarmSqlManagedFarmFilter')) {
			$sql .= dolifarmSqlManagedFarmFilter('t.fk_soc');
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$plots = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$plots[(int) $obj->rowid] = array('lat' => (float) $obj->geo_lat, 'lon' => (float) $obj->geo_lon);
		}
		$this->db->free($resql);

		$this->db->begin();
		$nb = 0;
		$used = array();
		foreach ($plots as $plotid => $pos) {
			$grid = self::era5GridPoint($pos['lat'], $pos['lon']);
			$stationid = $this->getOrCreateStation(self::SOURCE_ERA5LAND, $grid['code'], $grid['lat'], $grid['lon']);
			if ($stationid <= 0) {
				$this->db->rollback();
				return -1;
			}
			$used[$stationid] = $stationid;
			// Links of the plot to grid points of the same source that are no more its point are removed
			$sql = "DELETE FROM ".$this->db->prefix()."dolifield_weather_plot";
			$sql .= " WHERE entity = ".((int) $conf->entity)." AND fk_plot = ".((int) $plotid)." AND fk_station <> ".((int) $stationid);
			$sql .= " AND fk_station IN (SELECT s.rowid FROM ".$this->db->prefix()."dolifield_weather_station as s WHERE s.source = '".$this->db->escape(self::SOURCE_ERA5LAND)."')";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			$distance = self::distanceKm($pos['lat'], $pos['lon'], $grid['lat'], $grid['lon']);
			$sql = "SELECT rowid FROM ".$this->db->prefix()."dolifield_weather_plot";
			$sql .= " WHERE entity = ".((int) $conf->entity)." AND fk_plot = ".((int) $plotid)." AND fk_station = ".((int) $stationid);
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			$link = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if ($link) {
				$sql = "UPDATE ".$this->db->prefix()."dolifield_weather_plot SET distance_km = ".((float) $distance)." WHERE rowid = ".((int) $link->rowid);
			} else {
				$sql = "INSERT INTO ".$this->db->prefix()."dolifield_weather_plot (entity, fk_plot, fk_station, priority, distance_km, datec)";
				$sql .= " VALUES (".((int) $conf->entity).", ".((int) $plotid).", ".((int) $stationid).", 30, ".((float) $distance).", '".$this->db->idate(dol_now())."')";
				$nb++;
			}
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}
		// Links of plots that no longer qualify (no coordinates, disabled, not managed) are removed
		$sql = "DELETE FROM ".$this->db->prefix()."dolifield_weather_plot WHERE entity = ".((int) $conf->entity);
		if (count($plots)) {
			$sql .= " AND fk_plot NOT IN (".$this->db->sanitize(implode(',', array_keys($plots))).")";
		}
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		// Points used or not
		$sql = "UPDATE ".$this->db->prefix()."dolifield_weather_station SET status = 0 WHERE entity = ".((int) $conf->entity);
		if (count($used)) {
			$sql .= " AND rowid NOT IN (".$this->db->sanitize(implode(',', $used)).")";
		}
		$sql2 = "UPDATE ".$this->db->prefix()."dolifield_weather_station SET status = 1 WHERE entity = ".((int) $conf->entity);
		$sql2 .= " AND rowid IN (".(count($used) ? $this->db->sanitize(implode(',', $used)) : '0').")";
		if (!$this->db->query($sql) || !$this->db->query($sql2)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $nb;
	}

	/**
	 * Id of a weather point, created if it does not exist
	 *
	 * @param	string	$source		Source
	 * @param	string	$code		Code of the point for the source
	 * @param	float	$lat		Latitude
	 * @param	float	$lon		Longitude
	 * @return	int					Id, <0 if error
	 */
	public function getOrCreateStation(string $source, string $code, float $lat, float $lon): int
	{
		global $conf;

		$sql = "SELECT rowid FROM ".$this->db->prefix()."dolifield_weather_station";
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND source = '".$this->db->escape($source)."' AND source_code = '".$this->db->escape($code)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if ($obj) {
			return (int) $obj->rowid;
		}
		$label = 'ERA5-Land '.sprintf('%.2f, %.2f', $lat, $lon);
		$sql = "INSERT INTO ".$this->db->prefix()."dolifield_weather_station (entity, source, source_code, label, latitude, longitude, status, history_status, datec)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($source)."', '".$this->db->escape($code)."', '".$this->db->escape($label)."',";
		$sql .= " ".((float) $lat).", ".((float) $lon).", 1, ".self::HISTORY_TODO.", '".$this->db->idate(dol_now())."')";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return (int) $this->db->last_insert_id($this->db->prefix()."dolifield_weather_station");
	}

	/**
	 * Weather points of the entity
	 *
	 * @param	bool	$activeonly		Only the points used by plots
	 * @return	array<int,array<string,mixed>>|false	Points by id with the number of plots, false if error
	 */
	public function getStations(bool $activeonly = false)
	{
		global $conf;

		$sql = "SELECT s.rowid, s.source, s.source_code, s.label, s.latitude, s.longitude, s.elevation, s.status, s.history_status,";
		$sql .= " s.history_job, s.history_job_from, s.history_job_to, s.data_from, s.data_to, s.date_last_request, s.date_last_load,";
		$sql .= " s.nb_errors, s.last_error, COUNT(p.rowid) as nb_plots";
		$sql .= " FROM ".$this->db->prefix()."dolifield_weather_station as s";
		$sql .= " LEFT JOIN ".$this->db->prefix()."dolifield_weather_plot as p ON p.fk_station = s.rowid";
		$sql .= " WHERE s.entity = ".((int) $conf->entity);
		if ($activeonly) {
			$sql .= " AND s.status = 1";
		}
		$sql .= " GROUP BY s.rowid, s.source, s.source_code, s.label, s.latitude, s.longitude, s.elevation, s.status, s.history_status,";
		$sql .= " s.history_job, s.history_job_from, s.history_job_to, s.data_from, s.data_to, s.date_last_request, s.date_last_load, s.nb_errors, s.last_error";
		$sql .= " ORDER BY s.source_code";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}
		$result = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$result[(int) $obj->rowid] = array(
				'id' => (int) $obj->rowid,
				'source' => (string) $obj->source,
				'code' => (string) $obj->source_code,
				'label' => (string) $obj->label,
				'lat' => (float) $obj->latitude,
				'lon' => (float) $obj->longitude,
				'elevation' => ($obj->elevation === null ? null : (float) $obj->elevation),
				'status' => (int) $obj->status,
				'history_status' => (int) $obj->history_status,
				'history_job' => (string) $obj->history_job,
				'history_job_from' => (string) $obj->history_job_from,
				'history_job_to' => (string) $obj->history_job_to,
				'data_from' => (string) $obj->data_from,
				'data_to' => (string) $obj->data_to,
				'date_last_request' => ($obj->date_last_request ? $this->db->jdate($obj->date_last_request) : 0),
				'date_last_load' => ($obj->date_last_load ? $this->db->jdate($obj->date_last_load) : 0),
				'nb_errors' => (int) $obj->nb_errors,
				'last_error' => (string) $obj->last_error,
				'nb_plots' => (int) $obj->nb_plots,
			);
		}
		$this->db->free($resql);
		return $result;
	}

	/**
	 * Weather points of a plot, best first
	 *
	 * @param	int		$plotid		Id of the plot
	 * @return	array<int,array<string,mixed>>	Points with the distance from the plot
	 */
	public function getPlotStations(int $plotid): array
	{
		global $conf;

		$stations = $this->getStations();
		if ($stations === false) {
			return array();
		}
		$sql = "SELECT fk_station, priority, distance_km FROM ".$this->db->prefix()."dolifield_weather_plot";
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND fk_plot = ".((int) $plotid)." ORDER BY priority, distance_km";
		$resql = $this->db->query($sql);
		$result = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if (isset($stations[(int) $obj->fk_station])) {
					$result[] = $stations[(int) $obj->fk_station] + array('distance_km' => (float) $obj->distance_km, 'priority' => (int) $obj->priority);
				}
			}
			$this->db->free($resql);
		}
		return $result;
	}

	/**
	 * Save the state of the history of a weather point
	 *
	 * @param	int						$stationid	Id of the point
	 * @param	array<string,mixed>		$values		Columns and values (null for NULL)
	 * @return	bool
	 */
	private function updateStation(int $stationid, array $values): bool
	{
		$set = array();
		foreach ($values as $field => $value) {
			if ($value === null) {
				$set[] = $field." = NULL";
			} elseif (is_int($value) || is_float($value)) {
				$set[] = $field." = ".$value;
			} else {
				$set[] = $field." = '".$this->db->escape((string) $value)."'";
			}
		}
		$sql = "UPDATE ".$this->db->prefix()."dolifield_weather_station SET ".implode(', ', $set)." WHERE rowid = ".((int) $stationid);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return false;
		}
		return true;
	}


	/*
	 * Climate Data Store
	 */

	/**
	 * Call of the API of the Climate Data Store
	 *
	 * @param	string					$method		GET, POST or PUT
	 * @param	string					$url		Path after the base url, or full url of the service
	 * @param	?array<string,mixed>	$body		JSON body
	 * @param	int						$httpcode	HTTP code of the answer
	 * @return	array<string,mixed>|false			Answer, false if error (message in $this->error)
	 */
	private function cdsCall(string $method, string $url, ?array $body, int &$httpcode = 0)
	{
		global $langs;

		$key = getDolGlobalString('DOLIFIELD_CDS_API_KEY');
		if ($key === '') {
			$this->error = $langs->trans('WeatherCdsKeyMissing');
			return false;
		}
		if (!preg_match('/^https:\/\//', $url)) {
			$url = self::CDS_URL.$url;
		}
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		if (getDolGlobalString('MAIN_CURL_DISABLE_VERIFYPEER')) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		$headers = array('PRIVATE-TOKEN: '.$key, 'Accept: application/json');
		if ($method !== 'GET') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POSTFIELDS, ($body === null ? '' : json_encode($body)));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr doliField');
		$response = curl_exec($ch);
		$httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlerror = curl_error($ch);
		unset($ch);

		dol_syslog(__METHOD__.' '.$method.' '.preg_replace('/^'.preg_quote(self::CDS_URL, '/').'/', '', $url).' http_code='.$httpcode);
		if ($response === false || $httpcode == 0) {
			$this->error = $langs->trans('WeatherCdsError', $curlerror);
			return false;
		}
		$data = json_decode((string) $response, true);
		if ($httpcode == 401 || $httpcode == 403) {
			$detail = (is_array($data) ? trim(($data['title'] ?? '').' '.($data['detail'] ?? '')) : '');
			$this->error = $langs->trans('WeatherCdsKeyRefused').($detail !== '' ? ' ('.dol_trunc($detail, 300).')' : '');
			return false;
		}
		if ($httpcode < 200 || $httpcode >= 300) {
			$detail = (is_array($data) ? trim(($data['title'] ?? '').' '.($data['detail'] ?? '')) : dol_trunc((string) $response, 200));
			$this->error = $langs->trans('WeatherCdsError', 'HTTP '.$httpcode.($detail !== '' ? ' '.dol_trunc($detail, 300) : ''));
			return false;
		}
		if (!is_array($data)) {
			$this->error = $langs->trans('WeatherCdsError', 'JSON');
			return false;
		}
		return $data;
	}

	/**
	 * Check the key and the licence of the dataset
	 *
	 * @return	int		1 key valid and licence accepted, 0 key valid but licence not accepted, -1 key refused or error
	 */
	public function checkKey(): int
	{
		global $langs;

		if ($this->cdsCall('POST', '/profiles/v1/account/verification/pat', null) === false) {
			return -1;
		}
		$licences = $this->cdsCall('GET', '/profiles/v1/account/licences', null);
		if ($licences === false) {
			return -1;
		}
		$list = (isset($licences['licences']) && is_array($licences['licences']) ? $licences['licences'] : $licences);
		foreach ($list as $licence) {
			if (is_array($licence) && ($licence['id'] ?? '') === self::CDS_LICENCE) {
				return 1;
			}
		}
		$this->error = $langs->trans('WeatherCdsLicenceMissing');
		return 0;
	}

	/**
	 * Send the request of the hourly series of a grid point of ERA5-Land
	 *
	 * @param	array<string,mixed>	$station	Weather point
	 * @param	string				$from		First day (YYYY-MM-DD)
	 * @param	string				$to			Last day (YYYY-MM-DD)
	 * @return	string|false					Id of the request, false if error
	 */
	public function submitEra5(array $station, string $from, string $to)
	{
		global $langs;

		$body = array('inputs' => array(
			'variable' => self::ERA5LAND_VARIABLES,
			'location' => array('latitude' => (float) $station['lat'], 'longitude' => (float) $station['lon']),
			'date' => array($from.'/'.$to),
			'data_format' => 'csv',
		));
		$data = $this->cdsCall('POST', '/retrieve/v1/processes/'.self::CDS_DATASET.'/execution', $body);
		if ($data === false) {
			return false;
		}
		if (empty($data['jobID'])) {
			$this->error = $langs->trans('WeatherCdsError', 'jobID');
			return false;
		}
		return (string) $data['jobID'];
	}

	/**
	 * Status of a request
	 *
	 * @param	string	$jobid	Id of the request
	 * @return	string|false	accepted, running, successful, failed, rejected, dismissed, deleted; false if error
	 */
	public function jobStatus(string $jobid)
	{
		$data = $this->cdsCall('GET', '/retrieve/v1/jobs/'.rawurlencode($jobid), null);
		if ($data === false) {
			return false;
		}
		return (string) ($data['status'] ?? '');
	}

	/**
	 * Download the result of a request
	 *
	 * @param	string	$jobid		Id of the request
	 * @param	string	$destfile	File to write
	 * @return	bool
	 */
	public function downloadResult(string $jobid, string $destfile): bool
	{
		global $langs;

		$data = $this->cdsCall('GET', '/retrieve/v1/jobs/'.rawurlencode($jobid).'/results', null);
		if ($data === false) {
			return false;
		}
		$href = (string) ($data['asset']['value']['href'] ?? '');
		if (!preg_match('/^https:\/\//', $href)) {
			$this->error = $langs->trans('WeatherCdsError', 'href');
			return false;
		}
		dol_mkdir(dirname($destfile));
		$fp = fopen($destfile, 'wb');
		if ($fp === false) {
			$this->error = $langs->trans('ErrorFailToCreateFile', $destfile);
			return false;
		}
		$ch = curl_init($href);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		if (getDolGlobalString('MAIN_CURL_DISABLE_VERIFYPEER')) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}
		curl_setopt($ch, CURLOPT_USERAGENT, 'Dolibarr doliField');
		$ok = curl_exec($ch);
		$httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlerror = curl_error($ch);
		unset($ch);
		fclose($fp);
		dol_syslog(__METHOD__.' job='.$jobid.' http_code='.$httpcode.' size='.(int) @filesize($destfile));
		if ($ok === false || $httpcode != 200) {
			@unlink($destfile);
			$this->error = $langs->trans('WeatherCdsError', ($curlerror !== '' ? $curlerror : 'HTTP '.$httpcode));
			return false;
		}
		return true;
	}


	/*
	 * Import
	 */

	/**
	 * Open the CSV of a downloaded file (zip with one CSV, or CSV)
	 *
	 * @param	string	$file		Downloaded file
	 * @param	string	$tmpfile	Extracted CSV to delete after the import ('' if none)
	 * @return	resource|false		Handle of the CSV
	 */
	private function openCsv(string $file, string &$tmpfile)
	{
		global $conf, $langs;

		$tmpfile = '';
		$head = (string) file_get_contents($file, false, null, 0, 4);
		if (substr($head, 0, 2) === 'PK') {
			$zip = new ZipArchive();
			if ($zip->open($file) !== true) {
				$this->error = $langs->trans('WeatherFileUnreadable', basename($file));
				return false;
			}
			$name = '';
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$entry = (string) $zip->getNameIndex($i);
				if (preg_match('/\.csv$/i', $entry)) {
					$name = $entry;
					break;
				}
			}
			if ($name === '') {
				$zip->close();
				$this->error = $langs->trans('WeatherFileUnreadable', basename($file));
				return false;
			}
			$tmpdir = $conf->dolifield->dir_temp;
			dol_mkdir($tmpdir);
			$tmpfile = $tmpdir.'/weather_'.bin2hex(random_bytes(8)).'.csv';
			$in = $zip->getStream($name);
			$out = fopen($tmpfile, 'wb');
			if ($in === false || $out === false) {
				$zip->close();
				$this->error = $langs->trans('WeatherFileUnreadable', basename($file));
				return false;
			}
			stream_copy_to_stream($in, $out);
			fclose($in);
			fclose($out);
			$zip->close();
			$file = $tmpfile;
		}
		return fopen($file, 'r');
	}

	/**
	 * Columns of the CSV of ERA5-Land, from the header
	 *
	 * @param	string[]	$header		Header
	 * @return	array<string,int>		Index of time, lat, lon, t2m, d2m, tp, u10, v10, ssrd, swvl1, sp
	 */
	private static function csvColumns(array $header): array
	{
		$aliases = array(
			'time' => array('valid_time', 'time', 'date'),
			'lat' => array('latitude', 'lat'),
			'lon' => array('longitude', 'lon'),
			't2m' => array('t2m', '2m_temperature'),
			'd2m' => array('d2m', '2m_dewpoint_temperature'),
			'tp' => array('tp', 'total_precipitation'),
			'u10' => array('u10', '10m_u_component_of_wind'),
			'v10' => array('v10', '10m_v_component_of_wind'),
			'ssrd' => array('ssrd', 'surface_solar_radiation_downwards'),
			'swvl1' => array('swvl1', 'volumetric_soil_water_level_1'),
			'sp' => array('sp', 'surface_pressure'),
		);
		$cols = array();
		foreach ($header as $i => $name) {
			$name = strtolower(trim((string) $name, " \t\r\n\"\xEF\xBB\xBF"));
			foreach ($aliases as $key => $names) {
				if (!isset($cols[$key]) && in_array($name, $names, true)) {
					$cols[$key] = $i;
				}
			}
		}
		return $cols;
	}

	/**
	 * Time of a line of the CSV (UTC)
	 *
	 * @param	string	$value	Value of the time column
	 * @return	int|null		Timestamp, null if not readable
	 */
	private static function csvTime(string $value): ?int
	{
		$value = trim($value);
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $value, $m)) {
			return gmmktime((int) $m[4], (int) $m[5], 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		}
		return null;
	}

	/**
	 * Read a number of the CSV
	 *
	 * @param	array<int,string>	$row	Line
	 * @param	array<string,int>	$cols	Columns
	 * @param	string				$key	Column key
	 * @return	float|null
	 */
	private static function csvNumber(array $row, array $cols, string $key): ?float
	{
		if (!isset($cols[$key]) || !isset($row[$cols[$key]])) {
			return null;
		}
		$value = trim((string) $row[$cols[$key]]);
		if ($value === '' || !is_numeric($value)) {
			return null;
		}
		return (float) $value;
	}

	/**
	 * Tell if precipitation and radiation are accumulated from 00 UTC (convention of ERA5-Land) or hourly
	 *
	 * Accumulated series never decrease during the day, except at 01 UTC when they restart. The radiation of an
	 * hourly series decreases every afternoon.
	 *
	 * @param	resource			$fp		CSV, after the header
	 * @param	array<string,int>	$cols	Columns
	 * @return	bool						True if accumulated
	 */
	private static function csvIsAccumulated($fp, array $cols): bool
	{
		$key = (isset($cols['ssrd']) ? 'ssrd' : 'tp');
		$prev = null;
		$prevtime = null;
		$decrease = 0;
		$compared = 0;
		$n = 0;
		while ($n < 2000 && ($row = fgetcsv($fp, 0, ',', '"', '')) !== false) {
			$n++;
			$time = self::csvTime((string) ($row[$cols['time']] ?? ''));
			$value = self::csvNumber($row, $cols, $key);
			if ($time !== null && $value !== null && $prev !== null && $prevtime === $time - 3600 && (int) gmdate('G', $time) != 1) {
				$compared++;
				if ($value < $prev - 1e-9) {
					$decrease++;
				}
			}
			$prev = $value;
			$prevtime = $time;
		}
		return ($compared > 0 && $decrease <= $compared * 0.01);
	}

	/**
	 * Load a downloaded file of ERA5-Land: daily values of all the days, hourly values of the retained months
	 *
	 * The days of the file replace those already loaded.
	 *
	 * @param	array<string,mixed>	$station	Weather point
	 * @param	string				$file		Downloaded file (zip or CSV)
	 * @return	int								Number of days loaded, <0 if error
	 */
	public function importEra5File(array $station, string $file): int
	{
		global $langs;

		$tmpfile = '';
		$fp = $this->openCsv($file, $tmpfile);
		if ($fp === false) {
			return -1;
		}
		$header = fgetcsv($fp, 0, ',', '"', '');
		$cols = self::csvColumns(is_array($header) ? $header : array());
		if (!isset($cols['time']) || !isset($cols['t2m']) || !isset($cols['tp'])) {
			fclose($fp);
			if ($tmpfile !== '') {
				@unlink($tmpfile);
			}
			$this->error = $langs->trans('WeatherCsvUnknown', dol_trunc(implode(',', is_array($header) ? $header : array()), 200));
			return -1;
		}
		$datastart = ftell($fp);
		$accumulated = self::csvIsAccumulated($fp, $cols);
		fseek($fp, $datastart);

		$months = getDolGlobalInt('DOLIFIELD_WEATHER_HOURLY_MONTHS', 13);
		$hourlyfrom = ($months > 0 ? dol_time_plus_duree(dol_now(), -$months, 'm') : 0);

		$days = array();
		$hourly = array();
		$prev = array('tp' => null, 'ssrd' => null, 'time' => null);
		$gridlat = null;
		$gridlon = null;
		$mintime = null;
		$maxtime = null;
		while (($row = fgetcsv($fp, 0, ',', '"', '')) !== false) {
			$time = self::csvTime((string) ($row[$cols['time']] ?? ''));
			if ($time === null) {
				continue;
			}
			if ($gridlat === null) {
				$gridlat = self::csvNumber($row, $cols, 'lat');
				$gridlon = self::csvNumber($row, $cols, 'lon');
			}
			$mintime = ($mintime === null ? $time : min($mintime, $time));
			$maxtime = ($maxtime === null ? $time : max($maxtime, $time));

			// Instantaneous values (day of the hour)
			$t = self::csvNumber($row, $cols, 't2m');
			$td = self::csvNumber($row, $cols, 'd2m');
			if ($t !== null && $t > 150) {
				$t -= 273.15;
			}
			if ($td !== null && $td > 150) {
				$td -= 273.15;
			}
			$rh = ($t !== null && $td !== null ? self::relativeHumidity($t, $td) : null);
			$u = self::csvNumber($row, $cols, 'u10');
			$v = self::csvNumber($row, $cols, 'v10');
			$wind = ($u !== null && $v !== null ? sqrt($u * $u + $v * $v) : null);
			$winddir = ($u !== null && $v !== null && $wind > 0 ? fmod(rad2deg(atan2(-$u, -$v)) + 360, 360) : null);
			$sw = self::csvNumber($row, $cols, 'swvl1');
			$sp = self::csvNumber($row, $cols, 'sp');
			if ($sp !== null) {
				$sp = $sp / 1000;
			}

			// Accumulated values: those of the hour that ends at this time
			$tp = self::csvNumber($row, $cols, 'tp');
			$ssrd = self::csvNumber($row, $cols, 'ssrd');
			if ($accumulated) {
				$first = ((int) gmdate('G', $time) == 1);
				$contiguous = ($prev['time'] === $time - 3600);
				$tpraw = $tp;
				$ssrdraw = $ssrd;
				$tp = ($tp === null ? null : ($first ? $tp : ($contiguous && $prev['tp'] !== null ? $tp - $prev['tp'] : null)));
				$ssrd = ($ssrd === null ? null : ($first ? $ssrd : ($contiguous && $prev['ssrd'] !== null ? $ssrd - $prev['ssrd'] : null)));
				$prev = array('tp' => $tpraw, 'ssrd' => $ssrdraw, 'time' => $time);
			}
			$precip = ($tp === null ? null : max(0.0, $tp * 1000));		// m -> mm
			$radj = ($ssrd === null ? null : max(0.0, $ssrd));			// J/m2 in the hour

			$day = gmdate('Y-m-d', $time);
			$dayacc = gmdate('Y-m-d', $time - 3600);
			if (!isset($days[$day])) {
				$days[$day] = self::emptyDay();
			}
			if (!isset($days[$dayacc])) {
				$days[$dayacc] = self::emptyDay();
			}
			$d = &$days[$day];
			if ($t !== null) {
				$d['n']++;
				$d['tsum'] += $t;
				$d['tmin'] = ($d['tmin'] === null ? $t : min($d['tmin'], $t));
				$d['tmax'] = ($d['tmax'] === null ? $t : max($d['tmax'], $t));
			}
			if ($td !== null) {
				$d['tdsum'] += $td;
				$d['ntd']++;
			}
			if ($rh !== null) {
				$d['rhsum'] += $rh;
				$d['nrh']++;
				$d['rhmin'] = ($d['rhmin'] === null ? $rh : min($d['rhmin'], $rh));
				$d['rhmax'] = ($d['rhmax'] === null ? $rh : max($d['rhmax'], $rh));
			}
			if ($wind !== null) {
				$d['wsum'] += $wind;
				$d['nw']++;
				$d['wmax'] = ($d['wmax'] === null ? $wind : max($d['wmax'], $wind));
			}
			if ($sw !== null) {
				$d['swsum'] += $sw;
				$d['nsw']++;
			}
			if ($sp !== null) {
				$d['spsum'] += $sp;
				$d['nsp']++;
			}
			unset($d);
			$a = &$days[$dayacc];
			if ($precip !== null) {
				$a['precip'] += $precip;
				$a['nprecip']++;
			}
			if ($radj !== null) {
				$a['rad'] += $radj;
				$a['nrad']++;
			}
			unset($a);

			if ($time >= $hourlyfrom) {
				$hourly[] = array($time, $t, $td, $rh, $precip, $wind, $winddir, ($radj === null ? null : $radj / 3600), $sw, $sp);
			}
		}
		fclose($fp);
		if ($tmpfile !== '') {
			@unlink($tmpfile);
		}
		if ($mintime === null || $maxtime === null) {
			$this->error = $langs->trans('WeatherFileEmpty');
			return -1;
		}

		// Only the days of the period of the file (the accumulated values add the day before the first hour)
		$firstday = gmdate('Y-m-d', $mintime);
		$lastday = gmdate('Y-m-d', $maxtime);
		foreach (array_keys($days) as $day) {
			if ($day < $firstday || $day > $lastday) {
				unset($days[$day]);
			}
		}
		ksort($days);

		$stationid = (int) $station['id'];
		$this->db->begin();
		$sql = "DELETE FROM ".$this->db->prefix()."dolifield_weather_daily WHERE fk_station = ".$stationid." AND kind = ".self::KIND_REANALYSIS;
		$sql .= " AND day >= '".$this->db->escape($firstday)."' AND day <= '".$this->db->escape($lastday)."'";
		$ok = (bool) $this->db->query($sql);
		$values = array();
		foreach ($days as $day => $d) {
			if ($d['n'] == 0) {
				continue;
			}
			$values[] = "(".$stationid.", ".self::KIND_REANALYSIS.", '".$this->db->escape($day)."', "
				.self::sqlNum($d['tmin']).", ".self::sqlNum($d['tmax']).", ".self::sqlNum($d['tsum'] / $d['n']).", "
				.self::sqlNum($d['ntd'] ? $d['tdsum'] / $d['ntd'] : null).", "
				.self::sqlNum($d['rhmin']).", ".self::sqlNum($d['rhmax']).", ".self::sqlNum($d['nrh'] ? $d['rhsum'] / $d['nrh'] : null).", "
				// A day without its 24 hours of precipitation (end of the file) is completed by the next update
				.self::sqlNum($d['nprecip'] == 24 ? $d['precip'] : null).", "
				.self::sqlNum($d['nw'] ? $d['wsum'] / $d['nw'] : null).", ".self::sqlNum($d['wmax']).", "
				.self::sqlNum($d['nrad'] == 24 ? $d['rad'] / 1000000 : null).", "
				.self::sqlNum($d['nsw'] ? $d['swsum'] / $d['nsw'] : null, 4).", ".self::sqlNum($d['nsp'] ? $d['spsum'] / $d['nsp'] : null).", "
				.$d['n'].")";
			if (count($values) >= 500) {
				$ok = $ok && $this->insertDaily($values);
				$values = array();
			}
		}
		if (count($values)) {
			$ok = $ok && $this->insertDaily($values);
		}

		if ($ok && count($hourly)) {
			$sql = "DELETE FROM ".$this->db->prefix()."dolifield_weather_hourly WHERE fk_station = ".$stationid." AND kind = ".self::KIND_REANALYSIS;
			$sql .= " AND date_utc >= '".$this->db->idate($hourly[0][0], 'gmt')."' AND date_utc <= '".$this->db->idate($maxtime, 'gmt')."'";
			$ok = (bool) $this->db->query($sql);
			$values = array();
			foreach ($hourly as $h) {
				$values[] = "(".$stationid.", ".self::KIND_REANALYSIS.", '".$this->db->idate($h[0], 'gmt')."', "
					.self::sqlNum($h[1]).", ".self::sqlNum($h[2]).", ".self::sqlNum($h[3]).", ".self::sqlNum($h[4]).", "
					.self::sqlNum($h[5]).", ".self::sqlNum($h[6]).", ".self::sqlNum($h[7]).", ".self::sqlNum($h[8], 4).", ".self::sqlNum($h[9]).")";
				if (count($values) >= 500) {
					$ok = $ok && $this->insertHourly($values);
					$values = array();
				}
			}
			if (count($values)) {
				$ok = $ok && $this->insertHourly($values);
			}
		}
		if (!$ok) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		// Period loaded and position of the grid point given by the file
		$fields = array();
		$sql = "SELECT MIN(day) as dmin, MAX(day) as dmax FROM ".$this->db->prefix()."dolifield_weather_daily WHERE fk_station = ".$stationid." AND kind = ".self::KIND_REANALYSIS;
		$sql .= " AND precip IS NOT NULL";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$fields['data_from'] = ($obj->dmin ? (string) $obj->dmin : null);
			$fields['data_to'] = ($obj->dmax ? (string) $obj->dmax : null);
		}
		if ($gridlat !== null && $gridlon !== null) {
			$fields['latitude'] = (float) $gridlat;
			$fields['longitude'] = (float) $gridlon;
		}
		if (count($fields) && !$this->updateStation($stationid, $fields)) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		dol_syslog(__METHOD__.' station='.$stationid.' days='.count($days).' hourly='.count($hourly).' accumulated='.(int) $accumulated);
		return count($days);
	}

	/**
	 * Empty accumulator of a day
	 *
	 * @return	array<string,mixed>
	 */
	private static function emptyDay(): array
	{
		return array(
			'n' => 0, 'tsum' => 0.0, 'tmin' => null, 'tmax' => null,
			'tdsum' => 0.0, 'ntd' => 0,
			'rhsum' => 0.0, 'nrh' => 0, 'rhmin' => null, 'rhmax' => null,
			'wsum' => 0.0, 'nw' => 0, 'wmax' => null,
			'swsum' => 0.0, 'nsw' => 0, 'spsum' => 0.0, 'nsp' => 0,
			'precip' => 0.0, 'nprecip' => 0, 'rad' => 0.0, 'nrad' => 0,
		);
	}

	/**
	 * Relative humidity from temperature and dew point (Magnus formula)
	 *
	 * @param	float	$t		Temperature, degrees C
	 * @param	float	$td		Dew point, degrees C
	 * @return	float			Relative humidity, percent
	 */
	public static function relativeHumidity(float $t, float $td): float
	{
		$rh = 100 * exp(17.625 * $td / (243.04 + $td)) / exp(17.625 * $t / (243.04 + $t));
		return min(100.0, max(0.0, $rh));
	}

	/**
	 * Number for SQL
	 *
	 * @param	float|int|null	$value		Value
	 * @param	int				$decimals	Decimals
	 * @return	string
	 */
	private static function sqlNum($value, int $decimals = 3): string
	{
		if ($value === null || is_nan((float) $value) || is_infinite((float) $value)) {
			return 'NULL';
		}
		return number_format((float) $value, $decimals, '.', '');
	}

	/**
	 * Insert daily lines
	 *
	 * @param	string[]	$values		Lines
	 * @return	bool
	 */
	private function insertDaily(array $values): bool
	{
		$sql = "INSERT INTO ".$this->db->prefix()."dolifield_weather_daily (fk_station, kind, day, tmin, tmax, tmean, dewpoint, rh_min, rh_max, rh_mean,";
		$sql .= " precip, wind_mean, wind_max, radiation, soil_water, pressure, nb_hours) VALUES ".implode(', ', $values);
		return (bool) $this->db->query($sql);
	}

	/**
	 * Insert hourly lines
	 *
	 * @param	string[]	$values		Lines
	 * @return	bool
	 */
	private function insertHourly(array $values): bool
	{
		$sql = "INSERT INTO ".$this->db->prefix()."dolifield_weather_hourly (fk_station, kind, date_utc, temp, dewpoint, rh, precip,";
		$sql .= " wind_speed, wind_dir, radiation, soil_water, pressure) VALUES ".implode(', ', $values);
		return (bool) $this->db->query($sql);
	}

	/**
	 * Remove the hourly values of the reanalysis older than the months set in the setup (0 = keep all)
	 *
	 * The downloaded files stay in the documents and can be loaded again.
	 *
	 * @return	int		Number of lines removed, <0 if error
	 */
	public function purgeHourly(): int
	{
		$months = getDolGlobalInt('DOLIFIELD_WEATHER_HOURLY_MONTHS', 13);
		if ($months <= 0) {
			return 0;
		}
		$limit = dol_time_plus_duree(dol_now(), -$months, 'm');
		$sql = "DELETE FROM ".$this->db->prefix()."dolifield_weather_hourly WHERE kind = ".self::KIND_REANALYSIS;
		$sql .= " AND date_utc < '".$this->db->idate($limit, 'gmt')."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return (int) $this->db->affected_rows($resql);
	}


	/*
	 * Scheduled job
	 */

	/**
	 * Directory of the downloaded files of a weather point
	 *
	 * @param	array<string,mixed>	$station	Weather point
	 * @return	string
	 */
	public static function archiveDir(array $station): string
	{
		global $conf;

		return $conf->dolifield->dir_output.'/weather/'.dol_sanitizeFileName($station['source']).'/'.dol_sanitizeFileName($station['code']);
	}

	/**
	 * Scheduled job: weather points of the plots, requests of the history and of the updates, load of the results
	 *
	 * @return	int		0 if OK, <>0 if error (message in $this->error and $this->output)
	 */
	public function cronUpdate(): int
	{
		global $conf, $langs;

		$langs->load('dolifield@dolifield');
		$this->output = '';
		$this->error = '';
		$nberror = 0;

		if (getDolGlobalString('DOLIFIELD_CDS_API_KEY') === '') {
			$this->output = $langs->trans('WeatherCdsKeyMissing');
			return 0;
		}

		$nblinks = $this->syncPlots();
		if ($nblinks < 0) {
			$this->output = $this->error;
			return 1;
		}
		$stations = $this->getStations(true);
		if ($stations === false) {
			$this->output = $this->error;
			return 1;
		}

		$lines = array();
		$nbrequests = 0;
		$now = dol_now();
		$lastavailable = dol_print_date(dol_time_plus_duree($now, -self::ERA5LAND_DELAY_DAYS, 'd'), '%Y-%m-%d', 'gmt');
		$startyear = getDolGlobalInt('DOLIFIELD_WEATHER_START_YEAR', 1991);
		foreach ($stations as $station) {
			if ($station['source'] != self::SOURCE_ERA5LAND) {
				continue;
			}
			$name = $station['label'];

			// Request sent: check it, then download and load the result
			if ($station['history_status'] == self::HISTORY_REQUESTED && $station['history_job'] !== '') {
				$status = $this->jobStatus($station['history_job']);
				if ($status === false) {
					$nberror++;
					$lines[] = $name.': '.$this->error;
					continue;
				}
				if (in_array($status, array('accepted', 'running'))) {
					$lines[] = $name.': '.$langs->trans('WeatherJobWaiting', $status);
					continue;
				}
				if ($status != 'successful') {
					$this->stationError($station, $langs->trans('WeatherJobFailed', $status));
					$nberror++;
					$lines[] = $name.': '.$langs->trans('WeatherJobFailed', $status);
					continue;
				}
				$file = self::archiveDir($station).'/era5land_'.$station['history_job_from'].'_'.$station['history_job_to'].'.zip';
				if (!$this->downloadResult($station['history_job'], $file)) {
					$this->stationError($station, $this->error);
					$nberror++;
					$lines[] = $name.': '.$this->error;
					continue;
				}
				$nbdays = $this->importEra5File($station, $file);
				if ($nbdays < 0) {
					$this->stationError($station, $this->error);
					$nberror++;
					$lines[] = $name.': '.$this->error;
					continue;
				}
				$this->updateStation($station['id'], array('history_status' => self::HISTORY_LOADED, 'history_job' => null,
					'date_last_load' => $this->db->idate($now), 'nb_errors' => 0, 'last_error' => null));
				$lines[] = $name.': '.$langs->trans('WeatherDaysLoaded', $nbdays, $station['history_job_from'], $station['history_job_to']);
				continue;
			}

			// New request: the whole history, or the last days
			if ($nbrequests >= self::MAX_REQUESTS_PER_RUN) {
				continue;
			}
			if ($station['history_status'] == self::HISTORY_ERROR) {
				$wait = min(24, 6 * max(1, $station['nb_errors'])) * 3600;
				if ($station['date_last_request'] > $now - $wait) {
					continue;
				}
			}
			if ($station['data_to'] === '') {
				$from = sprintf('%04d-01-01', $startyear);
			} else {
				if ($station['date_last_request'] > $now - self::UPDATE_EVERY_HOURS * 3600 || $station['data_to'] >= $lastavailable) {
					continue;
				}
				$from = dol_print_date(dol_time_plus_duree(dol_stringtotime($station['data_to'].' 00:00:00', 1), -self::ERA5LAND_OVERLAP_DAYS, 'd'), '%Y-%m-%d', 'gmt');
			}
			$jobid = $this->submitEra5($station, $from, $lastavailable);
			if ($jobid === false) {
				$this->stationError($station, $this->error);
				$nberror++;
				$lines[] = $name.': '.$this->error;
				continue;
			}
			$nbrequests++;
			$this->updateStation($station['id'], array('history_status' => self::HISTORY_REQUESTED, 'history_job' => $jobid,
				'history_job_from' => $from, 'history_job_to' => $lastavailable, 'date_last_request' => $this->db->idate($now)));
			$lines[] = $name.': '.$langs->trans('WeatherJobSent', $from, $lastavailable);
		}

		// Hourly values beyond the months kept, once a day
		if (getDolGlobalString('DOLIFIELD_WEATHER_LAST_PURGE') != dol_print_date($now, '%Y-%m-%d')) {
			$nbpurged = $this->purgeHourly();
			if ($nbpurged >= 0) {
				dolibarr_set_const($this->db, 'DOLIFIELD_WEATHER_LAST_PURGE', dol_print_date($now, '%Y-%m-%d'), 'chaine', 0, '', $conf->entity);
				if ($nbpurged > 0) {
					$lines[] = $langs->trans('WeatherHourlyPurged', $nbpurged);
				}
			}
		}

		$this->output = $langs->trans('WeatherPoints', count($stations)).(count($lines) ? "\n".implode("\n", $lines) : '');
		if ($nberror) {
			$this->error = $this->output;
		}
		return ($nberror ? 1 : 0);
	}

	/**
	 * Save an error on a weather point
	 *
	 * @param	array<string,mixed>	$station	Weather point
	 * @param	string				$message	Error
	 * @return	void
	 */
	private function stationError(array $station, string $message): void
	{
		$this->updateStation($station['id'], array('history_status' => self::HISTORY_ERROR, 'history_job' => null,
			'nb_errors' => $station['nb_errors'] + 1, 'last_error' => dol_trunc($message, 1000)));
	}


	/*
	 * Data for the pages
	 */

	/**
	 * Monthly values of a weather point: precipitation, mean of the minimum and maximum temperatures
	 *
	 * @param	int		$stationid	Id of the point
	 * @param	int		$kind		Kind of values
	 * @return	array<string,array{precip:?float,tmin:?float,tmax:?float,days:int,precipdays:int}>	By "YYYY-MM"
	 */
	public function getMonthly(int $stationid, int $kind = self::KIND_REANALYSIS): array
	{
		$sql = "SELECT EXTRACT(YEAR FROM day) as y, EXTRACT(MONTH FROM day) as m, SUM(precip) as precip, COUNT(precip) as precipdays,";
		$sql .= " AVG(tmin) as tmin, AVG(tmax) as tmax, COUNT(*) as nb";
		$sql .= " FROM ".$this->db->prefix()."dolifield_weather_daily";
		$sql .= " WHERE fk_station = ".((int) $stationid)." AND kind = ".((int) $kind);
		$sql .= " GROUP BY EXTRACT(YEAR FROM day), EXTRACT(MONTH FROM day)";
		$resql = $this->db->query($sql);
		$result = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$result[sprintf('%04d-%02d', (int) $obj->y, (int) $obj->m)] = array(
					'precip' => ($obj->precip === null ? null : (float) $obj->precip),
					'tmin' => ($obj->tmin === null ? null : (float) $obj->tmin),
					'tmax' => ($obj->tmax === null ? null : (float) $obj->tmax),
					'days' => (int) $obj->nb,
					'precipdays' => (int) $obj->precipdays,
				);
			}
			$this->db->free($resql);
		}
		ksort($result);
		return $result;
	}

	/**
	 * Precipitation of a period and mean of the same period in the years of the climate normal
	 *
	 * @param	int		$stationid	Id of the point
	 * @param	string	$from		First day (YYYY-MM-DD)
	 * @param	string	$to			Last day (YYYY-MM-DD)
	 * @param	int		$normfrom	First year of the climate normal
	 * @param	int		$normto		Last year of the climate normal
	 * @return	array{precip:?float,days:int,normal:?float,normalyears:int}
	 */
	public function getPeriodPrecip(int $stationid, string $from, string $to, int $normfrom = 1991, int $normto = 2020): array
	{
		$result = array('precip' => null, 'days' => 0, 'normal' => null, 'normalyears' => 0);
		$sum = $this->sumPrecip($stationid, $from, $to);
		$result['precip'] = $sum['precip'];
		$result['days'] = $sum['days'];
		$expected = (int) round((dol_stringtotime($to.' 00:00:00', 1) - dol_stringtotime($from.' 00:00:00', 1)) / 86400) + 1;
		$total = 0.0;
		$years = 0;
		$shiftbase = (int) substr($to, 0, 4);
		for ($y = $normfrom; $y <= $normto; $y++) {
			$shift = $shiftbase - $y;
			$yfrom = dol_print_date(dol_time_plus_duree(dol_stringtotime($from.' 00:00:00', 1), -$shift, 'y'), '%Y-%m-%d', 'gmt');
			$yto = dol_print_date(dol_time_plus_duree(dol_stringtotime($to.' 00:00:00', 1), -$shift, 'y'), '%Y-%m-%d', 'gmt');
			$ysum = $this->sumPrecip($stationid, $yfrom, $yto);
			// Only the years with the whole period
			if ($ysum['precip'] !== null && $ysum['days'] >= $expected - 1) {
				$total += $ysum['precip'];
				$years++;
			}
		}
		if ($years > 0) {
			$result['normal'] = $total / $years;
			$result['normalyears'] = $years;
		}
		return $result;
	}

	/**
	 * Precipitation of a period
	 *
	 * @param	int		$stationid	Id of the point
	 * @param	string	$from		First day (YYYY-MM-DD)
	 * @param	string	$to			Last day (YYYY-MM-DD)
	 * @return	array{precip:?float,days:int}
	 */
	private function sumPrecip(int $stationid, string $from, string $to): array
	{
		$sql = "SELECT SUM(precip) as precip, COUNT(precip) as nb FROM ".$this->db->prefix()."dolifield_weather_daily";
		$sql .= " WHERE fk_station = ".((int) $stationid)." AND kind = ".self::KIND_REANALYSIS;
		$sql .= " AND day >= '".$this->db->escape($from)."' AND day <= '".$this->db->escape($to)."'";
		$resql = $this->db->query($sql);
		$result = array('precip' => null, 'days' => 0);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$result['days'] = (int) $obj->nb;
			$result['precip'] = ($obj->nb > 0 ? (float) $obj->precip : null);
		}
		return $result;
	}

	/**
	 * Last daily values of a weather point
	 *
	 * @param	int		$stationid	Id of the point
	 * @param	int		$nb			Number of days
	 * @return	array<int,array<string,mixed>>	Days, most recent first
	 */
	public function getLastDays(int $stationid, int $nb = 14): array
	{
		$sql = "SELECT day, tmin, tmax, tmean, rh_mean, precip, wind_mean, wind_max, radiation, soil_water";
		$sql .= " FROM ".$this->db->prefix()."dolifield_weather_daily";
		$sql .= " WHERE fk_station = ".((int) $stationid)." AND kind = ".self::KIND_REANALYSIS;
		$sql .= $this->db->order('day', 'DESC').$this->db->plimit($nb);
		$resql = $this->db->query($sql);
		$result = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$result[] = (array) $obj;
			}
			$this->db->free($resql);
		}
		return $result;
	}
}
