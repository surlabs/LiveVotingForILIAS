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

use ilLiveVotingPlugin;
use ilSystemStyleException;
use ilTemplate;
use ilTemplateException;
use LiveVoting\platform\LiveVotingDatabase;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\UI\Voting\Bar\LiveVotingBarFreeTextUI;
use LiveVoting\UI\Voting\Bar\LiveVotingBarGroupingCollectionUI;
use LiveVoting\votings\LiveVotingCategory;
use LiveVoting\votings\LiveVotingPlayer;

/**
 * Class LiveVotingInputFreeTextCategoriesUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInputFreeTextCategoriesUI
{
    /**
     * @var bool $removable
     */
    private bool $removable = false;
    /**
     * @var array $categories
     */
    protected array $categories = [];

    /**
     * LiveVotingFreeTextCategoriesUI constructor.
     *
     * @param LiveVotingPlayer $player
     * @param bool $edit_mode
     * @throws LiveVotingException
     */
    public function __construct(LiveVotingPlayer $player, bool $edit_mode = false)
    {
        $this->setRemovable($edit_mode);

        $database = new LiveVotingDatabase();

        $categories = $database->select("rep_robj_xlvo_cat", array(
            "voting_id" => $player->getActiveVoting(),
            "round_id" => $player->getRoundId()
        ), array("id"));

        foreach ($categories as $category) {
            $bar_collection = new LiveVotingBarGroupingCollectionUI();
            $bar_collection->setRemovable($this->isRemovable());

            $category = new LiveVotingCategory((int)$category["id"]);

            $this->categories[$category->getId()] = [
                "title" => $category->getTitle(),
                "votes" => $bar_collection
            ];
        }
    }

    /**
     * @param LiveVotingBarFreeTextUI $bar_gui
     * @param integer $cat_id
     *
     * @throws LiveVotingException
     */
    public function addBar(LiveVotingBarFreeTextUI $bar_gui, int $cat_id)
    {
        $bar_gui->setRemovable($this->isRemovable());

        if (!($this->categories[$cat_id]['votes'] instanceof LiveVotingBarGroupingCollectionUI)) {
            throw new LiveVotingException('category not found', 3);
        }
        $this->categories[$cat_id]['votes']->addBar($bar_gui);
    }


    /**
     * @return string
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     */
    public function getHTML(): string
    {

        $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/FreeInput/tpl.free_input_categories.html', true, true);
        foreach ($this->categories as $cat_id => $data) {
            $cat_tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/QuestionTypes/FreeInput/tpl.free_input_category.html', true, true);
            /** @var LiveVotingCategory $category */
            $cat_tpl->setVariable('ID', $cat_id);
            $cat_tpl->setVariable('TITLE', $data['title']);
            if ($this->isRemovable()) {
                $cat_tpl->touchBlock('remove_button');
            }

            $cat_tpl->setVariable('VOTES', $data['votes']->getHTML());
            $tpl->setCurrentBlock('category');
            $tpl->setVariable('CATEGORY', $cat_tpl->get());
            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
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
    public function setRemovable(bool $removable): void
    {
        $this->removable = $removable;
    }
}
