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

-- Weather point: a cell of the grid of a reanalysis or a station, shared by the plots that are near it
CREATE TABLE IF NOT EXISTS llx_dolifield_weather_station(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	source varchar(32) NOT NULL,				-- era5land, ...
	source_code varchar(64) NOT NULL,			-- code of the point for the source (grid cell: 37.50_14.10)
	label varchar(255),
	latitude double(24,8),
	longitude double(24,8),
	elevation double(24,8),
	status smallint DEFAULT 1 NOT NULL,			-- 1 active, 0 not used by any plot
	history_status smallint DEFAULT 0 NOT NULL,	-- 0 to do, 1 request sent, 2 loaded, 9 error
	history_job varchar(128),					-- id of the request at the service
	history_job_from date,						-- first day of the request
	history_job_to date,						-- last day of the request
	data_from date,								-- first day loaded
	data_to date,								-- last day loaded
	date_last_request datetime,
	date_last_load datetime,
	nb_errors integer DEFAULT 0 NOT NULL,
	last_error text,
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
