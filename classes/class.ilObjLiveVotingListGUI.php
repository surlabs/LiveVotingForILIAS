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

use LiveVoting\objects\modes\LiveVotingMode;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\votings\LiveVoting;

/**
 * Class ilObjLiveVotingListGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class ilObjLiveVotingListGUI extends ilObjectPluginListGUI
{
    public function getGuiClass(): string
    {
        return "ilObjLiveVotingGUI";
    }

    public function initCommands(): array
    {
        return [
            [
                "permission" => "read",
                "cmd" => "index",
                "default" => true,
            ],
            [
                "permission" => "write",
                "cmd" => "manage",
                "txt" => $this->txt("tab_manage"),
            ],
            [
                "permission" => "write",
                "cmd" => "editProperties",
                "txt" => $this->lng->txt("settings"),
            ],
        ];
    }

    public function initType(): void
    {
        $this->setType("xlvo");
    }

    /**
     * Get item properties
     *
     * @return    array        array of property arrays:
     *                        'alert' (boolean) => display as an alert property (usually in red)
     *                        'property' (string) => property name
     *                        'value' (string) => property value
     * @throws LiveVotingException
     */
    public function getCustomProperties(array $prop): array
    {
        if (!isset($this->obj_id)) {
            return [];
        }

        $props = parent::getCustomProperties($prop);


        if (ilObjLiveVotingAccess::_isOffline($this->obj_id)) {
            $props[] = array(
                'alert' => true,
                'newline' => true,
                'property' => 'Status',
                'value' => 'Offline'
            );
        }

        $props[] = array(
            'newline' => true,
            "property" => $this->txt("voter_pin_input"),
            "value" => LiveVoting::getPinFromObjId($this->obj_id)
        );

        $mode = LiveVoting::getModeFromObjId($this->obj_id);

        $mode = match ($mode) {
            LiveVotingMode::CHALLENGE_MODE => $this->txt("xlvo_mode_challenge"),
            default => $this->txt("xlvo_mode_basic"),
        };

        $props[] = array(
            'newline' => true,
            "property" => $this->txt("xlvo_mode"),
            "value" => $mode
        );

        return $props;
    }

    public function getAlertProperties(): array
    {
        if (!isset($this->obj_id)) {
            return [];
        }
        $props = parent::getAlertProperties();
        if (ilObjLiveVotingAccess::_isOffline($this->obj_id)) {
            $props[] = array(
                'alert' => true,
                'newline' => true,
                'property' => 'Status',
                'value' => 'Offline'
            );
        }
        return $props;
    }
}