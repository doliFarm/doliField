# DOLIFIELD FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

doliField collects the field data of the doliFarm suite: sensors, weather, satellite and cameras, linked to the plots, buildings and silos of doliFarm.

**In development.** Version 0.1.0 has only a demonstration page with example values: no sensor, weather station or satellite service is connected yet.

Requirements: Dolibarr 23 or later, doliFarm enabled, PHP 7.4 to 8.5. The folder must be named `dolifield` in lower case:

    git clone git@github.com:doliFarm/doliField.git dolifield

<!--
![Screenshot dolifield](img/screenshot_dolifield.png?raw=true "DoliField"){imgmd}
-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files in the module directories under `langs`.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Installation

Prerequisites: You must have Dolibarr ERP & CRM software installed. You can download it from [Dolistore.org](https://www.dolibarr.org).
You can also get a ready-to-use instance in the cloud from https://saas.dolibarr.org


### From the ZIP file and GUI interface

If the module is a ready-to-deploy zip file, so with a name `module_xxx-version.zip` (e.g., when downloading it from a marketplace like [Dolistore](https://www.dolistore.com)),
go to menu `Home> Setup> Modules> Deploy external module` and upload the zip file.

<!--

Note: If this screen tells you that there is no "custom" directory, check that your setup is correct:

- In your Dolibarr installation directory, edit the `htdocs/conf/conf.php` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading `//`) and assign the proper value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
-->

<!--

### From a GIT repository

Clone the repository in `$dolibarr_main_document_root_alt/dolifield`

```shell
cd ....../custom
git clone git@github.com:gitlogin/dolifield.git dolifield
```

-->

### Final steps

Using your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup"> "Modules"
  - You should now be able to find and enable the module



## License

Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>

This module is free software, released under the **GNU Affero General Public License version 3 or later** (AGPL-3.0-or-later, text in `COPYING` and `LICENSE`). If you run a modified version for users over a network (for example as a SaaS), you must offer them its source code.

Some files are derived from the ModuleBuilder templates of Dolibarr and keep the notice of their authors under the **GNU General Public License version 3 or later** (text in `COPYING-GPL-3.0`). The module as a whole is distributed under the AGPL, as allowed by section 13 of both licenses.

"doliFarm" and "doliField" are names of doliFarm.com: the license covers the code, not the names.
