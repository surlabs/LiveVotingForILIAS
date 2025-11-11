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


require_once __DIR__ . '/../../../../../../../../vendor/composer/vendor/autoload.php';
require_once "dir.php";

use LiveVoting\platform\ilias\LiveVotingContext;
use LiveVoting\platform\ilias\LiveVotingInitialisation;
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\player\LiveVotingInitialisationUI;
use LiveVoting\votings\LiveVotingParticipant;


$context = LiveVotingContext::getContext();

switch ($context) {
    case 1:
        LiveVotingInitialisationUI::init();
        LiveVotingParticipant::getInstance()->setIdentifier(session_id())->setType(2);
        break;
    case 2:
    default:
        LiveVotingInitialisation::init();
        global $DIC;
        LiveVotingParticipant::getInstance()->setIdentifier((string) $DIC->user()->getId())->setType(1);

        break;
}

LiveVotingConfig::load();

global $DIC;
$DIC->ctrl()->setTargetScript(LiveVotingConfig::getFullApiURL());
$DIC->ctrl()->callBaseClass();