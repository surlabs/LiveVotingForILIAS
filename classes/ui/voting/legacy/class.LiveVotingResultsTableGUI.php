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

namespace LiveVoting\legacy;

use Closure;
use ilCSVWriter;
use ilCtrlException;
use ilException;
use ilLiveVotingPlugin;
use ilObjLiveVotingGUI;
use ilTable2GUI;
use LiveVoting\objects\modes\LiveVotingMode;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\votings\LiveVotingParticipant;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingResultsTableGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingResultsTableGUI extends ilTable2GUI
{
    /**
     * @var array
     */
    protected array $filter;
    /**
     * @var bool
     */
    protected bool $showHistory = false;


    protected ?object $parent_obj;
    private int $player_id;


    /**
     * LiveVotingResultsTableGUI constructor.
     *
     * @param ilObjLiveVotingGUI $a_parent_obj
     * @param string $a_parent_cmd
     * @param bool $show_history
     * @throws ilException
     */
    public function __construct(ilObjLiveVotingGUI $a_parent_obj, $a_parent_cmd, $show_history = false)
    {
        $this->setId('xlvo_results');
        parent::__construct($a_parent_obj, $a_parent_cmd);
        $this->setRowTemplate('tpl.results_list.html', ilLiveVotingPlugin::getInstance()->getDirectory());
        $this->setTitle(ilLiveVotingPlugin::getInstance()->txt('results_title'));
        $this->player_id = $a_parent_obj->getObject()->getLiveVoting()->getPlayer()->getId();
        $this->showHistory = $show_history;
        $this->setExportFormats(array(self::EXPORT_CSV));

        $this->buildColumns();
    }


    protected function buildColumns(): void
    {
        $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('common_position'), 'position', '10%');
        $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('common_user'), 'user', '10%');
        $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('voting_type'), 'question_type', '10%');
        $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('voting_title'), 'title', '15%');
        $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('common_question'), 'question', '20%');
        $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('common_answer'), 'answer', 'auto');
        if ($this->isShowHistory()) {
            $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('common_history'), "", 'auto');
        }
        if ($this->parent_obj->getObject()->getLiveVoting()->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
            $this->addColumn(ilLiveVotingPlugin::getInstance()->txt('common_points'), 'points', 'auto');
        }
    }


    /**
     * @param $obj_id
     * @param $round_id
     * @throws LiveVotingException
     */
    public function buildData($obj_id, $round_id): void
    {
        $a_data = array();

        $a_questions = array();
        $questions = array();

        if (isset($this->filter['voting']) && $this->filter['voting'] != "") {
            $a_questions[] = LiveVotingQuestion::loadQuestionById((int)$this->filter['voting']);
        } else if (isset($this->filter['voting_title']) && $this->filter['voting_title'] != "") {
            $a_questions[] = LiveVotingQuestion::loadQuestionById((int)$this->filter['voting_title']);
        } else {
            $a_questions = LiveVotingQuestion::loadAllQuestionsByObjectId($obj_id);
        }

        foreach ($a_questions as $question) {
            $questions[$question->getId()] = $question;
        }

        $participant = isset($this->filter['participant']) && $this->filter['participant'] != "" ? $this->filter['participant'] : null;
        $votes = LiveVotingVote::getVotesForRound($round_id, true, $participant);
        $all_votes = LiveVotingVote::getVotesForRound($round_id, false, $participant);

        foreach ($votes as $vote) {
            foreach ($questions as $question) {
                $answers = $this->getVotesForQuestion($all_votes, $question->getId(), $vote);

                $a_data[] = array(
                    "position" => $question->getPosition(),
                    "participant" => $vote->getParticipantName($this->player_id),
                    "user_id" => $vote->getUserId(),
                    "user_identifier" => $vote->getUserIdentifier(),
                    "question_type" => $question->getQuestionTypeId(),
                    "title" => $question->getTitle(),
                    "question" => $question->getQuestion(),
                    "answer" => $question->getVotesRepresentation($answers),
                    "answer_ids" => $this->concatAnswersIds($answers),
                    "voting_id" => $question->getId(),
                    "round_id" => $round_id,
                    "id" => $vote->getId(),
                    "points" => LiveVotingPlayer::getPlayerPoints($vote->getUserIdType() == 1 ? $vote->getUserId() : $vote->getUserIdentifier(), $obj_id, $question->getId(), $round_id)
                );
            }
        }

        $this->setData($a_data);
    }


    /**
     * @param array $a_set
     * @throws ilCtrlException
     */
    public function fillRow(array $a_set): void
    {
        global $DIC;

        $this->tpl->setVariable("POSITION", $a_set['position']);
        $this->tpl->setVariable("USER", $a_set['participant']);
        $this->tpl->setVariable("QUESTION_TYPE", ilLiveVotingPlugin::getInstance()->txt("voting_type_" . $a_set['question_type']));
        $this->tpl->setVariable("QUESTION", $this->shorten($a_set['question']));
        $this->tpl->setVariable("TITLE", $this->shorten($a_set['title']));
        $this->tpl->setVariable("ANSWER", $this->shorten($a_set['answer'], 100));
        if ($this->parent_obj->getObject()->getLiveVoting()->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
            $this->tpl->setVariable("POINTS", $a_set['points']);
        }
        if ($this->isShowHistory()) {
            $this->tpl->setVariable("ACTION", ilLiveVotingPlugin::getInstance()->txt("common_show_history"));
            $DIC->ctrl()->setParameter($this->parent_obj, 'round_id', $a_set['round_id']);
            $DIC->ctrl()->setParameter($this->parent_obj, 'user_id', $a_set['user_id']);
            $DIC->ctrl()->setParameter($this->parent_obj, 'user_identifier', $a_set['user_identifier']);
            $DIC->ctrl()->setParameter($this->parent_obj, 'voting_id', $a_set['voting_id']);
            $this->tpl->setVariable("ACTION_URL", $DIC->ctrl()->getLinkTarget($this->parent_obj, "showHistory"));
        }
    }


    public function initFilter(): void
    {
        $this->filter['participant'] = $this->getFilterItemByPostVar('participant')->getValue();
        $this->filter['voting'] = $this->getFilterItemByPostVar('voting')->getValue();
        $this->filter['voting_title'] = $this->getFilterItemByPostVar('voting_title')->getValue();
    }


    /**
     * @return bool
     */
    public function isShowHistory(): bool
    {
        return $this->showHistory;
    }


    /**
     * @param bool $showHistory
     */
    public function setShowHistory(bool $showHistory): void
    {
        $this->showHistory = $showHistory;
    }


    /**
     * @param object $a_csv
     *
     * @return void
     */
    protected function fillHeaderCSV($a_csv): void
    {
        // return null;
    }


    /**
     * @return array
     */
    protected function getCSVCols(): array
    {
        return array(
            'participant' => 'participant',
            'title' => 'title',
            'question' => 'question',
            'answer' => 'answer',
        );
    }


    /**
     * @param ilCSVWriter $a_csv
     * @param array $a_set
     */
    protected function fillRowCSV(ilCSVWriter $a_csv, array $a_set): void
    {
        $a_set = array_intersect_key($a_set, $this->getCSVCols());
        array_walk($a_set, function (&$value) {
            //			$value = mb_convert_encoding($value, 'ISO-8859-1');
            //			$value = mb_convert_encoding($value, "UTF-8", "UTF-8");
            //			$value = utf8_encode($value);
            //			$value = iconv('UTF-8', 'macintosh', $value);
        });
        parent::fillRowCSV($a_csv, $a_set);
    }


    /**
     * @param     $question
     * @param int $length
     *
     * @return string
     */
    protected function shorten($question, int $length = 40): string
    {

        $closure = $this->getShortener($length);

        return $closure($question);
    }

    public function getShortener($length = 40): Closure
    {
        return function (&$question) use ($length) {
            $qs = nl2br($question, false);
            $qs = strip_tags($qs);

            $question = strlen($qs) > $length ? substr($qs, 0, $length) . "..." : $qs;

            return $question;
        };
    }

    private function getVotesForQuestion(array $all_votes, int $question_id, LiveVotingVote $vote): array
    {
        $votes = array();

        foreach ($all_votes as $v) {
            if ($v->getVotingId() == $question_id && ($v->getUserId() == $vote->getUserId() && $v->getUserIdentifier() == $vote->getUserIdentifier())) {
                $votes[] = $v;
            }
        }

        return $votes;
    }

    private function concatAnswersIds(array $answers): string
    {
        $answers_ids = array();

        foreach ($answers as $answer) {
            $answers_ids[] = $answer->getId();
        }

        return implode(",", $answers_ids);
    }
    
    public function getPlayerId(): int
    {
        return $this->player_id;
    }
}