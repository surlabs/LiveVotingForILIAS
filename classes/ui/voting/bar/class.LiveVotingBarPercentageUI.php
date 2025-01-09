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

namespace LiveVoting\UI\Voting\Bar;

use ilLiveVotingPlugin;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;

class LiveVotingBarPercentageUI implements LiveVotingGeneralBarUI
{
    /**
     * @var float
     */
    protected float $votes = 0;
    /**
     * @var int
     */
    protected int $max_votes = 100;
    /**
     * @var string
     */
    protected string $option_letter = '';
    /**
     * @var ilTemplate
     */
    protected ilTemplate $tpl;
    /**
     * @var string
     */
    protected string $title = '';
    /**
     * @var bool
     */
    protected bool $show_in_percent = false;
    /**
     * @var int
     */
    protected int $round = 2;


    /**
     *
     */
    public function __construct()
    {

    }


    /**
     * @return string
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     */
    public function getHTML(): string
    {
        $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/Bar/tpl.bar_percentage.html', true, true);

        $tpl->setVariable('TITLE', $this->getTitle());

        $tpl->setVariable('ID', uniqid());
        $tpl->setVariable('TITLE', $this->getTitle());

        if ($this->getOptionLetter()) {
            $tpl->setCurrentBlock('option_letter');
            $tpl->setVariable('OPTION_LETTER', $this->getOptionLetter());
            $tpl->parseCurrentBlock();
        }

        if ($this->getMaxVotes() == 0) {
            $calculated_percentage = 0;
        } else {
            $calculated_percentage = $this->getVotes() / $this->getMaxVotes() * 100;
        }

        $tpl->setVariable('MAX', $this->getMaxVotes());
        $tpl->setVariable('PERCENT', $this->getVotes());
        $tpl->setVariable('PERCENT_STYLE', str_replace(',', '.', (string)round($calculated_percentage, 1)));
        if ($this->isShowInPercent()) {
            $tpl->setVariable('PERCENT_TEXT', round($calculated_percentage, $this->getRound()) . ' %');
        } else {
            $tpl->setVariable('PERCENT_TEXT', round($this->getVotes(), $this->getRound()));
        }

        return $tpl->get();
    }


    /**
     * @return int
     */
    public function getVotes(): float
    {
        return $this->votes;
    }


    /**
     * @param int $votes
     */
    public function setVotes(int|float $votes)
    {
        $this->votes = $votes;
    }


    /**
     * @return int
     */
    public function getMaxVotes(): int
    {
        return $this->max_votes;
    }


    /**
     * @param int $max_votes
     */
    public function setMaxVotes(int $max_votes)
    {
        $this->max_votes = $max_votes;
    }


    /**
     * @return string
     */
    public function getOptionLetter(): string
    {
        return $this->option_letter;
    }


    /**
     * @param string $option_letter
     */
    public function setOptionLetter(string $option_letter)
    {
        $this->option_letter = $option_letter;
    }


    /**
     * @return ilTemplate
     */
    public function getTpl(): ilTemplate
    {
        return $this->tpl;
    }


    /**
     * @param ilTemplate $tpl
     */
    public function setTpl(ilTemplate $tpl)
    {
        $this->tpl = $tpl;
    }


    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }


    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
    }


    /**
     * @return boolean
     */
    public function isShowInPercent(): bool
    {
        return $this->show_in_percent;
    }


    /**
     * @param boolean $show_in_percent
     */
    public function setShowInPercent(bool $show_in_percent)
    {
        $this->show_in_percent = $show_in_percent;
    }


    /**
     * @return int
     */
    public function getRound(): int
    {
        return $this->round;
    }


    /**
     * @param int $round
     */
    public function setRound(int $round)
    {
        $this->round = $round;
    }
}

