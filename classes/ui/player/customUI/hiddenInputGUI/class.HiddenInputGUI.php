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

namespace LiveVoting\UI\Player\CustomUI\HiddenInputGUI;


use ilHiddenInputGUI;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use LiveVoting\platform\LiveVotingException;


class HiddenInputGUI extends ilHiddenInputGUI
{

    public function __construct(string $a_postvar = "")
    {
        parent::__construct($a_postvar);
    }

    /**
     * @return string
     * @throws LiveVotingException|ilTemplateException
     */
    public function render(): string
    {
        try {
            $tpl = new ilTemplate("tpl.property_form.html", true, true, "components/ILIAS/Form");
        } catch (ilSystemStyleException|ilTemplateException $e) {
            throw new LiveVotingException($e->getMessage());
        }
        $this->insert($tpl);

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
