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

namespace LiveVoting\UI\QuestionsResults;

use ilLiveVotingPlugin;
use ilTemplateException;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestionOption;
use LiveVoting\UI\Voting\Bar\LiveVotingBarCollectionUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarPercentageUI;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingInputCorrectOrderUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInputCorrectOrderUI extends LiveVotingSingleVoteResultsUI
{
    /**
     * @return string
     * @throws LiveVotingException|ilTemplateException
     */
    public function getHTML(): string
    {
        $bars = new LiveVotingBarCollectionUI($this->isUsingNewUI());

        $correct_order = array();
        $correct_order_json = $this->player->getActiveVotingObject()->getCorrectOrderJSON();

        $options = $this->player->getActiveVotingObject()->getOptions();

        foreach (json_decode($correct_order_json) as $value) {
            foreach ($options as $option) {
                if ($option->getId() == (int)$value) {
                    $correct_order[] = $option;
                    break;
                }
            }
        }

        $votes = LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId());
        $correct_votes = 0;
        $wrong_votes = 0;
        foreach ($votes as $xlvoVote) {
            if ($xlvoVote->getFreeInput() == $correct_order_json) {
                $correct_votes++;
            } else {
                $wrong_votes++;
            }
        }

        $correct_option = new LiveVotingQuestionOption();
        $correct_option->setText(ilLiveVotingPlugin::getInstance()->txt('qtype_4_correct'));
        $bar = new LiveVotingBarPercentageUI();
        $bar->setUseNewUI($this->isUsingNewUI());
        $bar->setColorIndex(2); // correct answers: green in the new palette
        $bar->setTitle($correct_option->getTextForPresentation());
        $bar->setVotes($correct_votes);
        $bar->setMaxVotes(LiveVotingVote::countVoters($this->player->getActiveVoting(), $this->player->getRoundId()));
        $bar->setShowInPercent(!$this->isShowAbsolute());

        $bars->addBar($bar);

        $wrong_option = new LiveVotingQuestionOption();
        $wrong_option->setText(ilLiveVotingPlugin::getInstance()->txt('qtype_4_wrong'));

        $bar = new LiveVotingBarPercentageUI();
        $bar->setUseNewUI($this->isUsingNewUI());
        $bar->setColorIndex(4); // wrong answers: red in the new palette
        $bar->setMaxVotes(LiveVotingVote::countVoters($this->player->getActiveVoting(), $this->player->getRoundId()));
        $bar->setTitle($wrong_option->getTextForPresentation());
        $bar->setVotes($wrong_votes);
        $bar->setShowInPercent(!$this->isShowAbsolute());

        $bars->addBar($bar);

        $bars->setShowTotalVotes(true);
        $bars->setTotalVotes(LiveVotingVote::countVotes($this->player->getActiveVoting(), $this->player->getRoundId()));
        if ($this->isShowCorrectOrder()) {
            $solution_html = ilLiveVotingPlugin::getInstance()->txt('qtype_4_correct_solution') . '<br>';
            /**
             * @var LiveVotingQuestionOption $item
             */
            foreach ($correct_order as $item) {
                $solution_html .= ' <p><div class="xlvo-option"><span class="label label-primary xlvo-option_letter">' . $item->getCipher()
                    . '</span> <span class="option_text">' . $item->getTextForPresentation() . '</span></div></p>';
            }
            $bars->addSolution($solution_html);
        }

        return $bars->getHTML();
    }


    /**
     * @return bool
     */
    protected function isShowCorrectOrder(): bool
    {
        $states = $this->getButtonsStates();

        return ((array_key_exists('display_correct_order', $states) && $states['display_correct_order']) && $this->player->isShowResults());
    }


    /**
     * @return bool
     */
    protected function isShowAbsolute(): bool
    {
        $states = $this->getButtonsStates();

        return ($this->player->isShowResults() && (bool)(array_key_exists('toggle_percentage', $states) && $states['toggle_percentage']));
    }

}
