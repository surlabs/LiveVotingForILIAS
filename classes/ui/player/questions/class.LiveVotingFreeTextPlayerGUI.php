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
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\Player\CustomUI\HiddenInputGUI\HiddenInputGUI;
use LiveVoting\UI\Player\CustomUI\MultiLineNewInputGUI;
use LiveVoting\UI\Player\CustomUI\TextAreaInputGUI\TextAreaInputGUI;
use LiveVoting\Utils\LiveVotingJs;
use LiveVoting\Utils\LiveVotingUtils;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingVote;


/**
 * Class LiveVotingFreeTextPlayerGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy LiveVotingFreeTextPlayerGUI: ilUIPluginRouterGUI, LiveVotingPlayerGUI
 * @ilCtrl_Calls LiveVotingFreeTextPlayerGUI: LiveVotingPlayerGUI, ilUIPluginRouterGUI
 */
class LiveVotingFreeTextPlayerGUI extends LiveVotingQuestionTypesUI
{
    /**
     * @var ilTemplate
     */
    protected ilTemplate $tpl;

    /**
     * @param bool $current
     * @throws ilCtrlException
     */
    public function initJS(bool $current = false)
    {
        global $tpl;

        $liveVotingPlayerGUI = new LiveVotingPlayerGUI();

        //MultiLineNewInputGUI::init();
        LiveVotingJs::getInstance()->api($liveVotingPlayerGUI)->name('FreeInput')->category('QuestionTypes')->init();
        //$tpl->addJavaScript(ilLiveVotingPlugin::getInstance()->getDirectory(). '/templates/js/QuestionTypes/FreeInput/xlvoFreeInput.js');
    }


    /**
     *
     * @throws LiveVotingException
     */
    protected function submit()
    {
        $param_manager = ParamManager::getInstance();
        $liveVoting = LiveVoting::getLiveVotingFromPin($param_manager->getPin());
        $this->player = $liveVoting->getPlayer();

        $input_gui = $this->getTextInputGUI("", 'free_input');

        $this->getPlayer()->unvoteAll();
        if ($this->player->getActiveVotingObject()->isMultiFreeInput()) {
            $array = array();

            $inputs = filter_input(INPUT_POST, 'vote_multi_line_input', FILTER_DEFAULT, FILTER_FORCE_ARRAY);

            if (!empty($inputs)) {
                foreach ($inputs as $item) {
                    $input = LiveVotingUtils::secureString($item['free_input']);
                    if (!empty($input) && strlen($input) <= $input_gui->getMaxLength()) {
                        $array[] = array(
                            "input" => $input,
                            "vote_id" => $item['vote_id'],
                        );
                    }
                }
            }

            $this->player->input($array);
        } else {
            $input = LiveVotingUtils::secureString(filter_input(INPUT_POST, 'free_input'));
            if (!empty($input) && strlen($input) <= $input_gui->getMaxLength()) {
                $this->player->input(array(
                    "input" => $input,
                    "vote_id" => filter_input(INPUT_POST, 'vote_id'),
                ));
            }
        }
    }

    /**
     * @return string
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     * @throws LiveVotingException|ilCtrlException
     */
    public function getMobileHTML(): string
    {
        if ($this->isUsingNewUI()) {
            return $this->getNewUIHTML();
        }

        $this->tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/FreeInput/tpl.free_input.html', true, true);
        $this->render();

        if ($this->isUsingNewUI()) {
            $is_multi_input = $this->player->getActiveVotingObject()->isMultiFreeInput();
            if ($is_multi_input) {
                $this->tpl->setVariable(
                    'VOTER_HINT',
                    ilLiveVotingPlugin::getInstance()->txt('qtype_2_multi_free_input_info')
                );
            }
        }

        return $this->tpl->get() . LiveVotingJs::getInstance()->name('FreeInput')->category('QuestionTypes')->getRunCode();
    }

