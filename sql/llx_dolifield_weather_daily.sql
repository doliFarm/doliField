-- Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU Affero General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU Affero General Public License for more details.
--
-- You should have received a copy of the GNU Affero General Public License
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

-- Daily values of a weather point (UTC days), kept forever. Kind: 1 reanalysis, 2 observed, 3 forecast.
CREATE TABLE IF NOT EXISTS llx_dolifield_weather_daily(
	fk_station integer NOT NULL,
	kind smallint NOT NULL,
	day date NOT NULL,
	tmin double(10,3),				-- degrees C
	tmax double(10,3),
	tmean double(10,3),
	dewpoint double(10,3),
	rh_min double(10,3),			-- percent
	rh_max double(10,3),
	rh_mean double(10,3),
	precip double(10,3),			-- mm
	wind_mean double(10,3),			-- m/s
	wind_max double(10,3),
	radiation double(10,3),			-- MJ/m2
	soil_water double(10,4),		-- m3/m3
	pressure double(10,3),			-- kPa
	et0 double(10,3),				-- reference evapotranspiration, mm (FAO-56, later)
	nb_hours smallint,
	PRIMARY KEY (fk_station, kind, day)
) ENGINE=innodb;
