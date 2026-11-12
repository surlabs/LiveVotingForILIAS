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

use LiveVoting\platform\LiveVotingException;
use LiveVoting\Utils\LiveVotingJs;


/**
 * Class LiveVotingPrioritiesPlayerGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy LiveVotingPrioritiesPlayerGUI: ilUIPluginRouterGUI, LiveVotingPlayerGUI
 * @ilCtrl_Calls LiveVotingPrioritiesPlayerGUI: LiveVotingPlayerGUI, ilUIPluginRouterGUI
 */
class LiveVotingPrioritiesPlayerGUI extends LiveVotingCorrectOrderPlayerGUI
{
    /**
     * @return bool
     * @throws LiveVotingException
     */
    protected function isRandomizeOptions(): bool
    {
        return $this->getPlayer()->getActiveVotingObject()->isRandomiseOptionSequence();
    }


    /**
     * @return string
     */
    public function getMobileHTML(): string
    {
        return '<div class="xlvo-priorities">' . $this->getFormContent() . '</div>'
            . LiveVotingJs::getInstance()->name('FreeOrder')->category('QuestionTypes/FreeOrder')->getRunCode();
    }


    /**
     * @return string
     */
    protected function getFormTemplatePath(): string
    {
        return ilLiveVotingPlugin::getInstance()->getDirectory()
            . '/templates/default/QuestionTypes/Priorities/'
            . 'tpl.priorities.html';
    }

    protected function decorateFormTemplate(ilTemplate $tpl, bool $has_answered): void
    {
        $this->setFormVariables($tpl, $has_answered, 'voter_hint_priorities', 'voter_submit_ranking');
    }


    /**
     * @param bool $current
     */
    public function initJS(bool $current = false)
    {

    }


    /**
     * @return array
     */
    public function getButtonInstances(): array
    {
        if (!$this->getPlayer()->isShowResults()) {
            return array();
        }
        $states = $this->getButtonsStates();
        $b = ilLinkButton::getInstance();
        $b->setId(self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER);
        if (array_key_exists(self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER, $states) && $states[self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER]) {
            $b->setCaption('<span class="glyphicon glyphicon-align-left"></span>', false);
        } else {
            $b->setCaption('<span class="glyphicon glyphicon-sort-by-attributes-alt"></span>', false);
        }


        return array($b);
    }

}
