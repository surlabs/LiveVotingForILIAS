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
 * Class LiveVotingNumberRangeQuestion
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingNumberRangeQuestion extends LiveVotingQuestion
{
    private bool $percentage = true;
    private int $start_range = 0;
    private int $end_range = 100;
    private int $step_range = 1;
    private ?int $alt_result_display_mode = null;

    public function __construct(?array $data = null)
    {
        parent::__construct($data);

        if ($data !== null) {
            $this->percentage = (bool)$data["percentage"];
            $this->start_range = (int)$data["start_range"];
            $this->end_range = (int)$data["end_range"];
            $this->step_range = (int)$data["step_range"];
            $this->alt_result_display_mode = isset($data["alt_result_display_mode"]) ? (int)$data["alt_result_display_mode"] : null;
        }
    }

    public function getQuestionType(): string
    {
        return "NumberRange";
    }

    public function getQuestionTypeLabel(): string
    {
        return ilLiveVotingPlugin::getInstance()->txt("voting_type_6");
    }

    public function save(): int
    {
        $id = parent::save();

        $database = new LiveVotingDatabase();

        $database->update("rep_robj_xlvo_voting_n", array(
            "percentage" => (int)$this->percentage,
            "start_range" => $this->start_range,
            "end_range" => $this->end_range,
            "step_range" => $this->step_range,
            "alt_result_display_mode" => $this->alt_result_display_mode,
        ), array(
            "id" => $id
        ));

        return $id;
    }

    public function isPercentage(): bool
    {
        return $this->percentage;
    }

    public function setPercentage(bool $percentage): void
    {
        $this->percentage = $percentage;
    }

    public function getStartRange(): int
    {
        return $this->start_range;
    }

    public function setStartRange(int $start_range): void
    {
        $this->start_range = $start_range;
    }

    public function getEndRange(): int
    {
        return $this->end_range;
    }

    public function setEndRange(int $end_range): void
    {
        $this->end_range = $end_range;
    }

    public function getStepRange(): int
    {
        return $this->step_range;
    }

    public function setStepRange(int $step_range): void
    {
        $this->step_range = $step_range;
    }

    public function getAltResultDisplayMode(): ?int
    {
        return $this->alt_result_display_mode;
    }

    public function setAltResultDisplayMode(?int $alt_result_display_mode): void
    {
        $this->alt_result_display_mode = $alt_result_display_mode;
    }

    public function getComputedColums(): float
    {
        return 12;
    }

    function getVotesRepresentation(array $answer): string
    {
        if (empty($answer)) {
            return "";
        }

        $testVotes = [];

        foreach ($answer as $vote) {
            $percentage = $this->isPercentage() ? ' %' : '';
            $testVotes[] = "{$vote->getFreeInput()}{$percentage}";
        }

        return implode(', ', $testVotes);
    }
}