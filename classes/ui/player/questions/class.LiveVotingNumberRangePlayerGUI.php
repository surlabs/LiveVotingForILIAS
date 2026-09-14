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
use LiveVoting\Utils\LiveVotingJs;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;


/**
 * Class LiveVotingNumberRangePlayerGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy LiveVotingNumberRangePlayerGUI: ilUIPluginRouterGUI, LiveVotingPlayerGUI
 * @ilCtrl_Calls LiveVotingNumberRangePlayerGUI: LiveVotingPlayerGUI, ilUIPluginRouterGUI
 */
class LiveVotingNumberRangePlayerGUI extends LiveVotingQuestionTypesUI
{

    const SAVE_BUTTON_VOTE = 'qtype_6_voter_start_button_vote';
    const CLEAR_BUTTON = 'qtype_6_voter_clear';
    const SAVE_BUTTON_UNVOTE = 'qtype_6_voter_start_button_unvote';


    /**
     * @param LiveVotingPlayer $player
     *
     * @throws ilException
     */
    public function setManager(LiveVotingPlayer $player): void
    {

        if ($player === null) {
            throw new ilException('The manager must not be null.');
        }

        parent::setManager($player);
    }


    /**
     * @param bool $current
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function initJS(bool $current = false)
    {
        /*xlvoJs::getInstance()->api($this)->name(xlvoQuestionTypes::NUMBER_RANGE)->category('QuestionTypes')->addLibToHeader('bootstrap-slider.min.js')
            ->addSettings([
                "step" => $this->getStep()
            ])->init();*/


    }

    /**
     *
     * @throws LiveVotingException
     */
    protected function submit(): void
    {
        $param_manager = ParamManager::getInstance();
        $liveVoting = LiveVoting::getLiveVotingFromPin($param_manager->getPin());
        $this->player = $liveVoting->getPlayer();

        $filteredInput = filter_input(INPUT_POST, "user_selected_number", FILTER_VALIDATE_INT);

        if ($filteredInput !== false && $filteredInput !== null) {
            if ($this->isVoteValid($this->getStart(), $this->getEnd(), $filteredInput)) {
                $this->player->unvoteAll();

                $this->player->input([
                    'input' => (string)$filteredInput,
                    'vote_id' => "0",
                ]);
            }
        }
    }

    /**
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    protected function clear(): void
    {
        $param_manager = ParamManager::getInstance();
        $liveVoting = LiveVoting::getLiveVotingFromPin($param_manager->getPin());
        $this->player = $liveVoting->getPlayer();

        $this->player->unvoteAll();

        $this->afterSubmit();
    }

    /**
     * @return string
     * @throws ilCtrlException
     * @throws LiveVotingException
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     */
    public function getMobileHTML(): string
    {
        global $DIC;

        $template = new IlTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/NumberRange/tpl.number_range.html', true, true);
        $live_voting = new LiveVoting($this->player->getObjId(), false);
        $is_new_ui = $live_voting->getMode()->getMode() === LiveVotingMode::BASIC_MODE
            && $live_voting->usesNewUI();
        $template->setVariable('ACTION', $DIC->ctrl()->getFormAction($this));
        $template->setVariable('SHOW_PERCENTAGE', (int)$this->getPlayer()->getActiveVotingObject()->isPercentage());

        /**
         * @var LiveVotingVote[] $userVotes
         */
        $userVotes = $this->getPlayer()->getVotesOfUser();
        $userVotes = array_values($userVotes);

        $template->setVariable('SLIDER_MIN', $this->getStart());
        $template->setVariable('SLIDER_MAX', $this->getEnd());
        $template->setVariable('SLIDER_STEP', $this->getStep());
        if (!empty($userVotes) && $userVotes[0] instanceof LiveVotingVote) {
            $user_has_voted = true;
            $value = (int)$userVotes[0]->getFreeInput();
        } else {
            $user_has_voted = false;
            $value = $this->getDefaultValue();
        }
        $template->setVariable('SLIDER_VALUE', $value);
        $template->setVariable('BTN_SAVE', ilLiveVotingPlugin::getInstance()->txt(self::SAVE_BUTTON_VOTE));

        if (!$is_new_ui) {
            $template->setCurrentBlock('clear');
            $template->setVariable('BTN_CLEAR', ilLiveVotingPlugin::getInstance()->txt(self::CLEAR_BUTTON));
            if (!$user_has_voted) {
                $template->setVariable('BTN_RESET_DISABLED', 'disabled="disabled"');
            }
            $template->parseCurrentBlock();
        }

        return $template->get() . LiveVotingJs::getInstance()->name('NumberRange')->category('QuestionTypes/NumberRange')->getRunCode();
    }


    /**
     * @return int
     * @throws LiveVotingException
     */
    private function getDefaultValue(): int
    {
        return $this->snapToStep(($this->getStart() + $this->getEnd()) / 2);
    }


    /**
     * @param int $start
     * @param int $end
     * @param float $value
     *
     * @return bool
     * @throws LiveVotingException
     */
    private function isVoteValid(int $start, int $end, float $value): bool
    {
        return ($value >= $start && $value <= $end && $value == $this->snapToStep($value));
    }


    /**
     * @return int
     * @throws LiveVotingException
     */
    private function getStart(): int
    {
        return (int)$this->getPlayer()->getActiveVotingObject()->getStartRange();
    }


    /**
     * @return int
     * @throws LiveVotingException
     */
    private function getEnd(): int
    {
        return (int)$this->getPlayer()->getActiveVotingObject()->getEndRange();
    }


    /**
     * @return int
     * @throws LiveVotingException
     */
    public function getStep(): int
    {
        return (int)$this->getPlayer()->getActiveVotingObject()->getStepRange();
    }


    /**
     * @param float $value
     *
     * @return int
     * @throws LiveVotingException
     */
    private function snapToStep(float $value): int
    {
        return intval(ceil(($value - $this->getStart()) / $this->getStep()) * $this->getStep()) + $this->getStart();
    }
}
