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

use Exception;
use ilAdvancedSelectionListGUI;
use ilCheckboxInputGUI;
use ilCtrlException;
use ilException;
use ilFormPropertyGUI;
use ilLiveVotingPlugin;
use ilObjLiveVotingAccess;
use ilObjLiveVotingGUI;
use ilSelectInputGUI;
use ilTable2GUI;
use ilTextInputGUI;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use ilLegacyFormElementsUtil;

/**
 * Class LiveVotingTableGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class liveVotingTableGUI extends ilTable2GUI
{


    const PLUGIN_CLASS_NAME = ilLiveVotingPlugin::class;
    const TBL_ID = 'tbl_xlvo';
    const LENGTH = 100;
    /**
     * @var ilObjLiveVotingGUI
     */
    protected ilObjLiveVotingGUI $voting_gui;
    /**
     * @var array
     */
    protected array $filter = array();
    /**
     * @var ilObjLiveVotingAccess
     */
    protected ilObjLiveVotingAccess $access;
    /**
     * @var ilLiveVotingPlugin
     */
    protected ilLiveVotingPlugin $pl;


    /**
     * @throws ilException
     * @throws LiveVotingException
     * @throws Exception
     */
    public function __construct(ilObjLiveVotingGUI $a_parent_obj, $a_parent_cmd)
    {
        global $DIC;

        $this->voting_gui = $a_parent_obj;
        $this->access = new ilObjLiveVotingAccess();
        $this->pl = ilLiveVotingPlugin::getInstance();

        $DIC->ui()->mainTemplate()->addJavaScript($this->pl->getDirectory() . "/templates/js/libs/sortable.min.js");

        $this->setId(self::TBL_ID);
        $this->setPrefix(self::TBL_ID);
        $this->setFormName(self::TBL_ID);
        $DIC->ctrl()->saveParameter($a_parent_obj, $this->getNavParameter());

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->setRowTemplate('tpl.tbl_voting.html', $this->pl->getDirectory());
        $this->setExternalSorting(true);
        $this->setExternalSegmentation(true);
        $this->initColums();
        $this->addFilterItems();
        $this->parseData();

        $this->setFormAction($DIC->ctrl()->getFormAction($a_parent_obj));
        $this->addCommandButton('saveSorting', $this->txt('voting_save_sorting'));

        $this->setFilterCommand('applyFilterQuestions');
        $this->setResetCommand('resetFilterQuestions');
    }


    /**
     * @param string $key
     *
     * @return string
     */
    protected function txt(string $key): string
    {
        return $this->pl->txt($key);
    }


    /**
     * @throws Exception
     */
    protected function addFilterItems(): void
    {
        $title = new ilTextInputGUI($this->txt('voting_title'), 'title');
        $this->addAndReadFilterItem($title);

        $question = new ilTextInputGUI($this->txt('voting_question'), 'question');
        $this->addAndReadFilterItem($question);

        $status = new ilSelectInputGUI($this->txt('voting_status'), 'voting_status');
        $status_options = array(
            -1 => $this->txt('common_all'),
            1 => $this->txt('voting_status_1'),
            5 => $this->txt('voting_status_5'),
            2 => $this->txt('voting_status_2'),
        );
        $status->setOptions($status_options);
        $this->addAndReadFilterItem($status);

        $type = new ilSelectInputGUI($this->txt('voting_type'), 'voting_type');
        $type_options = array(
            -1 => $this->txt('common_all'),
        );

        foreach (LiveVotingQuestion::QUESTION_TYPES_IDS as $qtype) {
            $type_options[$qtype] = $this->txt('voting_type_' . $qtype);
        }

        $type->setOptions($type_options);
        $this->addAndReadFilterItem($type);
    }


    /**
     * @param ilFormPropertyGUI $item
     * @throws Exception
     */
    protected function addAndReadFilterItem(ilFormPropertyGUI $item): void
    {
        $this->addFilterItem($item);
        $item->readFromSession();
        if ($item instanceof ilCheckboxInputGUI) {
            $this->filter[$item->getPostVar()] = $item->getChecked();
        } else {
            $this->filter[$item->getPostVar()] = $item->getValue();
        }
    }


    /**
     * @param array $a_set
     * @throws LiveVotingException
     */
    protected function fillRow(array $a_set): void
    {
        $question = LiveVotingQuestion::loadQuestionById((int)$a_set['id']);
        $this->tpl->setVariable('TITLE', $question->getTitle());
        //->tpl->setVariable('DESCRIPTION', $this->shorten($question->getQuestion()));

        //$question = strip_tags("QUESTION TEST");

        //$question = $this->shorten($question);
        $this->tpl->setVariable('QUESTION', strip_tags($this->shorten($question->getQuestion())));
        $this->tpl->setVariable('TYPE', $this->txt('voting_type_' . $a_set['voting_type']));

        $voting_status = $this->getVotingStatus("STATUS");
        //		$this->tpl->setVariable('STATUS', $voting_status); // deactivated at the moment

        $this->tpl->setVariable('ID', $question->getId());

        $this->addActionMenu($question);
    }


    protected function initColums()
    {
        $this->addColumn('', 'position', '20px');
        $this->addColumn($this->txt('voting_title'));
        $this->addColumn($this->txt('voting_question'));
        $this->addColumn($this->txt('voting_type'));
        //		$this->addColumn($this->txt('status'));
        $this->addColumn($this->txt('voting_actions'), '', '150px');
    }


    /**
     * @param LiveVotingQuestion $question
     * @throws ilCtrlException
     * @throws JsonException
     */
    protected function addActionMenu(LiveVotingQuestion $question)
    {
        global $DIC;
        $current_selection_list = new ilAdvancedSelectionListGUI();
        $current_selection_list->setListTitle($this->txt('common_actions'));
        $current_selection_list->setId('xlvo_actions_' . $question->getId());
        $current_selection_list->setUseImages(false);

        $DIC->ctrl()->setParameter($this->voting_gui, 'question_id', $question->getId());
        if ($this->access->hasWriteAccess()) {
            $current_selection_list->addItem($this->txt('voting_edit'), 'edit', $DIC->ctrl()
                ->getLinkTarget($this->voting_gui, 'edit'));
            $current_selection_list->addItem($this->txt('voting_reset'), 'confirmResetQuestion', $DIC->ctrl()
                ->getLinkTarget($this->voting_gui, 'confirmResetQuestion'));
            $current_selection_list->addItem($this->txt('voting_duplicate'), 'duplicateQuestion', $DIC->ctrl()
                ->getLinkTarget($this->voting_gui, 'duplicateQuestion'));
            $current_selection_list->addItem($this->txt('voting_duplicateToAnotherObject'), 'duplicateQuestionToAnotherObjectSelect', $DIC->ctrl()
                ->getLinkTarget($this->voting_gui, 'duplicateQuestionToAnotherObjectSelect'));
            $current_selection_list->addItem($this->txt('voting_delete'), 'confirmDeleteQuestion', $DIC->ctrl()
                ->getLinkTarget($this->voting_gui, 'confirmDeleteQuestion'));
        }
        $current_selection_list->getHTML();
        $this->tpl->setVariable('ACTIONS', $current_selection_list->getHTML());
    }


    /**
     * @throws LiveVotingException
     */
    protected function parseData()
    {
        // Filtern
        $this->determineOffsetAndOrder();
        $this->determineLimit();

        $database = new LiveVotingDatabase();
        $sorting_column = $this->getOrderField() ? $this->getOrderField() : 'position';
        $offset = $this->getOffset() ? $this->getOffset() : 0;

        $sorting_direction = $this->getOrderDirection();
        $num = $this->getLimit();


        $where = array(
            "obj_id" => $this->voting_gui->getObjId(),
        );

        if (isset($this->filter['voting_status']) && $this->filter['voting_status'] != -1 && $this->filter['voting_status'] != "") {
            $where['voting_status'] = $this->filter['voting_status'];
        }

        if (isset($this->filter['voting_type']) && $this->filter['voting_type'] != -1 && $this->filter['voting_type'] != "") {
            $where['voting_type'] = $this->filter['voting_type'];
        }

        $collection = $database->select("rep_robj_xlvo_voting_n", $where, null, "ORDER BY " . $sorting_column . " " . $sorting_direction . " LIMIT " . $offset . ", " . $num);

        if (isset($this->filter['question']) && isset($this->filter['title']) && $this->filter['title'] != "" || (isset($this->filter['question']) && $this->filter['question'] != "")) {
            $filtered = array();

            if (isset($this->filter['title']) && $this->filter['title'] != "") {
                foreach ($collection as $item) {
                    if (strpos($item['title'], $this->filter['title']) !== false) {
                        $filtered[] = $item;
                    }
                }
            }

            if (isset($this->filter['question']) && $this->filter['question'] != "") {
                foreach ($collection as $item) {
                    if (strpos($item['question'], $this->filter['question']) !== false) {
                        $filtered[] = $item;
                    }
                }
            }

            $collection = $filtered;
        }

        $this->setData($collection);
    }


    /**
     * @param $voting_status
     *
     * @return string
     */
    protected function getVotingStatus($voting_status)
    {
        return $this->txt('status_' . $voting_status);
    }


    /**
     * @param string $question
     *
     * @return string
     */
    protected function shorten($question)
    {
        return strlen($question) > self::LENGTH ? substr($question, 0, self::LENGTH) . "..." : $question;
    }
}
