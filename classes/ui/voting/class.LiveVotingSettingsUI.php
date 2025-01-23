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

use Exception;
use ilCtrlInterface;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLiveVotingPlugin;
use ilObjectListGUIFactory;
use ilObjLiveVoting;
use ilPlugin;

/**
 * Class LiveVotingSettingsUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilObjLiveVotingGUI: ilObjPluginGUI
 */
class LiveVotingSettingsUI
{
    /**
     * @var ilCtrlInterface
     */
    protected ilCtrlInterface $control;
    /**
     * @var Factory
     */
    protected Factory $factory;

    /**
     * @var ilPlugin
     */
    protected ilPlugin $plugin;
    protected renderer $renderer;
    protected $request;
    protected ilObjLiveVoting $object;

    public function __construct($parent_id)
    {
        global $DIC;

        $this->plugin = ilLiveVotingPlugin::getInstance();
        $this->control = $DIC->ctrl();
        $this->request = $DIC->http()->request();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->object = new ilObjLiveVoting($parent_id);

    }

    public function initPropertiesForm(): array
    {
        global $DIC;


        $sections = [];

        try {
            $this->control->setParameterByClass('ilObLiveVotingGUI', 'cmd', 'editProperties');
            $titleInput = $DIC->ui()->factory()->input()->field()->text($this->plugin->txt("obj_title"), '')
                ->withValue($this->object->getTitle())
                ->withRequired(true)
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->setTitle($v);
                    }
                ));

            $descriptionInput = $DIC->ui()->factory()->input()->field()->textarea($this->plugin->txt("obj_description"), '')
                ->withValue($this->object->getDescription())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->setDescription($v);
                    }
                ));

            $formFields = [
                'title' => $titleInput,
                'description' => $descriptionInput,
            ];

            $onlineCheckbox = $DIC->ui()->factory()->input()->field()->checkbox($this->plugin->txt("obj_online"), $this->plugin->txt("obj_info_online"))
                ->withValue($this->object->getLiveVoting()->isOnline())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->getLiveVoting()->setOnline((bool)$v);
                    }
                ));
            $formFields['online'] = $onlineCheckbox;

            $voteLoginCheck = $DIC->ui()->factory()->input()->field()->checkbox($this->plugin->txt("obj_anonymous"), $this->plugin->txt("obj_info_anonymous"))
                ->withValue($this->object->getLiveVoting()->isAnonymous())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->getLiveVoting()->setAnonymous((bool)$v);
                    }
                ));
            $formFields['vote_login_check'] = $voteLoginCheck;

            $voteHistoryCheck = $DIC->ui()->factory()->input()->field()->checkbox($this->plugin->txt("voting_history"), $this->plugin->txt("voting_history_info"))
                ->withValue($this->object->getLiveVoting()->isVotingHistory())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->getLiveVoting()->setVotingHistory((bool)$v);
                    }
                ));
            $formFields['vote_history_check'] = $voteHistoryCheck;

            $showAttendeesCheck = $DIC->ui()->factory()->input()->field()->checkbox($this->plugin->txt("show_attendees"), $this->plugin->txt("show_attendees_info"))
                ->withValue($this->object->getLiveVoting()->isShowAttendees())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->getLiveVoting()->setShowAttendees((bool)$v);
                    }
                ));
            $formFields['show_attendees'] = $showAttendeesCheck;

            $sectionObject = $DIC->ui()->factory()->input()->field()->section($formFields, $this->plugin->txt("obj_edit_properties"), "");

            $sections["object"] = $sectionObject;

            $formFields = [];

            $frozenOptions = $DIC->ui()->factory()->input()->field()->radio($this->plugin->txt('obj_frozen_behaviour'), "")
                ->withOption("1", $this->plugin->txt('obj_frozen_alway_on'), $this->plugin->txt('obj_frozen_alway_on_info'))
                ->withOption("0", $this->plugin->txt('obj_frozen_alway_off'), $this->plugin->txt('obj_frozen_alway_off_info'))
                ->withOption("2", $this->plugin->txt('obj_frozen_reuse'), $this->plugin->txt('obj_frozen_reuse_info'))
                ->withValue($this->object->getLiveVoting()->getFrozenBehaviour())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->getLiveVoting()->setFrozenBehaviour((int)$v);
                    }
                ));

            $formFields['frozen_options'] = $frozenOptions;

            $resultsOptions = $DIC->ui()->factory()->input()->field()->radio($this->plugin->txt('obj_results_behaviour'), "")
                ->withOption("1", $this->plugin->txt('obj_results_alway_on'), $this->plugin->txt('obj_results_alway_on_info'))
                ->withOption("0", $this->plugin->txt('obj_results_alway_off'), $this->plugin->txt('obj_results_alway_off_info'))
                ->withOption("2", $this->plugin->txt('obj_frozen_reuse'), $this->plugin->txt('obj_results_reuse_info'))
                ->withValue($this->object->getLiveVoting()->getResultsBehaviour())
                ->withAdditionalTransformation($DIC->refinery()->custom()->transformation(
                    function ($v) {
                        $this->object->getLiveVoting()->setResultsBehaviour((int)$v);
                    }
                ));

            $formFields['results_options'] = $resultsOptions;

            $sectionFrozen = $DIC->ui()->factory()->input()->field()->section($formFields, $this->plugin->txt("obj_formtitle_change_vote"), "");

            $sections["frozen"] = $sectionFrozen;


        } catch (Exception $e) {
            $section = $DIC->ui()->factory()->messageBox()->failure($e->getMessage());
            $sections["object"] = $section;
        }

        return $sections;

    }

    /**
     * @throws \ilObjectException
     */
    public function renderForm(string $form_action, array $sections): string
    {
        global $DIC;
        //Create the form
        $form = $DIC->ui()->factory()->input()->container()->form()->standard(
            $form_action,
            $sections
        );

        $saving_info = "";

        $request = $DIC->http()->request();

        //Check if the form has been submitted
        if ($request->getMethod() == "POST") {
            $form = $form->withRequest($request);
            $result = $form->getData();
            if ($result) {
                $saving_info = $this->saveProperties();

                $l_gui = ilObjectListGUIFactory::_getListGUIByType($this->object->getType());
                $l_gui->initItem($this->object->getRefId(), $this->object->getId(), $this->object->getType());
                $DIC["tpl"]->setAlertProperties($l_gui->getAlertProperties());

            }
        }

        return $saving_info . $this->renderer->render($form);
    }

    protected function saveProperties(): string
    {
        global $DIC;
        $renderer = $DIC->ui()->renderer();
        $this->object->update();

        return $renderer->render($DIC->ui()->factory()->messageBox()->success($this->plugin->txt('config_msg_success')));

    }

}