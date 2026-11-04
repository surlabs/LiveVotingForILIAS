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

use ilTemplateException;
use LiveVoting\objects\modes\LiveVotingMode;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\Voting\Bar\LiveVotingBarCollectionUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarPercentageUI;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingSingleVoteResultsUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingSingleVoteResultsUI extends LiveVotingInputResultsGUI
{
    /**
     * @return string
     * @throws LiveVotingException|ilTemplateException
     */
    public function getHTML(): string
    {
        if ($this->player->getActiveVotingObject()->isMultiSelection()) {
            return $this->getHTMLMulti();
        } else {
            return $this->getHTMLSingle();
        }
    }


    /**
     * @return string
     * @throws LiveVotingException
     * @throws ilTemplateException
     */
    protected function getHTMLSingle(): string
    {
        $total_votes = LiveVotingVote::countVotes($this->player->getActiveVoting(), $this->player->getRoundId());
        $voters = LiveVotingVote::countVoters($this->player->getActiveVoting(), $this->player->getRoundId());

        $bars = new LiveVotingBarCollectionUI($this->isUsingNewUI());
        $bars->setShowTotalVoters($this->isUsingNewUI());
        $bars->setTotalVoters($voters);
        $bars->setShowTotalVotes(true);
        $bars->setTotalVotes($this->isUsingNewUI() ? $total_votes : $voters);


        foreach ($this->player->getActiveVotingObject()->getOptions() as $xlvoOption) {
            $xlvoBarPercentageGUI = new LiveVotingBarPercentageUI();
            $xlvoBarPercentageGUI->setUseNewUI($this->isUsingNewUI());
            $xlvoBarPercentageGUI->setOptionLetter($xlvoOption->getCipher());
            $xlvoBarPercentageGUI->setTitle($xlvoOption->getTextForPresentation());
            $xlvoBarPercentageGUI->setVotes(count(LiveVotingVote::getVotesOfOption($xlvoOption->getId(), $this->player->getRoundId())));
            $xlvoBarPercentageGUI->setMaxVotes($voters);
            $xlvoBarPercentageGUI->setShowInPercent(!$this->isShowAbsolute());
            $bars->addBar($xlvoBarPercentageGUI);
        }

        return $bars->getHTML();
    }


    /**
     * @return string
     * @throws LiveVotingException
     * @throws ilTemplateException
     */
    protected function getHTMLMulti(): string
    {
        $total_votes = LiveVotingVote::countVotes($this->player->getActiveVoting(), $this->player->getRoundId());
        $voters = LiveVotingVote::countVoters($this->player->getActiveVoting(), $this->player->getRoundId());

        $bars = new LiveVotingBarCollectionUI($this->isUsingNewUI());
        $bars->setShowTotalVoters($this->isUsingNewUI());
        $bars->setTotalVoters($voters);
        $bars->setShowTotalVotes($this->player->getActiveVotingObject()->isMultiSelection());
        $bars->setTotalVotes($total_votes);

        foreach ($this->player->getActiveVotingObject()->getOptions() as $xlvoOption) {
            $xlvoBarPercentageGUI = new LiveVotingBarPercentageUI();
            $xlvoBarPercentageGUI->setUseNewUI($this->isUsingNewUI());
            $xlvoBarPercentageGUI->setOptionLetter($xlvoOption->getCipher());
            $xlvoBarPercentageGUI->setTitle($xlvoOption->getTextForPresentation());
            $xlvoBarPercentageGUI->setVotes(count(LiveVotingVote::getVotesOfOption($xlvoOption->getId(), $this->player->getRoundId())));
            // The new UI answers "what share of participants picked this option?", so it divides by voters.
            $xlvoBarPercentageGUI->setMaxVotes($this->isUsingNewUI() ? $voters : $total_votes);
            $xlvoBarPercentageGUI->setShowInPercent(!$this->isShowAbsolute());
            $bars->addBar($xlvoBarPercentageGUI);
        }

        return $bars->getHTML();
    }


    /**
     * @return array
     */
    protected function getButtonsStates(): array
    {
        return $this->player->getButtonStates();
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
