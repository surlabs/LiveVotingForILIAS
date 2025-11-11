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

use Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component\CustomFactory;
use Exception;
use ilCtrlInterface;
use ilException;
use ilHtmlPurifierFactory;
use ilHtmlPurifierNotFoundException;
use ILIAS\UI\Component\Input\Container\Form\Form;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLegacyFormElementsUtil;
use ilLiveVotingPlugin;
use ilObject;
use ilObjLiveVotingGUI;
use ilPlugin;
use ilPropertyFormGUI;
use ilRTE;
use ilTextAreaInputGUI;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\questions\LiveVotingQuestionOption;

/**
 * Class LiveVotingFreeInputUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilObjLiveVotingGUI: ilObjPluginGUI
 */
class LiveVotingFreeInputUI
{
    /**
     * @var LiveVotingQuestion
     */
    private LiveVotingQuestion $question;
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
    private CustomFactory $customFactory;


    /**
     * @throws LiveVotingException
     */
    public function __construct(?int $question_id = null)
    {
        global $DIC;

        $this->plugin = ilLiveVotingPlugin::getInstance();
        $this->control = $DIC->ctrl();
        $this->request = $DIC->http()->request();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->customFactory = new CustomFactory();

        if ($question_id) {
            $this->question = LiveVotingQuestion::loadQuestionById($question_id);
        }
    }

    /**
     * @throws ilException
     */
    public function getFreeForm(): Form
    {
        global $DIC;
        try {
            $form_questions = [];

            $form_questions["title"] = $this->factory->input()->field()->text(
                $this->plugin->txt('voting_title'))
                ->withValue(isset($this->question) ? $this->question->getTitle() : "")
                ->withRequired(true);

            $form_questions["question"] = $this->customFactory->textArea(
                $this->plugin->txt('voting_question'))
                ->withValue(isset($this->question) ? $this->question->getQuestion(true) : "")
                ->withRequired(true);


            $section_questions = $this->factory->input()->field()->section($form_questions, $this->plugin->txt("player_voting_list"), $this->plugin->txt("voting_type_2"));


            //Answers section
            $form_answers = [];

            $form_answers["multi_input"] = $this->factory->input()->field()->checkbox(
                $this->plugin->txt('qtype_2_multi_free_input'),
                $this->plugin->txt('qtype_2_multi_free_input_info'))
                ->withValue(isset($this->question) ? $this->question->isMultiFreeInput() : false);

            $form_answers["answer_field"] = $DIC->ui()->factory()->input()->field()->radio($this->plugin->txt('qtype_2_answer_field_multi_line'), "")
                ->withOption('1', $this->plugin->txt('qtype_2_answer_field_single_line'), $this->plugin->txt('qtype_2_answer_field_single_line_info'))
                ->withOption('2', $this->plugin->txt('qtype_2_answer_field_multi_line'), $this->plugin->txt('qtype_2_answer_field_multi_line_info'))
                ->withValue(isset($this->question) ? $this->question->getAnswerField() : 1);


            $section_answers = $this->factory->input()->field()->section($form_answers, $this->plugin->txt("qtype_form_header"), "");

            $sections = [
                "config_question" => $section_questions,
                "config_answers" => $section_answers
            ];

            if (isset($this->question)) {
                $this->control->setParameterByClass(ilObjLiveVotingGUI::class, "question_id", $this->question->getId());
                $form_action = $this->control->getFormActionByClass(ilObjLiveVotingGUI::class, "edit");

            } else {
                $form_action = $this->control->getFormActionByClass(ilObjLiveVotingGUI::class, "selectedFreeInput");
            }

            $DIC->ui()->mainTemplate()->addJavaScript("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/js/xlvoForms.js");

            $DIC->ui()->mainTemplate()->addCss("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/css/livevoting.css");


            return $this->createForm($form_action, $sections);
        } catch (Exception $e) {
            throw new ilException($e->getMessage());
        }
    }


    /**
     * @throws ilHtmlPurifierNotFoundException
     */
    private function createForm(string $form_action, array $sections): Form
    {
        $r = new ilTextAreaInputGUI($this->plugin->txt('question'), 'question');
        $r->addPlugin('latex');
        $r->addButton('latex');
        $r->addButton('pastelatex');
        $r->setRequired(true);
        $r->setRTESupport(ilObject::_lookupObjId((int)$_GET['ref_id']), "dcl", ilLiveVotingPlugin::PLUGIN_ID);
        $r->setUseRte(true);
        $r->setRteTags(array(
            'p',
            'a',
            'br',
            'strong',
            'b',
            'i',
            'em',
            'span',
            'img',
        ));
        $r->usePurifier(false);
        $r->setPurifier(ilHtmlPurifierFactory::getInstanceByType('frm_post'));
        $r->disableButtons(array(
            'charmap',
            'undo',
            'redo',
            'justifyleft',
            'justifycenter',
            'justifyright',
            'justifyfull',
            'anchor',
            'fullscreen',
            'cut',
            'copy',
            'paste',
            'pastetext',
            'formatselect',
            'bullist',
            'hr',
            'sub',
            'sup',
            'numlist',
            'cite',
        ));

        $r->setRows(5);

        $formAlt = new ilPropertyFormGUI();
        $formAlt->addItem($r);
        $formAlt->getHTML();

        return $this->factory->input()->container()->form()->standard(
            $form_action,
            $sections,
        );
    }

    /**
     * @throws LiveVotingException
     */
    public function save($result, ?int $question_id = null): int
    {
        if ($result && isset($result["config_question"]) && isset($result["config_answers"])) {
            $question_data = $result["config_question"];
            $answers_data = $result["config_answers"];


            $question = $question_id ? LiveVotingQuestion::loadQuestionById($question_id) : LiveVotingQuestion::loadNewQuestion("FreeText");

            $question->setTitle($question_data["title"] ?? null);
            $question->setQuestion($_POST["form/input_0/input_2"] ? ilRTE::_replaceMediaObjectImageSrc($_POST["form/input_0/input_2"]) : null);
            $question->setMultiFreeInput($answers_data["multi_input"] ? (bool)$answers_data["multi_input"] : false);
            $question->setAnswerField($answers_data["answer_field"] ? (int)$answers_data["answer_field"] : 1);

            $id = ilObject::_lookupObjId((int)$_GET['ref_id']);
            $question->setObjId($id);

            if (empty($question->getOptions())) {
                $option = new LiveVotingQuestionOption();

                $option->setVotingId($question->getId());
                $option->setType($question->getQuestionTypeId());

                $question->setOptions(array(
                    $option
                ));
            }

            $this->question = $question;

            return $question->save();
        } else {
            return 0;
        }
    }
}