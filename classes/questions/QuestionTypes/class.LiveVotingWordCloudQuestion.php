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

namespace LiveVoting\questions\QuestionTypes;

use ilLiveVotingPlugin;

/**
 * Class LiveVotingWordCloudQuestion
 *
 * A free text question whose answers are single words, shown as a word cloud. Every voter can hand in several
 * words, one per field, and the answers are stored as free text votes. Only available in the new UI.
 *
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingWordCloudQuestion extends LiveVotingFreeTextQuestion
{
    public const MAX_WORD_LENGTH = 30;

    public function __construct(?array $data = null)
    {
        parent::__construct($data);

        $this->setMultiFreeInput(true);
        $this->setAnswerField(1);
    }

    public function getQuestionType(): string
    {
        return "WordCloud";
    }

    public function getQuestionTypeLabel(): string
    {
        return ilLiveVotingPlugin::getInstance()->txt("voting_type_7");
    }

    /**
     * Splits an answer into the words a word cloud can show: whitespace starts the next word and every word is cut
     * to the maximum length.
     *
     * @return string[]
     */
    public static function splitWords(string $input): array
    {
        $words = [];

        foreach (preg_split('/\s+/u', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $words[] = mb_substr($word, 0, self::MAX_WORD_LENGTH);
        }

        return $words;
    }
}
