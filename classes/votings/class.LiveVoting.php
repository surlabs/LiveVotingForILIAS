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

use Endroid\QrCode\QrCode;
use ilLink;
use ilLiveVotingPlugin;
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\objects\modes\LiveVotingMode;

/**
 * Class LiveVoting
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVoting
{
    /**
     * @var int The id of the voting
     */
    private int $id = 0;

    /**
     * How the voting is being conducted
     * @var LiveVotingMode|null
     */
    private ?LiveVotingMode $mode;

    /**
     * The log of the voting
     * @var LiveVotingLog
     */
    private LiveVotingLog $log;

    /**
     * Which questions are being asked
     * @var LiveVotingQuestion[]
     */
    private array $questions = [];

    /**
     * Who is participating in the voting
     * @var LiveVotingParticipant[]
     */
    private array $participants = [];

    /**
     * @var bool Whether the voting is active or not
     */
    private bool $is_active = false;
    private string $pin = "";
    private bool $online = false;
    private bool $anonymous = false;
    private bool $voting_history = false;
    private bool $show_attendees = false;
    private int $frozen_behaviour = 0;
    private int $results_behaviour = 0;
    private string $puk = "";
    private bool $nicknames = false;
    private bool $scoreboard = false;
    private LiveVotingPlayer $player;
    private string $voting_style = "classic";

    /**
     * LiveVoting constructor.
     * @param int|null $id
     * @param bool $loadPlayer
     * @throws LiveVotingException
     */
    public function __construct(?int $id = null, bool $loadPlayer = true)
    {
        if ($id !== null && $id != 0) {
            $this->setId($id);

            $this->loadFromDB();
        }

        if ($loadPlayer && $this->getId() !== 0) {
            $this->player = LiveVotingPlayer::loadFromObjId($this->getId());

            if ($this->getMode()->getMode() === LiveVotingMode::CHALLENGE_MODE) {
                $this->player->setFrozen(false);
            }
        }
    }

    /**
     * @throws LiveVotingException
     */
    public function getShortLink(int $ref_id): string
    {
        switch ($this->isAnonymous()) {
            case true:
                $shortLinkEnabled = boolval(LiveVotingConfig::get("allow_shortlink"));

                if ($shortLinkEnabled) {
                    $url = LiveVotingConfig::get("allow_shortlink_link");
                    $url = rtrim($url, "/") . "/" . $this->pin;
                } else {
                    $url = ILIAS_HTTP_PATH . '/Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/pin.php?xlvo_pin=' . $this->getPin();
                }

                break;
            default:
                $url = ILIAS_HTTP_PATH . "/" . "goto.php?target=xlvo_1_pin_" . $this->getPin();
                break;
        }

        return $url;
    }

    /**
     * @throws LiveVotingException
     */
    public function getQRCode(int $ref_id, int $size): string
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $qrCodeLarge = new QrCode($this->getShortLink($ref_id));
        $qrCodeLarge->setErrorCorrection('high');
        $qrCodeLarge->setForegroundColor(array(
            'r' => 0,
            'g' => 0,
            'b' => 0,
            'a' => 0,
        ));
        $qrCodeLarge->setBackgroundColor(array(
            'r' => 255,
            'g' => 255,
            'b' => 255,
            'a' => 0,
        ));
        $qrCodeLarge->setPadding(10);
        $qrCodeLarge->setSize($size);

        return $qrCodeLarge->getDataUri();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getMode(): LiveVotingMode
    {
        return $this->mode;
    }

    public function setMode(LiveVotingMode $mode): void
    {
        $this->mode = $mode;
    }

    public function getLog(): LiveVotingLog
    {
        return $this->log;
    }

    public function setLog(LiveVotingLog $log): void
    {
        $this->log = $log;
    }

    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function setQuestions(array $questions): void
    {
        $this->questions = $questions;
    }

    public function getParticipants(): array
    {
        return $this->participants;
    }

    public function setParticipants(array $participants): void
    {
        $this->participants = $participants;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function setActive(bool $is_active): void
    {
        $this->is_active = $is_active;
    }

    public function getPin(): string
    {
        return $this->pin;
    }

    public function setPin(string $pin): void
    {
        $this->pin = $pin;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function setAnonymous(bool $anonymous): void
    {
        $this->anonymous = $anonymous;
    }

    public function isVotingHistory(): bool
    {
        return $this->voting_history;
    }

    public function setVotingHistory(bool $voting_history): void
    {
        $this->voting_history = $voting_history;
    }

    public function isShowAttendees(): bool
    {
        return $this->show_attendees;
    }

    public function setShowAttendees(bool $show_attendees): void
    {
        $this->show_attendees = $show_attendees;
    }

    public function getFrozenBehaviour(): int
    {
        return $this->frozen_behaviour;
    }

    public function setFrozenBehaviour(int $frozen_behaviour): void
    {
        $this->frozen_behaviour = $frozen_behaviour;
    }

    public function getResultsBehaviour(): int
    {
        return $this->results_behaviour;
    }

    public function setResultsBehaviour(int $results_behaviour): void
    {
        $this->results_behaviour = $results_behaviour;
    }

    public function getPuk(): string
    {
        return $this->puk;
    }

    public function setPuk(string $puk): void
    {
        $this->puk = $puk;
    }

    public function isNicknames(): bool
    {
        return $this->nicknames;
    }

    public function setNicknames(bool $nicknames): void
    {
        $this->nicknames = $nicknames;
    }

    public function isScoreboard(): bool
    {
        return $this->scoreboard;
    }

    public function setScoreboard(bool $scoreboard): void
    {
        $this->scoreboard = $scoreboard;
    }

    public function getPlayer(): LiveVotingPlayer
    {
        return $this->player;
    }

    public function setPlayer(LiveVotingPlayer $player): void
    {
        $this->player = $player;
    }

    public function getVotingStyle(): string
    {
        return $this->voting_style;
    }

    public function setVotingStyle(string $voting_style): void
    {
        $this->voting_style = $voting_style;
    }

    /**
     * @throws LiveVotingException
     */
    public function save(): int
    {
        if (!isset($this->id) || $this->id == 0) {
            throw new LiveVotingException("LiveVoting::save() - LiveVoting ID is 0");
        }

        $database = new LiveVotingDatabase();

        $database->insertOnDuplicatedKey("rep_robj_xlvo_config_n", array(
            "obj_id" => $this->id,
            "pin" => $this->pin,
            "obj_online" => (int)$this->online,
            "anonymous" => (int)$this->anonymous,
            "frozen_behaviour" => $this->frozen_behaviour,
            "results_behaviour" => $this->results_behaviour,
            "voting_history" => (int)$this->voting_history,
            "show_attendees" => (int)$this->show_attendees,
            "puk" => $this->puk,
            "mode" => $this->mode->getMode(),
            "nicknames" => (int)$this->nicknames,
            "scoreboard" => (int)$this->scoreboard,
            "voting_style" => $this->voting_style
        ));

        return $this->id;
    }

    /**
     * @throws LiveVotingException
     */
    public function loadFromDB(): void
    {
        $database = new LiveVotingDatabase();

        $result = $database->select("rep_robj_xlvo_config_n", ["obj_id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setPin($result[0]["pin"]);
            $this->setOnline((bool)$result[0]["obj_online"]);
            $this->setAnonymous((bool)$result[0]["anonymous"]);
            $this->setFrozenBehaviour((int)$result[0]["frozen_behaviour"]);
            $this->setResultsBehaviour((int)$result[0]["results_behaviour"]);
            $this->setVotingHistory((bool)$result[0]["voting_history"]);
            $this->setShowAttendees((bool)$result[0]["show_attendees"]);
            $this->setPuk($result[0]["puk"]);
            $this->setMode(LiveVotingMode::new((int) $result[0]["mode"]));
            $this->setNicknames((bool)$result[0]["nicknames"]);
            $this->setScoreboard((bool)$result[0]["scoreboard"]);
            $this->setVotingStyle($result[0]["voting_style"]);
        } else {
            $this->loadDefaultValues();
        }

        $this->questions = LiveVotingQuestion::loadAllQuestionsByObjectId($this->getId());
    }

    /**
     * Load default values for the voting
     * @throws LiveVotingException
     */
    private function loadDefaultValues(): void
    {
        $this->setPin(LiveVoting::generatePin());
        $this->setOnline(false);
        $this->setAnonymous(true);
        $this->setFrozenBehaviour(0);
        $this->setResultsBehaviour(0);
        $this->setVotingHistory(false);
        $this->setShowAttendees(false);
        $this->setPuk(LiveVoting::generatePuk());
        $this->setMode(LiveVotingMode::new(LiveVotingMode::BASIC_MODE));
        $this->setNicknames(false);

        $this->save();
    }

    /**
     * @return void
     * @throws LiveVotingException
     */
    public function delete(): void
    {
        $database = new LiveVotingDatabase();

        $database->delete("rep_robj_xlvo_config_n", ["obj_id" => $this->getId()]);

        foreach ($this->getQuestions() as $question) {
            $question->delete();
        }

        $this->player->delete();
    }

    public function getQuestionById(int $id): ?LiveVotingQuestion
    {
        foreach ($this->questions as $question) {
            if ($question->getId() === $id) {
                return $question;
            }
        }

        return null;
    }

    /**
     * Generate a random pin
     * @throws LiveVotingException
     */
    public static function generatePin(): string
    {
        $database = new LiveVotingDatabase();
        $pin = LiveVoting::generateCode(4, true);

        $result = $database->select("rep_robj_xlvo_config_n", ["pin" => $pin]);

        while (isset($result[0])) {
            $pin = LiveVoting::generateCode(4, true);
            $result = $database->select("rep_robj_xlvo_config_n", ["pin" => $pin]);
        }

        return $pin;
    }

    /**
     * Generate a random puk
     * @throws LiveVotingException
     */
    public static function generatePuk(): string
    {
        $database = new LiveVotingDatabase();
        $puk = LiveVoting::generateCode(10);

        $result = $database->select("rep_robj_xlvo_config_n", ["puk" => $puk]);

        while (isset($result[0])) {
            $puk = LiveVoting::generateCode(10);
            $result = $database->select("rep_robj_xlvo_config_n", ["puk" => $puk]);
        }

        return $puk;
    }

    /**
     * Generate a random code
     *
     * @param int $lenght
     * @return string
     */
    private static function generateCode(int $lenght, bool $start_with_letter = false): string
    {
        $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $pin = "";

        for ($i = 0; $i < ($start_with_letter ? $lenght - 1 : $lenght); $i++) {
            $pin .= $characters[rand(0, strlen($characters) - 1)];
        }

        if ($start_with_letter) {
            $letters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $pin = $letters[rand(0, strlen($letters) - 1)] . $pin;
        }

        return $pin;
    }

    /**
     * @throws LiveVotingException
     */
    public static function getObjIdFromPin(string $pin, bool $safe_mode = true): int
    {
        $database = new LiveVotingDatabase();
        $result = $database->select("rep_robj_xlvo_config_n", array("pin" => $pin), array("obj_id"));

        if (isset($result[0])) {
            $liveVoting = new LiveVoting((int)$result[0]["obj_id"]);

            if (!$liveVoting->isOnline()) {
                if ($safe_mode) {
                    throw new LiveVotingException('The voting is not online');
                }
            }

            if (!$liveVoting->isAnonymous() && LiveVotingParticipant::getInstance()->isPINUser()) {
                if ($safe_mode) {
                    throw new LiveVotingException('The voting is not anonymous');
                }
            }

            return $liveVoting->getId();
        }

        return 0;
    }

    /**
     * @throws LiveVotingException
     */
    public static function getLiveVotingFromPin(string $pin): ?LiveVoting
    {
        $database = new LiveVotingDatabase();
        $result = $database->select("rep_robj_xlvo_config_n", array("pin" => $pin), array("obj_id"));

        if (isset($result[0])) {
            return new LiveVoting((int)$result[0]["obj_id"]);
        }

        return null;
    }

    /**
     * @param int $obj_id
     * @return string
     * @throws LiveVotingException
     */
    public static function getPinFromObjId(int $obj_id): string
    {
        $database = new LiveVotingDatabase();
        $result = $database->select("rep_robj_xlvo_config_n", array("obj_id" => $obj_id), array("pin"));

        if (isset($result[0])) {
            return $result[0]["pin"];
        }

        return "";
    }

    /**
     * @throws LiveVotingException
     */
    public static function getModeFromObjId(int $obj_id): int
    {
        $database = new LiveVotingDatabase();
        $result = $database->select("rep_robj_xlvo_config_n", array("obj_id" => $obj_id), array("mode"));

        if (isset($result[0])) {
            return (int) $result[0]["mode"];
        }

        return 0;
    }

    /**
     * Count questions
     */
    public function countQuestions(): int
    {
        return count($this->questions);
    }

    /**
     * Get question position
     */
    public function getQuestionPosition(): int
    {
        $position = 0;

        $current_question_id = $this->player->getActiveVoting();

        foreach ($this->questions as $question) {
            if ($question->getId() === $current_question_id) {
                return $position;
            }

            $position++;
        }

        return -1;
    }
}