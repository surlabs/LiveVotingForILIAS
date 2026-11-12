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

class LiveVotingBarCollectionUI
{
    /**
     * @var ilTemplate
     */
    protected ilTemplate $tpl;
    /**
     * @var int
     */
    protected int $total_votes = 0;
    /**
     * @var bool
     */
    protected bool $show_total_votes = false;
    /**
     * @var int
     */
    protected int $total_voters = 0;
    /**
     * @var bool
     */
    protected bool $show_total_voters = false;
    /**
     * @var bool
     */
    protected bool $use_new_ui = false;
    protected int $bar_count = 0;

    /**
     * @throws ilSystemStyleException
     * @throws ilTemplateException
     */
    public function __construct(bool $use_new_ui = false)
    {
        $this->use_new_ui = $use_new_ui;
        $template = $use_new_ui ? 'tpl.bar_collection_new.html' : 'tpl.bar_collection.html';
        $this->tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/Bar/' . $template, true, true);
    }


    /**
     * @return bool
     */
    public function isUseNewUI(): bool
    {
        return $this->use_new_ui;
    }

    /**
     * @return string
     * @throws ilTemplateException
     */
    public function getHTML(): string
    {
        $this->renderVotersAndVotes();

        return $this->tpl->get();
    }

    /**
     * @param LiveVotingGeneralBarUI $bar_gui
     * @throws ilTemplateException
     */
    public function addBar(LiveVotingGeneralBarUI $bar_gui)
    {
        $this->bar_count++;
        if ($bar_gui instanceof LiveVotingBarPercentageUI && $bar_gui->getColorIndex() === 0) {
            $bar_gui->setColorIndex($this->bar_count);
        }
        $this->tpl->setCurrentBlock('bar');
        $this->tpl->setVariable('BAR', $bar_gui->getHTML());
        $this->tpl->parseCurrentBlock();
    }


    /**
     * @param $html
     * @throws ilTemplateException
     */
    public function addSolution($html): void
    {
        $this->tpl->setCurrentBlock('solution');
        $this->tpl->setVariable('SOLUTION', $html);
        $this->tpl->parseCurrentBlock();
    }


    /**
     * @return int
     */
    public function getTotalVotes(): int
    {
        return $this->total_votes;
    }


    /**
     * @param int $total_votes
     */
    public function setTotalVotes(int $total_votes): void
    {
        $this->total_votes = $total_votes;
    }


    /**
     * @return boolean
     */
    public function isShowTotalVotes(): bool
    {
        return $this->show_total_votes;
    }


    /**
     * @param boolean $show_total_votes
     */
    public function setShowTotalVotes(bool $show_total_votes): void
    {
        $this->show_total_votes = $show_total_votes;
    }


    /**
     * @return int
     */
    public function getTotalVoters(): int
    {
        return $this->total_voters;
    }


    /**
     * @param int $total_voters
     */
    public function setTotalVoters(int $total_voters)
    {
        $this->total_voters = $total_voters;
    }


    /**
     * @return boolean
     */
    public function isShowTotalVoters(): bool
    {
        return $this->show_total_voters;
    }


    /**
     * @param boolean $show_total_voters
     */
    public function setShowTotalVoters(bool $show_total_voters)
    {
        $this->show_total_voters = $show_total_voters;
    }


    /**
     *
     * @throws ilTemplateException
     */
    protected function renderVotersAndVotes()
    {
        if ($this->isShowTotalVotes()) {
            $this->tpl->setCurrentBlock('total_votes');
            $this->tpl->setVariable('TOTAL_VOTES', ilLiveVotingPlugin::getInstance()->txt('qtype_1_total_votes') . ': ' . $this->getTotalVotes());
            $this->tpl->parseCurrentBlock();
        }
        if ($this->isShowTotalVoters()) {
            $this->tpl->setCurrentBlock('total_voters');
            $voters_key = $this->isUseNewUI() ? 'results_participants' : 'qtype_1_total_voters';
            $this->tpl->setVariable('TOTAL_VOTERS', ilLiveVotingPlugin::getInstance()->txt($voters_key) . ': ' . $this->getTotalVoters());
            $this->tpl->parseCurrentBlock();
        }
    }

}

