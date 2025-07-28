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

namespace LiveVoting\Utils;

use ilCtrlException;
use ilObject;
use ilSetting;
use ilUIPluginRouterGUI;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\votings\LiveVoting;


/**
 * Class ParamManager
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
final class ParamManager
{

    /**
     * @var ParamManager
     */
    protected static $instance;
    /**
     * @var int
     */
    protected int $ref_id;
    /**
     * @var string
     */
    protected string $pin = '';
    /**
     * @var string
     */
    protected string $puk = '';
    /**
     * @var int
     */
    protected int $voting = 0;
    /**
     * @var bool
     */
    protected bool $ppt = false;

    /*
     * @var ilSetting
     */
    private ilSetting $settings;


    /**
     * ParamManager constructor
     * @throws ilCtrlException
     */
    public function __construct()
    {
        $this->loadAndPersistAllParams();
    }


    /**
     * @return self
     */
    public static function getInstance(): ParamManager
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     *
     * @throws ilCtrlException
     */
    private function loadAndPersistAllParams()
    {
        $pin = trim((string) filter_input(INPUT_GET, 'xlvo_pin') ?? "", "/");
        if (!empty($pin)) {
            $this->setPin($pin);
        }

        $ref_id = trim((string) filter_input(INPUT_GET, 'ref_id') ?? "", "/");
        if (!empty($ref_id)) {
            $this->setRefId((int)$ref_id);
        }

        $puk = trim((string) filter_input(INPUT_GET, 'xlvo_puk') ?? "", "/");
        if (!empty($puk)) {
            $this->setPuk($puk);
        }

        $voting = trim((string) filter_input(INPUT_GET, 'xlvo_voting') ?? "", "/");
        if (!empty($voting)) {
            $this->setVoting((int)$voting);
        }

        $ppt = trim((string) filter_input(INPUT_GET, 'xlvo_ppt') ?? "", "/");
        if (!empty($ppt)) {
            $this->setPpt((bool)$ppt);
        }
    }


    /**
     * @return int
     * @throws LiveVotingException
     */
    public function getRefId(): int
    {
        $ref_id = trim((string) filter_input(INPUT_GET, 'ref_id'), "/");

        if (!empty($ref_id)) {
            $this->ref_id = (int)$ref_id;
        }

        if (empty($this->ref_id)) {
            $obj_id = LiveVoting::getObjIdFromPin($this->pin);

            if ($obj_id > 0) {
                $this->ref_id = current(ilObject::_getAllReferences($obj_id));
            } else {
                $this->ref_id = 0;
            }
        }

        return $this->ref_id;
    }


    /**
     * @param int $ref_id
     * @throws ilCtrlException
     */
    public function setRefId(int $ref_id)
    {
        global $DIC;

        $this->ref_id = $ref_id;

        $DIC->ctrl()->setParameterByClass(ilUIPluginRouterGUI::class, 'ref_id', $ref_id);
    }


    /**
     * @return string
     */
    public function getPin(): string
    {
        return $this->pin;
    }


    /**
     * @param string $pin
     * @throws ilCtrlException
     */
    public function setPin(string $pin)
    {
        global $DIC;
        $this->pin = $pin;

        $DIC->ctrl()->setParameterByClass(ilUIPluginRouterGUI::class, 'xlvo_pin', $pin);
    }


    /**
     * @return string
     */
    public function getPuk(): string
    {
        return $this->puk;
    }


    /**
     * @param string $puk
     * @throws ilCtrlException
     */
    public function setPuk(string $puk)
    {
        global $DIC;

        $this->puk = $puk;
        $DIC->ctrl()->setParameterByClass(ilUIPluginRouterGUI::class, 'xlvo_puk', $puk);
    }


    /**
     * @return int
     */
    public function getVoting(): int
    {
        return $this->voting;
    }


    /**
     * @param int $voting
     * @throws ilCtrlException
     */
    public function setVoting(int $voting)
    {
        global $DIC;
        $this->voting = $voting;

        $DIC->ctrl()->setParameterByClass(ilUIPluginRouterGUI::class, 'xlvo_voting', $voting);
    }


    /**
     * @return bool
     */
    public function isPpt(): bool
    {
        return $this->ppt;
    }


    /**
     * @param bool $ppt
     * @throws ilCtrlException
     */
    public function setPpt(bool $ppt)
    {
        global $DIC;
        $this->ppt = $ppt;

        $DIC->ctrl()->setParameterByClass(ilUIPluginRouterGUI::class, 'xlvo_ppt', $ppt);
    }

}
