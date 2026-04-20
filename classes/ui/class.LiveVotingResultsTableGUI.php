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
    private int $player_id;
    private $request;
    private int $obj_id;
    private int $round_id;

    public function __construct(ilObjLiveVotingGUI $a_parent_obj, string $parent_cmd, int $obj_id, int $round_id)
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

        $this->obj_id = $obj_id;
        $this->round_id = $round_id;
    }

    /**
     * @throws LiveVotingException
     */
    public function getRows(DataRowBuilder $row_builder, array $visible_column_ids, Range $range, Order $order, ?array $filter_data, ?array $additional_parameters): Generator
    {
        $records = $this->getRecords($filter_data, $order);

        foreach ($records as $record) {
            yield $row_builder->buildDataRow((string) $record['id'], $record);
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        $records = $this->getRecords($filter_data);

        return count($records);
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function getHtml(): string
    {
        $this->ctrl->setParameterByClass(ilObjLiveVotingGUI::class, 'round_id', $this->round_id);

        $participants = [];
        $voting_titles = [];

        $records = $this->getRecords();

        foreach ($records as $record) {
            $participants[$record['user_id'] != 0 ? $record['user_id'] : $record['user_identifier']] = $record['participant'];
            $voting_titles[$record['voting_id']] = $record['title'];
        }

        $filter_inputs = [
            "participant" => $this->factory->input()->field()->select($this->plugin->txt('common_user'), $participants),
            "voting_title" => $this->factory->input()->field()->select($this->plugin->txt('voting_title'), $voting_titles),
        ];

        $active = array_fill(0, count($filter_inputs), true);

        $filter = $this->ui_service->filter()->standard(
            'results_table',
            $this->ctrl->getLinkTarget($this->parent_obj, $this->parent_cmd),
            $filter_inputs,
            $active,
            true
        );

        $table = $this->factory->table()->data(
            $this->plugin->txt('results_title'),
            $this->getColumns(),
            $this
        )->withRequest($this->request)->withFilter($this->ui_service->filter()->getData($filter));

        return $this->renderer->render($filter) . $this->renderer->render($table);
    }

    /**
     * @throws LiveVotingException
     */
    public function getExportRecords(): array
    {
        return $this->getRecords();
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

    /**
     * @throws LiveVotingException
     */
    private function getRecords(?array $filter_data = [], ?Order $order = null): array
    {
        $a_data = array();

        $a_questions = array();
        $questions = array();

        if (isset($filter_data['voting_title']) && $filter_data['voting_title'] != "") {
            $a_questions[] = LiveVotingQuestion::loadQuestionById((int)$filter_data['voting_title']);
        } else {
            $a_questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->obj_id);
        }

        foreach ($a_questions as $question) {
            $questions[$question->getId()] = $question;
        }

        $participant = isset($filter_data['participant']) && $filter_data['participant'] != "" ? $filter_data['participant'] : null;
        $votes = LiveVotingVote::getVotesForRound($this->round_id, true, $participant);
        $all_votes = LiveVotingVote::getVotesForRound($this->round_id, false, $participant);

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
                    "round_id" => $this->round_id,
                    "id" => $vote->getId(),
                    "points" => LiveVotingPlayer::getPlayerPoints($vote->getUserIdType() == 1 ? (string) $vote->getUserId() : (string) $vote->getUserIdentifier(), $this->obj_id, $question->getId(), $this->round_id)
                );
            }
        }

        if (isset($order)) {
            $fields = $order->get();

            usort($a_data, function ($a, $b) use ($fields) {
                foreach ($fields as $field => $direction) {
                    if ($a[$field] == $b[$field]) {
                        continue;
                    }

                    if ($direction === Order::ASC) {
                        return ($a[$field] < $b[$field]) ? -1 : 1;
                    } else {
                        return ($a[$field] > $b[$field]) ? -1 : 1;
                    }
                }

                return 0;
            });
        }

        return $a_data;
    }
}