    /**
     * New UI: one field per answer. Enter opens the next field (multi input), and the form posts the same fields as
     * the classic widget, so submit() is shared. After submitting, the answers stay visible but locked.
     *
     * @throws LiveVotingException
     * @throws ilTemplateException
     */
    protected function getNewUIHTML(): string
    {
        global $DIC;

        $plugin = ilLiveVotingPlugin::getInstance();
        $question = $this->player->getActiveVotingObject();
        $is_multi = $question->isMultiFreeInput();
        $is_textarea = (int) $question->getAnswerField() === 2;
        $max_length = $is_textarea ? 1000 : 200;

        $answers = [];
        foreach ($this->player->getVotesOfUser() as $vote) {
            $text = (string) $vote->getFreeInput();
            if ($vote->isActive() && $text !== '') {
                $answers[] = ['text' => $text, 'vote_id' => (string) $vote->getId()];
            }
        }
        if (!$is_multi) {
            $answers = array_slice($answers, 0, 1);
        }
        $has_answered = $answers !== [];
        if (!$has_answered) {
            $answers[] = ['text' => '', 'vote_id' => ''];
        }

        $tpl = new ilTemplate($plugin->getDirectory() . '/templates/default/QuestionTypes/FreeInput/tpl.free_input_new.html', true, true);
        foreach ($answers as $index => $answer) {
            $field_id = 'xlvo_free_text_' . $index;
            $name = $is_multi ? 'vote_multi_line_input[' . $index . '][free_input]' : 'free_input';
            $attributes = 'id="' . $field_id . '" class="xlvo-free-text-field" name="' . $name . '" maxlength="' . $max_length . '"'
                . ' placeholder="' . htmlspecialchars($plugin->txt('voter_free_text_placeholder'), ENT_QUOTES) . '"'
                . ' autocomplete="off"' . ($has_answered ? ' disabled' : '');
            $value = htmlspecialchars($answer['text'], ENT_QUOTES, 'UTF-8', false);
            $field = $is_textarea
                ? '<textarea ' . $attributes . ' rows="3">' . $value . '</textarea>'
                : '<input type="text" ' . $attributes . ' value="' . $value . '">';

            $tpl->setCurrentBlock('row');
            $tpl->setVariable('FIELD_ID', $field_id);
            $tpl->setVariable('ROW_CLASS', $answer['text'] !== '' ? ' has-value' : '');
            $tpl->setVariable('FIELD_LABEL', $plugin->txt('qtype_2_input') . ' ' . ($index + 1));
            $tpl->setVariable('FIELD', $field);
            $tpl->setVariable('VOTE_ID_NAME', $is_multi ? 'vote_multi_line_input[' . $index . '][vote_id]' : 'vote_id');
            $tpl->setVariable('VOTE_ID', $answer['vote_id']);
            if ($is_multi) {
                $tpl->setVariable('REMOVE_LABEL', $plugin->txt('voter_remove_answer'));
            }
            $tpl->parseCurrentBlock();
        }

        if ($is_multi) {
            $tpl->setCurrentBlock('add');
            $tpl->setVariable('ADD_TEXT', $plugin->txt('voter_add_answer'));
            $tpl->parseCurrentBlock();
        }

        $tpl->setVariable('ACTION', $DIC->ctrl()->getFormAction($this));
        $tpl->setVariable('FORM_CLASS', $has_answered ? ' is-locked' : '');
        $tpl->setVariable('MULTI', $is_multi ? '1' : '0');
        $tpl->setVariable('NEXT_INDEX', (string) count($answers));
        $tpl->setVariable(
            'VOTER_HINT',
            $plugin->txt($is_multi ? ($is_textarea ? 'voter_hint_free_text_multi_area' : 'voter_hint_free_text_multi') : 'voter_hint_free_text')
        );
        $tpl->setVariable('FEEDBACK', $plugin->txt('voter_enter_answer_first'));
        $tpl->setVariable('SUBMIT_TEXT', $plugin->txt($is_multi ? 'voter_submit_answers' : 'voter_submit_answer'));
        $tpl->setVariable(
            'SUBMIT_LABEL',
            $plugin->txt($has_answered ? 'voter_submitted' : ($is_multi ? 'voter_submit_answers' : 'voter_submit_answer'))
        );
        $tpl->setVariable('SUBMIT_DISABLED', $has_answered ? 'disabled' : '');
        $tpl->setVariable('CHANGE_TEXT', $plugin->txt('voter_change_answer'));
        $tpl->setVariable('CHANGE_DISABLED', $has_answered ? '' : 'disabled');

        return '<div class="col-md-12 xlvo-free-input xlvo-free-input-new">' . $tpl->get() . '</div>';
    }

    private function isUsingNewUI(): bool
    {
        $live_voting = new LiveVoting($this->player->getObjId(), false);

        return $live_voting->getMode()->getMode() === LiveVotingMode::BASIC_MODE
            && $live_voting->usesNewUI();
    }


    /**
     * @param string $a_title
     * @param string $a_postvar
     *
     * @return ilTextInputGUI
     * @throws LiveVotingException
     */
    protected function getTextInputGUI(string $a_title = "", string $a_postvar = "")
    {
        switch (intval($this->player->getActiveVotingObject()->getAnswerField())) {
            case 2:
                $input_gui = new TextAreaInputGUI($a_title, $a_postvar);
                $input_gui->setMaxlength(1000);
                break;

            case 1:
            default:
                $input_gui = new ilTextInputGUI($a_title, $a_postvar);
                $input_gui->setMaxLength(200);
                break;
        }

        return $input_gui;
    }


