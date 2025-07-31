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
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\Utils\LiveVotingJs;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingParticipant;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;


/**
 * Class LiveVotingSingleVotePlayerGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy LiveVotingSingleVotePlayerGUI: ilUIPluginRouterGUI, LiveVotingPlayerGUI
 * @ilCtrl_Calls LiveVotingSingleVotePlayerGUI: LiveVotingPlayerGUI, ilUIPluginRouterGUI
 */
class LiveVotingSingleVotePlayerGUI extends LiveVotingQuestionTypesUI
{

    const BUTTON_TOGGLE_PERCENTAGE = 'toggle_percentage';


    /**
     * @param bool $current
     * @throws ilCtrlException
     */
    public function initJS(bool $current = false)
    {

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

        $option_id = (int)$_GET['option_id'];

        $vote_id = null;

        $participant = LiveVotingParticipant::getInstance();

        if ($this->player->hasUserVotedForOption($option_id)) {
            LiveVotingVote::unvote($participant, $this->player->getActiveVoting(), $option_id);
        } else {
            if ($this->player->getActiveVotingObject()->isValidOption($option_id)) {
                $vote_id = LiveVotingVote::vote($participant, $this->player->getActiveVoting(), $this->player->getRoundId(), $option_id);
            }
        }
        if (!$this->player->getActiveVotingObject()->isMultiSelection()) {
            $this->player->unvoteAll($vote_id);
        }

        if ($liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
            $this->calculateReachedPoints();
        }

        $this->player->createHistoryObject();

        if (isset($_GET['isRequest']) && (bool)$_GET['isRequest']) {
            header('Content-type: application/json');
            echo json_encode(["ok" => true]);
            exit();
        }
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
        $t = ilLinkButton::getInstance();
        $t->setId(self::BUTTON_TOGGLE_PERCENTAGE);
        if (in_array(self::BUTTON_TOGGLE_PERCENTAGE, $states)) {
            $t->setCaption(' %', false);
        } else {
            $t->setCaption('<span class="glyphicon glyphicon-user" aria-hidden="true"></span>', false);
        }

        return array($t);
    }


    /**
     * @param $button_id
     * @param $data
     * @throws LiveVotingException
     */
    public function handleButtonCall($button_id, $data)
    {
        //var_dump($button_id, $data);exit;
        $states = $this->getButtonsStates();
        $this->saveButtonState($button_id, !(array_key_exists($button_id, $states) && $states[$button_id]));
    }


    /**
     * @return string
     * @throws LiveVotingException
     * @throws ilTemplateException
     * @throws ilCtrlException
     * @throws ilSystemStyleException
     */
    public function getMobileHTML(): string
    {
        global $DIC;
        $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/SingleVote/tpl.single_vote.html', false, true);
        $answer_count = 64;
        foreach ($this->getPlayer()->getActiveVotingObject()->getOptions() as $xlvoOption) {
            $answer_count++;
            $DIC->ctrl()->setParameter($this, 'option_id', $xlvoOption->getId());
            $tpl->setCurrentBlock('option');
            $tpl->setVariable('TITLE', $xlvoOption->getTextForPresentation());
            $tpl->setVariable('LINK', $DIC->ctrl()->getLinkTarget($this, 'submit'));
            $tpl->setVariable('OPTION_LETTER', chr($answer_count));
            $tpl->setVariable('TYPE', $this->getPlayer()->getActiveVotingObject()->isMultiSelection() ? 'checkbox' : 'radio');
            if ($this->player->hasUserVotedForOption($xlvoOption->getId())) {
                $tpl->setVariable('BUTTON_STATE', 'btn-primary');
                $tpl->setVariable('CHECKBOX', 'checked');
                $tpl->setVariable('ACTION', ilLiveVotingPlugin::getInstance()->txt('qtype_1_unvote'));
            } else {
                $tpl->setVariable('BUTTON_STATE', 'btn-default');
                $tpl->setVariable('ACTION', ilLiveVotingPlugin::getInstance()->txt('qtype_1_vote'));
            }
            $tpl->parseCurrentBlock();
        }

        return $tpl->get() . LiveVotingJs::getInstance()->name('SingleVote')->category('QuestionTypes/SingleVote')->getRunCode();
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    private function calculateReachedPoints(): void
    {
        $points = $this->player->getActiveVotingObject()->getScore();

        $correct_options = array();
        $user_votes = array();

        foreach ($this->player->getActiveVotingObject()->getOptions() as $option) {
            if ($option->isCorrect()) {
                $correct_options[] = $option->getId();
            }
        }

        foreach ($this->player->getVotesOfUser() as $vote) {
            $user_votes[] = $vote->getOptionId();
        }

        sort($correct_options);
        sort($user_votes);

        if (count(array_diff($correct_options, $user_votes)) > 0 || count(array_diff($user_votes, $correct_options)) > 0) {
            $points = 0;
        }

        $score_mult = 1;

        $time_to_answer = $this->player->getCountdown();

        if ($time_to_answer > 0) {
            $time_remaining = $this->player->remainingCountDown();

            if ($time_remaining < 0) {
                $time_remaining = 0;
            } elseif ($time_remaining > $time_to_answer) {
                $time_remaining = $time_to_answer;
            }

            $score_mult = 1 - (($time_to_answer - $time_remaining)/$time_to_answer)/2;
        }

        $points = round($points * $score_mult);

        $participant = LiveVotingParticipant::getInstance();

        $database = new LiveVotingDatabase();

        $database->insertOnDuplicatedKey("xlvo_points", [
            'identifier' => $participant->getIdentifier(),
            'obj_id' => $this->player->getObjId(),
            'voting_id' => $this->player->getActiveVoting(),
            'round_id' => $this->player->getRoundId(),
            'points' => $points
        ]);
    }
}