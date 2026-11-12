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

use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingPlayer;

/**
 * Class LiveVotingInputResultsGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
abstract class LiveVotingInputResultsGUI
{

    /**
     * @var LiveVotingPlayer
     */
    protected LiveVotingPlayer $player;

    /**
     * LiveVotingInputResultsGUI constructor.
     *
     * @param LiveVotingPlayer $player
     */
    public function __construct(LiveVotingPlayer $player)
    {

        $this->player = $player;
    }

    /**
     * void method to add necessary JS and CSS to maintemplate
     */
    public static function addJsAndCss(): void
    {
    }

    /**
     * @throws LiveVotingException
     */
    public function reset(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_vote_n", array(
            "voting_id" => $this->player->getActiveVoting(),
            "round_id" => $this->player->getRoundId()
        ));
    }

    /**
     * @throws LiveVotingException
     */
    public static function getInstance(LiveVotingPlayer $player)
    {
        switch ($player->getActiveVotingObject()->getQuestionType()) {
            case "Choices":
                return new LiveVotingSingleVoteResultsUI($player);
            case "FreeText":
                return new LiveVotingInputFreeTextUI($player);
            case "WordCloud":
                return new LiveVotingInputWordCloudUI($player);
            case "CorrectOrder":
                return new LiveVotingInputCorrectOrderUI($player);
            case "Priorities":
                return new LiveVotingInputPrioritiesUI($player);

            case "NumberRange":
                return new LiveVotingInputNumberRangeUI($player);
            default:
                throw new LiveVotingException("Could not find the results gui for the given voting");
        }
    }

}
