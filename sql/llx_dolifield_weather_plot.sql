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

-- Link between a plot of doliFarm and its weather points (doliField does not change the tables of doliFarm)
CREATE TABLE IF NOT EXISTS llx_dolifield_weather_plot(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_plot integer NOT NULL,
	fk_station integer NOT NULL,
	priority smallint DEFAULT 10 NOT NULL,		-- lower first: own station, public station, grid
	distance_km double(24,8),
	datec datetime,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