    /**
     * @return ilPropertyFormGUI
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    protected function getForm(): ilPropertyFormGUI
    {
        if ($this->player->getActiveVotingObject()->isMultiFreeInput()) {
            return $this->getMultiForm();
        } else {
            return $this->getSingleForm();
        }
    }


    /**
     * @return ilPropertyFormGUI
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    protected function getSingleForm(): ilPropertyFormGUI
    {
        global $DIC;
        $form = new ilPropertyFormGUI();
        $form->setFormAction($DIC->ctrl()->getFormAction($this));
        $form->setId('xlvo_free_input');

        $votes = array_values($this->player->getVotesOfUser(true));
        $vote = array_shift($votes);

        $an = $this->getTextInputGUI(ilLiveVotingPlugin::getInstance()->txt('qtype_2_input'), 'free_input');
        $hi2 = new HiddenInputGUI('vote_id');

        if ($vote instanceof LiveVotingVote) {
            if ($vote->isActive()) {
                $an->setValue($vote->getFreeInput());

                // Same confirmation the multi-input variant shows, so the voter knows the answer was stored.
                $saved = new ilNonEditableValueGUI();
                $saved->setValue(ilLiveVotingPlugin::getInstance()->txt('qtype_2_your_input'));
                $form->addItem($saved);
            }
            $hi2->setValue((string)$vote->getId());
            //$form->addCommandButton(self::CMD_CLEAR, $this->txt(self::CMD_CLEAR));
        }

        $form->addItem($an);
        $form->addItem($hi2);
        $form->addCommandButton('submit', ilLiveVotingPlugin::getInstance()->txt('qtype_2_send'));

        return $form;
    }


    /**
     * @return ilPropertyFormGUI
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    protected function getMultiForm(): ilPropertyFormGUI
    {
        global $DIC;
        $form = new ilPropertyFormGUI();
        $gui = new LiveVotingPlayerGUI();
        $form->setFormAction($DIC->ctrl()->getFormAction($this));

        $xlvoVotes = $this->player->getVotesOfUser();
        if (count($xlvoVotes) > 0) {
            $te = new ilNonEditableValueGUI();
            $te->setValue(ilLiveVotingPlugin::getInstance()->txt('qtype_2_your_input'));
            $form->addItem($te);
            //$form->addCommandButton(self::CMD_CLEAR, $this->txt('delete_all'));
        }

        $mli = new MultiLineNewInputGUI(ilLiveVotingPlugin::getInstance()->txt('qtype_2_answers'), 'vote_multi_line_input');
        $mli->setShowInputLabel(MultiLineNewInputGUI::SHOW_INPUT_LABEL_NONE);
        $te = $this->getTextInputGUI(ilLiveVotingPlugin::getInstance()->txt('qtype_2_text'), 'free_input');

        $hi2 = new HiddenInputGUI('vote_id');
        $mli->addInput($te);
        $mli->addInput($hi2);

        $form->addItem($mli);
        $array = array();

        foreach ($xlvoVotes as $xlvoVote) {
            $array[] = array(
                'free_input' => $xlvoVote->getFreeInput(),
                'vote_id' => $xlvoVote->getId(),
            );
        }

        if (empty($array)) {
            // Without a seeded line the widget only renders a bare "+" glyph and the voter has no field to type in.
            $array[] = array(
                'free_input' => '',
                'vote_id' => '',
            );
        }

        $form->setValuesByArray(array('vote_multi_line_input' => $array));
        $form->addCommandButton('submit', ilLiveVotingPlugin::getInstance()->txt('qtype_2_send'));

        return $form;
    }


    /**
     * @return ilButtonBase[]
     */
    public function getButtonInstances(): array
    {
        if (!$this->player->isShowResults()) {
            return array();
        }

        $b = ilLinkButton::getInstance();
        $b->setId('btn_categorize');
        $b->setUrl('#');

        if (array_key_exists('btn_categorize', $this->getButtonsStates()) && $this->getButtonsStates()['btn_categorize'] == 'true') {
            $b->setCaption('<span class="glyphicon glyphicon-folder-close"></span>' . '&nbsp' . ilLiveVotingPlugin::getInstance()->txt('btn_categorize_done', 'btn'), false);
        } else {
            $b->setCaption('<span class="glyphicon glyphicon-folder-open"></span> ' . '&nbsp' . ilLiveVotingPlugin::getInstance()->txt('btn_categorize', 'btn'), false);
        }

        return array($b);
    }


    /**
     * @param $button_id
     * @param $data
     * @throws LiveVotingException
     */
    public function handleButtonCall($button_id, $data)
    {
        $data = (array_key_exists('btn_categorize', $this->getButtonsStates()) && $this->getButtonsStates()['btn_categorize'] == 'true') ? 'false' : 'true';
        $this->saveButtonState($button_id, $data);
    }


    /**
     *
     * @throws LiveVotingException|ilCtrlException
     */
    protected function render()
    {
        $form = $this->getForm();

        $this->tpl->setVariable('FREE_INPUT_FORM', $form->getHTML());
    }
}
