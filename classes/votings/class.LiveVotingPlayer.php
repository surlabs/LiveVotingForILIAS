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
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\questions\LiveVotingQuestionOption;
use LiveVoting\Utils\LiveVotingUtils;
use LiveVoting\objects\modes\LiveVotingMode;

/**
 * Class LiveVotingPlayer
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingPlayer
{
    const STAT_STOPPED = 0;
    const STAT_RUNNING = 1;
    const STAT_START_VOTING = 2;
    const STAT_END_VOTING = 3;
    const STAT_FROZEN = 4;
    const STAT_SCOREBOARD = 5;

    private int $id;
    private int $obj_id;
    private int $active_voting = 0;
    private int $status = self::STAT_STOPPED;
    private bool $frozen = true;
    private int $timestamp_refresh = 0;
    private bool $show_results = false;
    private array $button_states = array();
    private int $countdown = 0;
    private int $countdown_start = 0;
    private bool $force_reload = false;
    private int $round_id = 0;
    private bool $keyboard_active = false;
    private bool $full_screen = true;

    /**
     * @throws LiveVotingException
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

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function setObjId(int $obj_id): void
    {
        $this->obj_id = $obj_id;
    }

    public function getActiveVoting(): int
    {
        return $this->active_voting;
    }

    public function setActiveVoting(int $active_voting): void
    {
        $this->active_voting = $active_voting;
    }

    /**
     * @throws LiveVotingException
     */
    public function getActiveVotingObject(): ?LiveVotingQuestion
    {
        if ($this->active_voting > 0) {
            return LiveVotingQuestion::loadQuestionById($this->active_voting);
        } else {
            return null;
        }
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    public function setFrozen(bool $frozen): void
    {
        $this->frozen = $frozen;
    }

    public function getTimestampRefresh(): int
    {
        return $this->timestamp_refresh;
    }

    public function setTimestampRefresh(int $timestamp_refresh): void
    {
        $this->timestamp_refresh = $timestamp_refresh;
    }

    public function isShowResults(): bool
    {
        return $this->show_results;
    }

    public function setShowResults(bool $show_results): void
    {
        $this->show_results = $show_results;
    }

    public function getButtonStates(): array
    {
        return $this->button_states;
    }

    public function setButtonStates(array $button_states): void
    {
        $this->button_states = $button_states;
    }

    public function getCountdown(): int
    {
        return $this->countdown;
    }

    public function setCountdown(int $countdown): void
    {
        $this->countdown = $countdown;
    }

    public function getCountdownStart(): int
    {
        return $this->countdown_start;
    }

    public function setCountdownStart(int $countdown_start): void
    {
        $this->countdown_start = $countdown_start;
    }

    public function isForceReload(): bool
    {
        return $this->force_reload;
    }

    public function setForceReload(bool $force_reload): void
    {
        $this->force_reload = $force_reload;
    }

    public function getRoundId(): int
    {
        return $this->round_id;
    }

    public function setRoundId(int $round_id): void
    {
        $this->round_id = $round_id;
    }

    public function isKeyboardActive(): bool
    {
        return $this->keyboard_active;
    }

    public function setKeyboardActive(bool $keyboard_active): void
    {
        $this->keyboard_active = $keyboard_active;
    }

    public function isFullScreen(): bool
    {
        return $this->full_screen;
    }

    public function setFullScreen(bool $full_screen): void
    {
        $this->full_screen = $full_screen;
    }

    /**
     * @throws LiveVotingException
     */
    public function save(): int
    {
        $database = new LiveVotingDatabase();

        if (isset($this->id) && $this->id != 0) {
            $database->update("rep_robj_xlvo_player_n", array(
                "active_voting" => $this->active_voting,
                "status" => $this->status,
                "frozen" => (int)$this->frozen,
                "timestamp_refresh" => $this->timestamp_refresh,
                "show_results" => (int)$this->show_results,
                "button_states" => json_encode($this->button_states),
                "countdown" => $this->countdown,
                "countdown_start" => $this->countdown_start,
                "force_reload" => (int)$this->force_reload,
                "round_id" => $this->round_id
            ), array(
                "id" => $this->id
            ));
        } else if (isset($this->obj_id) && $this->obj_id != 0) {
            $this->id = $database->nextId("rep_robj_xlvo_player_n");

            $database->insert("rep_robj_xlvo_player_n", array(
                "id" => $this->id,
                "obj_id" => $this->obj_id,
                "active_voting" => $this->active_voting,
                "status" => $this->status,
                "frozen" => (int)$this->frozen,
                "timestamp_refresh" => $this->timestamp_refresh,
                "show_results" => (int)$this->show_results,
                "button_states" => json_encode($this->button_states),
                "countdown" => $this->countdown,
                "countdown_start" => $this->countdown_start,
                "force_reload" => (int)$this->force_reload,
                "round_id" => $this->round_id
            ));
        } else {
            throw new LiveVotingException("Invalid object id");
        }

        return $this->id;
    }

    /**
     * @throws LiveVotingException
     */
    public function loadFromDB(): void
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_player_n", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setId((int)$result[0]["id"]);
            $this->setObjId((int)$result[0]["obj_id"]);
            $this->setActiveVoting((int)$result[0]["active_voting"]);
            $this->setStatus((int)$result[0]["status"]);
            $this->setFrozen((bool)$result[0]["frozen"]);
            $this->setTimestampRefresh((int)$result[0]["timestamp_refresh"]);
            $this->setShowResults((bool)$result[0]["show_results"]);
            $this->setButtonStates(json_decode($result[0]["button_states"], true));
            $this->setCountdown((int)$result[0]["countdown"]);
            $this->setCountdownStart((int)$result[0]["countdown_start"]);
            $this->setForceReload((bool)$result[0]["force_reload"]);
            $this->setRoundId((int)$result[0]["round_id"]);
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function delete(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_player_n", ["id" => $this->getId()]);
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public function freeze(): void
    {
        $this->setFrozen(true);
        $this->resetCountDown(false);
        $this->setButtonStates([]);
        $this->resetCountDown(false);
        $this->setTimestampRefresh(LiveVotingUtils::getTime() + 30);
        $this->save();
    }

    /**
     * @throws LiveVotingException
     */
    public function showScoreboard(): void
    {
        $this->resetCountDown(false);

        $this->setStatus(self::STAT_SCOREBOARD);
        $this->save();
    }


    /**
     * @param int $question_id
     * @throws LiveVotingException
     * @throws Exception
     */
    public function unfreeze(int $question_id = 0): void
    {
        if ($question_id > 0) {
            $this->setActiveVoting($question_id);
        }

        $this->setFrozen(false);
        $this->resetCountDown(false);
        $this->setButtonStates([]);
        $this->resetCountDown(false);
        $this->setTimestampRefresh(LiveVotingUtils::getTime() + 30);
        $this->save();
    }


    /**
     * @param int $question_id
     * @throws LiveVotingException
     */
    public function toggleFreeze(int $question_id = 0): void
    {
        if ($this->isFrozen()) {
            $this->unfreeze($question_id);
        } else {
            $this->freeze();
        }
    }

    /**
     * @return int
     * @throws Exception
     */
    public function remainingCountDown(): int
    {
        return $this->getCountdownStart() - LiveVotingUtils::getTime() + $this->getCountdown();
    }

    /**
     * @param int $seconds
     * @throws LiveVotingException
     * @throws Exception
     */
    public function startCountDown(int $seconds): void
    {
        $this->unfreeze();
        $this->setCountdown($seconds);
        $this->setCountdownStart(LiveVotingUtils::getTime());
        $this->save();
    }

    /**
     * @param bool $store
     * @throws LiveVotingException
     */
    public function resetCountDown(bool $store = true): void
    {
        $this->setCountdown(0);
        $this->setCountdownStart(0);

        if ($store) {
            $this->save();
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function show(): void
    {
        $this->setShowResults(true);

        $this->save();
    }


    /**
     * @throws LiveVotingException
     */
    public function hide(): void
    {
        $this->setShowResults(false);

        $this->save();
    }


    /**
     * @throws LiveVotingException
     */
    public function toggleResults(): void
    {
        $this->setShowResults(!$this->isShowResults());

        $this->save();
    }


    /**
     * @throws LiveVotingException
     */
    public function terminate(): void
    {
        $this->setStatus(self::STAT_END_VOTING);
        $this->freeze();
    }

    /**
     * @return bool
     * @throws LiveVotingException
     */
    public function isFrozenOrUnattended(): bool
    {
        if ($this->getStatus() == self::STAT_RUNNING) {
            return ($this->isFrozen() || $this->isUnattended());
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isCountDownRunning(): bool
    {
        return ($this->remainingCountDown() > 0 && $this->getCountdownStart() > 0);
    }


    /**
     * @return string
     */
    public function getCountdownClassname(): string
    {
        $cd = $this->remainingCountDown();

        return $cd > 10 ? 'running' : ($cd > 5 ? 'warning' : 'danger');
    }

    /**
     * @param $voting_id
     * @throws LiveVotingException
     */
    public function prepareStart($voting_id): void
    {
        $this->setStatus(self::STAT_START_VOTING);
        $this->setActiveVoting($voting_id);

        $this->setRoundId(LiveVotingRound::getLatestRoundId($this->getObjId()));

        $this->save();
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public function isUnattended(): bool
    {
        if ($this->getStatus() != self::STAT_STOPPED and ($this->getTimestampRefresh() < (LiveVotingUtils::getTime() - 30))) {
            $this->setStatus(self::STAT_STOPPED);

            $this->save();
        }
        if ($this->getStatus() == self::STAT_START_VOTING) {
            return false;
        }
        if ($this->getStatus() == self::STAT_STOPPED) {
            return false;
        }

        return ($this->getTimestampRefresh() < (LiveVotingUtils::getTime() - 4));
    }


    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public function attend(LiveVotingMode $mode): void
    {
        $this->setStatus(self::STAT_RUNNING);

        $this->setTimestampRefresh(LiveVotingUtils::getTime());

        if ($this->remainingCountDown() <= 0 && $this->getCountdownStart() > 0) {
            if ($mode->getMode() == LiveVotingMode::CHALLENGE_MODE) {
                $this->showScoreboard();
            } else {
                $this->freeze();
            }
        }
    }

    /**
     * @throws LiveVotingException
     * @throws Exception
     */
    public function getPlayerData(): array
    {
        $database = new LiveVotingDatabase();

        $votes = $database->select("rep_robj_xlvo_vote_n", array(
            'voting_id' => $this->getActiveVoting(),
            'status' => 1,
            'round_id' => $this->getRoundId()
        ), ["last_update"], "ORDER BY last_update DESC");

        $last_update = 0;

        if (count($votes) > 0) {
            $last_update = $votes[0]["last_update"];
        }

        return array(
            "is_first" => $this->getActiveVotingObject()->isFirst(),
            "is_last" => $this->getActiveVotingObject()->isLast(),
            "status" => $this->getStatus(),
            "active_voting_id" => $this->getActiveVoting(),
            "show_results" => $this->isShowResults(),
            "frozen" => $this->isFrozen(),
            "votes" => count($votes),
            "last_update" => (int)$last_update,
            "attendees" => vsprintf(ilLiveVotingPlugin::getInstance()->txt("start_online"), [LiveVotingVoter::countVoters($this->getId())]),
            "qtype" => $this->getActiveVotingObject()->getQuestionType(),
            "countdown" => $this->remainingCountDown(),
            "has_countdown" => $this->isCountDownRunning()
        );
    }

    /**
     * @throws Exception
     */
    public function getPlayerDataForVoter(): array
    {
        return array(
            "status" => $this->getStatus(),
            "force_reload" => false,
            "active_voting_id" => $this->getActiveVoting(),
            "countdown" => $this->remainingCountDown(),
            "has_countdown" => $this->isCountDownRunning(),
            "countdown_classname" => $this->getCountdownClassname(),
            "frozen" => $this->isFrozen(),
            "show_results" => $this->isShowResults(),
            "show_correct_order" => false,
        );
    }

    /**
     * @throws LiveVotingException
     */
    public static function loadFromObjId(int $getId): LiveVotingPlayer
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_player_n", ["obj_id" => $getId], ["id"]);

        $player = new LiveVotingPlayer();

        if (isset($result[0])) {
            $player->setId((int)$result[0]["id"]);
            $player->loadFromDB();
        } else {
            $player->setObjId($getId);
            $player->save();
        }

        return $player;
    }

    public function handleQuestionSwitching(LiveVoting $liveVoting): void
    {
        switch ($liveVoting->getResultsBehaviour()) {
            case 1:
                $this->setShowResults(true);
                break;
            case 0:
                $this->setShowResults(false);
                break;
            case 2:
                $this->setShowResults($this->isShowResults());
                break;
        }

        if ($liveVoting->getMode()->getMode() != LiveVotingMode::CHALLENGE_MODE) {
            switch ($liveVoting->getFrozenBehaviour()) {
                case 1:
                    $this->setFrozen(false);
                    break;
                case 0:
                    $this->setFrozen(true);
                    break;
                case 2:
                    $this->setFrozen($this->isFrozen());
                    break;
            }
        } else {
            $this->setFrozen(false);
        }
    }


    /**
     * @throws LiveVotingException
     */
    public function previousQuestion(): void
    {
        $active_voting = $this->getActiveVotingObject();

        if ($active_voting->isFirst()) {
            return;
        }

        $liveVoting = new LiveVoting($this->obj_id, false);

        $questions = $liveVoting->getQuestions();

        $prev = false;

        foreach ($questions as $question) {
            if ($question->getId() == $active_voting->getId()) {
                if ($prev) {
                    $this->handleQuestionSwitching($liveVoting);
                    $this->setActiveVoting($prev->getId());
                    $this->save();
                    return;
                }
            }
            $prev = $question;
        }

        throw new LiveVotingException("Question not found");
    }

    /**
     * @throws LiveVotingException
     */
    public function nextQuestion(): bool
    {
        $active_voting = $this->getActiveVotingObject();

        if ($active_voting->isLast()) {
            return false;
        }

        $liveVoting = new LiveVoting($this->obj_id, false);

        $questions = $liveVoting->getQuestions();

        $next = false;

        foreach ($questions as $question) {
            if ($next) {
                $this->handleQuestionSwitching($liveVoting);
                $this->setActiveVoting($question->getId());
                $this->save();
                return true;
            }
            if ($question->getId() == $active_voting->getId()) {
                $next = true;
            }
        }

        throw new LiveVotingException("Question not found");
    }

    /**
     * @throws LiveVotingException
     */
    public function reset(): void
    {
        $this->setButtonStates([]);
        $this->save();

        foreach (LiveVotingVote::getVotesForRound($this->getRoundId()) as $vote) {
            $vote->delete();
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function open(int $question_id): void
    {
        $this->setActiveVoting($question_id);
        $this->save();
    }

    /**
     * @throws LiveVotingException
     */
    public function unvoteAll(int $except_vote_id = null): void
    {
        foreach ($this->getVotesOfUser() as $vote) {
            if ($except_vote_id && $vote->getId() == $except_vote_id) {
                continue;
            }
            $vote->setStatus(0);
            $vote->save();
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function getVotesOfUser($incl_inactive = false): array
    {
        return LiveVotingVote::getVotesOfUser(LiveVotingParticipant::getInstance(), $this->getActiveVoting(), $this->getRoundId(), $incl_inactive);
    }

    /**
     * @throws LiveVotingException
     */
    public function hasUserVotedForOption(int $option_id): bool
    {
        foreach ($this->getVotesOfUser() as $vote) {
            if ($vote->getOptionId() == $option_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws LiveVotingException
     */
    public function input(array $array): void
    {
        if (array_key_exists("vote_id", $array) || array_key_exists("input", $array)) {
            $array = array($array);
        }

        $liveVotingConfig = new LiveVoting($this->obj_id, false);

        foreach ($array as $item) {
            $vote = new LiveVotingVote((int)$item['vote_id']);
            $user = LiveVotingParticipant::getInstance();

            if ($user->isILIASUser()) {
                $vote->setUserId((int)$user->getIdentifier());
                $vote->setUserIdType(1);
            } else {
                $vote->setUserIdentifier($user->getIdentifier());
                $vote->setUserIdType(2);
            }

            $vote->setVotingId($this->getActiveVoting());
            $options = $this->getActiveVotingObject()->getOptions();
            $var = array_values($options);
            $option = array_shift($var);
            if (!$option instanceof LiveVotingQuestionOption) {
                throw new LiveVotingException('No Option given');
            }
            $vote->setOptionId($option->getId());
            $vote->setType(2);
            $vote->setStatus(1);
            $vote->setFreeInput($item['input']);
            $vote->setRoundId(LiveVotingRound::getLatestRoundId($liveVotingConfig->getId()));
            $vote->save();
            if ($this->getActiveVotingObject()->getQuestionType() == "FreeText" && !$this->getActiveVotingObject()->isMultiFreeInput()) {
                $this->unvoteAll($vote->getId());
            }
        }

        if ($liveVotingConfig->isVotingHistory()) {
            LiveVotingVote::createHistoryObject(LiveVotingParticipant::getInstance(), $this->getActiveVoting(), $this->getRoundId());
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function createHistoryObject(): void
    {
        $liveVoting = new LiveVoting($this->obj_id, false);

        if ($liveVoting->isVotingHistory()) {
            LiveVotingVote::createHistoryObject(LiveVotingParticipant::getInstance(), $this->getActiveVoting(), $this->getRoundId());
        }
    }

    /**
     * @throws LiveVotingException
     */
    public static function getPlayersForScoreboard(LiveVotingPlayer $player): array
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("xlvo_points", ["obj_id" => $player->getObjId(), "round_id" => $player->getRoundId()], ["identifier", "SUM(points) AS points"], "GROUP BY identifier, obj_id, round_id ORDER BY points DESC");

        foreach ($result as $key => $item) {
            $result[$key]["nickname"] = LiveVotingParticipant::getNicknameFromDatabase($item["identifier"], $player->getId());
        }

        return $result;
    }

    /**
     * @throws LiveVotingException
     */
    public function nextQuestionCM(): void
    {
        if ($this->nextQuestion()) {
            $this->setStatus(LiveVotingPlayer::STAT_RUNNING);

            $this->startCountDown(30);
        } else {
            $this->setStatus(self::STAT_END_VOTING);

            $this->setButtonStates([]);
            $this->resetCountDown(false);
            $this->setTimestampRefresh(LiveVotingUtils::getTime() + 30);
            $this->save();
        }
    }
}