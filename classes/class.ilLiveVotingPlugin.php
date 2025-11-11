<?php
declare(strict_types=1);
/**
 * This file is part of the LiveVoting Repository Object plugin for ILIAS.
 * This plugin allows to create real time votings within ILIAS.
 *
 * The LiveVoting Repository Object plugin for ILIAS is open-source and licensed under GPL-3.0.
 * For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 * To report bugs or participate in discussions, visit the Mantis system and filter by
 * the category "LiveVoting" at https://mantis.ilias.de.
 *
 * More information and source code are available at:
 * https://github.com/surlabs/LiveVoting
 *
 * If you need support, please contact the maintainer of this software at:
 * info@surlabs.es
 *
 */

/**
 * Class ilLiveVotingPlugin
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class ilLiveVotingPlugin extends ilRepositoryObjectPlugin
{

    const PLUGIN_ID = 'xlvo';

    const PLUGIN_NAME = 'LiveVoting';
    private static $instance;

    protected function uninstallCustom(): void
    {
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            global $DIC;

            $component_repository = $DIC["component.repository"];

            $info = null;
            $plugin_name = self::PLUGIN_NAME;
            $info = $component_repository->getPluginByName($plugin_name);

            $component_factory = $DIC["component.factory"];

            $plugin_obj = $component_factory->getPlugin($info->getId());

            self::$instance = $plugin_obj;
        }

        return self::$instance;
    }

    public function allowCopy(): bool
    {
        return true;
    }

    public static function _getIcon(string $a_type): string
    {
        return "/Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/images/icon_xlvo.svg";
    }
}
