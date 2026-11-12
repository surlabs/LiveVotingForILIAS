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
use LiveVoting\UI\Voting\Bar\LiveVotingBarCollectionUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarPercentageUI;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingInputPrioritiesUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInputPrioritiesUI extends LiveVotingInputCorrectOrderUI
{
    /**
     * @return string
     * @throws LiveVotingException|ilTemplateException
     */
    public function getHTML(): string
    {
        $bars = new LiveVotingBarCollectionUI();
        $total_voters = LiveVotingVote::countVoters($this->player->getActiveVoting(), $this->player->getRoundId());
        $bars->setTotalVoters($total_voters);
        $bars->setShowTotalVoters(true);

        $option_amount = count($this->player->getActiveVotingObject()->getOptions());
        $option_weight = array();
        foreach ($this->player->getActiveVotingObject()->getOptions() as $option) {
            $option_weight[$option->getId()] = 0;
        }

        foreach (LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId()) as $xlvoVote) {
            $option_amount2 = $option_amount;
            $json_decode = json_decode($xlvoVote->getFreeInput(), true);
            if (is_array($json_decode)) {
                foreach ($json_decode as $option_id) {
                    $option_weight[$option_id] = (array_key_exists($option_id, $option_weight) ? $option_weight[$option_id] : 0) + $option_amount2;
                    $option_amount2--;
                }
            }
        }

        // Every bar is scaled against the current leader, so the ranking reads at a glance.
        $possible_max = (int) max(1, max($option_weight ?: [0]));
        $color_slots = array();
        foreach ($this->player->getActiveVotingObject()->getOptions() as $option) {
            $color_slots[$option->getId()] = count($color_slots) + 1;
        }
        if (LiveVotingVote::hasVotes($this->player->getActiveVoting(), $this->player->getRoundId())) {
            $unsorted_options = $this->player->getActiveVotingObject()->getOptions();
            $options = array();
            arsort($option_weight);
            foreach ($option_weight as $option_id => $weight) {
                foreach ($unsorted_options as $option) {
                    if ($option->getId() == $option_id) {
                        $options[] = $option;
                        break;
                    }
                }
            }
        } else {
            $options = $this->player->getActiveVotingObject()->getOptions();
        }

        // Add bars
        foreach ($options as $xlvoOption) {
            $xlvoBarPercentageGUI = new LiveVotingBarPercentageUI();
            // Keep each option's colour while the bars re-sort.
            $xlvoBarPercentageGUI->setColorIndex($color_slots[$xlvoOption->getId()] ?? 0);
            $xlvoBarPercentageGUI->setUnit(ilLiveVotingPlugin::getInstance()->txt('qtype_5_points_short'));
            $xlvoBarPercentageGUI->setRound(2);
            $xlvoBarPercentageGUI->setShowInPercent(false);
            $xlvoBarPercentageGUI->setMaxVotes($possible_max);
            $xlvoBarPercentageGUI->setTitle($xlvoOption->getTextForPresentation());
            $xlvoBarPercentageGUI->setVotes($total_voters == 0 ? 0 : $option_weight[$xlvoOption->getId()]);
            $xlvoBarPercentageGUI->setOptionLetter($xlvoOption->getCipher());

            $bars->addBar($xlvoBarPercentageGUI);
        }

        return '<div class="xlvo-priorities-results">' . $bars->getHTML() . '</div>';
    }

}
