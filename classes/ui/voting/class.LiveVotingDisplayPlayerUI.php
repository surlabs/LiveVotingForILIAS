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

use ilException;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLiveVotingPlugin;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use LiveVoting\objects\modes\LiveVotingMode;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\questions\LiveVotingQuestion;
use LiveVoting\questions\LiveVotingQuestionOption;
use LiveVoting\UI\QuestionsResults\LiveVotingInputResultsGUI;
use LiveVoting\Utils\ParamManager;
use LiveVoting\votings\LiveVoting;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;
use LiveVoting\votings\LiveVotingVoter;

/**
 * Class LiveVotingDisplayPlayerUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy  ilObjLiveVotingGUI: ilObjPluginGUI
 * @ilCtrl_IsCalledBy  LiveVotingUI: ilUIPluginRouterGUI
 */
class LiveVotingDisplayPlayerUI
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
     * @var ilTemplate
     */
    protected ilTemplate $tpl;
    /**
     * @var int
     */
    protected int $answer_count = 64;
    /**
     * @var ParamManager
     */
    protected ParamManager $manager;


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

        try {
            if ($this->liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
                $this->buildTpl();
            } else {
                $this->tpl = new ilTemplate($this->pl->getDirectory() . "/templates/default/Player/tpl.player.html", true, true);
            }

            $DIC->ui()->mainTemplate()->addCss($this->pl->getDirectory() . '/templates/default/default.css');
        } catch (ilSystemStyleException|ilTemplateException $e) {
            $DIC->ui()->mainTemplate()->setContent($this->renderer->render($this->factory->messageBox()->failure($e->getMessage())));
        }

    }

    /**
     * @param bool $inner
     * @return string
     * @throws ilTemplateException
     * @throws ilException
     * @throws LiveVotingException
     */
    public function getHTML(bool $inner = false): string
    {
        if ($this->liveVoting->getMode()->getMode() == LiveVotingMode::CHALLENGE_MODE) {
            $this->renderTpl();
        } else {
            $this->render();
        }

        $open = '<div id="xlvo-display-player" class="display-player panel panel-primary">';
        $close = '</div>';

        if ($inner) {
            return $this->tpl->get();
        } else {
            return $open . $this->tpl->get() . $close;
        }
    }

    /**
     * @throws ilException
     * @throws LiveVotingException
     */
    protected function render()
    {

        $player = $this->liveVoting->getPlayer();
        $question = $player->getActiveVotingObject();


        $xlvoInputResultGUI = LiveVotingInputResultsGUI::getInstance($player);

        if ($player->isShowResults()) {
            //add result view to player
            $this->tpl->setVariable('OPTION_CONTENT', $xlvoInputResultGUI->getHTML());
        } else {
            //add options to player
            $xlvoOptions = LiveVotingQuestionOption::loadAllOptionsByVotingId($question->getId());

            foreach ($xlvoOptions as $item) {
                $this->addOption($item);
            }
        }

        $this->tpl->setVariable('TITLE', $question->getTitle());
        $this->tpl->setVariable('QUESTION', $question->getQuestionForPresentation());
        $this->tpl->setVariable('VOTING_ID', $this->liveVoting->getId());
        $this->tpl->setVariable('OBJ_ID', $this->liveVoting->getId());
        $this->tpl->setVariable('FROZEN', $player->isFrozen());
        $this->tpl->setVariable('PIN', $this->liveVoting->getPin());
        if ($this->liveVoting->isShowAttendees()) {
            $this->tpl->setCurrentBlock('attendees');
            $this->tpl->setVariable('ONLINE_TEXT', vsprintf(ilLiveVotingPlugin::getInstance()->txt("start_online"), [LiveVotingVoter::countVoters($player->getId())]));
            $this->tpl->parseCurrentBlock();
        }
        if ($player->isCountDownRunning()) {
            $this->tpl->setCurrentBlock('countdown');
            $cd = $player->remainingCountDown();
            $this->tpl->setVariable('COUNTDOWN', $cd . ' ' . $this->pl->txt('player_seconds'));
            $this->tpl->setVariable('COUNTDOWN_CSS', $player->getCountdownClassname());
            $this->tpl->parseCurrentBlock();
        }

        $this->tpl->setVariable('VOTERS_TEXT', vsprintf(ilLiveVotingPlugin::getInstance()->txt("player_voters_description"), [LiveVotingVote::countVoters($player->getActiveVoting(), $player->getRoundId())]));

        $this->tpl->setVariable('COUNT', $this->liveVoting->countQuestions());
        $this->tpl->setVariable('POSITION', $this->liveVoting->getQuestionPosition() + 1);
    }

    /**
     * @throws ilTemplateException
     * @throws LiveVotingException
     */
    protected function addOption(LiveVotingQuestionOption $option)
    {
        if ($option->getType() == LiveVotingQuestion::QUESTION_TYPES_IDS["FreeText"]) {
            return;
        }


        $player = $this->liveVoting->getPlayer();
        $question = $player->getActiveVotingObject();

        if ($option->getType() == LiveVotingQuestion::QUESTION_TYPES_IDS["NumberRange"]) {
            $columnWith = 6; //because of bootstrap grid 12 = 100%, 6 = 50% therefore 2 columns
            $percentage = (int)$question->isPercentage() === 1 ? ' %' : '';

            $this->tpl->setCurrentBlock('option2');
            $this->tpl->setVariable('OPTION_LETTER', ilLiveVotingPlugin::getInstance()->txt('qtype_6_range_start'));
            $this->tpl->setVariable('OPTION_COL', $columnWith);
            $this->tpl->setVariable('OPTION_TEXT', "{$question->getStartRange()}{$percentage}");
            $this->tpl->parseCurrentBlock();

            $this->tpl->setCurrentBlock('option2');
            $this->tpl->setVariable('OPTION_LETTER', ilLiveVotingPlugin::getInstance()->txt('qtype_6_range_end'));
            $this->tpl->setVariable('OPTION_COL', $columnWith);
            $this->tpl->setVariable('OPTION_TEXT', "{$question->getEndRange()}{$percentage}");
            $this->tpl->parseCurrentBlock();

            return;
        }

        $this->answer_count++;
        $this->tpl->setCurrentBlock('option');
        $this->tpl->setVariable('OPTION_LETTER', $option->getCipher());
        $this->tpl->setVariable('OPTION_COL', $question->getComputedColums());
        $this->tpl->setVariable('OPTION_TEXT', $option->getTextForPresentation());
        $this->tpl->parseCurrentBlock();
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     */
    private function buildTpl(): void
    {
        $this->tpl = match ($this->liveVoting->getPlayer()->getStatus()) {
            LiveVotingPlayer::STAT_END_VOTING, LiveVotingPlayer::STAT_SCOREBOARD => new ilTemplate($this->pl->getDirectory() . "/templates/default/Voter/tpl.scoreboard.html", true, false),
            default => new ilTemplate($this->pl->getDirectory() . "/templates/default/Player/tpl.player.html", true, true),
        };
    }

    /**
     * @throws LiveVotingException
     * @throws ilException
     */
    private function renderTpl(): void
    {
        switch ($this->liveVoting->getPlayer()->getStatus()) {
            case LiveVotingPlayer::STAT_SCOREBOARD:
            case LiveVotingPlayer::STAT_END_VOTING:
                $this->renderScoreboard();
                break;
            default:
                $this->render();
                break;
        }
    }

    /**
     * @throws LiveVotingException
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     * @throws ilException
     */
    private function renderScoreboard(): void
    {
        $players = LiveVotingPlayer::getPlayersForScoreboard($this->liveVoting->getPlayer());

        $html = '';

        foreach ($players as $player) {
            $tpl_scoreboard_points = new ilTemplate($this->pl->getDirectory() . '/templates/default/Voter/tpl.scoreboard_score.html', true    , false);
            $tpl_scoreboard_points->setVariable('PLAYER', $player['nickname']);
            $tpl_scoreboard_points->setVariable('POINTS', $player['points']);

            $html .= $tpl_scoreboard_points->get();
        }

        if ($this->liveVoting->getPlayer()->isShowResults() && $this->liveVoting->getPlayer()->getStatus() == LiveVotingPlayer::STAT_SCOREBOARD) {
            $xlvoInputResultGUI = LiveVotingInputResultsGUI::getInstance($this->liveVoting->getPlayer());
            $this->tpl->setVariable('OPTION_CONTENT', $xlvoInputResultGUI->getHTML());
        }

        $this->tpl->setVariable('POINTS', $html);
    }
}