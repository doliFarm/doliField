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

-- Hourly values of a weather point (UTC). Kind: 1 reanalysis, 2 observed, 3 forecast.
-- Precipitation and radiation are those of the hour that ends at date_utc (of the precip_hours hours for the
-- forecasts beyond the first days, given every 6 hours).
-- Forecasts: each new forecast replaces the hours from its first hour on; the past hours keep the last forecast made
-- before them and are kept forever, as the raw files in the documents.
-- The reanalysis is kept for the months set in the setup (DOLIFIELD_WEATHER_HOURLY_MONTHS); its files stay in the documents.
CREATE TABLE IF NOT EXISTS llx_dolifield_weather_hourly(
	fk_station integer NOT NULL,
	kind smallint NOT NULL,
	date_utc datetime NOT NULL,
	temp double(10,3),				-- air temperature at 2 m, degrees C
	dewpoint double(10,3),			-- dew point at 2 m, degrees C
	rh double(10,3),				-- relative humidity, percent
	precip double(10,3),			-- precipitation, mm
	wind_speed double(10,3),		-- wind at 10 m, m/s
	wind_dir double(10,3),			-- direction the wind comes from, degrees
	radiation double(10,3),			-- global solar radiation, W/m2 (mean of the hour)
	soil_water double(10,4),		-- volumetric soil water 0-7 cm, m3/m3
	pressure double(10,3),			-- surface pressure, kPa
	cloud double(10,3),				-- cloud cover, percent (forecasts)
	symbol_code varchar(32),		-- weather symbol of the next hour (forecasts of MET Norway)
	precip_hours smallint,			-- hours of the precipitation that ends at date_utc (1, or 6 for the forecasts at 6 hours)
	run_utc datetime,				-- time of the forecast (model run of the source)
	PRIMARY KEY (fk_station, kind, date_utc)
) ENGINE=innodb;
