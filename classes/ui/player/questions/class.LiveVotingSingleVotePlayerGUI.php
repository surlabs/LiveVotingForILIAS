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

        if ($liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE && $this->isAnswerTimeOver()) {
            if (isset($_GET['isRequest']) && (bool) $_GET['isRequest']) {
                header('Content-type: application/json');
                echo json_encode(["ok" => false]);
                exit();
            }

            return;
        }

        if (isset($_POST['xlvo_selection'])) {
            $this->submitSelection();

            return;
        }

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
        if (array_key_exists(self::BUTTON_TOGGLE_PERCENTAGE, $states) && $states[self::BUTTON_TOGGLE_PERCENTAGE]) {
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
    /**
     * the voter selects and then submits the whole selection at once. Votes for options that are no longer
     * selected are withdrawn, so "Change Answer" followed by a new submit replaces the previous answer.
     *
     * @throws LiveVotingException
     */
    /**
     * In a challenge the countdown is part of the game: an answer sent after it ran out does not count.
     * Two seconds of grace cover the request travelling while the timer hits zero. Once the presenter has moved on
     * to the scoreboard the countdown is reset, so the status is checked too.
     */
    private function isAnswerTimeOver(): bool
    {
        if ($this->player->getStatus() !== LiveVotingPlayer::STAT_RUNNING) {
            return true;
        }

        return $this->player->getCountdownStart() > 0 && $this->player->remainingCountDown() < -2;
    }

    protected function submitSelection(): void
    {
        $question = $this->player->getActiveVotingObject();
        $selected = array_map('intval', (array) ($_POST['option_ids'] ?? []));
        $selected = array_values(array_unique(array_filter($selected, static fn(int $option_id): bool => $question->isValidOption($option_id))));
        if (!$question->isMultiSelection()) {
            $selected = array_slice($selected, 0, 1);
        }
        if ($selected === []) {
            return;
        }

        $participant = LiveVotingParticipant::getInstance();
        foreach ($this->player->getVotesOfUser() as $vote) {
            if (!in_array((int) $vote->getOptionId(), $selected, true)) {
                LiveVotingVote::unvote($participant, $this->player->getActiveVoting(), (int) $vote->getOptionId());
            }
        }
        foreach ($selected as $option_id) {
            if (!$this->player->hasUserVotedForOption($option_id)) {
                LiveVotingVote::vote($participant, $this->player->getActiveVoting(), $this->player->getRoundId(), $option_id);
            }
        }

        if (LiveVoting::getModeFromObjId($this->player->getObjId()) == LiveVotingMode::CHALLENGE_MODE) {
            $this->calculateReachedPoints();
        }

        $this->player->createHistoryObject();
    }

    /**
     * @throws LiveVotingException
     * @throws ilTemplateException
     */
    protected function getVoterFormHTML(): string
    {
        global $DIC;

        $plugin = ilLiveVotingPlugin::getInstance();
        $question = $this->getPlayer()->getActiveVotingObject();
        $is_multi = $question->isMultiSelection();
        $tpl = new ilTemplate($plugin->getDirectory() . '/templates/default/QuestionTypes/SingleVote/tpl.single_vote.html', true, true);

        $has_answered = false;
        foreach ($question->getOptions() as $option) {
            if ($this->player->hasUserVotedForOption($option->getId())) {
                $has_answered = true;
                break;
            }
        }

        foreach ($question->getOptions() as $option) {
            $voted = $this->player->hasUserVotedForOption($option->getId());
            $tpl->setCurrentBlock('option');
            $tpl->setVariable('TYPE', $is_multi ? 'checkbox' : 'radio');
            $tpl->setVariable('OPTION_ID', (string) $option->getId());
            $tpl->setVariable('OPTION_LETTER', $option->getCipher());
            $tpl->setVariable('TITLE', $option->getTextForPresentation());
            $tpl->setVariable('CHECKED', $voted ? 'checked' : '');
            $tpl->setVariable('DISABLED', $has_answered ? 'disabled' : '');
            $tpl->setVariable('SUBMITTED_CLASS', $voted && $has_answered ? ' is-submitted' : '');
            $tpl->parseCurrentBlock();
        }

        $DIC->ctrl()->clearParameters($this);
        $tpl->setVariable('ACTION', $DIC->ctrl()->getFormAction($this));
        $tpl->setVariable('LOCKED_CLASS', $has_answered ? ' is-locked' : '');
        $tpl->setVariable('VOTER_HINT', $question->getQuestionTypeLabel());
        $tpl->setVariable('SUBMIT_TEXT', $plugin->txt('voter_submit_vote'));
        $tpl->setVariable('SUBMIT_LABEL', $plugin->txt($has_answered ? 'voter_submitted' : 'voter_submit_vote'));
        $tpl->setVariable('SUBMIT_DISABLED', $has_answered ? 'disabled' : '');
        $tpl->setVariable('CHANGE_TEXT', $plugin->txt('voter_change_answer'));
        $tpl->setVariable('CHANGE_DISABLED', $has_answered ? '' : 'disabled');
        $tpl->setVariable('FEEDBACK', $plugin->txt($is_multi ? 'voter_select_options_first' : 'voter_select_option_first'));

        return $tpl->get();
    }

    /**
     * Challenge, once the question is closed: the voter's own answer with the inputs kept, the chosen options
     * in green (correct) or red (wrong) and the correct ones marked. A voter who did not answer in time gets the
     * options without any feedback.
     *
     * @throws LiveVotingException
     * @throws ilTemplateException
     */
    public function getChallengeFeedbackHTML(): string
    {
        $plugin = ilLiveVotingPlugin::getInstance();
        $question = $this->getPlayer()->getActiveVotingObject();
        $is_multi = $question->isMultiSelection();
        $tpl = new ilTemplate($plugin->getDirectory() . '/templates/default/QuestionTypes/SingleVote/tpl.single_vote.html', true, true);

        $options = $question->getOptions();
        $chosen = [];
        foreach ($options as $option) {
            if ($this->player->hasUserVotedForOption($option->getId())) {
                $chosen[] = $option->getId();
            }
        }
        $has_answered = $chosen !== [];

        if ($has_answered) {
            $is_correct = true;
            foreach ($options as $option) {
                if ($option->isCorrect() !== in_array($option->getId(), $chosen, true)) {
                    $is_correct = false;
                    break;
                }
            }

            $points = LiveVotingPlayer::getPlayerPoints(
                LiveVotingParticipant::getInstance()->getIdentifier(),
                $this->player->getObjId(),
                $this->player->getActiveVoting(),
                $this->player->getRoundId()
            );

            $tpl->setCurrentBlock('challenge_result');
            $tpl->setVariable('RESULT_STATE', $is_correct ? 'correct' : 'wrong');
            $tpl->setVariable('RESULT_TEXT', sprintf(
                $plugin->txt($is_correct ? 'challenge_feedback_correct' : 'challenge_feedback_wrong'),
                $points . ' ' . $plugin->txt('scoreboard_points_unit')
            ));
            $tpl->parseCurrentBlock();
        }

        foreach ($options as $option) {
            $voted = in_array($option->getId(), $chosen, true);
            $result_class = '';
            $result_label = '';
            if ($has_answered && $voted) {
                $result_class = $option->isCorrect() ? ' is-correct' : ' is-wrong';
                $result_label = $plugin->txt($option->isCorrect() ? 'challenge_option_correct' : 'challenge_option_wrong');
            } elseif ($has_answered && $option->isCorrect()) {
                $result_class = ' is-solution';
                $result_label = $plugin->txt('challenge_option_solution');
            }

            if ($result_label !== '') {
                $tpl->setCurrentBlock('option_result');
                $tpl->setVariable('RESULT_LABEL', $result_label);
                $tpl->parseCurrentBlock();
            }

            $tpl->setCurrentBlock('option');
            $tpl->setVariable('TYPE', $is_multi ? 'checkbox' : 'radio');
            $tpl->setVariable('OPTION_ID', (string) $option->getId());
            $tpl->setVariable('OPTION_LETTER', $option->getCipher());
            $tpl->setVariable('TITLE', $option->getTextForPresentation());
            $tpl->setVariable('CHECKED', $voted ? 'checked' : '');
            $tpl->setVariable('DISABLED', 'disabled');
            $tpl->setVariable('RESULT_CLASS', $result_class);
            $tpl->parseCurrentBlock();
        }

        // Nothing can be sent any more: the buttons are hidden by the closed-question style.
        $tpl->setVariable('ACTION', '#');
        $tpl->setVariable('LOCKED_CLASS', ' is-locked xlvo-challenge-closed');
        $tpl->setVariable('VOTER_HINT', $question->getQuestionTypeLabel());
        $tpl->setVariable('SUBMIT_LABEL', $plugin->txt('voter_submitted'));
        $tpl->setVariable('SUBMIT_DISABLED', 'disabled');
        $tpl->setVariable('CHANGE_DISABLED', 'disabled');

        return $tpl->get();
    }

    public function getMobileHTML(): string
    {
        return $this->getVoterFormHTML();
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