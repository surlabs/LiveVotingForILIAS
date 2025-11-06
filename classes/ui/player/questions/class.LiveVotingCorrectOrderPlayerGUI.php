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


use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestionOption;
use LiveVoting\UI\Voting\Bar\LiveVotingBarMovableUI;
use LiveVoting\Utils\LiveVotingJs;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingVote;


/**
 * Class LiveVotingCorrectOrderPlayerGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_isCalledBy LiveVotingCorrectOrderPlayerGUI: ilUIPluginRouterGUI, LiveVotingPlayerGUI
 * @ilCtrl_Calls LiveVotingCorrectOrderPlayerGUI: LiveVotingPlayerGUI, ilUIPluginRouterGUI
 */
class LiveVotingCorrectOrderPlayerGUI extends LiveVotingQuestionTypesUI
{

    const BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER = 'display_correct_order';
    const BUTTON_TOGGLE_PERCENTAGE = 'toggle_percentage';

    /**
     * @return string
     */
    public function getMobileHTML(): string
    {
        return $this->getFormContent() . LiveVotingJs::getInstance()->name('CorrectOrder')->category('QuestionTypes/CorrectOrder')->getRunCode();
    }


    /**
     * @param bool $current
     * @throws ilCtrlException
     */
    public function initJS(bool $current = false)
    {
        $gui = new LiveVotingPlayerGUI();
        LiveVotingJs::getInstance()->api($gui)->name('CorrectOrder')->category('QuestionTypes/CorrectOrder')
            ->addLibToHeader('jquery.ui.touch-punch.min.js')->init();
    }


    /**
     *
     * @throws LiveVotingException
     */
    protected function submit(): void
    {
        $param_manager = ParamManager::getInstance();
        $liveVoting = LiveVoting::getLiveVotingFromPin($param_manager->getPin());
        $this->player = $liveVoting->getPlayer();

        $this->player->input(array(
            "input" => json_encode($_POST['id']),
            "vote_id" => $_POST['vote_id']
        ));
    }

    /**
     * @throws LiveVotingException
     * @throws ilCtrlException
     */
    protected function clear(): void
    {
        $param_manager = ParamManager::getInstance();
        $liveVoting = LiveVoting::getLiveVotingFromPin($param_manager->getPin());
        $this->player = $liveVoting->getPlayer();

        $this->player->unvoteAll();

        $this->afterSubmit();
    }

    /**
     * @return string
     * @throws ilCtrlException|ilTemplateException
     * @throws LiveVotingException
     * @throws ilSystemStyleException
     */
    protected function getFormContent(): string
    {
        global $DIC;
        $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/FreeOrder/tpl.free_order.html', true, false);
        $tpl->setVariable('ACTION', $DIC->ctrl()->getFormAction($this));
        $tpl->setVariable('ID', 'xlvo_sortable');
        $tpl->setVariable('BTN_RESET', ilLiveVotingPlugin::getInstance()->txt('qtype_4_clear'));
        $tpl->setVariable('BTN_SAVE', ilLiveVotingPlugin::getInstance()->txt('qtype_4_save'));

        $votes = array_values($this->player->getVotesOfUser());
        $vote = array_shift($votes);
        $order = array();
        $vote_id = null;
        if ($vote instanceof LiveVotingVote) {
            $order = json_decode($vote->getFreeInput());
            $vote_id = $vote->getId();
        }
        if (!$vote_id) {
            $tpl->setVariable('BTN_RESET_DISABLED', 'disabled="disabled"');
        }

        $options = $this->getPlayer()->getActiveVotingObject()->getOptions();
        if ($this->isRandomizeOptions()) {
            //randomize the options for the voters
            $options = $this->randomizeWithoutCorrectSequence($options);
        }
        $bars = new LiveVotingBarMovableUI($options, $order, $vote_id);
        $bars->setShowOptionLetter(true);
        $tpl->setVariable('CONTENT', $bars->getHTML());

        if ($this->isShowCorrectOrder() && $this->getPlayer()->getActiveVotingObject()->isCorrectOrder()) {
            $correct_order = $this->getCorrectOrder();
            $solution_html = '<p>' . ilLiveVotingPlugin::getInstance()->txt('qtype_4_correct_solution');

            foreach ($correct_order as $item) {
                $solution_html .= ' <span class="label label-primary">' . $item->getCipher() . '</span>';
            }
            $solution_html .= '</p>';
            $tpl->setVariable('YOUR_SOLUTION', $solution_html);
        }

        return $tpl->get();
    }


