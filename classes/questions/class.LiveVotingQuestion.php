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
use ilObjectTypeMismatchException;
use ilRTE;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\QuestionTypes\LiveVotingChoicesQuestion;
use LiveVoting\questions\QuestionTypes\LiveVotingFreeTextQuestion;
use LiveVoting\questions\QuestionTypes\LiveVotingNumberRangeQuestion;
use LiveVoting\questions\QuestionTypes\LiveVotingOrderQuestion;

/**
 * Abstract Class LiveVotingQuestion
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
abstract class LiveVotingQuestion
{
    const QUESTION_TYPES_IDS = array(
        "Choices" => 1,
        "FreeText" => 2,
        "CorrectOrder" => 4,
        "Priorities" => 5,
        "NumberRange" => 6,
    );

    protected int $id = 0;
    protected int $obj_id = 0;
    protected string $title = "";
    protected string $question = "";
    protected ?int $position = null;
    protected int $voting_status = 5;
    protected array $options = array();
    private LiveVotingQuestionOption $first_option;

    public function __construct(?array $data = null)
    {
        if ($data !== null) {
            $this->id = (int)$data["id"];
            $this->obj_id = (int)$data["obj_id"];
            $this->title = (string)$data["title"];
            $this->question = (string)$data["question"];
            $this->position = (int)$data["position"];
            $this->voting_status = (int)$data["voting_status"];
            $this->options = $data["options"];

            if (!empty($this->options)) {
                $this->setFirstOption($this->options[0]);
            }
        }
    }

    public abstract function getQuestionType(): string;

    public function getQuestionTypeId(): int
    {
        return self::QUESTION_TYPES_IDS[$this->getQuestionType()];
    }

    /**
     * @throws LiveVotingException
     */
    public static function loadAllQuestionsByObjectId(int $obj_id): array
    {
        $questions = array();

        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_voting_n", array(
            "obj_id" => $obj_id
        ), null, "ORDER BY position ASC");

        if ($result) {
            foreach ($result as $row) {
                $row["options"] = LiveVotingQuestionOption::loadAllOptionsByVotingId((int)$row["id"]);

                switch ($row["voting_type"]) {
                    case self::QUESTION_TYPES_IDS["Choices"]:
                        $question = new LiveVotingChoicesQuestion($row);
                        break;
                    case self::QUESTION_TYPES_IDS["FreeText"]:
                        $question = new LiveVotingFreeTextQuestion($row);
                        break;
                    case self::QUESTION_TYPES_IDS["CorrectOrder"]:
                        $question = new LiveVotingOrderQuestion($row);
                        $question->setCorrectOrder(true);
                        break;
                    case self::QUESTION_TYPES_IDS["Priorities"]:
                        $question = new LiveVotingOrderQuestion($row);
                        break;
                    case self::QUESTION_TYPES_IDS["NumberRange"]:
                        $question = new LiveVotingNumberRangeQuestion($row);
                        break;
                }

                $questions[] = $question;
            }
        }

        return $questions;
    }

    /**
     * @throws LiveVotingException
     */
    public static function loadQuestionById(int $id): ?LiveVotingQuestion
    {
        $question = null;

        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_voting_n", array(
            "id" => $id
        ));


        if ($result && isset($result[0])) {
            $result[0]["options"] = LiveVotingQuestionOption::loadAllOptionsByVotingId($id);

            switch ($result[0]["voting_type"]) {
                case self::QUESTION_TYPES_IDS["Choices"]:
                    $question = new LiveVotingChoicesQuestion($result[0]);
                    break;
                case self::QUESTION_TYPES_IDS["FreeText"]:
                    $question = new LiveVotingFreeTextQuestion($result[0]);
                    break;
                case self::QUESTION_TYPES_IDS["CorrectOrder"]:
                    $question = new LiveVotingOrderQuestion($result[0]);
                    $question->setCorrectOrder(true);
                    break;
                case self::QUESTION_TYPES_IDS["Priorities"]:
                    $question = new LiveVotingOrderQuestion($result[0]);
                    break;
                case self::QUESTION_TYPES_IDS["NumberRange"]:
                    $question = new LiveVotingNumberRangeQuestion($result[0]);
                    break;
            }
        }

        return $question;
    }

    /**
     * @throws LiveVotingException
     */
    public static function loadNewQuestion(string $type): ?LiveVotingQuestion
    {
        if (!array_key_exists($type, self::QUESTION_TYPES_IDS)) {
            throw new LiveVotingException("Invalid question type");
        }

        $question = null;

        switch ($type) {
            case "Choices":
                $question = new LiveVotingChoicesQuestion();
                break;
            case "FreeText":
                $question = new LiveVotingFreeTextQuestion();
                break;
            case "CorrectOrder":
                $question = new LiveVotingOrderQuestion();
                $question->setCorrectOrder(true);
                break;
            case "Priorities":
                $question = new LiveVotingOrderQuestion();
                break;
            case "NumberRange":
                $question = new LiveVotingNumberRangeQuestion();
                break;
        }

        return $question;
    }

    /**
     * @throws LiveVotingException
     */
    public function delete(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_voting_n", array(
            "id" => $this->id
        ));
        $database->delete("rep_robj_xlvo_option_n", array(
            "voting_id" => $this->id
        ));
        $database->delete("rep_robj_xlvo_vote_n", array(
            "voting_id" => $this->id
        ));
    }

    /**
     * In this method we only save common data for all question types, specific data is saved in the child classes
     *
     * @throws LiveVotingException
     */
    public function save(): int
    {
        $database = new LiveVotingDatabase();

        if ($this->id != 0) {
            $database->update("rep_robj_xlvo_voting_n", array(
                "obj_id" => $this->obj_id,
                "title" => $this->title,
                "question" => $this->question,
                "voting_status" => $this->voting_status,
                "position" => $this->position
            ), array(
                "id" => $this->id
            ));
        } else if ($this->obj_id != 0) {
            $this->id = $database->nextId("rep_robj_xlvo_voting_n");

            if ($this->position == null || $this->position == 0) {
                $this->position = $this->generateNewPosition();
            }

            $database->insert("rep_robj_xlvo_voting_n", array(
                "id" => $this->id,
                "obj_id" => $this->obj_id,
                "title" => $this->title,
                "question" => $this->question,
                "voting_type" => $this->getQuestionTypeId(),
                "voting_status" => $this->voting_status,
                "position" => $this->position
            ));
        } else {
            throw new LiveVotingException("Invalid object id");
        }

        foreach ($this->options as $option) {
            $option->setVotingId($this->id);
            $option->save();
        }

        return $this->id;
    }

    /**
     * @throws LiveVotingException
     */
    public function reset(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_vote_n", array(
            "voting_id" => $this->id
        ));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function setObjId(int $obj_id): void
    {
        $this->obj_id = $obj_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): void
    {
        $this->question = $question;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getVotingStatus(): int
    {
        return $this->voting_status;
    }

    public function setVotingStatus(int $voting_status): void
    {
        $this->voting_status = $voting_status;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getFirstOption(): LiveVotingQuestionOption
    {
        return $this->first_option;
    }

    public function setFirstOption(LiveVotingQuestionOption $first_option): void
    {
        $this->first_option = $first_option;
    }

    /**
     * @throws LiveVotingException
     */
    public function isFirst(): bool
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_voting_n", array(
            "obj_id" => $this->obj_id
        ), ["id"], "ORDER BY position ASC LIMIT 1");

        if (isset($result[0])) {
            return (int)$result[0]["id"] == $this->id;
        }

        return false;
    }

    /**
     * @throws LiveVotingException
     */
    public function isLast(): bool
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_voting_n", array(
            "obj_id" => $this->obj_id
        ), ["id"], "ORDER BY position DESC LIMIT 1");

        if (isset($result[0])) {
            return (int)$result[0]["id"] == $this->id;
        }

        return false;
    }

    /**
     * @throws LiveVotingException
     */
    public function regenerateOptionSorting(): void
    {
        $i = 1;
        foreach ($this->options as $option) {
            $option->setPosition($i);
            $option->save();
            $i++;
        }
    }

    abstract function getComputedColums(): float;

    abstract function getVotesRepresentation(array $answer): string;

    /**
     * @throws LiveVotingException
     */
    public function fullClone(bool $change_name = true, bool $clone_options = true, ?int $new_obj_id = null): LiveVotingQuestion
    {
        $newObj = $this->copy();

        if ($new_obj_id) {
            $newObj->setObjId($new_obj_id);
        }

        if ($change_name) {
            $count = 1;

            $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($newObj->getObjId());

            if (in_array($newObj->getTitle(), array_column($questions, 'title'))) {
                while (in_array($newObj->getTitle() . ' (' . $count . ')', array_column($questions, 'title'))) {
                    $count++;
                }

                $newObj->setTitle($newObj->getTitle() . ' (' . $count . ')');
            }
        }

        $newObj->save();

        if ($clone_options) {
            foreach ($this->getOptions() as $votingOption) {
                $votingOptionNew = $votingOption->copy();
                $votingOptionNew->setVotingId($newObj->getId());
                $votingOptionNew->save();
            }

            $newObj->regenerateOptionSorting();
        }

        return $newObj;
    }

    private function copy(): LiveVotingQuestion
    {
        $newObj = clone $this;
        $newObj->setId(0);
        $newObj->setOptions(array());
        $newObj->setPosition(null);

        return $newObj;
    }

    /**
     * @throws LiveVotingException
     */
    private function generateNewPosition(): int
    {
        $questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->obj_id);

        if (empty($questions)) {
            return 1;
        }

        $max = 0;

        foreach ($questions as $question) {
            if ($question->getPosition() > $max) {
                $max = $question->getPosition();
            }
        }

        return $max + 1;
    }

    public function getQuestionForPresentation(): string
    {
        $question = $this->question;

        $question = ilRTE::_replaceMediaObjectImageSrc($question, 1);

        return ilLegacyFormElementsUtil::prepareTextareaOutput($question, true);
    }

    public function isValidOption(int $option_id): bool
    {
        foreach ($this->options as $option) {
            if ($option->getId() == $option_id) {
                return true;
            }
        }

        return false;
    }
}