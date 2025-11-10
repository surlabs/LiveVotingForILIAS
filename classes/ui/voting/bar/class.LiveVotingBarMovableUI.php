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
use LiveVoting\questions\LiveVotingQuestionOption;

class LiveVotingBarMovableUI implements LiveVotingGeneralBarUI
{
    /**
     * @var ilTemplate
     */
    protected ilTemplate $tpl;
    /**
     * @var LiveVotingQuestionOption[]
     */
    protected array $options = array();
    /**
     * @var array
     */
    protected array $order = array();
    /**
     * @var int|null
     */
    protected ?int $vote_id = null;
    /**
     * @var bool
     */
    protected bool $show_option_letter = false;


    /**
     * xlvoBarMovableGUI constructor.
     *
     * @param array $options
     * @param array $order
     * @param int|null $vote_id
     */
    public function __construct(array $options, array $order = array(), ?int $vote_id = null)
    {
        $this->options = $options;
        $this->order = $order;
        $this->vote_id = $vote_id;
        $this->tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/Bar/tpl.bar_movable.html', true, true);


    }


    /**
     * @return string
     * @throws ilTemplateException
     */
    public function getHTML(): string
    {
        $i = 1;
        $this->tpl->setVariable('VOTE_ID', $this->vote_id);
        if (count($this->order) > 0) {
            $this->tpl->setVariable('YOUR_ORDER', ilLiveVotingPlugin::getInstance()->txt('qtype_4_your_order'));
            foreach ($this->order as $key => $value) {
                $xlvoOption = LiveVotingQuestionOption::loadOptionById((int)$value);
                if (!$xlvoOption instanceof LiveVotingQuestionOption) {
                    continue;
                }
                $this->tpl->setCurrentBlock('option');
                $this->tpl->setVariable('ID', $xlvoOption->getId());
                if ($this->getShowOptionLetter()) {
                    $this->tpl->setVariable('OPTION_LETTER', $xlvoOption->getCipher());
                }
                $this->tpl->setVariable('OPTION', $xlvoOption->getTextForPresentation());
                $this->tpl->parseCurrentBlock();
                $i++;
            }
        } else {
            foreach ($this->options as $xlvoOption) {
                $this->tpl->setCurrentBlock('option');
                $this->tpl->setVariable('ID', $xlvoOption->getId());
                if ($this->getShowOptionLetter()) {
                    $this->tpl->setVariable('OPTION_LETTER', $xlvoOption->getCipher());
                }
                $this->tpl->setVariable('OPTION', $xlvoOption->getTextForPresentation());
                $this->tpl->parseCurrentBlock();
                $i++;
            }
        }

        return $this->tpl->get();
    }


    /**
     * @return bool
     */
    public function getShowOptionLetter(): bool
    {
        return $this->show_option_letter;
    }


    /**
     * @param bool $show_option_letter
     */
    public function setShowOptionLetter(bool $show_option_letter)
    {
        $this->show_option_letter = $show_option_letter;
    }

}

