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
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\Voting\Bar\LiveVotingBarCollectionUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarPercentageUI;
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

        $bars = new LiveVotingBarCollectionUI();
        $bars->setShowTotalVoters(false);
        $bars->setTotalVoters($voters);
        $bars->setShowTotalVotes(true);
        $bars->setTotalVotes($voters);


        foreach ($this->player->getActiveVotingObject()->getOptions() as $xlvoOption) {
            $xlvoBarPercentageGUI = new LiveVotingBarPercentageUI();
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

        $bars = new LiveVotingBarCollectionUI();
        $bars->setShowTotalVoters(false);
        $bars->setTotalVoters($voters);
        $bars->setShowTotalVotes($this->player->getActiveVotingObject()->isMultiSelection());
        $bars->setTotalVotes($total_votes);

        foreach ($this->player->getActiveVotingObject()->getOptions() as $xlvoOption) {
            $xlvoBarPercentageGUI = new LiveVotingBarPercentageUI();
            $xlvoBarPercentageGUI->setOptionLetter($xlvoOption->getCipher());
            $xlvoBarPercentageGUI->setTitle($xlvoOption->getTextForPresentation());
            $xlvoBarPercentageGUI->setVotes(count(LiveVotingVote::getVotesOfOption($xlvoOption->getId(), $this->player->getRoundId())));
            $xlvoBarPercentageGUI->setMaxVotes($total_votes);
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
        return ($this->player->isShowResults() && (bool)in_array('toggle_percentage', $states));
    }

}
