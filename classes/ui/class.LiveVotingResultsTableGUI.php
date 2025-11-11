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

use Generator;
use ilCtrl;
use ilCtrlException;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLiveVotingPlugin;
use ilObjLiveVotingGUI;
use ilUIService;
use LiveVoting\objects\modes\LiveVotingMode;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingResultsTableGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingResultsTableGUI implements DataRetrieval
{
    private ilObjLiveVotingGUI $parent_obj;
    private string $parent_cmd;
    private Factory $factory;
    private Renderer $renderer;
    private ilCtrl $ctrl;
    private ilUIService $ui_service;
    private ilLiveVotingPlugin $plugin;
    private array $records;
    private int $player_id;
    private $request;

    public function __construct(ilObjLiveVotingGUI $a_parent_obj, string $parent_cmd)
    {
        global $DIC;

        $this->parent_obj = $a_parent_obj;
        $this->parent_cmd = $parent_cmd;

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->ctrl = $DIC->ctrl();
        $this->ui_service = $DIC->uiService();

        $this->plugin = ilLiveVotingPlugin::getInstance();

        $this->player_id = $a_parent_obj->getObject()->getLiveVoting()->getPlayer()->getId();

        $this->request = $DIC->http()->request();
    }

    public function getRows(DataRowBuilder $row_builder, array $visible_column_ids, Range $range, Order $order, ?array $filter_data, ?array $additional_parameters): Generator
    {
        foreach ($this->records as $record) {
            yield $row_builder->buildDataRow((string) $record['id'], $record);
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return count($this->records);
    }

    /**
     * @throws ilCtrlException
     */
    public function getHtml(): string
    {
        $table = $this->factory->table()->data(
            $this->plugin->txt('results_title'),
            $this->getColumns(),
            $this
        )->withRequest($this->request);

        $participants = [];
        $voting_titles = [];
        $votings = [];

        foreach ($this->records as $record) {
            $participants[$record['user_id']] = $record['participant'];
            $voting_titles[$record['voting_id']] = $record['title'];
            $votings[$record['voting_id']] = $record['title'];
        }

        $filter_inputs = [
            "participant" => $this->factory->input()->field()->select($this->plugin->txt('common_user'), $participants),
            "voting_title" => $this->factory->input()->field()->select($this->plugin->txt('voting_title'), $voting_titles),
            "voting" => $this->factory->input()->field()->select($this->plugin->txt('voting_title'), $votings),
        ];

        $active = array_fill(0, count($filter_inputs), true);

        $filter = $this->ui_service->filter()->standard(
            'results_table',
            $this->ctrl->getLinkTarget($this->parent_obj, $this->parent_cmd),
            $filter_inputs,
            $active,
            true
        );

        return $this->renderer->render($filter) . $this->renderer->render($table);
    }

    private function getColumns(): array
    {
        $columns = [
            'position' => $this->factory->table()->column()->text($this->plugin->txt('common_position')),
            'participant' => $this->factory->table()->column()->text($this->plugin->txt('common_user')),
            'question_type' => $this->factory->table()->column()->text($this->plugin->txt('voting_type')),
            'title' => $this->factory->table()->column()->text($this->plugin->txt('voting_title')),
            'question' => $this->factory->table()->column()->text($this->plugin->txt('common_question')),
            'answer' => $this->factory->table()->column()->text($this->plugin->txt('common_answer')),
        ];

        if ($this->parent_obj->getObject()->getLiveVoting()->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
            $columns['points'] = $this->factory->table()->column()->text($this->plugin->txt('common_points'));
        }


        return $columns;
    }

    /**
     * @throws LiveVotingException
     */
    public function buildData(int $obj_id, int $round_id): void
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
                    "points" => LiveVotingPlayer::getPlayerPoints($vote->getUserIdType() == 1 ? (string) $vote->getUserId() : (string) $vote->getUserIdentifier(), $obj_id, $question->getId(), $round_id)
                );
            }
        }

        $this->records = $a_data;
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
}