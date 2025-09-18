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

use DateTime;
use DateTimeZone;
use Exception;

/**
 *  Class LiveVotingUtils
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingUtils
{
    /**
     * @throws Exception
     */
    public static function getTime(): int
    {
        $time = new DateTime("now", new DateTimeZone("Europe/Berlin"));

        return $time->getTimestamp();
    }


    /**
     * This method is a better alternative to ilUtil::secureString because ensure that the tag is really a tag and not a comparator.
     *
     * @param string $a_str
     * @return string
     */
    public static function secureString(string $a_str): string
    {
        $sec_tags = ["strong",
            "em",
            "u",
            "strike",
            "ol",
            "li",
            "ul",
            "p",
            "div",
            "i",
            "b",
            "code",
            "sup",
            "sub",
            "pre",
            "gap",
            "a",
            "img",
            "bdo"
        ];

        return preg_replace_callback('/<[^>]*>/', function ($matches) use ($sec_tags) {
            if (in_array($matches[0], $sec_tags)) {
                return $matches[0];
            } else {
                return '';
            }
        }, $a_str);
    }

    public static function _solveKeyBracketsBug(string $text): string
    {
        $text1 = str_replace("{", "&#123;", $text);

        return str_replace("}", "&#125;", $text1);
    }
}