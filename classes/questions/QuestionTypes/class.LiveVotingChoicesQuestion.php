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

namespace LiveVoting\questions\QuestionTypes;

use ilLiveVotingPlugin;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\questions\LiveVotingQuestion;

/**
 * Class LiveVotingChoicesQuestion
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingChoicesQuestion extends LiveVotingQuestion
{
    private bool $multi_selection = false;
    private int $columns = 1;

    public function __construct(?array $data = null)
    {
        parent::__construct($data);

        if ($data !== null) {
            $this->multi_selection = (bool)$data["multi_selection"];
            $this->columns = (int)$data["columns"];
        }
    }

    public function getQuestionType(): string
    {
        return "Choices";
    }

    public function getQuestionTypeLabel(): string
    {
        if ($this->multi_selection) {
            return ilLiveVotingPlugin::getInstance()->txt("multiple_choice");
        } else {
            return ilLiveVotingPlugin::getInstance()->txt("single_choice");
        }
    }

    public function save(): int
    {
        $id = parent::save();

        $database = new LiveVotingDatabase();

        $database->update("rep_robj_xlvo_voting_n", array(
            "multi_selection" => (int)$this->multi_selection,
            "columns" => $this->columns,
        ), array(
            "id" => $id
        ));

        return $id;
    }

    public function isMultiSelection(): bool
    {
        return $this->multi_selection;
    }

    public function setMultiSelection(bool $multi_selection): void
    {
        $this->multi_selection = $multi_selection;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function setColumns(int $columns): void
    {
        $this->columns = $columns;
    }

    public function getComputedColums(): float
    {
        return (12 / (in_array($this->getColumns(), array(
                1,
                2,
                3,
                4,
            )) ? $this->getColumns() : 1));
    }

    function getVotesRepresentation(array $answer): string
    {
        if (empty($answer)) {
            return "";
        }

        $strings = array();

        foreach ($answer as $vote) {
            foreach ($this->options as $option) {
                if ($option->getId() == $vote->getOptionId()) {
                    $strings[] = $option->getText();
                }
            }
        }

        return implode(", ", $strings);
    }
}