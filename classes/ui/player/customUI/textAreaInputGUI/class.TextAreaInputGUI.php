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

namespace LiveVoting\UI\Player\CustomUI\TextAreaInputGUI;


use ilLiveVotingPlugin;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use ilTextAreaInputGUI;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\Utils\LiveVotingJs;


class TextAreaInputGUI extends ilTextAreaInputGUI
{

    /**
     * @var string
     */
    protected string $inline_style = '';
    /**
     * @var int
     */
    protected int $maxlength = 1000;


    /**
     *
     */
    public function customPrepare(): void
    {
        $this->addPlugin('latex');
        $this->addButton('latex');
        $this->addButton('pastelatex');
        LiveVotingJs::getInstance()->initLatexButton();
        $this->setUseRte(true);
        $this->setRteTags(array(
            'p',
            'br',
            'b',
            'span'
        ));
        $this->usePurifier(false);
        $this->disableButtons(array(
            'charmap',
            'undo',
            'redo',
            'justifyleft',
            'justifycenter',
            'justifyright',
            'justifyfull',
            'anchor',
            'fullscreen',
            'cut',
            'copy',
            'paste',
            'pastetext',
            'formatselect'
        ));
    }


    /**
     * @return string
     */
    public function getInlineStyle(): string
    {
        return $this->inline_style;
    }


    /**
     * @param string $inline_style
     */
    public function setInlineStyle(string $inline_style): void
    {
        $this->inline_style = $inline_style;
    }


    /**
     * @return int
     */
    public function getMaxlength(): int
    {
        return $this->maxlength;
    }


    /**
     * @param int $maxlength
     */
    public function setMaxlength(int $maxlength): void
    {
        $this->maxlength = $maxlength;
    }


    /**
     * @return string
     * @throws LiveVotingException|ilTemplateException
     */
    public function render(): string
    {
        try {
            $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/customUI/textAreaInput/tpl.text_area_helper.html', false, false);
        } catch (ilSystemStyleException|ilTemplateException $e) {
            throw new LiveVotingException($e->getMessage());
        }
        $this->insert($tpl);
        $tpl->setVariable('INLINE_STYLE', $this->getInlineStyle());

        return $tpl->get();
    }

    public function getCanonicalName(): string
    {
        return "";
    }

    public function getTableFilterHTML(): string
    {
        return "";
    }
}
