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

-- Forecasts of MET Norway (doliField 0.3.0), for the databases installed with 0.2.0.
-- Dolibarr ignores "column already exists", so the script can run at every activation.

ALTER TABLE llx_dolifield_weather_hourly ADD COLUMN cloud double(10,3);
ALTER TABLE llx_dolifield_weather_hourly ADD COLUMN symbol_code varchar(32);
ALTER TABLE llx_dolifield_weather_hourly ADD COLUMN precip_hours smallint;
ALTER TABLE llx_dolifield_weather_hourly ADD COLUMN run_utc datetime;

ALTER TABLE llx_dolifield_weather_station ADD COLUMN forecast_run datetime;
ALTER TABLE llx_dolifield_weather_station ADD COLUMN forecast_expires datetime;
ALTER TABLE llx_dolifield_weather_station ADD COLUMN forecast_modified varchar(64);
ALTER TABLE llx_dolifield_weather_station ADD COLUMN date_last_forecast datetime;
ALTER TABLE llx_dolifield_weather_station ADD COLUMN forecast_error text;
