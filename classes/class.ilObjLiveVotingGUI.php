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
 * info@surlabs.esr
 *
 */

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use LiveVoting\legacy\LiveVotingResultsTableGUI;
use LiveVoting\legacy\liveVotingTableGUI;
use LiveVoting\platform\ilias\LiveVotingInitialisation;
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\UI\LiveVotingChoicesUI;
use LiveVoting\UI\LiveVotingCorrectOrderUI;
use LiveVoting\UI\LiveVotingFreeInputUI;
use LiveVoting\UI\LiveVotingManageUI;
use LiveVoting\UI\LiveVotingPrioritiesUI;
use LiveVoting\UI\LiveVotingRangeUI;
use LiveVoting\UI\LiveVotingChoicesCMUI;
use LiveVoting\UI\LiveVotingResultsUI;
use LiveVoting\UI\LiveVotingSettingsUI;
use LiveVoting\UI\LiveVotingUI;
use LiveVoting\Utils\LiveVotingJs;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingCategory;
use LiveVoting\votings\LiveVotingParticipant;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingRound;
use LiveVoting\votings\LiveVotingVote;
use LiveVoting\votings\LiveVotingVoter;
use LiveVoting\objects\modes\LiveVotingMode;

/**
 * Class ilObjLiveVotingGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy ilObjLiveVotingGUI: ilRepositoryGUI, ilObjPluginDispatchGUI, ilAdministrationGUI, LiveVotingUI, LiveVotingChoicesUI, LiveVotingManageUI, LiveVotingSettingsUI, LiveVotingFreeInputUI, LiveVotingRangeUI, LiveVotingPrioritiesUI, LiveVotingCorrectOrderUI, LiveVotingResultsUI, ilFormPropertyDispatchGUI
 * @ilCtrl_Calls      ilObjLiveVotingGUI: ilObjectCopyGUI, ilPermissionGUI, ilInfoScreenGUI, ilCommonActionDispatcherGUI, LiveVotingUI, LiveVotingChoicesUI, LiveVotingManageUI, LiveVotingSettingsUI, LiveVotingFreeInputUI, LiveVotingRangeUI, LiveVotingPrioritiesUI, LiveVotingCorrectOrderUI, LiveVotingResultsUI, ilFormPropertyDispatchGUI
 */
class ilObjLiveVotingGUI extends ilObjectPluginGUI
{
    private Factory $factory;
    private Renderer $renderer;

    public function __construct(int $a_ref_id = 0, int $a_id_type = self::REPOSITORY_NODE_ID, int $a_parent_node_id = 0)
    {
        global $DIC;

        parent::__construct($a_ref_id, $a_id_type, $a_parent_node_id);

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->request = $DIC->http()->request();
    }

    public function getType(): string
    {
        return "xlvo";
    }

    public function getAfterCreationCmd(): string
    {
        return 'index';
    }

    public function getStandardCmd(): string
    {
        return 'index';
    }

    public function performCommand(string $cmd): void
    {
        global $DIC;
        $this->checkPermission("read");
        $this->setTitleAndDescription();
        $DIC->help()->setScreenIdComponent(ilLiveVotingPlugin::PLUGIN_ID);

        $DIC->ui()->mainTemplate()->setPermanentLink(ilLiveVotingPlugin::PLUGIN_ID, $this->ref_id);

        switch ($cmd) {
            case 'index':
                $this->showContent();
                break;
            case 'editProperties':
            case 'startPlayer':
            case 'startPlayerAnUnfreeze':
            case 'getPlayerData':
            case 'getAttendees':
            case 'removeVoter':
            case 'manage':
            case 'results':
            case 'selectType':
            case 'selectedChoices':
            case 'selectedFreeInput':
            case 'selectedCorrectOrder':
            case 'selectedPriorities':
            case 'selectedRange':
            case 'selectedChoicesCM':
            case 'updateProperties':
            case 'confirmNewRound':
            case 'newRound':
            case 'changeRound':
            case 'applyFilter':
            case 'resetFilter':
            case 'applyFilterQuestions':
            case 'resetFilterQuestions':
            case 'apiCall':
            case 'confirmResetAll':
            case 'resetAll':
            case 'confirmResetQuestion':
            case 'resetQuestion':
            case 'duplicateQuestion':
            case 'duplicateQuestionToAnotherObjectSelect':
            case 'duplicateQuestionToAnotherObject':
            case 'confirmDeleteQuestion':
            case 'deleteQuestion':
            case 'saveSorting':
            case 'endTime':
                $this->{$cmd}();
                break;
            case 'edit':
                if ($this->object->getLiveVoting()->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
                    $this->editQuestionCM();
                } else {
                    $this->editQuestion();
                }
                break;
        }
    }

    public function showContent(): void
    {
        global $DIC;
        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $param_manager = ParamManager::getInstance();
            $pin_url = $this->object->getLiveVoting()->getShortLink($param_manager->getRefId());
            $DIC->ctrl()->redirectToURL($pin_url);
            return;
        }

        $liveVotingUI = new LiveVotingUI($this->object->getLiveVoting());

        $this->tabs->activateTab("tab_content");

