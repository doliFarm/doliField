# CHANGELOG MODULE DOLIFIELD FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 0.3.2 (2026-11-18)

Menu. Dopo l'aggiornamento **riattivare doliField** (menu); richiede doliFarm 1.5.10 e, per l'app, doliTrace 1.5.5.

- Il top menu si chiama **Campo** ed è il terzo della suite, dopo Fascicoli e QdC.
- "App di campo" e "Aiutami" in fondo al menu a sinistra, dopo un separatore.

## 0.3.1 (2026-11-18)

Help per ruolo (richiede doliFarm 1.5.9 per la separazione). Dopo l'aggiornamento non serve riattivare il modulo.

- Testi separati per ruolo: chiave di Copernicus mancante (agli utenti "avvisare l'amministratore"), finestra dei trattamenti nel widget (soglie solo agli amministratori), spiegazione dei punti meteo (lavoro pianificato solo agli amministratori).

## 0.3.0 (2026-11-17)

Weather of the plots, phase M2 (forecasts). After the update reactivate the module (new columns, scheduled job "Weather forecasts of the plots", widget).

- Forecasts of MET Norway Locationforecast 2.0 (CC BY 4.0, no key) for the weather points of the plots: hourly for about 2.5 days, then every 6 hours up to 9 days. New scheduled job every hour, each point asked again after 3 hours and never before the time given by the service (Expires, If-Modified-Since); User-Agent with the site of doliFarm and the contact of the setup, as the terms of service ask.
- Each forecast replaces the hours from its first one; the past hours keep the last forecast made before them and are kept forever. Raw answers kept compressed in the documents (`dolifield/weather/metno/<point>/<month>/`).
- Tab "Weather" of the plot: forecast of the next 48 hours every 3 hours (symbol, temperature, humidity, wind and direction, rain), windows suited to the treatments, next days (minimum, maximum, rain, wind), button "Update the forecasts" (permission "Manage the weather points").
- Conditions of a period for doliTrace (`getConditions()`): for each hour the observed value, else the reanalysis, else the last forecast; temperature, humidity, wind, rain during the period and in the hours after; text in the language of the user (`formatConditions()`) and warnings over the thresholds (`conditionWarnings()`).
- Setup: contact for MET Norway; thresholds for the treatments (maximum wind 5 m/s, rain 1 mm, hours after the treatment 6, maximum temperature 30 °C).
- Widget "doliField - Weather of the next 48 hours": by weather point the plots, minimum and maximum temperature (frost), rain (heavy from 20 mm), maximum wind, first window suited to the treatments.
- Numbers of the weather pages without trailing zeros.

## 0.2.0 (2026-11-06)

Weather of the plots, phase M1 (history). Requires doliFarm 1.5.4. After the update reactivate the module (tables, tab, scheduled job, permission), then enter the key of the Climate Data Store in the setup.

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
