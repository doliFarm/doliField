# CHANGELOG MODULE DOLIFIELD FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 0.2.0 (in development)

Weather of the plots, phase M1 (history). After the update reactivate the module (tables, tab, scheduled job, permission).

- Weather points: each plot of doliFarm with latitude and longitude is linked to the nearest point of the ERA5-Land grid (0.1 degrees); nearby plots share it.
- History from Copernicus ERA5-Land (hourly time series by point, CC BY 4.0) through the API of the Climate Data Store: from the year set in the setup (default 1991), then every day the last days. Asynchronous requests sent and loaded by the scheduled job "Weather of the plots" (every hour); downloaded files kept in the documents (`dolifield/weather/`).
- Daily values kept forever (temperatures, humidity, precipitation, wind, radiation, soil water, pressure); hourly values kept for 13 months by default (setting, 0 = all).
- Tab "Weather" on the plot: weather point and distance, precipitation of the last 30, 90, 180 and 365 days compared with the mean 1991-2020, last 14 days, last 12 months with the monthly means, years.
- Page "Weather points" (menu doliField > Weather): state of the history, errors, "Update now".
- Setup: key of the Climate Data Store (checked, stored encrypted), start year, months of hourly data. New permission "Manage the weather points".

## 0.1.0

- Demonstration page of the field data (example values, no sensor connected).
- Module of the doliFarm suite: depends on doliFarm, requires Dolibarr 23, AGPL-3.0-or-later.
- One permission (read the field data), menus of the areas (water, production, soil, energy, satellite, weather, cameras).
- Translations it_IT, en_US, fr_FR.
