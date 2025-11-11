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

use ilButtonToSplitButtonMenuItemAdapter;
use ilCtrlException;
use ilException;
use ilGlyphGUI;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use iljQueryUtil;
use ilLinkButton;
use ilLiveVotingPlugin;
use ilObjLiveVotingGUI;
use ilSetting;
use ilSplitButtonGUI;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use JsonException;
use LiveVoting\objects\modes\LiveVotingMode;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\QuestionsResults\LiveVotingInputFreeTextUI;
use LiveVoting\Utils\LiveVotingJs;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVoter;
use stdClass;

/**
 * Class LiveVotingUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilObjLiveVotingGUI: ilObjPluginGUI
 * @ilCtrl_IsCalledBy  LiveVotingUI: ilUIPluginRouterGUI
 */
class LiveVotingUI
{
    /**
     * @var ilLiveVotingPlugin
     */
    private ilLiveVotingPlugin $pl;

    /**
     * @var LiveVoting
     */
    private LiveVoting $liveVoting;
    /**
     * @var Renderer
     */
    private Renderer $renderer;
    /**
     * @var Factory $factory
     */
    private Factory $factory;

    /**
     * LiveVotingUI constructor.
     */
    public function __construct(LiveVoting $liveVoting)
    {
        global $DIC;

        $this->pl = ilLiveVotingPlugin::getInstance();
        $this->liveVoting = $liveVoting;
        $this->renderer = $DIC->ui()->renderer();
        $this->factory = $DIC->ui()->factory();
    }

