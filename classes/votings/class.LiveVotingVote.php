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

namespace LiveVoting\votings;

use Exception;
use ilLiveVotingPlugin;
use ilObjUser;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\questions\LiveVotingQuestionOption;
use LiveVoting\Utils\LiveVotingUtils;

/**
 * Class LiveVotingVote
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingVote
{
    private int $id = 0;
    private int $type;
    private int $status;
    private int $option_id;
    private int $voting_id;
    private int $user_id_type;
    private string $user_identifier = "0";
    private int $user_id = 0;
    private int $last_update;
    private int $round_id = 0;
    private ?string $free_input = null;
    private int $free_input_category = 0;

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public function __construct(?int $id = null)
    {
        if ($id !== null && $id != 0) {
            $this->setId($id);
            $this->loadFromDB();
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
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

    public function getOptionId(): int
    {
        return $this->option_id;
    }

    public function setOptionId(int $option_id): void
    {
        $this->option_id = $option_id;
    }

    public function getVotingId(): int
    {
        return $this->voting_id;
    }

    public function setVotingId(int $voting_id): void
    {
        $this->voting_id = $voting_id;
    }

    public function getUserIdType(): int
    {
        return $this->user_id_type;
    }

    public function setUserIdType(int $user_id_type): void
    {
        $this->user_id_type = $user_id_type;
    }

    public function getUserIdentifier(): string
    {
        return $this->user_identifier;
    }

    public function setUserIdentifier(string $user_identifier): void
    {
        $this->user_identifier = $user_identifier;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getLastUpdate(): int
    {
        return $this->last_update;
    }

    public function setLastUpdate(int $last_update): void
    {
        $this->last_update = $last_update;
    }

    public function getRoundId(): int
    {
        return $this->round_id;
    }

    public function setRoundId(int $round_id): void
    {
        $this->round_id = $round_id;
    }

    public function getFreeInput(): ?string
    {
        return $this->free_input;
    }

    public function setFreeInput(?string $free_input): void
    {
        $this->free_input = $free_input;
    }

    public function getFreeInputCategory(): int
    {
        return $this->free_input_category;
    }

    public function setFreeInputCategory(int $free_input_category): void
    {
        $this->free_input_category = $free_input_category;
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public function save(): int
    {
        $database = new LiveVotingDatabase();

        $this->last_update = LiveVotingUtils::getTime();

        if ($this->id != 0) {
            $database->update("rep_robj_xlvo_vote_n", array(
                "type" => $this->type,
                "status" => $this->status,
                "option_id" => $this->option_id,
                "voting_id" => $this->voting_id,
                "user_id_type" => $this->user_id_type,
                "user_identifier" => $this->user_identifier,
                "user_id" => $this->user_id,
                "last_update" => $this->last_update,
                "round_id" => $this->round_id,
                "free_input" => $this->free_input,
                "free_input_category" => $this->free_input_category
            ), array(
                "id" => $this->id
            ));
        } else {
            $this->id = $database->nextId("rep_robj_xlvo_vote_n");

            $database->insert("rep_robj_xlvo_vote_n", array(
                "id" => $this->id,
                "type" => $this->type,
                "status" => $this->status,
                "option_id" => $this->option_id,
                "voting_id" => $this->voting_id,
                "user_id_type" => $this->user_id_type,
                "user_identifier" => $this->user_identifier,
                "user_id" => $this->user_id,
                "last_update" => $this->last_update,
                "round_id" => $this->round_id,
                "free_input" => $this->free_input,
                "free_input_category" => $this->free_input_category
            ));
        }

        return $this->id;
    }

    /**
     * @throws LiveVotingException
     */
    public function loadFromDB(): void
    {
        $database = new LiveVotingDatabase();

        if ($this->getId() == 0) {
            throw new LiveVotingException("Invalid vote id");
        }

        $result = $database->select("rep_robj_xlvo_vote_n", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setType((int)$result[0]["type"]);
            $this->setStatus((int)$result[0]["status"]);
            $this->setOptionId((int)$result[0]["option_id"]);
            $this->setVotingId((int)$result[0]["voting_id"]);
            $this->setUserIdType((int)$result[0]["user_id_type"]);
            $this->setUserIdentifier($result[0]["user_identifier"]);
            $this->setUserId((int)$result[0]["user_id"]);
            $this->setLastUpdate((int)$result[0]["last_update"]);
            $this->setRoundId((int)$result[0]["round_id"]);
            $this->setFreeInput($result[0]["free_input"]);
            $this->setFreeInputCategory((int)$result[0]["free_input_category"]);
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function delete(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_vote_n", ["id" => $this->getId()]);
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return ($this->getStatus() == 1);
    }

    /**
     * @param LiveVotingParticipant $participant
     * @param int $voting_id
     * @param int $round_id
     * @param int $option_id
     * @return int
     * @throws LiveVotingException
     */
    public static function vote(LiveVotingParticipant $participant, int $voting_id, int $round_id, int $option_id): int
    {
        $obj = self::getUserInstance($participant, $voting_id, $option_id);

        $obj->setStatus(1);
        $obj->setRoundId($round_id);

        $obj->save();

        return $obj->getId();
    }

    /**
     * @param LiveVotingParticipant $participant
     * @param int $voting_id
     * @param int|null $option_id
     * @return int
     * @throws LiveVotingException
     */
    public static function unvote(LiveVotingParticipant $participant, int $voting_id, ?int $option_id = null): int
    {
        $obj = self::getUserInstance($participant, $voting_id, $option_id);

        $obj->setStatus(0);

        $obj->save();

        return $obj->getId();
    }

    /**
     * @throws LiveVotingException
     */
    public function getOption(): LiveVotingQuestionOption
    {
        return LiveVotingQuestionOption::loadOptionById($this->getOptionId());
    }

    public function getParticipantName(?int $player = null): string
    {
        if (isset($player)) {
            $nickname = $this->checkNickName($player);

            if (isset($nickname)) {
                return $nickname;
            }
        }

        if ($this->getUserIdType() == 1 && $this->getUserId()) {
            $name = ilObjUser::_lookupName($this->getUserId());

            return $name['firstname'] . " " . $name['lastname'];
        }


        return ilLiveVotingPlugin::getInstance()->txt("common_participant") . " " . substr($this->getUserIdentifier(), 0, 4);
    }

    /**
     * @throws LiveVotingException
     */
    private function checkNickName(int $player): ?string
    {
        $identifier = $this->getUserIdentifier();

        if ($this->getUserIdType() == 1 && $this->getUserId()) {
            $identifier = $this->getUserId();
        }

        $nickname = LiveVotingParticipant::getNicknameFromDatabase($identifier, $player);

        if ($nickname != "") {
            return $nickname;
        }

        return null;
    }

    /**
     * @param LiveVotingParticipant $participant
     * @param int $voting_id
     * @param int $round_id
     * @param bool $incl_inactive
     * @return array
     * @throws LiveVotingException
     */
    public static function getVotesOfUser(LiveVotingParticipant $participant, int $voting_id, int $round_id, bool $incl_inactive = false): array
    {
        $database = new LiveVotingDatabase();

        $where = array(
            'voting_id' => $voting_id,
            'round_id' => $round_id,
        );

        if (!$incl_inactive) {
            $where['status'] = 1;
        }

        if ($participant->isILIASUser()) {
            $where['user_id'] = $participant->getIdentifier();
        } else {
            $where['user_identifier'] = $participant->getIdentifier();
        }

        $result = $database->select("rep_robj_xlvo_vote_n", $where, ["id"]);

        $votes = array();

        foreach ($result as $row) {
            $votes[] = new LiveVotingVote((int)$row["id"]);
        }

        return $votes;
    }

    /**
     * @param LiveVotingParticipant $participant
     * @param int $voting_id
     * @param int|null $option_id
     * @return LiveVotingVote
     * @throws LiveVotingException
     * @throws Exception
     */
    protected static function getUserInstance(LiveVotingParticipant $participant, int $voting_id, $option_id): LiveVotingVote
    {
        $database = new LiveVotingDatabase();

        $where = array(
            'voting_id' => $voting_id,
            'option_id' => $option_id
        );

        if ($participant->isILIASUser()) {
            $where['user_id'] = $participant->getIdentifier();
        } else {
            $where['user_identifier'] = $participant->getIdentifier();
        }

        $result = $database->select("rep_robj_xlvo_vote_n", $where, ["id"]);

        $vote = new LiveVotingVote();

        if (isset($result[0])) {
            $vote->setId((int)$result[0]["id"]);
            $vote->loadFromDB();
        } else {
            $vote->setType(1);
            $vote->setStatus(0);
            $vote->setLastUpdate(LiveVotingUtils::getTime());
        }

        $vote->setUserIdType($participant->isILIASUser() ? 1 : 2);

        if ($participant->isILIASUser()) {
            $vote->setUserId((int)$participant->getIdentifier());
        } else {
            $vote->setUserIdentifier($participant->getIdentifier());
        }

        $vote->setOptionId($option_id);
        $vote->setVotingId($voting_id);

        return $vote;
    }

    /**
     * @param LiveVotingParticipant $participant
     * @param int $voting_id
     * @param int $round_id
     * @return void
     * @throws LiveVotingException
     * @throws Exception
     */
    public static function createHistoryObject(LiveVotingParticipant $participant, int $voting_id, int $round_id): void
    {
        $database = new LiveVotingDatabase();

        $question = LiveVotingQuestion::loadQuestionById($voting_id);

        $votes = self::getVotesOfUser($participant, $voting_id, $round_id);

        $database->insert("rep_robj_xlvo_votehist", array(
            "id" => $database->nextId("rep_robj_xlvo_votehist"),
            "user_id_type" => $participant->isILIASUser() ? 0 : 1,
            "user_id" => $participant->isILIASUser() ? $participant->getIdentifier() : null,
            "user_identifier" => $participant->isILIASUser() ? null : $participant->getIdentifier(),
            "voting_id" => $voting_id,
            "timestamp" => LiveVotingUtils::getTime(),
            "round_id" => $round_id,
            "answer" => $question->getVotesRepresentation($votes)
        ));
    }


    /**
     * @throws LiveVotingException
     */
    public static function getVotesForRound(int $round_id, bool $distinct = false, ?string $filter = null): array
    {
        $votes = array();

        $database = new LiveVotingDatabase();

        if ($filter) {
            $result = $database->select("rep_robj_xlvo_vote_n", array(
                "round_id" => $round_id,
                "status" => 1
            ), ["id", "user_identifier", "user_id"], "AND (user_identifier LIKE '" . $filter . "' OR user_id = '" . $filter . "')");
        } else {
            $result = $database->select("rep_robj_xlvo_vote_n", array(
                "round_id" => $round_id,
                "status" => 1
            ), ["id", "user_identifier", "user_id"]);
        }

        foreach ($result as $row) {
            if (!$distinct) {
                $votes[] = new LiveVotingVote((int)$row["id"]);
            } else {
                $votes[$row["user_identifier"] . "_" . $row["user_id"]] = new LiveVotingVote((int)$row["id"]);
            }
        }

        if ($distinct) {
            $votes = array_values($votes);
        }

        return $votes;
    }

    /**
     * @throws LiveVotingException
     */
    public static function getVotesOfOption(int $option_id, int $round_id): array
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_vote_n", array(
            "option_id" => $option_id,
            "status" => 1,
            "round_id" => $round_id
        ), ["id"]);

        $votes = array();

        foreach ($result as $row) {
            $votes[] = new LiveVotingVote((int)$row["id"]);
        }

        return $votes;
    }

    /**
     * @throws LiveVotingException
     */
    public static function getVotesOfQuestion(int $voting_id, ?int $round_id = null, bool $order_by_free_input = false): array
    {
        $database = new LiveVotingDatabase();

        $where = array(
            "voting_id" => $voting_id,
            "status" => 1
        );

        if ($round_id) {
            $where["round_id"] = $round_id;
        }

        $result = $database->select("rep_robj_xlvo_vote_n", $where, ["id"], $order_by_free_input ? "ORDER BY free_input ASC" : "");

        $votes = array();

        foreach ($result as $row) {
            $votes[] = new LiveVotingVote((int)$row["id"]);
        }

        return $votes;
    }

    /**
     * @throws LiveVotingException
     */
    public static function countVotes(int $voting_id, int $round_id): int
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_vote_n", array(
            "voting_id" => $voting_id,
            "status" => 1,
            "round_id" => $round_id
        ), ["COUNT(id) AS count"]);

        return (int)$result[0]["count"];
    }

    /**
     * @throws LiveVotingException
     */
    public static function countVoters(int $voting_id, int $round_id): int
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_vote_n", array(
            "voting_id" => $voting_id,
            "status" => 1,
            "round_id" => $round_id
        ), ["user_id_type", "user_identifier", "user_id"], "GROUP BY user_id_type, user_identifier, user_id");

        return count($result);
    }

    /**
     * @throws LiveVotingException
     */
    public static function hasVotes(int $voting_id, int $round_id): bool
    {
        return self::countVotes($voting_id, $round_id) > 0;
    }
}