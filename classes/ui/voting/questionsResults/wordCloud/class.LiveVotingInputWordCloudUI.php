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

namespace LiveVoting\UI\QuestionsResults;

use ilLiveVotingPlugin;
use ilTemplate;
use ilTemplateException;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\QuestionTypes\LiveVotingWordCloudQuestion;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingInputWordCloudUI
 *
 * Results of a word cloud question. The server groups the answers and the browser lays the cloud out
 * (xlvoWordCloud.js), because the placement depends on the rendered size of every word.
 *
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInputWordCloudUI extends LiveVotingInputResultsGUI
{
    public function __construct(LiveVotingPlayer $player)
    {
        parent::__construct($player);
    }

    /**
     * Groups the answers without distinguishing case and keeps the first spelling received. The most voted words come
     * first; ties keep the order of arrival, so the cloud does not reshuffle on every refresh.
     *
     * @param LiveVotingVote[] $votes
     * @return array<int, array{key: string, label: string, count: int}>
     */
    public static function groupWords(array $votes): array
    {
        usort($votes, static fn(LiveVotingVote $a, LiveVotingVote $b): int => $a->getId() <=> $b->getId());

        $words = [];
        foreach ($votes as $vote) {
            $label = LiveVotingWordCloudQuestion::splitWords((string) $vote->getFreeInput())[0] ?? '';
            if ($label === '') {
                continue;
            }

            $key = self::normalise($label);
            if (!isset($words[$key])) {
                $words[$key] = ['key' => $key, 'label' => $label, 'count' => 0, 'order' => count($words)];
            }
            $words[$key]['count']++;
        }

        usort($words, static fn(array $a, array $b): int => [$b['count'], $a['order']] <=> [$a['count'], $b['order']]);

        return array_map(static fn(array $word): array => [
            'key' => $word['key'],
            'label' => $word['label'],
            'count' => $word['count'],
        ], $words);
    }

    public static function normalise(string $word): string
    {
        return mb_strtolower($word, 'UTF-8');
    }

    /**
     * @throws ilTemplateException
     * @throws LiveVotingException
     */
    public function getHTML(): string
    {
        $plugin = ilLiveVotingPlugin::getInstance();
        $tpl = new ilTemplate($plugin->getDirectory() . '/templates/default/QuestionTypes/WordCloud/tpl.word_cloud_results_new.html', true, true);

        $votes = LiveVotingVote::getVotesOfOption($this->player->getActiveVotingObject()->getFirstOption()->getId(), $this->player->getRoundId());
        $words = self::groupWords($votes);
        $total_answers = array_sum(array_column($words, 'count'));

        $tpl->setVariable(
            'PARTICIPANTS',
            $plugin->txt('results_participants') . ': '
            . LiveVotingVote::countVoters($this->player->getActiveVoting(), $this->player->getRoundId())
        );
        $tpl->setVariable('ANSWERS', $plugin->txt('qtype_2_total_answers') . ': ' . $total_answers);
        $tpl->setVariable('UNIQUE', $plugin->txt('qtype_7_unique_words') . ': ' . count($words));
        $tpl->setVariable('EMPTY_TEXT', $plugin->txt('results_waiting_answers'));
        $tpl->setVariable('EMPTY_HIDDEN', $words === [] ? '' : 'hidden');
        $tpl->setVariable('WORDS', htmlspecialchars((string) json_encode($words, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
        $tpl->setVariable('TOTAL', (string) $total_answers);

        foreach ([
            'TXT_VOTE' => 'qtype_7_vote',
            'TXT_VOTES' => 'qtype_7_votes',
            'TXT_SHARE' => 'qtype_7_share',
            'TXT_REMOVE' => 'qtype_7_remove_word',
            'TXT_OVERFLOW_ONE' => 'qtype_7_overflow_one',
            'TXT_OVERFLOW_MANY' => 'qtype_7_overflow_many',
        ] as $variable => $key) {
            $tpl->setVariable($variable, htmlspecialchars($plugin->txt($key), ENT_QUOTES, 'UTF-8'));
        }

        return $tpl->get();
    }

    /**
     * @param LiveVotingVote[] $votes
     */
    public function getTextRepresentationForVotes(array $votes): string
    {
        $words = [];
        foreach ($votes as $vote) {
            $words[] = (string) $vote->getFreeInput();
        }

        return implode(", ", $words);
    }
}