    public function executeCommand(): void
    {
        global $DIC;

        $cmd = $DIC->ctrl()->getCmd('showIndex');

        $this->{$cmd}();
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     * @throws LiveVotingException
     * @throws ilCtrlException
     * @throws JsonException
     */
    public function showIndex(): string
    {
        global $DIC;

        $is_online = $this->liveVoting->isOnline();
        $is_empty = empty($this->liveVoting->getQuestions());

        if (!$is_online && $is_empty) {
            return $this->renderer->render($this->factory->messageBox()->failure($this->pl->txt("player_msg_no_start_3")));
        } elseif (!$is_online) {
            return $this->renderer->render($this->factory->messageBox()->failure($this->pl->txt("player_msg_no_start_1")));
        } elseif ($is_empty) {
            return $this->renderer->render($this->factory->messageBox()->failure($this->pl->txt("player_msg_no_start_2")));
        }

        if (isset($this->liveVoting->getQuestions()[0])) {
            $this->liveVoting->getPlayer()->prepareStart($this->liveVoting->getQuestions()[0]->getId());
        } else {
            return $this->renderer->render($this->factory->messageBox()->failure($this->pl->txt("player_msg_no_start_2")));
        }

        if ($this->liveVoting->getMode()->getMode() != LiveVotingMode::CHALLENGE_MODE) {
            $b = ilLinkButton::getInstance();
            $b->setCaption($this->pl->txt('player_start_voting'), false);
            $b->addCSSClass('xlvo-preview');
            $b->setUrl($DIC->ctrl()->getLinkTargetByClass("ilObjLiveVotingGUI", "startPlayer"));
            $b->setId('btn-start-voting');
            $b->setPrimary(true);
            $DIC->toolbar()->addButtonInstance($b);


            $DIC->toolbar()->addText($this->getQuestionSelectionList());

            $b2 = ilLinkButton::getInstance();
            $b2->setCaption($this->pl->txt('player_start_voting_and_unfreeze'), false);
            $b2->addCSSClass('xlvo-preview');
            $b2->setUrl($DIC->ctrl()->getLinkTargetByClass("ilObjLiveVotingGUI", "startPlayerAnUnfreeze"));
            $b2->setId('btn-start-voting-unfreeze');
            $DIC->toolbar()->addButtonInstance($b2);
        } else {
            $b = ilLinkButton::getInstance();
            $b->setCaption($this->pl->txt('player_start_voting'), false);
            $b->addCSSClass('xlvo-preview');
            $b->setUrl($DIC->ctrl()->getLinkTargetByClass("ilObjLiveVotingGUI", "startPlayerAnUnfreeze"));
            $b->setId('btn-start-voting');
            $b->setPrimary(true);
            $DIC->toolbar()->addButtonInstance($b);
        }

        $template = new ilTemplate($this->pl->getDirectory() . "/templates/default/Player/tpl." . $this->liveVoting->getMode()->getStartTemplate() . ".html", true, true);
        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/default/default.css');


        $template->setVariable('PIN', $this->liveVoting->getPin());

        $param_manager = ParamManager::getInstance();

        if ($this->liveVoting->getMode()->getMode() != LiveVotingMode::CHALLENGE_MODE) {
            $template->setVariable('QR-CODE', $this->liveVoting->getQRCode($param_manager->getRefId(), 180));
        } else {
            $template->setVariable('QR-CODE', $this->liveVoting->getQRCode($param_manager->getRefId(), 280));
        }

        $template->setVariable('SHORTLINK', $this->liveVoting->getShortLink($param_manager->getRefId()));

        $modal = new LiveVotingQRModal($this->liveVoting);

        $template->setVariable('MODAL', $modal->getHTML());
        $template->setVariable("ZOOM_TEXT", $this->pl->txt("start_zoom"));
        $template->setVariable("MODAL_SIGNAL", $modal->getShowSignal());

        $js = LiveVotingJs::getInstance()->addSetting("base_url", $DIC->ctrl()->getLinkTargetByClass("ilObjLiveVotingGUI", "", "", true))->name('Player')->init();

        if ($this->liveVoting->isShowAttendees()) {
            $js->call('updateAttendees');
            $template->setVariable("ONLINE_TEXT", vsprintf($this->pl->txt("start_online"), [LiveVotingVoter::countVoters($this->liveVoting->getPlayer()->getId())]));
        }

        $js->call('handleStartButton');

        return '<div>' . $template->get() . '</div>';
    }

    /**
     * @param ilObjLiveVotingGUI $parent
     * @throws ilCtrlException
     */
    public function initJsAndCss(ilObjLiveVotingGUI $parent): void
    {
        global $DIC;
        $mathJaxSetting = new ilSetting("MathJax");
        $settings = array(
            'status_running' => 1,
            'identifier' => 'xvi',
            'use_mathjax' => (bool)$mathJaxSetting->get("enable"),
            'debug' => false,
            "isChallenge" => $this->liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE
        );

        LiveVotingJS::getInstance()->initMathJax();

        $keyboard = new stdClass();
        $keyboard->active = $this->liveVoting->getPlayer()->isKeyboardActive();
        if ($keyboard->active) {
            $keyboard->toggle_results = 9;
            $keyboard->toggle_freeze = 32;
            $keyboard->previous = 37;
            $keyboard->next = 39;
        }
        $settings['keyboard'] = $keyboard;

        $param_manager = ParamManager::getInstance();

        $settings['xlvo_ppt'] = $param_manager->isPpt();

        iljQueryUtil::initjQuery();


        LiveVotingJS::getInstance()->addLibToHeader('screenfull.js');
        LiveVotingJS::getInstance()->ilias($parent)->addSettings($settings)->name('Player')->addTranslations(array(
            'voting_confirm_reset',
        ))->init()->setRunCode();

        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/css/player.css');
        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/css/bar.css');

        LiveVotingInputFreeTextUI::addJsAndCss();
        /*xlvoCorrectOrderResultsGUI::addJsAndCss();
        xlvoFreeOrderResultsGUI::addJsAndCss();
        xlvoNumberRangeResultsGUI::addJsAndCss();
        xlvoSingleVoteResultsGUI::addJsAndCss();*/
    }

    /**
     * @throws LiveVotingException
     */
    public function showVoting(): void
    {
        global $DIC;
        $liveVoting = $this->liveVoting;
        $liveVoting->getPlayer()->getActiveVotingObject()->regenerateOptionSorting();
        $liveVoting->getPlayer()->setStatus(LiveVotingPlayer::STAT_RUNNING);
        $liveVoting->getPlayer()->freeze();

        $param_manager = ParamManager::getInstance();

        if ($voting_id = $param_manager->getVoting()) {
            $liveVoting->getPlayer()->setActiveVoting($voting_id);
            $liveVoting->getPlayer()->save();
        }

        try {
            $modal = new LiveVotingQRModal($this->liveVoting);

            $DIC->ui()->mainTemplate()->setContent($modal->getHtml() . $this->getPlayerHTML());

            $this->initToolbarDuringVoting();
        } catch (JsonException|ilCtrlException|LiveVotingException|ilTemplateException|ilException $e) {
            $DIC->ui()->mainTemplate()->setContent($DIC->ui()->renderer()->render($DIC->ui()->factory()->messageBox()->failure($e->getMessage())));
        }
    }

    /**
     * @throws ilCtrlException
     * @throws JsonException
     * @throws LiveVotingException
     */
    protected function initToolbarDuringVoting()
    {
        global $DIC;
        if ($this->liveVoting->getMode()->getMode() != LiveVotingMode::CHALLENGE_MODE) {
            // Freeze
            $suspendButton = ilLinkButton::getInstance();
            $suspendButton->addCSSClass('btn-warning');
            $suspendButton->setCaption(
                '<span class="glyphicon glyphicon-pause"></span> ' . $this->pl->txt('player_freeze'), false
            );
            $suspendButton->setUrl('#');
            $suspendButton->setId('btn-freeze');
            $DIC->toolbar()->addButtonInstance($suspendButton);

            // Unfreeze
            $playButton = ilLinkButton::getInstance();
            $playButton->setPrimary(true);
            $playButton->setCaption(
                '<span class="glyphicon glyphicon-play"></span> ' . $this->pl->txt('player_unfreeze'), false
            );
            $playButton->setUrl('#');
            $playButton->setId('btn-unfreeze');

            $split = ilSplitButtonGUI::getInstance();
            $split->setDefaultButton($playButton);
            foreach (array(10, 30, 90, 120, 180, 240, 300) as $seconds) {
                $cd = ilLinkButton::getInstance();
                $cd->setUrl('#');
                $cd->setCaption($seconds . ' ' . $this->pl->txt('player_seconds'), false);
                $cd->setOnClick("xlvoPlayer.countdown(event, $seconds);");
                $ilSplitButtonMenuItem = new ilButtonToSplitButtonMenuItemAdapter($cd);
                $split->addMenuItem($ilSplitButtonMenuItem);
            }

            $DIC->toolbar()->addStickyItem($split);
        }

        // Hide
        $suspendButton = ilLinkButton::getInstance();
        $suspendButton->setCaption($this->pl->txt('player_hide_results'), false);
        $suspendButton->setUrl('#');
        $suspendButton->setId('btn-hide-results');
        $DIC->toolbar()->addButtonInstance($suspendButton);

        // Show
        $suspendButton = ilLinkButton::getInstance();
        $suspendButton->setCaption($this->pl->txt('player_show_results'), false);
        $suspendButton->setUrl('#');
        $suspendButton->setId('btn-show-results');
        $DIC->toolbar()->addButtonInstance($suspendButton);

        // Reset
        if ($this->liveVoting->getMode()->getMode() != LiveVotingMode::CHALLENGE_MODE) {
            $suspendButton = ilLinkButton::getInstance();
            $suspendButton->setCaption('<span class="glyphicon glyphicon-remove"></span> ' . $this->pl->txt('player_reset'), false);
            $suspendButton->setUrl('#');
            $suspendButton->setId('btn-reset');
            $DIC->toolbar()->addButtonInstance($suspendButton);
        }

        if ($this->liveVoting->getMode()->getMode() != LiveVotingMode::CHALLENGE_MODE) {
            $DIC->toolbar()->addSeparator();

            $param_manager = ParamManager::getInstance();
            if (!$param_manager->isPpt()) {
                $prevBtn = ilLinkButton::getInstance();
                $prevBtn->setCaption(ilGlyphGUI::get(ilGlyphGUI::PREVIOUS), false);
                $prevBtn->setId('btn-previous');
                $prevBtn->setDisabled(true);
                $DIC->toolbar()->addButtonInstance($prevBtn);

                $nextBtn = ilLinkButton::getInstance();
                $nextBtn->setCaption(ilGlyphGUI::get(ilGlyphGUI::NEXT), false);
                $nextBtn->setId('btn-next');
                $nextBtn->setDisabled(true);
                $DIC->toolbar()->addButtonInstance($nextBtn);

                $current_selection_list = $this->getQuestionSelectionList();
                $DIC->toolbar()->addText($current_selection_list);
            }

            $DIC->toolbar()->addSeparator();
        }

        $suspendButton = ilLinkButton::getInstance();
        $suspendButton->setCaption('<span class="glyphicon glyphicon-fullscreen"></span>', false);
        $suspendButton->setUrl('#');
        $suspendButton->setId('btn-start-fullscreen');
        $DIC->toolbar()->addButtonInstance($suspendButton);

        $suspendButton = ilLinkButton::getInstance();
        $suspendButton->setCaption('<span class="glyphicon glyphicon-resize-small"></span>', false);
        $suspendButton->setUrl('#');
        $suspendButton->setId('btn-close-fullscreen');
        $DIC->toolbar()->addButtonInstance($suspendButton);

        if ($this->liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
            $endTime = ilLinkButton::getInstance();
            $endTime->setCaption($this->pl->txt("end_time"), false);
            $endTime->setId('btn-end_time');
            $DIC->toolbar()->addButtonInstance($endTime);

            $nextBtn = ilLinkButton::getInstance();
            $nextBtn->setCaption($this->pl->txt("next"), false);
            $nextBtn->setId('btn-next_cm');
            $DIC->toolbar()->addButtonInstance($nextBtn);
        } else {
            $suspendButton = ilLinkButton::getInstance();
            $suspendButton->setCaption(ilGlyphGUI::get(ilGlyphGUI::CLOSE) . $this->pl->txt('player_terminate'), false);
            $suspendButton->setUrl($DIC->ctrl()->getLinkTarget(new ilObjLiveVotingGUI(), 'terminate'));
            $suspendButton->setId('btn-terminate');
            $DIC->toolbar()->addButtonInstance($suspendButton);
        }
    }



    /**
     * @throws ilCtrlException
     */
    protected function getQuestionSelectionList(): string
    {
        global $DIC;

        $factory = $DIC->ui()->factory();

        $items = array();

        foreach ($this->liveVoting->getQuestions() as $question) {
            $DIC->ctrl()->setParameterByClass("ilObjLiveVotingGUI", "xlvo_voting", $question->getId());

            $items[] = $factory->button()->shy(
                $question->getTitle(),
                $DIC->ctrl()->getLinkTargetByClass("ilObjLiveVotingGUI", "startPlayer")
            );
        }

        $DIC->ctrl()->clearParameterByClass("ilObjLiveVotingGUI", "xlvo_voting");

        return $DIC->ui()->renderer()->render($factory->dropdown()->standard($items)->withLabel($this->pl->txt('player_voting_list')));
    }

    /**
     * @throws ilException
     * @throws ilTemplateException|LiveVotingException
     */
    public function getPlayerHTML(bool $inner = false): string
    {
        $liveVotingDisplayPlayerUI = new LiveVotingDisplayPlayerUI($this->liveVoting);
        return $liveVotingDisplayPlayerUI->getHTML($inner);
    }
}