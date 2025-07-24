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
 * Class LiveVotingOrderQuestion
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingOrderQuestion extends LiveVotingQuestion
{
    private int $columns = 1;
    private bool $randomise_option_sequence = false;
    private bool $correct_order = false;

    public function __construct(?array $data = null)
    {
        parent::__construct($data);

        if ($data !== null) {
            $this->columns = (int)$data["columns"];
            $this->randomise_option_sequence = (bool)$data["randomise_option_sequence"];
        }
    }

    public function getQuestionType(): string
    {
        if ($this->correct_order) {
            return "CorrectOrder";
        } else {
            return "Priorities";
        }
    }

    public function getQuestionTypeLabel(): string
    {
        if ($this->correct_order) {
            return ilLiveVotingPlugin::getInstance()->txt("voting_type_4");
        } else {
            return ilLiveVotingPlugin::getInstance()->txt("voting_type_5");
        }
    }

    public function save(): int
    {
        $id = parent::save();

        $database = new LiveVotingDatabase();

        $data = array(
            "columns" => $this->columns
        );

        if ($this->correct_order) {
            $data["randomise_option_sequence"] = (int)$this->randomise_option_sequence;
        }

        $database->update("rep_robj_xlvo_voting_n", $data, array(
            "id" => $id
        ));

        return $id;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function setColumns(int $columns): void
    {
        $this->columns = $columns;
    }

    public function isRandomiseOptionSequence(): bool
    {
        return $this->randomise_option_sequence;
    }

    public function setRandomiseOptionSequence(bool $randomise_option_sequence): void
    {
        $this->randomise_option_sequence = $randomise_option_sequence;
    }

    public function isCorrectOrder(): bool
    {
        return $this->correct_order;
    }

    public function setCorrectOrder(bool $correct_order): void
    {
        $this->correct_order = $correct_order;
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

        $vote = array_shift($answer);

        if ($this->correct_order) {
            $plugin = ilLiveVotingPlugin::getInstance();

            $correct_order_json = $this->getCorrectOrderJSON();

            $return = ($correct_order_json == $vote->getFreeInput())
                ? $plugin->txt("common_correct_order")
                : $plugin->txt("common_incorrect_order");

            $return .= ": ";

            foreach (json_decode($vote->getFreeInput()) as $option_id) {
                foreach ($this->options as $option) {
                    if ($option->getId() == $option_id) {
                        $strings[] = $option->getText();
                    }
                }
            }

            return $return . implode(", ", $strings);
        } else {
            $json_decode = json_decode($vote->getFreeInput());

            if (!is_array($json_decode)) {
                return "";
            }

            foreach ($json_decode as $option_id) {
                foreach ($this->options as $option) {
                    if ($option->getId() == $option_id) {
                        $strings[] = $option->getText();
                    }
                }
            }

            return implode(", ", $strings);
        }
    }

    public function getCorrectOrderJSON(): string
    {
        $correct_order_ids = array();

        foreach ($this->options as $option) {
            $correct_order_ids[(int)$option->getCorrectPosition()] = (string)$option->getId();
        };

        ksort($correct_order_ids);

        return json_encode(array_values($correct_order_ids));
    }
}