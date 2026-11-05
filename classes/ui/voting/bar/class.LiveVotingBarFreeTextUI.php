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
use ilTemplate;
use ilTemplateException;
use LiveVoting\votings\LiveVotingVote;

class LiveVotingBarFreeTextUI extends LiveVotingAbstractBarUI implements LiveVotingGeneralBarUI
{

    /**
     * @var LiveVotingVote
     */
    protected LiveVotingVote $vote;
    /**
     * @var int
     */
    private int $occurrences;
    /**
     * @var bool
     */
    private bool $removable = false;
    /**
     * @var bool
     */
    private bool $strong = false;
    /**
     * @var bool
     */
    private bool $center = false;
    /**
     * @var bool
     */
    private bool $big = false;
    /**
     * Number range lists show how many voters picked each value, also when it is only one.
     */
    private bool $always_show_count = false;


    /**
     * @param LiveVotingVote $vote
     */
    public function __construct(LiveVotingVote $vote)
    {
        global $DIC;
        parent::__construct();
        $this->vote = $vote;
        $this->tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/Bar/tpl.bar_free_input.html', true, true);

        $this->occurrences = 0;
    }


    /**
     *
     * @throws ilTemplateException
     */
    protected function render()
    {
        $this->tpl->setVariable('FREE_INPUT', nl2br($this->vote->getFreeInput(), false));
        $this->tpl->setVariable('ID', $this->vote->getId());

        if ($this->isRemovable()) {
            $this->tpl->touchBlock('remove_button');
        }
        if ($this->isCenter()) {
            $this->tpl->touchBlock('center');
        }
        if ($this->isBig()) {
            $this->tpl->touchBlock('big');
        }
        if ($this->isStrong()) {
            $this->tpl->touchBlock('strong');
            $this->tpl->touchBlock('strong_end');
        }

        if ($this->occurrences > 1 || ($this->always_show_count && $this->occurrences > 0)) {
            $this->tpl->setVariable('GROUPED_BARS_COUNT', $this->occurrences);
        }
    }


    /**
     * @return string
     * @throws ilTemplateException
     */
    public function getHTML(): string
    {
        $this->render();

        return $this->tpl->get();
    }


    /**
     * @return int
     */
    public function getOccurrences(): int
    {
        return $this->occurrences;
    }


    /**
     * Compares the freetext of the current with the given object.
     * This function returns true if the free text is case insensitive equal to the
     * given one.
     *
     * @param LiveVotingBarFreeTextUI $bar The object which should be used for the comparison.
     *
     * @return bool True if the freetext is case insensitive equal to the given one.
     */
    public function equals(LiveVotingBarFreeTextUI $bar): bool
    {
        return strcasecmp(nl2br($this->vote->getFreeInput(), false), nl2br($bar->vote->getFreeInput(), false)) === 0;
    }


    /**
     * @param int $occurrences
     */
    public function setOccurrences(int $occurrences)
    {
        $this->occurrences = $occurrences;
    }


    public function setAlwaysShowCount(bool $always_show_count): void
    {
        $this->always_show_count = $always_show_count;
    }


    /**
     * @return bool
     */
    public function isStrong(): bool
    {
        return $this->strong;
    }


    /**
     * @param bool $strong
     */
    public function setStrong(bool $strong)
    {
        $this->strong = $strong;
    }


    /**
     * @return bool
     */
    public function isCenter(): bool
    {
        return $this->center;
    }


    /**
     * @param bool $center
     */
    public function setCenter(bool $center)
    {
        $this->center = $center;
    }


    /**
     * @return bool
     */
    public function isBig(): bool
    {
        return $this->big;
    }


    /**
     * @param bool $big
     */
    public function setBig(bool $big)
    {
        $this->big = $big;
    }


    /**
     * @return bool
     */
    public function isRemovable(): bool
    {
        return $this->removable;
    }


    /**
     * @param bool $removable
     */
    public function setRemovable(bool $removable)
    {
        $this->removable = $removable;
    }
}