        try {
            $this->tpl->setContent($liveVotingUI->showIndex());
        } catch (ilSystemStyleException|ilTemplateException $e) {
            $this->tpl->setContent($this->renderer->render($this->factory->messageBox()->failure($e->getMessage())));
        }
    }

    /**
     * @throws ilCtrlException|ilException
     */
    public function manage(): void
    {
        $this->tabs->activateTab("tab_manage");

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $this->tpl->setContent($this->renderer->render($this->factory->messageBox()->failure($this->plugin->txt("permission_denied"))));
        } elseif (ilObjLiveVotingAccess::hasWriteAccess()) {
            $liveVotingManageUI = new LiveVotingManageUI();
            try {
                $this->tpl->setContent($liveVotingManageUI->showManage($this));
            } catch (ilSystemStyleException|ilTemplateException $e) {
                $this->tpl->setContent($this->renderer->render($this->factory->messageBox()->failure($e->getMessage())));
            }


        }
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     * @throws ilException
     */
    public function results(): void
    {
        $this->tabs->activateTab("tab_results");

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $this->tpl->setContent($this->renderer->render($this->factory->messageBox()->failure($this->plugin->txt("permission_denied"))));
        } else {
            $liveVotingResultsUI = new LiveVotingResultsUI($this->object->getLiveVoting());
            $this->tpl->setContent($liveVotingResultsUI->showResults($this));
        }
    }

    /**
     * @throws ilException
     * @throws LiveVotingException
     */
    public function selectedChoices(): void
    {
        global $DIC;
        $this->tabs->activateTab("tab_manage");

        $liveVotingChoicesUI = new LiveVotingChoicesUI();
        $form = $liveVotingChoicesUI->getChoicesForm();
        if ($DIC->http()->request()->getMethod() == "POST") {

            $id = $liveVotingChoicesUI->save($form->withRequest($DIC->http()->request())->getData());

            if ($id !== 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $id);
                $DIC->ctrl()->setParameter($this, "show_success", true);
                $DIC->ctrl()->redirect($this, "edit");

            } else {
                $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($DIC->language()->txt("form_input_not_valid")));
                $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
            }
        } else {
            $this->tpl->setContent($DIC->ui()->renderer()->render($form));

        }
    }

    /**
     * @throws ilException
     * @throws LiveVotingException
     */
    public function selectedChoicesCM(): void
    {
        global $DIC;
        $this->tabs->activateTab("tab_manage");

        $liveVotingChoicesCMUI = new LiveVotingChoicesCMUI();
        $form = $liveVotingChoicesCMUI->getChoicesForm();
        if ($DIC->http()->request()->getMethod() == "POST") {

            $id = $liveVotingChoicesCMUI->save($form->withRequest($DIC->http()->request())->getData());

            if ($id != 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $id);
                $DIC->ctrl()->setParameter($this, "show_success", true);
                $DIC->ctrl()->redirect($this, "edit");

            } else {
                $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($this->plugin->txt("form_input_not_valid_cm")));
                $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
            }
        } else {
            $this->tpl->setContent($DIC->ui()->renderer()->render($form));

        }
    }

    /**
     * @throws ilException
     * @throws LiveVotingException
     */
    public function selectedFreeInput(): void
    {
        global $DIC;
        $this->tabs->activateTab("tab_manage");

        $liveVotingFreeInputUI = new LiveVotingFreeInputUI();
        $form = $liveVotingFreeInputUI->getFreeForm();
        if ($DIC->http()->request()->getMethod() == "POST") {

            $id = $liveVotingFreeInputUI->save($form->withRequest($DIC->http()->request())->getData());

            if ($id !== 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $id);
                $DIC->ctrl()->setParameter($this, "show_success", true);
                $DIC->ctrl()->redirect($this, "edit");

            } else {
                $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($DIC->language()->txt("form_input_not_valid")));
                $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
            }
        } else {
            $this->tpl->setContent($DIC->ui()->renderer()->render($form));
        }
    }

    /**
     * @throws ilException
     * @throws LiveVotingException
     */
    public function selectedCorrectOrder(): void
    {
        global $DIC;
        $this->tabs->activateTab("tab_manage");

        $liveVotingCorrectOrderUI = new LiveVotingCorrectOrderUI();
        $form = $liveVotingCorrectOrderUI->getCorrectOrderForm();
        if ($DIC->http()->request()->getMethod() == "POST") {

            $id = $liveVotingCorrectOrderUI->save($form->withRequest($DIC->http()->request())->getData());

            if ($id !== 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $id);
                $DIC->ctrl()->setParameter($this, "show_success", true);
                $DIC->ctrl()->redirect($this, "edit");

            } else {
                $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($DIC->language()->txt("form_input_not_valid")));
                $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
            }
        } else {
            $this->tpl->setContent($DIC->ui()->renderer()->render($form));

        }
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function selectedPriorities(): void
    {
        global $DIC;
        $this->tabs->activateTab("tab_manage");

        $liveVotingPrioritiesUI = new LiveVotingPrioritiesUI();
        $form = $liveVotingPrioritiesUI->getPrioritiesForm();
        if ($DIC->http()->request()->getMethod() == "POST") {

            $id = $liveVotingPrioritiesUI->save($form->withRequest($DIC->http()->request())->getData());

            if ($id !== 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $id);
                $DIC->ctrl()->setParameter($this, "show_success", true);
                $DIC->ctrl()->redirect($this, "edit");

            } else {
                $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($DIC->language()->txt("form_input_not_valid")));
                $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
            }
        } else {
            $this->tpl->setContent($DIC->ui()->renderer()->render($form));

        }
    }

    /**
     * @throws ilException
     * @throws LiveVotingException
     */
    public function selectedRange(): void
    {
        global $DIC;
        $this->tabs->activateTab("tab_manage");

        $liveVotingRangeUI = new LiveVotingRangeUI();
        $form = $liveVotingRangeUI->getRangeForm();
        if ($DIC->http()->request()->getMethod() == "POST") {

            $id = $liveVotingRangeUI->save($form->withRequest($DIC->http()->request())->getData());

            if ($id !== 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $id);
                $DIC->ctrl()->setParameter($this, "show_success", true);
                $DIC->ctrl()->redirect($this, "edit");

            } else {
                $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($DIC->language()->txt("form_input_not_valid")));
                $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
            }
        } else {
            $this->tpl->setContent($DIC->ui()->renderer()->render($form));

        }
    }

    /**
     * @throws ilCtrlException
     */
    protected function setTabs(): void
    {
        if (ilObjLiveVotingAccess::hasWriteAccess()) {
            $this->tabs->addTab("tab_content", $this->lng->txt("tab_content"), $this->ctrl->getLinkTarget($this, "index"));
            $this->tabs->addTab("tab_manage", $this->plugin->txt("tab_manage"), $this->ctrl->getLinkTarget($this, "manage"));
            $this->tabs->addTab("tab_results", $this->plugin->txt("tab_results"), $this->ctrl->getLinkTarget($this, "results"));
            $this->tabs->addTab("info_short", $this->lng->txt('info_short'), $this->ctrl->getLinkTargetByClass(array(
                get_class($this),
                "ilInfoScreenGUI",
            ), "showSummary"));

            if ($this->checkPermissionBool("write")) {
                $this->tabs->addTab("tab_edit", $this->plugin->txt("tab_edit"), $this->ctrl->getLinkTarget($this, "editProperties"));
            }

            if ($this->checkPermissionBool("edit_permission")) {
                $this->tabs->addTab("perm_settings", $this->lng->txt("perm_settings"), $this->ctrl->getLinkTargetByClass(array(
                    get_class($this),
                    "ilPermissionGUI",
                ), "perm"));
            }
        }
    }

    /**
     * Add sub tabs and activate the forwarded sub tab in the parameter.
     *
     * @param string $tab
     * @param string $active_sub_tab
     * @throws ilCtrlException
     */
    protected function setSubTabs(string $tab, string $active_sub_tab): void
    {
        if ($tab == 'tab_content') {
            $this->tabs->addSubTab("subtab_show",
                $this->plugin->txt('subtab_show'),
                $this->ctrl->getLinkTarget($this, "index")
            );
            $this->tabs->addSubTab("subtab_edit",
                $this->plugin->txt('subtab_edit'),
                $this->ctrl->getLinkTarget($this, "content")
            );

//            if (ilObjLiveVotingAccess::hasWriteAccess()) {
//                $this->tabs->addSubTab("subtab_edit",
//                    $this->plugin->txt('subtab_edit'),
//                    $this->ctrl->getLinkTargetByClass("LiveVotingUI", "showContent")
//                );
//            }
        }

        $this->tabs->activateSubTab($active_sub_tab);
    }

    /*    protected function triageCmdClass($next_class, $cmd): void
        {
            switch($next_class){
                default:
                    if (strcasecmp($_GET['baseClass'], ilAdministrationGUI::class) == 0) {
                        $this->viewObject();
                        return;
                    }
                    if (!$cmd) {
                        $cmd = $this->getStandardCmd();
                    }
                    if ($this->getCreationMode()) {
                        $this->$cmd();
                    } else {
                        $this->performCommand($cmd);
                    }
                    break;
            }
        }*/

    public function getObjId(): int
    {
        return $this->object->getId();
    }

    /**
     * @throws ilCtrlException
     */
    public function editProperties(): void
    {
        global $DIC;
        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $this->tpl->setContent(
                $this->renderer->render($this->factory->messageBox()->failure($this->plugin->txt("permission_denied")))
            );
        }
         else {
             $this->tabs->activateTab("tab_edit");

             $liveVotingSettingsUI = new LiveVotingSettingsUI($this->getRefId());

             $sections = $liveVotingSettingsUI->initPropertiesForm();
             $form_action = $DIC->ctrl()->getLinkTargetByClass(ilObjLiveVotingGUI::class, "editProperties");
             $rendered = $liveVotingSettingsUI->renderForm($form_action, $sections);

             $this->tpl->setContent($rendered);
         }


    }

    /**
     * @throws LiveVotingException
     * @throws ilException
     */
    public function editQuestion(): void
    {
        global $DIC;

        if (ilObjLiveVotingAccess::hasWriteAccess()) {
            $this->tabs->activateTab("tab_manage");

            $question = $this->object->getLiveVoting()->getQuestionById((int)$_GET['question_id']);
            switch ($question->getQuestionType()) {
                case "Choices":
                    $liveVotingChoicesUI = new LiveVotingChoicesUI($question->getId());
                    $form = $liveVotingChoicesUI->getChoicesForm();
                    $saving_info = "";
                    if ($DIC->http()->request()->getMethod() == "POST") {

                        $id = $liveVotingChoicesUI->save($form->withRequest($DIC->http()->request())->getData(), $question->getId());

                        if ($id !== 0) {
                            $liveVotingChoicesUI = new LiveVotingChoicesUI($id);
                            $form = $liveVotingChoicesUI->getChoicesForm();

                            $DIC->ctrl()->setParameter($this, "question_id", $id);
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_updated')));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));
                        } else {
                            $this->tpl->setContent($DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
                        }

                    } else {
                        if (isset($_GET['show_success'])) {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_created')));
                        }
                        $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));

                    }
                    break;
                case "FreeText":
                    $liveVotingFreeInputUI = new LiveVotingFreeInputUI($question->getId());
                    $form = $liveVotingFreeInputUI->getFreeForm();
                    $saving_info = "";
                    if ($DIC->http()->request()->getMethod() == "POST") {

                        $id = $liveVotingFreeInputUI->save($form->withRequest($DIC->http()->request())->getData(), $question->getId());

                        if ($id !== 0) {
                            $liveVotingFreeInputUI = new LiveVotingFreeInputUI($id);
                            $form = $liveVotingFreeInputUI->getFreeForm();

                            $DIC->ctrl()->setParameter($this, "question_id", $id);
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_updated')));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));
                        } else {
                            $this->tpl->setContent($DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
                        }

                    } else {
                        if (isset($_GET['show_success'])) {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_created')));
                        }
                        $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));

                    }
                    break;
                case "NumberRange":
                    $liveVotingRangeUI = new LiveVotingRangeUI($question->getId());
                    $form = $liveVotingRangeUI->getRangeForm();
                    $saving_info = "";
                    if ($DIC->http()->request()->getMethod() == "POST") {

                        $id = $liveVotingRangeUI->save($form->withRequest($DIC->http()->request())->getData(), $question->getId());

                        if ($id !== 0) {
                            $liveVotingRangeUI = new LiveVotingRangeUI($id);
                            $form = $liveVotingRangeUI->getRangeForm();

                            $DIC->ctrl()->setParameter($this, "question_id", $id);
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_updated')));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));
                        } else {
                            $this->tpl->setContent($DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
                        }

                    } else {
                        if (isset($_GET['show_success'])) {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_created')));
                        }
                        $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));

                    }
                    break;
                case "Priorities":
                    $liveVotingPrioritiesUI = new LiveVotingPrioritiesUI($question->getId());
                    $form = $liveVotingPrioritiesUI->getPrioritiesForm();
                    $saving_info = "";
                    if ($DIC->http()->request()->getMethod() == "POST") {

                        $id = $liveVotingPrioritiesUI->save($form->withRequest($DIC->http()->request())->getData(), $question->getId());

                        if ($id !== 0) {
                            $liveVotingPrioritiesUI = new LiveVotingPrioritiesUI($id);
                            $form = $liveVotingPrioritiesUI->getPrioritiesForm();

                            $DIC->ctrl()->setParameter($this, "question_id", $id);
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_updated')));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));
                        } else {
                            $this->tpl->setContent($DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
                        }

                    } else {
                        if (isset($_GET['show_success'])) {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_created')));
                        }
                        $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));

                    }
                    break;
                case "CorrectOrder":
                    $liveVotingCorrectOrderUI = new LiveVotingCorrectOrderUI($question->getId());
                    $form = $liveVotingCorrectOrderUI->getCorrectOrderForm();
                    $saving_info = "";
                    if ($DIC->http()->request()->getMethod() == "POST") {

                        $id = $liveVotingCorrectOrderUI->save($form->withRequest($DIC->http()->request())->getData(), $question->getId());

                        if ($id !== 0) {
                            $liveVotingCorrectOrderUI = new LiveVotingCorrectOrderUI($id);
                            $form = $liveVotingCorrectOrderUI->getCorrectOrderForm();

                            $DIC->ctrl()->setParameter($this, "question_id", $id);
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_updated')));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));
                        } else {
                            $this->tpl->setContent($DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
                        }

                    } else {
                        if (isset($_GET['show_success'])) {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_created')));
                        }
                        $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));

                    }
                    break;
            }

            $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->obj_id);

            $prev_id = 0;
            $next_id = 0;

            foreach ($questions as $qst) {
                if ($qst->getPosition() < $question->getPosition()) {
                    $prev_id = $qst->getId();
                } elseif ($qst->getPosition() > $question->getPosition()) {
                    $next_id = $qst->getId();
                    break;
                }
            }

            if ($prev_id != 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $prev_id);
                $prev = ilLinkButton::getInstance();
                $prev->setUrl($DIC->ctrl()->getLinkTarget($this, "edit"));
                $prev->setCaption(ilGlyphGUI::get(ilGlyphGUI::PREVIOUS), false);
                $DIC->toolbar()->addButtonInstance($prev);
            }

            if ($next_id != 0) {
                $DIC->ctrl()->setParameter($this, "question_id", $next_id);
                $next = ilLinkButton::getInstance();
                $next->setUrl($DIC->ctrl()->getLinkTarget($this, "edit"));
                $next->setCaption(ilGlyphGUI::get(ilGlyphGUI::NEXT), false);
                $DIC->toolbar()->addButtonInstance($next);
            }
        } else {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        }
    }

    /**
     * @throws ilCtrlException
     * @throws ilException
     * @throws LiveVotingException
     */
    public function editQuestionCM(): void
    {
        global $DIC;

        if (ilObjLiveVotingAccess::hasWriteAccess()) {
            $this->tabs->activateTab("tab_manage");

            $question = $this->object->getLiveVoting()->getQuestionById((int)$_GET['question_id']);
            switch ($question->getQuestionType()) {
                case "Choices":
                    $liveVotingChoicesUI = new LiveVotingChoicesCMUI($question->getId());
                    $form = $liveVotingChoicesUI->getChoicesForm();
                    $saving_info = "";
                    if ($DIC->http()->request()->getMethod() == "POST") {

                        $id = $liveVotingChoicesUI->save($form->withRequest($DIC->http()->request())->getData(), $question->getId());

                        if ($id !== 0) {
                            $liveVotingChoicesUI = new LiveVotingChoicesCMUI($id);
                            $form = $liveVotingChoicesUI->getChoicesForm();

                            $DIC->ctrl()->setParameter($this, "question_id", $id);
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_updated')));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));
                        } else {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($this->plugin->txt("form_input_not_valid_cm")));
                            $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form->withRequest($DIC->http()->request())));
                        }

                    } else {
                        if (isset($_GET['show_success'])) {
                            $saving_info = $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('msg_success_voting_created')));
                        }
                        $this->tpl->setContent($saving_info . $DIC->ui()->renderer()->render($form));

                    }
                    break;
            }
        } else {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        }
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    protected function confirmResetAll(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $this->tabs->activateTab("tab_manage");

            $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->obj_id);

            $num_votes = 0;

            foreach ($questions as $question) {
                $num_votes += count(LiveVotingVote::getVotesOfQuestion($question->getId()));
            }

            $confirm = new ilConfirmationGUI();
            $confirm->addItem("question_id", "", $this->txt('voting_confirm_number_of_votes') . " " . $num_votes);
            $confirm->setHeaderText($this->txt('voting_confirm_reset_all'));
            $confirm->setFormAction($DIC->ctrl()->getFormAction($this));
            $confirm->setCancel($this->txt('voting_cancel'), "cancel");
            $confirm->setConfirm($this->txt('voting_reset_all'), "resetAll");

            $DIC->ui()->mainTemplate()->setContent($confirm->getHTML());
        }
    }


    /**
     *
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    protected function resetAll(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->obj_id);

            foreach ($questions as $question) {
                $question->reset();
            }

            foreach (LiveVotingRound::getRounds($this->obj_id) as $round) {
                $round->delete();
            }

            $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->txt('voting_msg_all_reset'), true);

            $DIC->ctrl()->redirect($this, "manage");
        }

    }

    /**
     * @throws ilCtrlException
     */
    public function confirmResetQuestion(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $this->tabs->activateTab("tab_manage");

            $question = $this->object->getLiveVoting()->getQuestionById((int)$_GET['question_id']);

            if ($question->getObjId() == $this->getObjId()) {
                $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->confirmation($this->txt('voting_confirm_reset')));

                $confirm = new ilConfirmationGUI();
                $confirm->addItem("question_id", (string)$question->getId(), $question->getTitle());
                $confirm->setHeaderText($this->txt('voting_confirm_reset'));
                $confirm->setFormAction($DIC->ctrl()->getFormAction($this));
                $confirm->setCancel($this->txt('voting_cancel'), "manage");
                $confirm->setConfirm($this->txt('voting_reset'), "resetQuestion");

                $DIC->ui()->mainTemplate()->setContent($confirm->getHTML());
            } else {
                $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_object')));
                $DIC->ctrl()->redirect($this, "index");
            }
        }
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function resetQuestion(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $question = $this->object->getLiveVoting()->getQuestionById((int)$_POST['question_id']);

            if ($question->getObjId() == $this->getObjId()) {
                $question->reset();

                $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->txt('voting_msg_reset'), true);

                $DIC->ctrl()->redirect($this, "manage");
            } else {
                $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('reset_failed')));
                $DIC->ctrl()->redirect($this, "index");
            }
        }

    }

    /**
     * @throws ilCtrlException
     */
    public function duplicateQuestion(): void
    {
        global $DIC;

        $question = $this->object->getLiveVoting()->getQuestionById((int)$_GET['question_id']);

        $question->fullClone(true, true);

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->txt('voting_msg_duplicated'), true);

        $DIC->ctrl()->redirect($this, "manage");
    }

    public function duplicateQuestionToAnotherObjectSelect(): void
    {
        global $DIC;

        $this->tabs->activateTab("tab_manage");

        $question = $this->object->getLiveVoting()->getQuestionById((int)$_GET['question_id']);

        $DIC->ui()->mainTemplate()->setContent($this->getDuplicateToAnotherObjectSelectTree($question));
    }

    private function getDuplicateToAnotherObjectSelectTree(LiveVotingQuestion $question): string
    {
        $liveVotings = [
            [
                "ref_id" => $this->tree->getRootId(),
                "title" => "LiveVotings",
                "livevoting" => false
            ]
        ];

        $recursion = new class () implements \ILIAS\UI\Component\Tree\TreeRecursion {
            public function getChildren($record, $environment = null): array
            {
                global $DIC;

                $childs = $DIC->repositoryTree()->getChilds($record["ref_id"]);

                $result = [];

                foreach ($childs as $child) {
                    if (($child["type"] == ilLiveVotingPlugin::PLUGIN_ID || count($DIC->repositoryTree()->getChilds((int) $child["ref_id"])) > 0) && $child["type"] != "adm") {
                        $result[] = [
                            "ref_id" => (int) $child["ref_id"],
                            "title" => $child["title"],
                            "livevoting" => $child["type"] == ilLiveVotingPlugin::PLUGIN_ID
                        ];
                    }
                }

                return $result;
            }

            public function build(
                \ILIAS\UI\Component\Tree\Node\Factory $factory,
                $record,
                $environment = null
            ): \ILIAS\UI\Component\Tree\Node\Node {
                $node = $factory->simple($record['title']);

                if ($record['livevoting']) {
                    $uri = new \ILIAS\Data\URI($environment['url'] . "&to_ref_id=" . $record['ref_id']);
                    $node = $node->withLink($uri);
                }

                return $node;
            }
        };

        $this->ctrl->setParameter($this, "question_id", $question->getId());
        $tree = $this->factory->tree()->expandable("LiveVotings", $recursion)->withData($liveVotings)->withHighlightOnNodeClick(true)->withEnvironment(array(
            "url" => ILIAS_HTTP_PATH . "/" . $this->ctrl->getLinkTarget($this, "duplicateQuestionToAnotherObject")
        ));

        return $this->renderer->render($tree);
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function duplicateQuestionToAnotherObject()
    {
        global $DIC;

        $question_id = isset($this->request->getQueryParams()["question_id"]) ? intval($this->request->getQueryParams()["question_id"]) : 0;
        $to_ref_id = isset($this->request->getQueryParams()["to_ref_id"]) ? intval($this->request->getQueryParams()["to_ref_id"]) : 0;

        if ($question_id == 0 || $to_ref_id == 0) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->txt('voting_msg_duplicated_failed'), true);
            $this->ctrl->redirect($this, "manage");
            return;
        }

        $obj_id = ilObject::_lookupObjId($to_ref_id);

        if ($obj_id == 0) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->txt('voting_msg_duplicated_failed'), true);
            $this->ctrl->redirect($this, "manage");
            return;
        }

        $question = LiveVotingQuestion::loadQuestionById($question_id);

        if ($question == null) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage("failure", $this->txt('voting_msg_duplicated_failed'), true);
            $this->ctrl->redirect($this, "manage");
            return;
        }

        $question->fullClone(true, true, $obj_id);

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->txt('voting_msg_duplicated'), true);
        $this->ctrl->redirect($this, "manage");
    }

    /**
     * @throws ilCtrlException
     */
    public function confirmDeleteQuestion(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $this->tabs->activateTab("tab_manage");

            $question = $this->object->getLiveVoting()->getQuestionById((int)$_GET['question_id']);

            if ($question->getObjId() == $this->getObjId()) {
                $confirm = new ilConfirmationGUI();
                $confirm->addItem("question_id", (string)$question->getId(), $question->getTitle());
                $confirm->setHeaderText($this->txt('voting_delete_confirm'));
                $confirm->setFormAction($DIC->ctrl()->getFormAction($this));
                $confirm->setCancel($this->txt('voting_cancel'), "manage");
                $confirm->setConfirm($this->txt('voting_delete'), "deleteQuestion");

                $DIC->ui()->mainTemplate()->setContent($confirm->getHTML());
            } else {
                $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_object')));
                $DIC->ctrl()->redirect($this, "index");

            }
        }
    }

    /**
     * @throws ilCtrlException
     */
    public function deleteQuestion(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $question = $this->object->getLiveVoting()->getQuestionById((int)$_POST['question_id']);

            if ($question->getObjId() == $this->getObjId()) {
                $question->delete();

                $DIC->ctrl()->redirect($this, "manage");
            } else {
                $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('delete_failed')));
                $DIC->ctrl()->redirect($this, "index");
            }
        }
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    protected function startPlayer(): void
    {

        $this->tabs->activateTab("tab_content");

        $liveVotingUI = new LiveVotingUI($this->object->getLiveVoting());
        $liveVotingUI->initJsAndCss($this);
        $liveVotingUI->showVoting();

    }

    protected function startPlayerAnUnfreeze(): void
    {
        $this->tabs->activateTab("tab_content");

        $liveVotingUI = new LiveVotingUI($this->object->getLiveVoting());
        $liveVotingUI->initJsAndCss($this);
        $liveVotingUI->showVoting();

        $this->object->getLiveVoting()->getPlayer()->setStatus(LiveVotingPlayer::STAT_RUNNING);

        $param_manager = ParamManager::getInstance();

        $countdown = $this->object->getLiveVoting()->getPlayer()->getActiveVotingObject()->getCountdown();

        if ($this->object->getLiveVoting()->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE && $countdown !== -1) {
            $this->object->getLiveVoting()->getPlayer()->startCountDown($countdown);
        } else {
            $this->object->getLiveVoting()->getPlayer()->unfreeze($param_manager->getVoting());

        }
    }

    /**
     * @throws LiveVotingException
     */
    protected function getPlayerData()
    {

        $liveVotingPlayer = LiveVotingPlayer::loadFromObjId($this->object->getLiveVoting()->getId());

        $liveVotingPlayer->attend($this->object->getLiveVoting()->getMode());

        $param_manager = ParamManager::getInstance();

        //Set Active Voting of Presenter via URL - bot don't save it - PLLV-272
        if ($param_manager->getVoting() > 0) {
            $this->object->getLiveVoting()->getPlayer()->setActive($param_manager->getVoting());
        }

        $liveVotingUI = new LiveVotingUI($this->object->getLiveVoting());

        try {
            $results = array(
                'player' => $this->object->getLiveVoting()->getPlayer()->getPlayerData(),
                'player_html' => $liveVotingUI->getPlayerHTML(true),
                'buttons_html' => $this->getButtonsHTML()
            );

            LiveVotingJs::sendResponse($results);

        } catch (LiveVotingException|ilTemplateException|ilException $e) {
            $this->tpl->setContent($this->renderer->render($this->factory->messageBox()->failure($e->getMessage())));
        }

        //xlvoJsResponse::getInstance($results)->send();
    }

    /**
     * @return string
     */
    protected function getButtonsHTML(): string
    {
        // Buttons from Questions
        try {
            $xlvoQuestionTypesGUI = LiveVotingQuestionTypesUI::getInstance($this->object->getLiveVoting()->getPlayer());
            if ($xlvoQuestionTypesGUI->hasButtons()) {

                $toolbar = new ilToolbarGUI();

                foreach ($xlvoQuestionTypesGUI->getButtonInstances() as $buttonInstance) {
                    if ($buttonInstance instanceof ilButton || $buttonInstance instanceof ilButtonBase) {
                        $toolbar->addButtonInstance($buttonInstance);
                    }
                }

                return $toolbar->getHTML();
            }
        } catch (LiveVotingException|ilException $e) {
            return $this->renderer->render($this->factory->messageBox()->failure($e->getMessage()));
        }


        return '';
    }

    public function confirmNewRound(): void
    {
        global $DIC;

        $this->tabs->activateTab("tab_results");

        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setHeaderText($this->plugin->txt('common_confirm_new_round'));
        $conf->setConfirm($this->plugin->txt("common_new_round"), "newRound");
        $conf->setCancel($this->plugin->txt('common_cancel'), "results");
        $DIC->ui()->mainTemplate()->setContent($conf->getHTML());
    }

    /**
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    public function newRound(): void
    {
        global $DIC;

        $obj_id = $this->object->getLiveVoting()->getId();
        $lastRound = LiveVotingRound::getLatestRound($obj_id);
        $newRound = new LiveVotingRound();
        $newRound->setObjId($obj_id);
        $newRound->setRoundNumber($lastRound->getRoundNumber() + 1);
        $newRound->save();

        $DIC->ctrl()->setParameter($this, 'round_id', LiveVotingRound::getLatestRound($obj_id)->getId());

        $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->txt('common_new_round_created'), true);

        $DIC->ctrl()->redirect($this, "results");
    }

    /**
     * @throws ilCtrlException
     */
    public function changeRound(): void
    {
        global $DIC;

        $round = $_POST['round_id'];
        $DIC->ctrl()->setParameter($this, 'round_id', $round);
        $DIC->ctrl()->redirect($this, "results");
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function applyFilter(): void
    {
        global $DIC;

        $table = new LiveVotingResultsTableGUI($this, "results");
        LiveVotingResultsUI::buildFilters($table, (int)$_GET['round_id'], $this->object->getLiveVoting()->getQuestions());
        $table->initFilter();
        $table->writeFilterToSession();
        $DIC->ctrl()->redirect($this, "results");
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function resetFilter(): void
    {
        global $DIC;

        $table = new LiveVotingResultsTableGUI($this, "results");
        LiveVotingResultsUI::buildFilters($table, (int)$_GET['round_id'], $this->object->getLiveVoting()->getQuestions());
        $table->initFilter();
        $table->resetFilter();
        $DIC->ctrl()->redirect($this, "results");
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function applyFilterQuestions(): void
    {
        global $DIC;

        $table = new LiveVotingTableGUI($this, "manage");
        $table->initFilter();
        $table->writeFilterToSession();
        $DIC->ctrl()->redirect($this, "manage");
    }

    /**
     * @throws ilException
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function resetFilterQuestions(): void
    {
        global $DIC;

        $table = new LiveVotingTableGUI($this, "manage");
        $table->initFilter();
        $table->resetFilter();
        $DIC->ctrl()->redirect($this, "manage");
    }


    /**
     * @throws LiveVotingException
     * @throws ilException
     */
    protected function apiCall(): void
    {
        $return_value = true;

        /** @var LiveVoting $liveVoting */
        $liveVoting = $this->object->getLiveVoting();
        switch ($_POST['call']) {
            case 'toggle_freeze':
                $param_manager = ParamManager::getInstance();
                $liveVoting->getPlayer()->toggleFreeze($param_manager->getVoting());
                break;
            case 'toggle_results':
                $liveVoting->getPlayer()->toggleResults();
                break;
            case 'reset':
                $liveVoting->getPlayer()->reset();
                break;
            case 'next':
                $liveVoting->getPlayer()->nextQuestion();
                break;
            case 'previous':
                $liveVoting->getPlayer()->previousQuestion();
                break;
            case 'open':
                $liveVoting->getPlayer()->open((int)$_POST["xvi"]);
                break;
            case 'countdown':
                $liveVoting->getPlayer()->startCountDown((int)$_POST['seconds']);
                break;
            case 'input':
                global $DIC;
                LiveVotingParticipant::getInstance()->setIdentifier($DIC->user()->getId())->setType(1);
                $liveVoting->getPlayer()->input(['input' => $_POST['input']]);
                break;
            case 'add_vote':
                $vote = new LiveVotingVote();
                $user = LiveVotingParticipant::getInstance();
                $vote->setUserId((int)$user->getIdentifier());
                $vote->setUserIdType(1);
                $vote->setVotingId($liveVoting->getPlayer()->getActiveVoting());
                $options = $liveVoting->getPlayer()->getActiveVotingObject()->getOptions();
                $var = array_values($options);
                $option = array_shift($var);
                $vote->setOptionId($option->getId());
                $vote->setType(2);
                $vote->setStatus(1);
                $vote->setFreeInput($_POST['input']);
                $vote->setRoundId(LiveVotingRound::getLatestRoundId($liveVoting->getId()));
                $vote->save();

                $return_value = ['vote_id' => $vote->getId()];

                break;
            case 'remove_vote':
                $vote = new LiveVotingVote((int)$_POST['vote_id']);
                $vote->delete();
                break;
            case 'add_category':
                $category = new LiveVotingCategory();

                $category->setTitle($_POST['title']);

                $category->setVotingId($liveVoting->getPlayer()->getActiveVoting());
                $category->setRoundId($liveVoting->getPlayer()->getRoundId());

                $category->save();
                $return_value = ['category_id' => $category->getId()];
                break;
            case 'remove_category':
                $database = new LiveVotingDatabase();

                $database->update('rep_robj_xlvo_vote_n', array(
                    "free_input_category" => 0
                ), array(
                    "voting_id" => $liveVoting->getPlayer()->getActiveVoting(),
                    "round_id" => $liveVoting->getPlayer()->getRoundId(),
                    "free_input_category" => $_POST['category_id']
                ));

                $database->delete('rep_robj_xlvo_cat', array(
                    "id" => $_POST['category_id']
                ));

                break;
            case 'change_category':
                $vote = new LiveVotingVote((int)$_POST['vote_id']);

                $database = new LiveVotingDatabase();

                $votes = $database->select('rep_robj_xlvo_vote_n', array(
                    'voting_id' => $vote->getVotingId(),
                    'round_id' => $vote->getRoundId(),
                    'free_input' => $vote->getFreeInput(),
                    'free_input_category' => $vote->getFreeInputCategory()
                ), array('id'));


                foreach ($votes as $vote) {
                    $vote = new LiveVotingVote((int)$vote['id']);
                    $vote->setFreeInputCategory((int)$_POST['category_id']);
                    $vote->save();
                }
                exit;
                break;
            case 'button':
                $xlvoQuestionTypesGUI = LiveVotingQuestionTypesUI::getInstance($this->object->getLiveVoting()->getPlayer());
                $xlvoQuestionTypesGUI->handleButtonCall($_POST['button_id'], $_POST['button_data']);
                $return_value = new stdClass();
                $return_value->buttons_html = $this->getButtonsHTML();
                break;
            case 'end_time':
                $liveVoting->getPlayer()->setCountdown(0);

                if ($liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE && $liveVoting->getPlayer()->getActiveVotingObject()->getCountdown() == -1) {
                    $liveVoting->getPlayer()->setStatus(LiveVotingPlayer::STAT_SCOREBOARD);
                }

                $liveVoting->getPlayer()->save();
                break;
            case 'next-cm':
                $liveVoting->getPlayer()->nextQuestionCM();
                break;
            default:
                $return_value = false;
                break;
        }

        LiveVotingJs::sendResponse($return_value);
    }

    /**
     * @throws LiveVotingException
     */
    public function getAttendees(): void
    {
        $player = $this->object->getLiveVoting()->getPlayer();

        LiveVotingJs::sendResponse([
            "count" => vsprintf($this->plugin->txt("start_online"), [LiveVotingVoter::countVoters($player->getId())]),
            "nicknames" => LiveVotingVoter::getVotersNicknames($player->getId())
        ]);
    }

    public function removeVoter() {
        $user_identifier = $_POST['player'];

        $player_id = $this->object->getLiveVoting()->getPlayer()->getId();

        LiveVotingVoter::removeVoter($player_id, $user_identifier);
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     */
    public function saveSorting(): void
    {
        global $DIC;

        if (!ilObjLiveVotingAccess::hasWriteAccess()) {
            $DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure(ilLiveVotingPlugin::getInstance()->txt('permission_denied_write')));
            $DIC->ctrl()->redirect($this, "index");
        } else {
            $positions = $_POST["position"];

            $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->obj_id);

            foreach ($questions as $question) {
                $question->setPosition(array_search($question->getId(), $positions) + 1);
                $question->save();
            }

            $DIC->ui()->mainTemplate()->setOnScreenMessage("success", $this->txt('sorting_msg_saved'), true);

            $DIC->ctrl()->redirect($this, "manage");
        }
    }

    /**
     * @throws ilCtrlException
     * @throws Exception
     */
    public static function _goto(array $a_target): void
    {
        if (preg_match("/[\\d]*_pin_([\\w]*)/", $a_target[0], $matches)) {
            global $DIC;

            LiveVotingInitialisation::saveContext(2);

            $param_manager = ParamManager::getInstance();
            $param_manager->setPin($matches[1]);

            $DIC->ctrl()->setTargetScript(ltrim(LiveVotingConfig::getFullApiURL(), './'));
            $DIC->ctrl()->redirectByClass(["ilUIPluginRouterGUI", "LiveVotingPlayerGUI"], 'startVoterPlayer');
        }

        parent::_goto($a_target);
    }

    protected function initCreateForm(string $new_type): ilPropertyFormGUI
    {
        $form = parent::initCreateForm($new_type);

        $mode = new ilRadioGroupInputGUI($this->plugin->txt('xlvo_mode'), 'xlvo_mode');
        $mode->addOption(new ilRadioOption($this->plugin->txt('xlvo_mode_basic'), (string) LiveVotingMode::BASIC_MODE, $this->plugin->txt('xlvo_mode_basic_info')));
        $mode->addOption(new ilRadioOption($this->plugin->txt('xlvo_mode_challenge'), (string) LiveVotingMode::CHALLENGE_MODE, $this->plugin->txt('xlvo_mode_challenge_info')));

        $mode->setValue((string) LiveVotingMode::BASIC_MODE);
        $mode->setRequired(true);

        $form->addItem($mode);

        return $form;
    }

    /**
     * @throws LiveVotingException
     */
    protected function afterSave(ilObject $new_object): void
    {
        $form = $this->initCreateForm("xlvo");

        if ($form->checkInput()) {
            $new_object->getLiveVoting()->setMode(LiveVotingMode::new((int)$form->getInput("xlvo_mode")));
            $new_object->getLiveVoting()->save();
        }

        parent::afterSave($new_object);
    }
}