    /**
     * @return array
     */
    public function getButtonInstances(): array
    {
        if (!$this->getPlayer()->isShowResults()) {
            return array();
        }
        $states = $this->getButtonsStates();
        $b = ilLinkButton::getInstance();
        $b->setId(self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER);
        if (array_key_exists(self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER, $states) && $states[self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER]) {
            $b->setCaption('<span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span>', false);
        } else {
            $b->setCaption('<span class="glyphicon glyphicon-eye-close" aria-hidden="true"></span>', false);
        }


        $t = ilLinkButton::getInstance();
        $t->setId(self::BUTTON_TOGGLE_PERCENTAGE);
        if (array_key_exists(self::BUTTON_TOGGLE_PERCENTAGE, $states) && $states[self::BUTTON_TOGGLE_PERCENTAGE]) {
            $t->setCaption(' %', false);
        } else {
            $t->setCaption('<span class="glyphicon glyphicon-user" aria-hidden="true"></span>', false);
        }

        return array($b, $t);
    }


    /**
     * @return bool
     */
    protected function isShowCorrectOrder(): bool
    {
        $states = $this->getButtonsStates();

        return ((bool)array_key_exists(self::BUTTON_TOTTLE_DISPLAY_CORRECT_ORDER, $states) && $this->getPlayer()->isShowResults());
    }


    /**
     * @param $button_id
     * @param $data
     * @throws LiveVotingException
     */
    public function handleButtonCall($button_id, $data)
    {
        $states = $this->getButtonsStates();
        $this->saveButtonState($button_id, !(array_key_exists($button_id, $states) && $states[$button_id]));
    }


    /**
     * Checks whether the options displayed to the voter is randomized.
     *
     * @return bool
     */
    protected function isRandomizeOptions(): bool
    {
        return $this->getPlayer()->getActiveVotingObject()->isRandomiseOptionSequence();
    }


    /**
     * @return LiveVotingQuestionOption[]
     * @throws LiveVotingException
     */
    protected function getCorrectOrder(): array
    {
        $correct_order = array();
        $options = $this->getPlayer()->getActiveVotingObject()->getOptions();
        foreach ($options as $xlvoOption) {
            $correct_order[(int)$xlvoOption->getCorrectPosition()] = $xlvoOption;
        };
        ksort($correct_order);

        return $correct_order;
    }


    /**
     * Randomizes an array of xlvoOption.
     * This function never returns the correct sequence of options.
     *
     * @param LiveVotingQuestionOption[] $options The options which should get randomized.
     *
     * @return LiveVotingQuestionOption[] The randomized option array.
     */
    private function randomizeWithoutCorrectSequence(array &$options): array
    {
        if (count($options) < 2) {
            return $options;
        }

        //shuffle array items (can't use the PHP shuffle function because the keys are not preserved.)
        $optionsClone = $this->shuffleArray($options);

        foreach ($optionsClone as $key => $option) {
            $option->setPosition($key + 1);
        }

        $lastCorrectPosition = 0;

        /**
         * @var LiveVotingQuestionOption $option
         */
        foreach ($optionsClone as $option) {
            //get correct item position
            $currentCurrentPosition = $option->getCorrectPosition();

            //calculate the difference
            $difference = $lastCorrectPosition - $currentCurrentPosition;
            $lastCorrectPosition = $currentCurrentPosition;

            //check if we shuffled the correct answer by accident.
            //the correct answer would always produce a difference of -1.
            //1 - 2 = -1, 2 - 3 = -1, 3 - 4 = -1 ...
            if ($difference !== -1) {
                return $optionsClone;
            }
        }

        //try to shuffle again because we got the right answer by accident.
        //we pass the original array, this should enable php to drop the array clone out of the memory.
        return $this->randomizeWithoutCorrectSequence($options);
    }


    /**
     * Shuffles the array given array the keys are preserved.
     * Please note that the array passed into this method get never modified.
     *
     * @param array $array The array which should be shuffled.
     *
     * @return array The newly shuffled array.
     */
    private function shuffleArray(array &$array): array
    {
        $clone = $this->cloneArray($array);
        $shuffledArray = [];

        while (count($clone) > 0) {
            $key = array_rand($clone);
            $shuffledArray[$key] = &$clone[$key];
            unset($clone[$key]);
        }

        return $shuffledArray;
    }


    /**
     * Create a shallow copy of the given array.
     *
     * @param array $array The array which should be copied.
     *
     * @return array    The newly created shallow copy of the given array.
     */
    private function cloneArray(array &$array): array
    {
        $clone = [];
        foreach ($array as $key => $value) {
            $clone[$key] = &$array[$key]; //get the ref on the array value not the foreach value.
        }

        return $clone;
    }
}