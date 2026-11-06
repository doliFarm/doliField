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

ALTER TABLE llx_dolifield_weather_plot ADD UNIQUE INDEX uk_dolifield_weather_plot(entity, fk_plot, fk_station);
ALTER TABLE llx_dolifield_weather_plot ADD INDEX idx_dolifield_weather_plot_station(fk_station);
