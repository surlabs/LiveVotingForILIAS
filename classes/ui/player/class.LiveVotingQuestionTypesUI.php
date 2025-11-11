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
use LiveVoting\votings\LiveVotingPlayer;

/**
 * Class LiveVotingQuestionTypesUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
abstract class LiveVotingQuestionTypesUI
{
    /**
     * @var LiveVotingPlayer Object
     */
    protected LiveVotingPlayer $player;

    /**
     * @var bool $show_question
     */
    protected bool $show_question = true;


    /**
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        global $DIC;

        $nextClass = $DIC->ctrl()->getNextClass();

        switch ($nextClass) {
            default:
                $cmd = null;
                if (isset($_POST['cmd'])) {
                    $cmd = $_POST['cmd'];
                    if (!empty($cmd)) {
                        if (is_array($cmd)) {
                            // this most likely only works by accident, but
                            // the selected or clicked command button will
                            // always be sent as first array entry. This
                            // should definitely be done differently.
                            $cmd = (string)array_key_first($cmd);
                        } else {
                            $cmd = (string)$cmd;
                        }
                    }
                }
                if (empty($cmd)) {
                    $cmd = $DIC->ctrl()->getCmd('submit');
                }

                $this->{$cmd}();
                if ($cmd == 'submit') {
                    $this->afterSubmit();
                }
                break;
        }
    }

    /**
     * @param LiveVotingPlayer $player
     * @param null $override_type
     *
     * @return LiveVotingQuestionTypesUI
     * @throws ilException                 Throws an ilException if no gui class was found.
     * @throws LiveVotingException
     */
    public static function getInstance(LiveVotingPlayer $player, $override_type = null): LiveVotingQuestionTypesUI
    {

        $gui = null;
        switch ($player->getActiveVotingObject()->getQuestionType()) {
            case "CorrectOrder":
                $gui = new LiveVotingCorrectOrderPlayerGUI();
                break;
            case "FreeText":
                $gui = new LiveVotingFreeTextPlayerGUI();
                break;
            case "Priorities":
                $gui = new LiveVotingPrioritiesPlayerGUI();
                break;
            case "Choices":
                $gui = new LiveVotingSingleVotePlayerGUI();
                break;
            case "NumberRange":
                $gui = new LiveVotingNumberRangePlayerGUI();
                break;
            default:
                throw new ilException("Could not find the gui for the current voting.");
        }

        $gui->setManager($player);

        return $gui;
    }

    /**
     * @return LiveVotingPlayer
     */
    public function getPlayer(): LiveVotingPlayer
    {
        return $this->player;
    }

    /**
     * @param LiveVotingPlayer $player
     */
    public function setManager(LiveVotingPlayer $player): void
    {
        $this->player = $player;
    }

    /**
     * @return boolean
     */
    public function isShowQuestion(): bool
    {
        return $this->show_question;
    }


    /**
     * @param boolean $show_question
     */
    public function setShowQuestion(bool $show_question)
    {
        $this->show_question = $show_question;
    }


    /**
     * @param bool $current
     */
    public abstract function initJS(bool $current = false);


    /**
     *
     */
    protected abstract function submit();


    /**
     *
     * @throws ilCtrlException
     */
    protected function afterSubmit()
    {
        global $DIC;
        $DIC->ctrl()->redirect(new LiveVotingPlayerGUI, 'startVoterPlayer');
    }


    /**
     * @return string
     */
    public abstract function getMobileHTML(): string;


    //
    // Custom Buttons
    //

    /**
     * @param $button_id
     * @param $data
     * @throws LiveVotingException
     */
    public function handleButtonCall($button_id, $data)
    {
        $this->saveButtonState($button_id, $data);
    }


    /**
     * @return array
     */
    protected function getButtonsStates(): array
    {
        $xlvoPlayer = $this->getPlayer();

        return $xlvoPlayer->getButtonStates();
    }


    /**
     * @return ilButtonBase[]
     */
    public function getButtonInstances(): array
    {
        return array();
    }


    /**
     * @return bool
     */
    public function hasButtons(): bool
    {

        return (count($this->getButtonInstances()) > 0);
    }


    /**
     * @param $button_id
     * @param $state
     * @throws LiveVotingException
     */
    protected function saveButtonState($button_id, $state)
    {
        $xlvoPlayer = $this->getPlayer();
        $states = $xlvoPlayer->getButtonStates();
        $states[$button_id] = $state;
        $xlvoPlayer->setButtonStates($states);
        $xlvoPlayer->save();
    }

    /**
     * @throws ilCtrlException
     */
    protected function startVoterPlayer()
    {
        global $DIC;
        $DIC->ctrl()->redirectByClass(["ilObjPluginDispatchGUI", "LiveVotingPlayerGUI"], 'startVoterPlayer');
    }
}