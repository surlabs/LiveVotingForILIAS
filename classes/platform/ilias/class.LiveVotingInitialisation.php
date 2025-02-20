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

namespace LiveVoting\platform\ilias;

use Exception;
use ilGlobalTemplate;
use ILIAS\DI\Container;
use ilInitialisation;
use iljQueryUtil;
use ilLiveVotingPlugin;
use ilLoggerFactory;
use ilObjectDefinition;
use ilTemplateException;
use ilTree;
use ilUIFramework;
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\player\LiveVotingContextUI;

/**
 * Class LiveVotingConfig
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingInitialisation extends ilInitialisation
{
    /**
     * @var ilTree
     */
    protected static ilTree $tree;

    /**
     * @var int
     */
    protected static int $context = 1;

    /**
     * LiveVotingInitialisation constructor.
     *
     * @param int|null $context
     * @throws Exception
     */
    protected function __construct(int $context = null)
    {
        if ($context) {
            self::saveContext($context);
        } else {
            $context = (int) LiveVotingContext::getContext();

            if ($context > 0 && $context < 3) {
                self::saveContext($context);
            } else {
                throw new LiveVotingException("Invalid context");
            }
        }

        $this->run();
    }

    /**
     * @throws Exception
     */
    protected function run(): void
    {
        //		$this->setContext(self::CONTEXT_ILIAS);
        switch (self::getContext()) {
            case 2:
                require_once 'include/inc.header.php';
                self::initHTML2();
                break;
            case 1:
                LiveVotingContext::init(LiveVotingContextUI::class);
                self::initILIAS2();
                break;
        }
    }

    /**
     *
     * @throws LiveVotingException
     */
    public static function initILIAS2(): void
    {
        global $DIC;
        require_once 'include/inc.ilias_version.php';
        self::initDependencyInjection();
        self::initCore();
        self::initClient();
        self::initUser();
        self::initLanguage();
        self::$tree->initLangCode();
        self::initHTML2();
        $GLOBALS["objDefinition"] = $DIC["objDefinition"] = new ilObjectDefinition();
    }

    /**
     *
     */
    public static function initDependencyInjection(): void
    {
        global $DIC;
        $DIC = new Container();
        $DIC["ilLoggerFactory"] = function ($c) {
            return ilLoggerFactory::getInstance();
        };
    }

    /**
     * @param int|null $context
     *
     * @return LiveVotingInitialisation
     * @throws Exception
     */
    public static function init(int $context = null): LiveVotingInitialisation
    {
        return new self($context);
    }


    /**
     * @param int $context
     *
     * @throws Exception
     */
    public static function saveContext(int $context): void
    {
        self::setContext($context);

        LiveVotingContext::setContext($context);
    }

    /**
     *
     * @throws LiveVotingException
     * @throws ilTemplateException
     */
    protected static function initHTML2(): void
    {
        global $DIC;
        if ($DIC->offsetExists("tpl")) {
            $DIC->offsetUnset("tpl");
        }
        if ($DIC->offsetExists("ilNavigationHistory")) {
            $DIC->offsetUnset("ilNavigationHistory");
        }
        if ($DIC->offsetExists("ilHelp")) {
            $DIC->offsetUnset("ilHelp");
        }
        if ($DIC->offsetExists("styleDefinition")) {
            $DIC->offsetUnset("styleDefinition");
        }

        if (self::getContext() != 2) {
            self::initHTML();
        }

        $tpl = new ilGlobalTemplate("tpl.main.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting", "DEFAULT", true);

        $tpl->touchBlock("navbar");
        $tpl->addCss('./templates/default/delos.css');
        $tpl->addCss(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/css/old_delos.css');
        $tpl->addCss(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/default.css');
        //$tpl->addCss('/templates/default/030-tools/legacy-bootstrap-mixins/_nav-divider.scss');

        $tpl->addBlockFile("CONTENT", "content", "tpl.main_voter.html", "Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting");

        $tpl->setVariable("BASE", LiveVotingConfig::getBaseVoteUrl());

        self::initGlobal("tpl", $tpl);

        iljQueryUtil::initjQuery();
        ilUIFramework::init();
    }

    public static function initUIFramework(Container $c): void
    {
        parent::initUIFramework($c);
    }

    /**
     * @return int
     */
    public static function getContext(): int
    {
        return self::$context;
    }

    /**
     * @param int $context
     */
    public static function setContext(int $context): void
    {
        self::$context = $context;
    }
}