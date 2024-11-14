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

namespace LiveVoting\platform\ilias;
use ilException;
use ILIAS;

class LiveVotingILIAS extends ILIAS
{
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * @param string $a_keyword
     * @param string|null $a_default_value
     * @return string|null
     */
    public function getSetting(string $a_keyword, ?string $a_default_value = null): ?string
    {
        global $DIC;
        return $DIC->settings()->get($a_keyword);
    }


    /**
     * wrapper for downward compability
     *
     * @throws ilException
     */
    public function raiseError(string $a_msg, int $a_err_obj): void
    {
        throw new ilException($a_msg);
    }
}
