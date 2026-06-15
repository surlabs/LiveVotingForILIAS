<div alt style="text-align: center; transform: scale(.5);">
	<picture>
		<source media="(prefers-color-scheme: dark)" srcset="https://github.com/surlabs/LiveVotingRW/blob/ilias8/templates/images/GitBannerLiveVoting.png" />
		<img alt="LiveVoting" src="https://github.com/surlabs/LiveVotingRW/blob/ilias8/templates/images/GitBannerLiveVoting.png" />
	</picture>
</div>

# LiveVoting Repository Object Plugin for ILIAS 8
This plugin allows to create real time votings within ILIAS.
It is compatible with the previous LiveVoting plugin for ILIAS < 7.0 information and objects.
### Software Requirements
This LiveVoting version 8.0 requires [PHP](https://php.net) version 7.4 or 8.0.x to work properly on your ILIAS 8 platform

## Installation & Update
Please, notice that previous versions of the plugin were numbered with dates (e.g. 2021.01.01). This version is numbered with the ILIAS version it is compatible with (e.g. release_8 -> 8.x).
**You need to change the plugin version in the il_plugin table of the database to something lower than 8.0.0 before running the following commands.**

1. **Ensure you delete any previous LiveVoting folder** in Customizing/global/plugins/Services/Repository/RepositoryObject/

2. Create subdirectories, if necessary for Customizing/global/plugins/Services/Repository/RepositoryObject/ or run the following script from the ILIAS root

```bash
mkdir -p Customizing/global/plugins/Services/Repository/RepositoryObject
cd Customizing/global/plugins/Services/Repository/RepositoryObject
```

3. Then, execute:

```bash
git clone https://github.com/surlabs/LiveVotingForILIAS.git ./LiveVoting
cd LiveVoting
git checkout release_8
```

Ensure you run composer install at platform root before you install/update the plugin
```bash
composer install --no-dev
```

**Ensure you don't ignore plugins at the ilias .gitignore files and don't use --no-plugins option at ILIAS setup**

## Configuration
If you want to use the Shortlink mode, you need to rewrite the rule in .htaccess or Apache-Config
```apacheconf
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteRule ^/?vote(/\w*)? /Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/pin.php?xlvo_pin=$1 [L]
</IfModule>
```

# Authors
* Initially created by studer + raimann ag, switzerland
* Further maintained by fluxlabs ag, switzerland
* Revamped and currently maintained by SURLABS, spain [SURLABS](https://surlabs.com)

# Bug Reports & Discussion
- Bug Reports: [Mantis](https://www.ilias.de/mantis) (Choose project "ILIAS plugins" and filter by category "LiveVoting")
- SIG LiveVoting: [Forum](https://docu.ilias.de/goto_docu_frm_13535.html)

# Version History
* The version 9.x.x for **ILIAS 9** maintained by SURLABS can be found in the Github branch **release_9**
* The version 8.x.x for **ILIAS 8** maintained by SURLABS can be found in the Github branch **release_8**
* The previous plugin versions for ILIAS <8 is archived. It can be found in https://github.com/fluxapps/LiveVoting
