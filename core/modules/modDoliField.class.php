<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
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
 * 	\defgroup   dolifield     Module DoliField
 *  \brief      DoliField module descriptor: field data (sensors, weather, satellite, cameras) of the doliFarm suite
 *
 *  \file       core/modules/modDoliField.class.php
 *  \ingroup    dolifield
 *  \brief      Description and activation file for module DoliField
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
dol_include_once('/dolifarm/lib/dolifarm_icons.lib.php');


/**
 *  Description and activation class for module DoliField
 */
class modDoliField extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		$this->numero = 500030;
		$this->rights_class = 'dolifield';
		$this->family = "doliFARM";
		$this->module_position = '92';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "ModuleDoliFieldDesc";
		$this->descriptionlong = "ModuleDoliFieldDesc";
		$this->editor_name = 'doliFarm.com';
		$this->editor_url = 'https://www.dolifarm.com';
		$this->version = '0.3.3';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Icon of the suite when doliFarm is installed
		$this->picto = (function_exists('dolifarm_get_fa_icon') ? dolifarm_get_fa_icon('dolifield') : 'fa-satellite-dish');

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0
		);

		$this->dirs = array("/dolifield/temp", "/dolifield/weather");
		$this->config_page_url = array("setup.php@dolifield");
		$this->hidden = getDolGlobalInt('MODULE_DOLIFIELD_DISABLED');

		// The field data belong to the plots, buildings and silos of doliFarm
		$this->depends = array('modDolifarm');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("dolifield@dolifield");
		$this->phpmin = array(8, 2);
		$this->need_dolibarr_version = array(23, -3);
		$this->need_javascript_ajax = 0;
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array();

		if (!isModEnabled("dolifield")) {
			$conf->dolifield = new stdClass();
			$conf->dolifield->enabled = 0;
		}

		// Weather tab on the plots of doliFarm
		$this->tabs = array(
			array('data' => 'dolifarmplot@dolifarm:+dolifieldweather:Weather:dolifield@dolifield:$user->hasRight("dolifield", "read"):/dolifield/dolifield_plot_weather.php?id=__ID__'),
		);
		$this->dictionaries = array();
		$this->boxes = array(
			0 => array('file' => 'box_dolifield_weather.php@dolifield', 'note' => 'Weather of the next 48 hours', 'enabledbydefaulton' => 'Home'),
		);
		// Weather: weather points of the plots, history and updates from Copernicus ERA5-Land (needs the key of the
		// Climate Data Store, set in the setup). Every hour: a request sent is checked at the next run.
		$this->cronjobs = array(
			0 => array(
				'label' => 'DolifieldWeatherJob',
				'jobtype' => 'method',
				'class' => '/dolifield/class/dolifieldweather.class.php',
				'objectname' => 'DoliFieldWeather',
				'method' => 'cronUpdate',
				'parameters' => '',
				'comment' => 'Weather of the plots: history and updates from Copernicus ERA5-Land',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("dolifield")',
				'priority' => 50,
			),
			1 => array(
				'label' => 'DolifieldForecastJob',
				'jobtype' => 'method',
				'class' => '/dolifield/class/dolifieldweather.class.php',
				'objectname' => 'DoliFieldWeather',
				'method' => 'cronForecast',
				'parameters' => '',
				'comment' => 'Weather of the plots: forecasts of MET Norway (every 3 hours for each weather point)',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 1,
				'test' => 'isModEnabled("dolifield")',
				'priority' => 50,
			),
		);

		// Permissions
		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", 1);
		$this->rights[$r][1] = 'Read the field data';
		$this->rights[$r][4] = 'read';
		$this->rights[$r][5] = '';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", 2);
		$this->rights[$r][1] = 'Manage the weather points and their updates';
		$this->rights[$r][4] = 'weather';
		$this->rights[$r][5] = 'write';
		$r++;

		// Menus: one entry for each area of the field data (pages still to be written open the area page; Weather has its page)
		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'DoliFieldTopMenu',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifield',
			'leftmenu' => '',
			'url' => '/dolifield/dolifieldindex.php',
			'langs' => 'dolifield@dolifield',
			'position' => 1003,	// Top menu of the suite: Farm dossiers (1001), Field notebook of doliTrace (1002), Field (1003)
			'enabled' => "isModEnabled('dolifield')",
			'perms' => '$user->hasRight("dolifield", "read")',
			'target' => '',
			'user' => 2,
		);
		$areas = array(
			'water' => 'WaterManagement',
			'production' => 'ProductionManagement',
			'soil' => 'SoilManagement',
			'energy' => 'SolarEnergy',
			'satellite' => 'Satellite',
			'weather' => 'Weather',
			'videocam' => 'VideoCam',
		);
		foreach ($areas as $code => $title) {
			$this->menu[$r++] = array(
				'fk_menu' => 'fk_mainmenu=dolifield',
				'type' => 'left',
				'titre' => $title,
				'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle paddingright"'),
				'mainmenu' => 'dolifield',
				'leftmenu' => 'dolifield_'.$code,
				'url' => ($code == 'weather' ? '/dolifield/dolifield_weather.php' : '/dolifield/dolifieldindex.php'),
				'langs' => 'dolifield@dolifield',
				'position' => 1000 + $r,
				'enabled' => "isModEnabled('dolifield')",
				'perms' => '$user->hasRight("dolifield", "read")',
				'target' => '',
				'user' => 2,
			);
		}
		// "Help me" (getting started of doliFarm): last section of the left menu of the three modules of the suite, after a
		// separator (dolifarm/css/dolifarm.css), with the field app under it (declared after it: the parent must exist)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifield',
			'type' => 'left',
			'titre' => 'StartTitle',
			'prefix' => img_picto('', 'fa-question-circle', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifield',
			'leftmenu' => 'dolifarm_start',
			'url' => '/dolifarm/dolifarm_start.php',
			'langs' => 'dolifarm@dolifarm',
			'position' => 9012,	// Last entry of the left menu; one position for each module (unique key of llx_menu)
			'enabled' => 'isModEnabled("dolifarm")',
			'perms' => '1',
			'target' => '',
			'user' => 0,
		);
		// Field app (PWA) for the phone: under "Help me", in the same section at the bottom of the left menu of the three modules
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=dolifield,fk_leftmenu=dolifarm_start',
			'type' => 'left',
			'titre' => 'PwaTitle',
			'prefix' => img_picto('', 'fa-mobile-alt', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'dolifield',
			'leftmenu' => 'dolitrace_pwa',
			'url' => '/dolitrace/dolitrace_pwa.php',
			'langs' => 'dolitrace@dolitrace',
			'position' => 9002,	// One position for each module (unique key of llx_menu)
			'enabled' => 'isModEnabled("dolitrace") && isModEnabled("dolifarm")',
			'perms' => '$user->hasRight("dolitrace", "dolitracepwa", "use") || $user->hasRight("dolitrace", "dolitraceoperation", "write") || $user->hasRight("dolitrace", "dolitraceharvest", "write")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/dolifield/sql/');
		if ($result < 0) {
			return -1;
		}

		// Permissions and menus of the previous version are removed, then recreated
		$this->remove($options);

		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
