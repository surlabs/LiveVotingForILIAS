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

namespace LiveVoting\UI;

use Closure;
use Exception;
use ilCtrlException;
use ilException;
use ilLinkButton;
use ilLiveVotingPlugin;
use ilObjLiveVotingGUI;
use ilSelectInputGUI;
use ilSubmitButton;
use LiveVoting\legacy\LiveVotingResultsTableGUI;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingRound;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingResultsUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilObjLiveVotingGUI: ilObjPluginGUI
 */
class LiveVotingResultsUI
{
    private LiveVoting $liveVoting;
    private ?LiveVotingRound $round;

    /**
     * @throws LiveVotingException
     */
    public function __construct(LiveVoting $liveVoting)
    {
        $this->liveVoting = $liveVoting;
        $this->buildRound();
    }

    /**
     * @throws LiveVotingException
     */
    private function buildRound(): void
    {
        if (isset($_GET['round_id'])) {
            $this->round = new LiveVotingRound((int)$_GET['round_id']);
        } else {
            $this->round = LiveVotingRound::getLatestRound($this->liveVoting->getId());
        }
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     * @throws ilException
     * @throws Exception
     */
    public function showResults(ilObjLiveVotingGUI $parent): string
    {
        global $DIC;

        $this->buildToolbar();

        $liveVotingTableGUI = new LiveVotingResultsTableGUI($parent, 'results');
        $this->buildFilters($liveVotingTableGUI, $this->round->getId(), $this->liveVoting->getQuestions());
        $liveVotingTableGUI->initFilter();
        $liveVotingTableGUI->buildData($this->liveVoting->getId(), $this->round->getId());

        return $liveVotingTableGUI->getHTML();
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    private function buildToolbar(): void
    {
        global $DIC;

        $button = ilLinkButton::getInstance();
        $button->setUrl($DIC->ctrl()->getLinkTargetByClass("ilObjLiveVotingGUI", "confirmNewRound"));
        $button->setCaption(ilLiveVotingPlugin::getInstance()->txt("new_round"), false);
        $DIC->toolbar()->addButtonInstance($button);

        $DIC->toolbar()->addSeparator();

        $rounds = LiveVotingRound::getRounds($this->liveVoting->getId());
        if (!empty($rounds)) {
            $table_selection = new ilSelectInputGUI('', 'round_id');
            $options = array();
            foreach ($rounds as $round) {
                $options[$round->getId()] = $round->getTitle();
            }
            $table_selection->setOptions($options);
            $table_selection->setValue($this->round->getId());

            $DIC->toolbar()->setFormAction($DIC->ctrl()->getFormActionByClass("ilObjLiveVotingGUI", "changeRound"));
            $DIC->toolbar()->addText(ilLiveVotingPlugin::getInstance()->txt("common_round"));
            $DIC->toolbar()->addInputItem($table_selection);

            $button = ilSubmitButton::getInstance();
            $button->setCaption(ilLiveVotingPlugin::getInstance()->txt('common_change'), false);
            $button->setCommand("changeRound");
            $DIC->toolbar()->addButtonInstance($button);
        }
    }

    /**
     * @param LiveVotingResultsTableGUI $table
     * @param int $round_id
     * @param array $question_list
     *
     * @throws LiveVotingException
     * @throws ilCtrlException
     * @throws Exception
     */
    public static function buildFilters(LiveVotingResultsTableGUI &$table, int $round_id, array $question_list): void
    {
        global $DIC;

        $plugin = ilLiveVotingPlugin::getInstance();
        $filter = new ilSelectInputGUI($plugin->txt("common_participant"), "participant");

        $votes = LiveVotingVote::getVotesForRound($round_id);
        $options = array(0 => $plugin->txt("common_all"));
        foreach ($votes as $vote) {
            $options[$vote->getUserIdentifier() ?? $vote->getUserId()] = $vote->getParticipantName($table->getPlayerId());
        }
        $filter->setOptions($options);
        $table->addFilterItem($filter);
        $filter->readFromSession();

        $titles = array(
            0 => $plugin->txt("common_all")
        );
        $questions = array(
            0 => $plugin->txt("common_all")
        );
        $closure = self::getShortener();

        foreach ($question_list as $question) {
            $titles[$question->getId()] = $question->getTitle();
            $questions[$question->getId()] = $question->getQuestion();
        }

        // Title
        $filter = new ilSelectInputGUI($plugin->txt("voting_title"), "voting_title");
        array_walk($titles, $closure);
        $filter->setOptions($titles);
        $table->addFilterItem($filter);
        $filter->readFromSession();

        // Question
        $filter = new ilSelectInputGUI($plugin->txt("common_question"), "voting");
        array_walk($questions, $closure);
        $filter->setOptions($questions);
        $table->addFilterItem($filter);
        $filter->readFromSession();

        // Set post var round_id
        $DIC->ctrl()->setParameterByClass("ilObjLiveVotingGUI", "round_id", $round_id);

        // Read values
        $table->setFormAction($DIC->ctrl()->getFormActionByClass("ilObjLiveVotingGUI", "applyFilter"));
    }

    public static function getShortener($length = 40): Closure
    {
        return function (&$question) use ($length) {
            $qs = nl2br($question, false);
            $qs = strip_tags($qs);

            $question = strlen($qs) > $length ? substr($qs, 0, $length) . "..." : $qs;

            return $question;
        };
    }
}