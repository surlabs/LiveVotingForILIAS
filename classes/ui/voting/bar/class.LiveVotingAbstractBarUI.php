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

abstract class LiveVotingAbstractBarUI implements LiveVotingGeneralBarUI
{
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
     * @var bool
     */
    private bool $dark = false;
    /**
     * @var ilTemplate
     */
    protected ilTemplate $tpl;


    /**
     * xlvoAbstractBarGUI constructor.
     */
    public function __construct()
    {

    }


    /**
     *
     */
    protected function initTemplate()
    {
        global $DIC;
        try {
            $this->tpl = new ilTemplate (ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/Bar/tpl.bar_free_input.html', true, true);
            $DIC->ui()->mainTemplate()->addCss("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/default/Bar/bar.css");
        } catch (ilSystemStyleException|ilTemplateException $e) {
            $DIC->ui()->mainTemplate()->setContent($DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($e->getMessage())));
        }
    }


    /**
     *
     * @throws ilTemplateException
     */
    protected function render()
    {
        $this->initTemplate();
        if ($this->isCenter()) {
            $this->tpl->touchBlock('center');
        }
        if ($this->isBig()) {
            $this->tpl->touchBlock('big');
        }
        if ($this->isDark()) {
            $this->tpl->touchBlock('dark');
        }
        if ($this->isStrong()) {
            $this->tpl->touchBlock('strong');
            $this->tpl->touchBlock('strong_end');
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
    public function isDark(): bool
    {
        return $this->dark;
    }


    /**
     * @param bool $dark
     */
    public function setDark(bool $dark)
    {
        $this->dark = $dark;
    }

}

