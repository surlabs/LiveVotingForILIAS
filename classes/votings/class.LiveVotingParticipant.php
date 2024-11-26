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

use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;

/**
 * Class LiveVotingParticipant
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingParticipant
{
    protected static $instance;
    protected $type = 1;
    protected $identifier = '';
    protected $nickname = '';

    public static function getInstance(): LiveVotingParticipant
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }


    /**
     * @return bool
     */
    public function isILIASUser(): bool
    {
        return ($this->getType() == 1);
    }


    /**$
     * @return bool
     */
    public function isPINUser(): bool
    {
        return ($this->getType() == 2);
    }


    /**
     * @param $type
     *
     * @return $this
     */
    public function setType($type): LiveVotingParticipant
    {
        $this->type = $type;

        return $this;
    }


    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }


    /**
     * @param $identifier
     *
     * @return $this
     */
    public function setIdentifier($identifier): LiveVotingParticipant
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @param int|null $player
     * @return string
     * @throws LiveVotingException
     */
    public function getNickname(?int $player = null): string
    {
        if ($this->nickname == '' && isset($player)) {
            $this->nickname = $this->getNicknameFromDatabase($this->getIdentifier(), $player);
        }

        return $this->nickname;
    }

    /**
     * @param string $identifier
     * @param int    $player
     * @return string
     * @throws LiveVotingException
     */
    public static function getNicknameFromDatabase(string $identifier, int $player): string
    {
        $database = new LiveVotingDatabase();
        $result = $database->select("xlvo_nicknames", [
            'identifier' => $identifier,
            'player_id' => $player
        ], ['nickname']);

        if (!empty($result)) {
            return $result[0]['nickname'];
        }

        return '';
    }

    /**
     * @param string $nickname
     * @param int    $player_id
     * @return $this
     * @throws LiveVotingException
     */
    public function setNickname(string $nickname, int $player_id): LiveVotingParticipant
    {
        $this->nickname = $nickname;

        $database = new LiveVotingDatabase();

        $database->insertOnDuplicatedKey('xlvo_nicknames', [
            'identifier' => $this->getIdentifier(),
            'player_id' => $player_id,
            'nickname' => $this->nickname
        ]);

        return $this;
    }
}