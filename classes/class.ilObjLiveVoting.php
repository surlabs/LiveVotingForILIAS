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

use LiveVoting\platform\LiveVotingException;
use LiveVoting\votings\LiveVoting;

/**
 * Class ilObjLiveVoting
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class ilObjLiveVoting extends ilObjectPlugin
{
    private LiveVoting $liveVoting;

    /**
     * Create a new object
     * @param bool $clone_mode
     * @throws LiveVotingException
     */
    protected function doCreate(bool $clone_mode = false): void
    {
        $this->liveVoting = new LiveVoting();
        $this->liveVoting->setId($this->getId());
        $this->liveVoting->loadFromDB();

        $this->liveVoting->save();
    }

    /**
     * Read the object
     * @throws LiveVotingException
     */
    protected function doRead(): void
    {
        $this->liveVoting = new LiveVoting($this->getId());
    }

    /**
     * Delete the object
     * @throws LiveVotingException
     */
    protected function doDelete(): void
    {
        $this->liveVoting->delete();
    }

    /**
     * Update the object
     * @throws LiveVotingException
     */
    protected function doUpdate(): void
    {
        $this->liveVoting->save();
    }

    /**
     * @throws LiveVotingException
     */
    protected function doCloneObject(ilObject2 $new_obj, int $a_target_id, ?int $a_copy_id = null): void
    {
        $liveVoting = $this->getLiveVoting();

        /** @var LiveVoting $new_obj_lv */
        $new_obj_lv = $new_obj->getLiveVoting();

        $questions = array();

        foreach ($liveVoting->getQuestions() as $question) {
            $questions[] = $question->fullClone(false, true, $new_obj_lv->getId());
        }

        $new_obj_lv->setQuestions($questions);
        $new_obj_lv->setMode($liveVoting->getMode());
        $new_obj_lv->save();
    }

    protected function initType(): void
    {
        $this->setType("xlvo");
    }

    /**
     * Get the LiveVoting object
     * @return LiveVoting
     */
    public function getLiveVoting(): LiveVoting
    {
        return $this->liveVoting;
    }
}