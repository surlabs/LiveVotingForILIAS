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


use ilException;
use ilLiveVotingPlugin;
use ilTemplate;
use ilTemplateException;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\Voting\Bar\LiveVotingBarCollectionUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarFreeTextUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarGroupingCollectionUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarInfoGUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarPercentageUI;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingInputNumberRangeUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInputNumberRangeUI extends LiveVotingInputResultsGUI
{

    const BAR_COUNT = 5;
    const MAX_HISTOGRAM_BARS = 12;
    const TARGET_HISTOGRAM_BINS = 10;
    const DISPLAY_MODE_GROUPED_TEXT = 0;
    const DISPLAY_MODE_BARS = 1;
    const DISPLAY_MODE_GROUPED_TEXT_EXTENDED = 2;


    /**
     * @return string
     * @throws LiveVotingException
     * @throws ilTemplateException
     * @throws ilException
     */
    public function getHTML(): string
    {
        switch ($this->player->getActiveVotingObject()->getAltResultDisplayMode()) {
            case self::DISPLAY_MODE_BARS:
                return $this->isUsingNewUI() ? $this->renderHistogramResult() : $this->renderBarResult();
            case self::DISPLAY_MODE_GROUPED_TEXT_EXTENDED:
                return $this->renderGroupedTextResultWithInfo();
            case self::DISPLAY_MODE_GROUPED_TEXT:
            default:
                return $this->renderGroupedTextResult();
        }
    }


    /**
     * Render 5 horizontal bars which display the distribution of the answers.
     *
     * @return string The rendered result page.
     * @throws ilTemplateException|LiveVotingException
     */
    private function renderBarResult(): string
    {
        $values = $this->getAllVoteValues();

        $bars = new LiveVotingBarCollectionUI($this->isUsingNewUI());
        $voteSum = array_sum($values);

        foreach ($values as $key => $value) {
            $bar = new LiveVotingBarPercentageUI();
            $bar->setUseNewUI($this->isUsingNewUI());
            $bar->setMaxVotes($voteSum);
            $bar->setVotes((int)$value);
            $bar->setTitle($key);
            $bars->addBar($bar);
        }

        return $bars->getHTML();
    }


    /**
     * @return string
     * @throws LiveVotingException
     * @throws ilTemplateException|ilException
     */
    /**
     * New UI bars mode: vertical histogram with Participants, Mean and Median.
     * Small scales (up to MAX_HISTOGRAM_BARS distinct values, e.g. a 1-5 Likert) get one bar per value;
     * larger ranges are grouped into about TARGET_HISTOGRAM_BINS intervals of a round width (0-199, not 0-166).
     *
     * @throws ilTemplateException
     * @throws LiveVotingException
     */
    private function renderHistogramResult(): string
    {
        $plugin = ilLiveVotingPlugin::getInstance();
        $question = $this->player->getActiveVotingObject();
        $unit = ((int) $question->isPercentage() === 1) ? ' %' : '';
        $start = $question->getStartRange();
        $end = max($start, $question->getEndRange());
        $step = max(1, $question->getStepRange());

        $values = [];
        foreach (LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId()) as $vote) {
            $values[] = (int) $vote->getFreeInput();
        }
        sort($values, SORT_NUMERIC);

        $bins = [];
        $distinct = intdiv($end - $start, $step) + 1;
        if ($distinct <= self::MAX_HISTOGRAM_BARS) {
            for ($value = $start; $value <= $end; $value += $step) {
                $bins[] = ['lo' => $value, 'hi' => $value + $step - 1, 'count' => 0, 'label' => $value . $unit];
            }
        } else {
            $width = $this->getNiceBinWidth(($end - $start) / self::TARGET_HISTOGRAM_BINS, $step);
            $bin_count = max(1, (int) ceil(($end - $start) / $width));
            for ($i = 0; $i < $bin_count; $i++) {
                $lo = $start + $i * $width;
                // The last interval absorbs the upper bound, so 0-2000 ends in 1800-2000 instead of a lone 2000.
                $hi = ($i === $bin_count - 1) ? $end : $lo + $width - 1;
                $bins[] = ['lo' => $lo, 'hi' => $hi, 'count' => 0, 'label' => $lo . $unit . ' – ' . $hi . $unit];
            }
        }

        foreach ($values as $value) {
            $index = count($bins) - 1;
            foreach ($bins as $i => $bin) {
                if ($value <= $bin['hi']) {
                    $index = $i;
                    break;
                }
            }
            $bins[$index]['count']++;
        }

        $tpl = new ilTemplate($plugin->getDirectory() . '/templates/default/QuestionTypes/NumberRange/tpl.number_range_histogram_new.html', true, true);
        $participants = count($values);
        $mean = $participants > 0 ? round(array_sum($values) / $participants, 1) : null;
        $median = null;
        if ($participants > 0) {
            $middle = intdiv($participants, 2);
            $median = $participants % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
        }

        $tpl->setVariable('PARTICIPANTS', $plugin->txt('results_participants') . ': ' . $participants);
        $tpl->setVariable('MEAN', $plugin->txt('qtype_6_mean') . ': ' . ($mean === null ? '–' : $mean . $unit));
        $tpl->setVariable('MEDIAN', $plugin->txt('qtype_6_median') . ': ' . ($median === null ? '–' : $median . $unit));
        $tpl->setVariable(
            'AXIS_CAPTION',
            $plugin->txt($distinct <= self::MAX_HISTOGRAM_BARS ? 'qtype_6_answers_per_value' : 'qtype_6_answers_per_interval')
        );

        $max_count = max(1, max(array_column($bins, 'count')));
        foreach ($bins as $bin) {
            $tpl->setCurrentBlock('bin');
            $tpl->setVariable('LABEL', $bin['label']);
            $tpl->setVariable('COUNT', (string) $bin['count']);
            $tpl->setVariable('HEIGHT', str_replace(',', '.', (string) round($bin['count'] / $max_count * 100, 1)));
            $tpl->setVariable('EMPTY_CLASS', $bin['count'] === 0 ? ' is-empty' : '');
            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
    }

    /**
     * Rounds a raw interval width up to 1, 2, 2.5, 5 or 10 times a power of ten, and to a multiple of the question's step.
     */
    private function getNiceBinWidth(float $raw, int $step): int
    {
        $raw = max(1.0, $raw);
        $exponent = floor(log10($raw));
        $fraction = $raw / (10 ** $exponent);
        if ($fraction <= 1) {
            $nice = 1;
        } elseif ($fraction <= 2) {
            $nice = 2;
        } elseif ($fraction <= 2.5) {
            $nice = 2.5;
        } elseif ($fraction <= 5) {
            $nice = 5;
        } else {
            $nice = 10;
        }
        $width = max(1, (int) round($nice * (10 ** $exponent)));

        return (int) (ceil($width / $step) * $step);
    }

    private function renderGroupedTextResultWithInfo(): string
    {
        $votes = LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId());
        $vote_count = LiveVotingVote::countVotes($this->player->getActiveVoting(), $this->player->getRoundId());

        $vote_sum = 0;
        $values = [];
        $modes = [];

        foreach ($votes as $vote) {
            $value = (int)$vote->getFreeInput();

            $values[] = $value;

            if (!isset($modes[$value])) {
                $modes[$value] = 0;
            }

            $modes[$value]++;

            $vote_sum = $vote_sum + $value;
        }

        $mode = ilLiveVotingPlugin::getInstance()->txt("qtype_6_mode_not_applicable");

        if (!empty($modes)) {
            $mode = array_keys($modes, max($modes))[0];
        }

        $calculateMedian = function ($aValues) {
            $aToCareAbout = array_filter($aValues, function ($value) {
                return $value >= 0; // Eliminar esta condición si quieres incluir todos los valores
            });

            $iCount = count($aToCareAbout);
            if ($iCount === 0) return 0;

            sort($aToCareAbout, SORT_NUMERIC);

            $middle = floor($iCount / 2);

            if ($iCount % 2 === 0) {
                return ($aToCareAbout[$middle - 1] + $aToCareAbout[$middle]) / 2;
            } else {
                return $aToCareAbout[$middle];
            }
        };

        $info = new LiveVotingBarCollectionUI($this->isUsingNewUI());
        $value = $vote_count > 0 ? round($vote_sum / $vote_count, 2) : 0;
        $mean = new LiveVotingBarInfoGUI(ilLiveVotingPlugin::getInstance()->txt("qtype_6_mean"), (string)$value);
        $mean->setBig(true);
        $mean->setDark(true);
        $mean->setCenter(true);
        $info->addBar($mean);

        $median = new LiveVotingBarInfoGUI(ilLiveVotingPlugin::getInstance()->txt("qtype_6_median"), (string)$calculateMedian($values));
        $median->setBig(true);
        $median->setCenter(true);
        $median->setDark(true);
        $info->addBar($median);

        $mode = new LiveVotingBarInfoGUI(ilLiveVotingPlugin::getInstance()->txt("qtype_6_mode"), (string)$mode);
        $mode->setBig(true);
        $mode->setDark(true);
        $mode->setCenter(true);
        $info->addBar($mode);
        return $info->getHTML() . $this->renderGroupedTextResult();
    }


    /**
     * Render a result page which shows all answers as text.
     * The answers are grouped together and sorted descending.
     *
     * @return string The rendered result page.
     * @throws LiveVotingException
     * @throws ilException
     */
    private function renderGroupedTextResult(): string
    {
        $bars = new LiveVotingBarGroupingCollectionUI($this->isUsingNewUI());
        //$bars->sorted(true);
        $votes = LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId());
        usort($votes, function (LiveVotingVote $v1, LiveVotingVote $v2) {
            return (intval($v1->getFreeInput()) - intval($v2->getFreeInput()));
        });
        foreach ($votes as $value) {
            $bar = new LiveVotingBarFreeTextUI($value);
            $bar->setBig(true);
            $bar->setCenter(true);
            $bar->setAlwaysShowCount($this->isUsingNewUI());
            $bars->addBar($bar);
        }

        return $bars->getHTML();
    }


    /**
     * @param LiveVotingVote[] $votes
     *
     * @return string
     * @throws LiveVotingException
     */
    public function getTextRepresentationForVotes(array $votes): string
    {
        $result = LiveVotingInputNumberRangeUI::getInstance($this->player);

        return $result->getTextRepresentationForVotes($votes);
    }


    /**
     * Fetches all data and simplifies them to an array with 10 values.
     *
     * The array keys indicates the range and the value reflects the sum of all votes within it.
     *
     * @return string[]
     * @throws LiveVotingException
     */
    private function getAllVoteValues(): array
    {
        $percentage = ((int)$this->player->getActiveVotingObject()->isPercentage() === 1) ? ' %' : '';

        //generate array which is equal in its length to the range from start to end
        $start = $this->player->getActiveVotingObject()->getStartRange();
        $end = $this->player->getActiveVotingObject()->getEndRange();
        $count = ($end - $start);
        $values = array_fill($start, ($count + 1), 0);

        $votes = LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId());

        //count all votes per option
        /**
         * @var LiveVotingVote $vote
         */
        foreach ($votes as $vote) {
            $value = (int)$vote->getFreeInput();
            $values[$value]++;
        }

        //Create 10 slices and sum each slice
        $slices = [];
        $sliceWidth = ceil($count / self::BAR_COUNT);

        for ($i = 0; $i < $count; $i += $sliceWidth) {
            //create a slice
            $slice = array_slice($values, (int)$i, (int)$sliceWidth + (($i + $sliceWidth >= $count) ? 1 : 0), true);

            //sum slice values
            $sum = array_sum($slice);

            //fetch keys to generate new key for slices
            $keys = array_keys($slice);
            $keyCount = count($keys);

            //only display a range if we got more than one element
            if ($keyCount > 1) {
                $key = "{$keys[0]}$percentage - {$keys[$keyCount - 1]}$percentage";
            } else {
                $key = "{$keys[0]}$percentage";
            }

            //create now slice entry
            $slices[$key] = $sum;
        }

        return $slices;
    }
}
