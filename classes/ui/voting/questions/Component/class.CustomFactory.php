<?php

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

declare(strict_types=1);

namespace Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component;

use Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component\Input\Field\MultipleOptions;
use Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component\Input\Field\CorrectOrder;
use Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component\Input\Field\MultipleCheck;
use Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component\Input\Field\TextArea;

/**
 * Class CustomFactory
 */
class CustomFactory
{
    public function multipleOptions(string $label, ?string $byline = null): MultipleOptions
    {
        return new MultipleOptions($label, $byline);
    }
    public function correctOrder(string $label, ?string $byline = null): CorrectOrder
    {
        return new CorrectOrder($label, $byline);
    }
    public function multipleCheck(string $label, ?string $byline = null): MultipleCheck
    {
        return new MultipleCheck($label, $byline);
    }

    public function textArea(string $label, ?string $byline = null): TextArea
    {
        return new TextArea($label, $byline);
    }
}