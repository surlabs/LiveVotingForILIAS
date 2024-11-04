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

use ilCtrlException;
use ilCtrlInterface;
use ilException;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLiveVotingPlugin;
use ilObjLiveVotingGUI;
use ilPlugin;
use LiveVoting\legacy\liveVotingTableGUI;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\objects\modes\LiveVotingMode;

/**
 * Class LiveVotingManageUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilObjLiveVotingGUI: ilObjPluginGUI
 */
class LiveVotingManageUI
{
    /**
     * @var ilCtrlInterface
     */
    protected ilCtrlInterface $control;
    /**
     * @var Factory
     */
    protected Factory $factory;

    /**
     * @var ilPlugin
     */
    protected ilPlugin $plugin;
    protected renderer $renderer;
    protected $request;

    public function __construct()
    {
        global $DIC;

        $this->plugin = ilLiveVotingPlugin::getInstance();
        $this->control = $DIC->ctrl();
        $this->request = $DIC->http()->request();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
    }

    /**
     * @throws ilCtrlException
     * @throws ilException
     * @throws LiveVotingException
     */
    public function showManage(ilObjLiveVotingGUI $parent): string
    {
        global $DIC;

        $f = $DIC->ui()->factory();
        $renderer = $DIC->ui()->renderer();
        $ico = $f->symbol()->icon()->standard('', '')->withSize('medium')->withAbbreviation('+');
        $image = $f->image()->responsive("src/UI/examples/Image/mountains.jpg", "Image source: https://stocksnap.io, Creative Commons CC0 license");
        $page = $f->modal()->lightboxImagePage($image, 'Mountains');
        $modal = $f->modal()->lightbox($page);

        $glyph = $f->symbol()->glyph()->add("#");
        $glyph_reset = $f->symbol()->glyph()->reset("#");

        $button = $f->button()->bulky($glyph, '<div style="margin-left:10px">' . $this->plugin->txt("voting_type_1") . ' <br/><small><muted>' . $this->plugin->txt("voting_type_1_info") . '</muted></small></div>', $this->control->getLinkTargetByClass(ilObjLiveVotingGUI::class, 'selectedChoices'));
        $button2 = $f->button()->bulky($glyph, '<div style="margin-left:10px">' . $this->plugin->txt("voting_type_2") . ' <br/><small><muted>' . $this->plugin->txt("voting_type_2_info") . '</muted></small></div>', $this->control->getLinkTargetByClass(ilObjLiveVotingGUI::class, 'selectedFreeInput'));
        $button3 = $f->button()->bulky($glyph, '<div style="margin-left:10px">' . $this->plugin->txt("voting_type_4") . ' <br/><small><muted>' . $this->plugin->txt("voting_type_4_info") . '</muted></small></div>', $this->control->getLinkTargetByClass(ilObjLiveVotingGUI::class, 'selectedCorrectOrder'));
        $button4 = $f->button()->bulky($glyph, '<div style="margin-left:10px">' . $this->plugin->txt("voting_type_5") . ' <br/><small><muted>' . $this->plugin->txt("voting_type_5_info") . '</muted></small></div>', $this->control->getLinkTargetByClass(ilObjLiveVotingGUI::class, 'selectedPriorities'));
        $button5 = $f->button()->bulky($glyph, '<div style="margin-left:10px">' . $this->plugin->txt("voting_type_6") . ' <br/><small><muted>' . $this->plugin->txt("voting_type_6_info") . '</muted></small></div>', $this->control->getLinkTargetByClass(ilObjLiveVotingGUI::class, 'selectedRange'));

        $buttons = [$button];

        if ($parent->getObject()->getLiveVoting()->getMode()->getMode() == LiveVotingMode::BASIC_MODE) {
            $buttons[] = $button2;
            $buttons[] = $button3;
            $buttons[] = $button4;
            $buttons[] = $button5;
        }

        $items = [
            $f->menu()->sub('<div><span class="glyphicon glyphicon-plus-sign" aria-hidden="true"></span> ' . $this->plugin->txt('voting_add') . '</div>', $buttons),

            $f->button()->bulky($glyph_reset, '&nbsp;' . $this->plugin->txt('voting_reset_all'), $this->control->getLinkTargetByClass(ilObjLiveVotingGUI::class, 'confirmResetAll')),
        ];

        $dd = $f->menu()->drilldown(ilLiveVotingPlugin::getInstance()->txt('voting_actions'), $items);

        $liveVotingTableGUI = new LiveVotingTableGUI($parent, 'manage');

        return $renderer->render($dd) . $liveVotingTableGUI->getHTML();
    }
}