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
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\Utils\LiveVotingUtils;

/**
 * Class LiveVotingVoter
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingVoter
{
    private int $id;
    private int $player_id = 0;
    private string $user_identifier;
    private int $last_access;

    /**
     * @throws LiveVotingException
     */
    public function __construct(?int $id = null)
    {
        if ($id !== null) {
            $this->setId($id);

            $this->loadFromDb();
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

    public function getPlayerId(): int
    {
        return $this->player_id;
    }

    public function setPlayerId(int $player_id): void
    {
        $this->player_id = $player_id;
    }

    public function getUserIdentifier(): string
    {
        return $this->user_identifier;
    }

    public function setUserIdentifier(string $user_identifier): void
    {
        $this->user_identifier = $user_identifier;
    }

    public function getLastAccess(): int
    {
        return $this->last_access;
    }

    public function setLastAccess(int $last_access): void
    {
        $this->last_access = $last_access;
    }

    /**
     * @throws LiveVotingException
     */
    public function save(): int
    {
        $database = new LiveVotingDatabase();

        if (isset($this->id) && $this->id != 0) {
            $database->update("xlvo_voter", array(
                "player_id" => $this->player_id,
                "user_identifier" => $this->user_identifier,
                "last_access" => date('Y-m-d H:i:s', $this->last_access)
            ), array(
                "id" => $this->id
            ));
        } else {
            $this->id = $database->nextId("xlvo_voter");

            $database->insert("xlvo_voter", array(
                "id" => $this->id,
                "player_id" => $this->player_id,
                "user_identifier" => $this->user_identifier,
                "last_access" => date('Y-m-d H:i:s', $this->last_access)
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

        $result = $database->select("xlvo_voter", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setId((int)$result[0]["id"]);
            $this->setPlayerId((int)$result[0]["player_id"]);
            $this->setUserIdentifier($result[0]["user_identifier"]);
            $this->setLastAccess((int)$result[0]["last_access"]);
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function delete(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("xlvo_voter", ["id" => $this->getId()]);
    }

    /**
     * @throws LiveVotingException
     */
    private static function getVoters(int $player_id): array
    {
        $delay = LiveVotingConfig::get("request_frequency");

        if (is_numeric($delay)) {
            $delay = ((float)$delay);
        } else {
            $delay = 1;
        }

        // Calculate the cutoff time taking into account the delay and an additional 50% of delay
        $cutoff_time = LiveVotingUtils::getTime() - ($delay + $delay * 0.5);

        // Format the cutoff time to match the format used in sleep (Y-m-d H:i:s)
        $formatted_cutoff_time = date('Y-m-d H:i:s', (int)$cutoff_time);

        $database = new LiveVotingDatabase();

        return $database->select("xlvo_voter", [
            "player_id" => $player_id
        ], ["user_identifier"], "AND last_access > '$formatted_cutoff_time'");
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public static function countVoters(int $player_id): int
    {
        return count(self::getVoters($player_id));
    }

    /**
     * @throws LiveVotingException
     */
    public static function getVotersNicknames(int $player_id): array
    {
        $voters = self::getVoters($player_id);

        $database = new LiveVotingDatabase();

        $nicknames = $database->select("xlvo_nicknames", [
            "player_id" => $player_id
        ], ["identifier", "nickname"]);

        $nicknames_map = array_column($nicknames, "nickname", "identifier");

        $voters_nicknames = [];

        foreach ($voters as $voter) {
            if (isset($nicknames_map[$voter["user_identifier"]])) {
                $voters_nicknames[$voter["user_identifier"]] = $nicknames_map[$voter["user_identifier"]];
            }
        }

        return $voters_nicknames;
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public static function register(int $player_id): void
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("xlvo_voter", array(
            "user_identifier" => LiveVotingParticipant::getInstance()->getIdentifier(),
            "player_id" => $player_id,
        ), ["id"]);

        if (isset($result[0])) {
            $voter = new self((int)$result[0]["id"]);
        } else {
            $voter = new self();
            $voter->setPlayerId($player_id);
            $voter->setUserIdentifier(LiveVotingParticipant::getInstance()->getIdentifier());
        }

        $voter->setLastAccess(LiveVotingUtils::getTime() + 2);
        $voter->save();
    }

    public static function removeVoter(int $player_id, string $identifier): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("xlvo_voter", [
            "player_id" => $player_id,
            "user_identifier" => $identifier
        ]);

        $database->delete("xlvo_nicknames", [
            "player_id" => $player_id,
            "identifier" => $identifier
        ]);
    }
}