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

use Exception;
use ilContext;
use LiveVoting\player\LiveVotingContextUI;

class LiveVotingContext extends ilContext
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        self::init(LiveVotingContextUI::class);
    }

    /**
     * @param $context
     * @return bool
     * @throws Exception
     */
    public static function init($context): bool
    {
        ilContext::$class_name = LiveVotingContextUI::class;
        ilContext::$type = "-1";

        if ($context) {
            self::setContext($context);
        }

        return true;
    }

    /**
     * @return mixed
     */
    public static function getContext()
    {
        if (!empty($_COOKIE['xlvo_context'])) {
            return $_COOKIE['xlvo_context'];
        }

        return 2;
    }

    /**
     * @param $context
     * @throws Exception
     */
    public static function setContext($context)
    {
        if ($context === 2 || $context === 1) {
            $result = setcookie('xlvo_context', (string)$context, 0, '/');
        } else {
            throw new Exception('Invalid context received');
        }

        if (!$result) {
            throw new Exception("error setting cookie");
        }
    }
}