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

namespace LiveVoting\questions;

use ilLegacyFormElementsUtil;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;

/**
 * Class LiveVotingQuestionOption
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingQuestionOption
{
    private int $id = 0;
    private int $voting_id = 0;
    private ?string $text = null;
    private int $type;
    private int $status = 1;
    private ?int $position = null;
    private ?int $correct_position = null;

    public function __construct(?array $data = null)
    {
        if ($data !== null) {
            $this->id = isset($data["id"]) ? (int)$data["id"] : 0;
            $this->voting_id = isset($data["voting_id"]) ? (int)$data["voting_id"] : 0;
            $this->text = $data["text"] ?? null;
            $this->type = (int)$data["type"];
            $this->status = isset($data["status"]) ? (int)$data["status"] : 1;
            $this->position = isset($data["position"]) ? (int)$data["position"] : null;
            $this->correct_position = isset($data["correct_position"]) ? (int)$data["correct_position"] : null;
        }
    }

    /**
     * @throws LiveVotingException
     */
    public static function loadOptionById(int $id): ?LiveVotingQuestionOption
    {
        $option = null;

        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_option_n", array(
            "id" => $id
        ));

        if ($result && isset($result[0])) {
            $option = new LiveVotingQuestionOption($result[0]);
        }

        return $option;
    }

    /**
     * @throws LiveVotingException
     */
    public static function loadAllOptionsByVotingId(int $voting_id, bool $correct_position = false): array
    {
        $options = array();

        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_option_n", array(
            "voting_id" => $voting_id
        ), null, "ORDER BY " . ($correct_position ? "correct_position, position" : "position"));

        if ($result) {
            foreach ($result as $row) {
                $options[] = new LiveVotingQuestionOption($row);
            }
        }

        return $options;
    }

    public static function loadNewOption(int $type): ?LiveVotingQuestionOption
    {
        $option = new LiveVotingQuestionOption();

        $option->setType($type);

        return $option;
    }

    /**
     * @throws LiveVotingException
     */
    public function delete(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_option_n", array(
            "id" => $this->id
        ));
    }

    /**
     * @throws LiveVotingException
     */
    public function save(): int
    {
        $database = new LiveVotingDatabase();

        if ($this->id != 0) {
            $database->update("rep_robj_xlvo_option_n", array(
                "voting_id" => $this->voting_id,
                "text" => $this->text,
                "type" => $this->type,
                "status" => $this->status,
                "position" => $this->position,
                "correct_position" => $this->correct_position
            ), array(
                "id" => $this->id
            ));
        } else {
            $this->id = $database->nextId("rep_robj_xlvo_option_n");

            $database->insert("rep_robj_xlvo_option_n", array(
                "id" => $this->id,
                "voting_id" => $this->voting_id,
                "text" => $this->text,
                "type" => $this->type,
                "status" => $this->status,
                "position" => $this->position,
                "correct_position" => $this->correct_position
            ));
        }

        return $this->id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getVotingId(): int
    {
        return $this->voting_id;
    }

    public function setVotingId(int $voting_id): void
    {
        $this->voting_id = $voting_id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getTextForPresentation(): string
    {
        return ilLegacyFormElementsUtil::prepareTextareaOutput(htmlentities($this->text), true);
    }

    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getCorrectPosition(): ?int
    {
        return $this->correct_position;
    }

    public function setCorrectPosition(?int $correct_position): void
    {
        $this->correct_position = $correct_position;
    }

    public function getCipher(): string
    {
        return chr($this->getPosition() + 64);
    }

    public function copy(): LiveVotingQuestionOption
    {
        $option = clone $this;

        $option->setId(0);

        return $option;
    }
}