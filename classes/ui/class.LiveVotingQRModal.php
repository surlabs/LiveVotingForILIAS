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

namespace LiveVoting\UI;

use ILIAS\UI\Component\Modal\RoundTrip;
use ILIAS\UI\Component\Signal;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLiveVotingPlugin;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;

/**
 * Class LiveVotingQRModal
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingQRModal
{
    private Factory $factory;
    private Renderer $renderer;
    private RoundTrip $modal;

    public function __construct(LiveVoting $liveVoting)
    {
        global $DIC;

        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();

        $param_manager = ParamManager::getInstance();

        $link = '<span class="label label-default xlvo-label-url resize">' . $liveVoting->getShortLink($param_manager->getRefId()) . '</span>'; //
        $modal_body = '<img id="xlvo-modal-qr" src="' . $liveVoting->getQRCode($param_manager->getRefId(), 720) . '">';


        $this->modal = $this->factory->modal()->roundtrip(
            vsprintf(ilLiveVotingPlugin::getInstance()->txt("player_pin"), [$liveVoting->getPin()]) . $link,
            $this->factory->legacy($modal_body)
        );
    }

    public function getHtml(): string
    {
        return $this->renderer->render($this->modal) . "<div style='display:none;' id='QRModal' data-signal='" . $this->getShowSignal() . "'></div>";
    }

    public function getShowSignal(): Signal
    {
        return $this->modal->getShowSignal();
    }
}
