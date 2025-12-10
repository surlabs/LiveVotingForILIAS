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
use ILIAS\Data\URI;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\UI\Component\Table\OrderingBinding;
use ILIAS\UI\Component\Table\OrderingRowBuilder;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\UI\URLBuilder;
use ilLiveVotingPlugin;
use ilObjLiveVotingGUI;
use ilUIService;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;

/**
 * Class LiveVotingTableGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingTableGUI  implements OrderingBinding
{
    private ilObjLiveVotingGUI $parent_obj;
    private string $parent_cmd;
    private Factory $factory;
    private Renderer $renderer;
    private ilCtrl $ctrl;
    private ilUIService $ui_service;
    private ilLiveVotingPlugin $plugin;
    private $request;
    private WrapperFactory $wrapper;
    private \ILIAS\Refinery\Factory $refinery;
    private array $records;

    /**
     * @throws LiveVotingException
     */
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

        $this->request = $DIC->http()->request();

        $this->wrapper = $DIC->http()->wrapper();
        $this->refinery = $DIC->refinery();

        $this->parseData();
    }

    public function getRows(OrderingRowBuilder $row_builder, array $visible_column_ids): Generator
    {
        foreach ($this->records as $record) {
            $record['question'] = htmlentities($this->shorten($record['question']));
            $record['type'] = $this->plugin->txt('voting_type_' . $record['voting_type']);

            yield $row_builder->buildOrderingRow((string) $record['id'], $record);
        }
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function getHtml(): string
    {
        $table = $this->factory->table()->ordering(
            "",
            $this->getColumns(),
            $this,
            (new URI((string) $this->request->getUri()))->withParameter('saveOrder', 1)
        )->withRequest($this->request)
            ->withActions($this->getActions());

        if ($this->request->getMethod() == "POST" && $this->wrapper->query()->has('saveOrder') && $this->wrapper->query()->retrieve('saveOrder', $this->refinery->kindlyTo()->int()) == 1) {
            $this->saveOrder($table->getData());
        }

        $filter_inputs = [
            "title" => $this->factory->input()->field()->text($this->plugin->txt('voting_title')),
            "question" => $this->factory->input()->field()->text($this->plugin->txt('voting_question')),
            "type" => $this->factory->input()->field()->select(
                $this->plugin->txt('voting_type'),
                [
                    -1 => $this->plugin->txt('common_all'),
                    1 => $this->plugin->txt('voting_status_1'),
                    5 => $this->plugin->txt('voting_status_5'),
                    2 => $this->plugin->txt('voting_status_2'),
                ]
            ),
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
        return [
            'title' => $this->factory->table()->column()->text($this->plugin->txt('voting_title')),
            'question' => $this->factory->table()->column()->text($this->plugin->txt('voting_question')),
            'type' => $this->factory->table()->column()->text($this->plugin->txt('voting_type')),
        ];
    }

    /**
     * @throws LiveVotingException
     */
    private function parseData(): void
    {
        $database = new LiveVotingDatabase();

        $where = array(
            "obj_id" => $this->parent_obj->getObjId(),
        );

        if (isset($this->filter['voting_type']) && $this->filter['voting_type'] != -1 && $this->filter['voting_type'] != "") {
            $where['voting_type'] = $this->filter['voting_type'];
        }

        $collection = $database->select("rep_robj_xlvo_voting_n", $where, null, "ORDER BY position ASC");

        if (isset($this->filter['question']) && isset($this->filter['title']) && $this->filter['title'] != "" || (isset($this->filter['question']) && $this->filter['question'] != "")) {
            $filtered = array();

            if (isset($this->filter['title']) && $this->filter['title'] != "") {
                foreach ($collection as $item) {
                    if (str_contains($item['title'], $this->filter['title'])) {
                        $filtered[] = $item;
                    }
                }
            }

            if (isset($this->filter['question']) && $this->filter['question'] != "") {
                foreach ($collection as $item) {
                    if (str_contains($item['question'], $this->filter['question'])) {
                        $filtered[] = $item;
                    }
                }
            }

            $collection = $filtered;
        }

        $this->records = $collection;
    }

    protected function shorten($question): string
    {
        return strlen($question) > 100 ? substr($question, 0, 100) . "..." : $question;
    }

    /**
     * @throws ilCtrlException
     */
    private function getActions(): array
    {
        $df = new \ILIAS\Data\Factory();
        $here_uri = $df->uri($this->request->getUri()->__toString());
        $url_builder = new URLBuilder($here_uri);

        $query_params_namespace = ['schedule_table'];
        list($url_builder, $id_token, $action_token) = $url_builder->acquireParameters(
            $query_params_namespace,
            "relay_param",
            "action"
        );

        $query = $this->wrapper->query();
        if ($query->has($action_token->getName())) {
            $action = $query->retrieve($action_token->getName(), $this->refinery->to()->string());
            $ids = $query->retrieve($id_token->getName(), $this->refinery->custom()->transformation(fn($v) => $v));
            $id = $ids[0] ?? null;

            $this->ctrl->setParameter($this->parent_obj, 'question_id', $id);
            $this->ctrl->redirect($this->parent_obj, $action);
        }

        return [
            $this->factory->table()->action()->single(
                $this->plugin->txt('voting_edit'),
                $url_builder->withParameter($action_token, "edit"),
                $id_token
            ),
            $this->factory->table()->action()->single(
                $this->plugin->txt('voting_reset'),
                $url_builder->withParameter($action_token, "confirmResetQuestion"),
                $id_token
            ),
            $this->factory->table()->action()->single(
                $this->plugin->txt('voting_duplicate'),
                $url_builder->withParameter($action_token, "duplicateQuestion"),
                $id_token
            ),
            $this->factory->table()->action()->single(
                $this->plugin->txt('voting_duplicateToAnotherObject'),
                $url_builder->withParameter($action_token, "duplicateQuestionToAnotherObjectSelect"),
                $id_token
            ),
            $this->factory->table()->action()->single(
                $this->plugin->txt('voting_delete'),
                $url_builder->withParameter($action_token, "confirmDeleteQuestion"),
                $id_token
            ),
        ];
    }

    private function saveOrder(array $data)
    {
        global $DIC;

        $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->parent_obj->getObjId());

        foreach ($questions as $question) {
            $question->setPosition(array_search($question->getId(), $data) + 1);
            $question->save();
        }

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->plugin->txt('sorting_msg_saved'));
    }
}