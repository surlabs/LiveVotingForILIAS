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
                return $this->renderBarResult();
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

        $bars = new LiveVotingBarCollectionUI();
        $voteSum = array_sum($values);

        foreach ($values as $key => $value) {
            $bar = new LiveVotingBarPercentageUI();
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

        $info = new LiveVotingBarCollectionUI();
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
        return $info->getHTML() . "<div class='row'><br></div>" . $this->renderGroupedTextResult();
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
        $bars = new LiveVotingBarGroupingCollectionUI();
        //$bars->sorted(true);
        $votes = LiveVotingVote::getVotesOfQuestion($this->player->getActiveVoting(), $this->player->getRoundId());
        usort($votes, function (LiveVotingVote $v1, LiveVotingVote $v2) {
            return (intval($v1->getFreeInput()) - intval($v2->getFreeInput()));
        });
        foreach ($votes as $value) {
            $bar = new LiveVotingBarFreeTextUI($value);
            $bar->setBig(true);
            $bar->setCenter(true);
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
