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

use LiveVoting\platform\ilias\LiveVotingContext;
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\player\LiveVotingInitialisationUI;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingParticipant;

require_once __DIR__ . '/../../../../../../../../vendor/composer/vendor/autoload.php';
require_once "dir.php";


try {
    LiveVotingInitialisationUI::init();

    LiveVotingParticipant::getInstance()->setIdentifier(session_id())->setType(2);

    LiveVotingContext::setContext(1);

    $param_manager = ParamManager::getInstance();

    $pin = $param_manager->getPin();

    global $DIC;

    $DIC->ctrl()->setTargetScript(LiveVotingConfig::getFullApiUrl());

    if (!empty($pin)) {
        $live_voting = LiveVoting::getLiveVotingFromPin($pin);
        if ($live_voting && $live_voting->isAnonymous()) {
            $DIC->ctrl()->redirectByClass(["ilObjPluginDispatchGUI", "LiveVotingPlayerGUI"], 'startVoterPlayer');
        } else if ($live_voting) {
            $obj_id = LiveVoting::getObjIdFromPin($pin, false);
            $refs = ilObject::_getAllReferences($obj_id);

            if (empty($refs)) {
                throw new Exception("No references found for pin: {$pin}");
            }

            $non_anonymous_voting_link = LiveVotingConfig::getBaseVoteUrl() . "goto.php?target=xlvo_1_pin_" . $pin;

            $DIC->ctrl()->redirectToURL($non_anonymous_voting_link);
        } else {
            $DIC->ctrl()->redirectByClass(["ilObjPluginDispatchGUI", "LiveVotingPlayerGUI"], 'requestPin');
        }
    } else {
        $DIC->ctrl()->redirectByClass(["ilObjPluginDispatchGUI", "LiveVotingPlayerGUI"], 'requestPin');
    }
} catch (Throwable $ex) {
    echo $ex->getMessage() . "<br /><br /><a href='/'>back</a>";
}
