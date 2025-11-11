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

namespace LiveVoting\UI\QuestionsResults;

use ilException;
use ilLiveVotingPlugin;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\Voting\Bar\LiveVotingBarFreeTextUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarGroupingCollectionUI;
use LiveVoting\votings\LiveVotingPlayer;
use LiveVoting\votings\LiveVotingVote;

/**
 * Class LiveVotingInputFreeTextUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInputFreeTextUI extends LiveVotingInputResultsGUI
{
    /**
     * @var bool
     */
    protected bool $edit_mode = false;

    /**
     * LiveVotingInputFreeTextUI constructor.
     *
     * @param LiveVotingPlayer $player
     */
    public function __construct(LiveVotingPlayer $player)
    {
        parent::__construct($player);
    }

    /**
     * @throws ilSystemStyleException
     * @throws ilTemplateException
     * @throws LiveVotingException
     * @throws ilException
     */
    public function getHTML(): string
    {
        global $DIC;
        $button_states = $this->player->getButtonStates();

        //dump($this->player->getButtonStates());exit;

        $this->edit_mode = (array_key_exists('btn_categorize', $button_states) && $button_states['btn_categorize'] == 'true');
        $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/FreeInput/tpl.free_input_results.html', true, true);

        $categories = new LiveVotingInputFreeTextCategoriesUI($this->player, $this->edit_mode);

        $bars = new LiveVotingBarGroupingCollectionUI();
        $bars->setRemovable($this->edit_mode);
        $bars->setShowTotalVotes(true);


        $votes = LiveVotingVote::getVotesOfOption($this->player->getActiveVotingObject()->getFirstOption()->getId(), $this->player->getRoundId());

        foreach ($votes as $vote) {
            if ($cat_id = $vote->getFreeInputCategory()) {
                try {
                    $categories->addBar(new LiveVotingBarFreeTextUI($vote), $cat_id);
                } catch (LiveVotingException $e) {
                    if ($e->getCode() == 3) {
                        $bars->addBar(new LiveVotingBarFreeTextUI($vote));
                    }
                }
            } else {
                $bars->addBar(new LiveVotingBarFreeTextUI($vote));
            }
        }

        $bars->setTotalVotes(count($votes));

        $tpl->setVariable('ANSWERS', $bars->getHTML());
        //dump($categories->getHTML());exit;
        $tpl->setVariable('CATEGORIES', $categories->getHTML());
        if ($this->edit_mode) {
            $tpl->setVariable('LABEL_ADD_CATEGORY', ilLiveVotingPlugin::getInstance()->txt('btn_add_category'));
            $tpl->setVariable('PLACEHOLDER_ADD_CATEGORY', ilLiveVotingPlugin::getInstance()->txt('category_title'));
            $tpl->setVariable('LABEL_ADD_ANSWER', ilLiveVotingPlugin::getInstance()->txt('btn_add_answer'));
            $tpl->setVariable('PLACEHOLDER_ADD_ANSWER', ilLiveVotingPlugin::getInstance()->txt('voter_answer'));
            $tpl->setVariable('BASE_URL', $DIC->ctrl()->getLinkTargetByClass(\ilObjLiveVotingGUI::class, 'apiCall', "", true));
        }

        return $tpl->get();

    }

    public static function addJsAndCss(): void
    {
        global $DIC;

        $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/js/libs/waiter.min.js');
        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/default/Waiter/waiter.css');

        $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/js/libs/dragula.min.js');
        $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/js/QuestionTypes/FreeInput/xlvoFreeInputCategorize.js');
        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/css/dragula.css');
        $DIC->ui()->mainTemplate()->addCss('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/default/QuestionTypes/FreeInput/free_input.css');
    }


    /**
     * @param LiveVotingVote[] $votes
     *
     * @return string
     */
    public function getTextRepresentationForVotes(array $votes): string
    {
        $string_votes = array();
        foreach ($votes as $vote) {
            $string_votes[] = str_replace(["\r\n", "\r", "\n"], " ", $vote->getFreeInput());
        }

        return implode(", ", $string_votes);
    }

}
