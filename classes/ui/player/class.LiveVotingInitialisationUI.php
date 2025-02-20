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

namespace LiveVoting\player;

use ilAccess;
use ilAppEventHandler;
use ilCtrlException;
use ilDatabaseException;
use ilDBWrapperFactory;
use ilErrorHandling;
use ilGlobalTemplate;
use ilGSProviderFactory;
use ilHelp;
use ilHelpUITextRetriever;
use ilHTTPS;
use ILIAS\DI\Container;
use ILIAS\DI\Exceptions\Exception;
use ILIAS\FileDelivery\Delivery\LegacyDelivery;
use ILIAS\FileDelivery\Delivery\ResponseBuilder\PHPResponseBuilder;
use ILIAS\FileDelivery\Delivery\ResponseBuilder\ResponseBuilder;
use ILIAS\FileDelivery\Delivery\ResponseBuilder\XSendFileResponseBuilder;
use ILIAS\FileDelivery\Init;
use ILIAS\FileDelivery\Setup\DeliveryMethodObjective;
use ILIAS\FileDelivery\Setup\KeyRotationObjective;
use ILIAS\FileDelivery\Token\DataSigner;
use ILIAS\FileDelivery\Token\Signer\Key\Secret\SecretKey;
use ILIAS\FileDelivery\Token\Signer\Key\Secret\SecretKeyRotation;
use ILIAS\GlobalScreen\Services;
use ILIAS\HTTP\Cookies\CookieJarFactoryImpl;
use ILIAS\HTTP\Request\RequestFactoryImpl;
use ILIAS\HTTP\Response\ResponseFactoryImpl;
use ILIAS\HTTP\Response\Sender\DefaultResponseSenderStrategy;
use ILIAS\Refinery\Factory;
use ilIniFile;
use ilInitialisation;
use iljQueryUtil;
use ilLanguage;
use ilLiveVotingPlugin;
use ilLoggerFactory;
use ilMailMimeSenderFactory;
use ilMailMimeTransportFactory;
use ilNavigationHistory;
use ilObjectDataCache;
use ilObjectDefinition;
use ilRbacReview;
use ilRbacSystem;
use ilSetting;
use ilTabsGUI;
use ilTemplateException;
use ilTimeZone;
use ilToolbarGUI;
use ilTree;
use ilUIFramework;
use InitComponentService;
use InitCtrlService;
use InitResourceStorage;
use LiveVoting\platform\ilias\DummyUser;
use LiveVoting\platform\ilias\LiveVotingILIAS;
use LiveVoting\platform\ilias\LiveVotingStyleDefinition;
use LiveVoting\Utils\SessionHandler;
use LiveVoting\Utils\ParamManager;
use LiveVoting\platform\LiveVotingConfig;
use LiveVoting\platform\ilias\LiveVotingContext;
use LiveVoting\platform\LiveVotingException;
use LiveVoting\platform\ilias\LiveVotingInitialisation;

class LiveVotingInitialisationUI
{
    const PLUGIN_CLASS_NAME = ilLiveVotingPlugin::class;

    /**
     * @var ilIniFile
     */
    protected ilIniFile $iliasIniFile;

    /**
     * @var ilSetting
     */
    protected ilSetting $settings;

    /**
     * @throws \Exception
     */
    protected function __construct($context = null)
    {
        if ($context) {
            LiveVotingContext::setContext($context);
        }

        $this->bootstrapApp();
    }

    /**
     * @param int|null $context
     * @return LiveVotingInitialisationUI
     * @throws \Exception
     */
    public static function init(int $context = null): LiveVotingInitialisationUI
    {
        return new self($context);
    }

    /**
     * @throws ilCtrlException
     * @throws LiveVotingException
     * @throws Exception
     * @throws ilTemplateException
     * @throws \Exception
     */
    private function bootstrapApp(): void
    {
        global $DIC;
        //bootstrap ILIAS


        //file_put_contents( "/var/www/html/ilias/tmp" . "/" . "debug.txt", "v7/bootstrapApp" . "\n",FILE_APPEND);

        $this->initDependencyInjection();
        $this->setCookieParams();
        $this->removeUnsafeCharacters();
        $this->loadIniFile();
        $this->requireCommonIncludes();
        $this->initErrorHandling();
        $this->determineClient();
        $this->initHTTPServices();
        $this->loadClientIniFile();
        $this->initDatabase();
        $this->initLog(); //<-- required for ilCtrl error messages
        $this->initSessionHandler();
        $this->initSettings();  //required
        $this->initLocale();
        $this->buildHTTPPath();
        $this->initCore();
        $this->initUser();
        $this->initLanguage();
        $this->initTree();
        $this->initComponentService($GLOBALS["DIC"]);
        $this->initControllFlow();
        $this->initAccessHandling();
        $this->initObjectDefinition();
        $this->initAppEventHandler();
        $this->initAccess();
        $this->initMail();
        $this->initFilesystem();
        $this->initResourceStorage();
        $this->initFileDelivery();
        $this->initGlobalScreen($GLOBALS["DIC"]);
        $this->initRbacAdmin();
        $this->initTemplate();
        $this->initTabs();
        $this->initNavigationHistory();
        $this->initHelp();
        $this->initHelpTextRetriever();

        LiveVotingInitialisation::initUIFramework($DIC);
    }

    /**
     * Remove unsafe characters from GET
     */
    protected function removeUnsafeCharacters(): void
    {
        // Remove unsafe characters from GET parameters.
        // We do not need this characters in any case, so it is
        // feasible to filter them everytime. POST parameters
        // need attention through ilUtil::stripSlashes() and similar functions)
        if (is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                // \r\n used for IMAP MX Injection
                // ' used for SQL Injection
                $_GET[$k] = str_replace(array(
                    "\x00",
                    "\n",
                    "\r",
                    "\\",
                    "'",
                    '"',
                    "\x1a",
                ), "", $v);

                // this one is for XSS of any kind
                $_GET[$k] = strip_tags($_GET[$k]);
            }
        }
    }

    /**
     * Initialize a fake rbacadmin service to satisfy the help system module
     */
    private function initRbacAdmin(): void
    {
        $this->makeGlobal('rbacadmin', new \ilRbacAdmin());
    }

    /**
     * @throws ilTemplateException
     * @throws LiveVotingException
     */
    private function initTemplate(): void
    {
        $styleDefinition = new LiveVotingStyleDefinition();
        $this->makeGlobal('styleDefinition', $styleDefinition);

        $ilias = new LiveVotingILIAS();
        $this->makeGlobal("ilias", $ilias);

        $tpl = new ilGlobalTemplate("tpl.main.html", true, true, "Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting", "DEFAULT", true);

        $param_manager = ParamManager::getInstance();

        if (!$param_manager->getPuk()) {
            $tpl->touchBlock("navbar");
        }

        $tpl->addCss('./templates/default/delos.css');
        $tpl->addCss(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/css/old_delos.css');
        $tpl->addCss(ilLiveVotingPlugin::getInstance()->getDirectory() . '/templates/default/default.css');
        //$tpl->addCss('/templates/default/030-tools/legacy-bootstrap-mixins/_nav-divider.scss');

        $tpl->addBlockFile("CONTENT", "content", "tpl.main_voter.html", "Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting");

        $tpl->setVariable("BASE", LiveVotingConfig::getBaseVoteUrl());

        $this->makeGlobal("tpl", $tpl);

        iljQueryUtil::initjQuery();
        ilUIFramework::init();

        $ilToolbar = new ilToolbarGUI();

        $this->makeGlobal("ilToolbar", $ilToolbar);
    }

    /**
     * initialise database object $ilDB
     * @throws ilDatabaseException
     */
    private function initDatabase()
    {
        // build dsn of database connection and connect
        $ilDB = ilDBWrapperFactory::getWrapper(IL_DB_TYPE);
        $ilDB->initFromIniFile();
        $ilDB->connect();

        $this->makeGlobal("ilDB", $ilDB);
    }

    private function loadIniFile()
    {
        $this->iliasIniFile = new ilIniFile("./ilias.ini.php");
        $this->iliasIniFile->read();

        $this->makeGlobal('ilIliasIniFile', $this->iliasIniFile);

        //Initialize constants
        define("ILIAS_DATA_DIR", $this->iliasIniFile->readVariable("clients", "datadir"));
        define("ILIAS_WEB_DIR", $this->iliasIniFile->readVariable("clients", "path"));
        define("ILIAS_ABSOLUTE_PATH", $this->iliasIniFile->readVariable("server", "absolute_path"));

        //loggin
        define("ILIAS_LOG_DIR", $this->iliasIniFile->readVariable("log", "path"));
        define("ILIAS_LOG_FILE", $this->iliasIniFile->readVariable("log", "file"));
        define("ILIAS_LOG_ENABLED", $this->iliasIniFile->readVariable("log", "enabled"));
        define("ILIAS_LOG_LEVEL", $this->iliasIniFile->readVariable("log", "level"));
        define("SLOW_REQUEST_TIME", $this->iliasIniFile->readVariable("log", "slow_request_time"));

        // read path + command for third party tools from ilias.ini
        define("PATH_TO_CONVERT", $this->iliasIniFile->readVariable("tools", "convert"));
        define("PATH_TO_FFMPEG", $this->iliasIniFile->readVariable("tools", "ffmpeg"));
        define("PATH_TO_ZIP", $this->iliasIniFile->readVariable("tools", "zip"));
        define("PATH_TO_MKISOFS", $this->iliasIniFile->readVariable("tools", "mkisofs"));
        define("PATH_TO_UNZIP", $this->iliasIniFile->readVariable("tools", "unzip"));
        define("PATH_TO_GHOSTSCRIPT", $this->iliasIniFile->readVariable("tools", "ghostscript"));
        define("PATH_TO_JAVA", $this->iliasIniFile->readVariable("tools", "java"));
        define("PATH_TO_HTMLDOC", $this->iliasIniFile->readVariable("tools", "htmldoc"));
        define("URL_TO_LATEX", $this->iliasIniFile->readVariable("tools", "latex"));
        define("PATH_TO_FOP", $this->iliasIniFile->readVariable("tools", "fop"));

        // read virus scanner settings
        switch ($this->iliasIniFile->readVariable("tools", "vscantype")) {
            case "sophos":
                define("IL_VIRUS_SCANNER", "Sophos");
                define("IL_VIRUS_SCAN_COMMAND", $this->iliasIniFile->readVariable("tools", "scancommand"));
                define("IL_VIRUS_CLEAN_COMMAND", $this->iliasIniFile->readVariable("tools", "cleancommand"));
                break;

            case "antivir":
                define("IL_VIRUS_SCANNER", "AntiVir");
                define("IL_VIRUS_SCAN_COMMAND", $this->iliasIniFile->readVariable("tools", "scancommand"));
                define("IL_VIRUS_CLEAN_COMMAND", $this->iliasIniFile->readVariable("tools", "cleancommand"));
                break;

            case "clamav":
                define("IL_VIRUS_SCANNER", "ClamAV");
                define("IL_VIRUS_SCAN_COMMAND", $this->iliasIniFile->readVariable("tools", "scancommand"));
                define("IL_VIRUS_CLEAN_COMMAND", $this->iliasIniFile->readVariable("tools", "cleancommand"));
                break;

            default:
                define("IL_VIRUS_SCANNER", "None");
                break;
        }

        $tz = ilTimeZone::initDefaultTimeZone($this->iliasIniFile);
        define("IL_TIMEZONE", $tz);
        define("IL_INITIAL_WD", getcwd());

    }

    private function loadClientIniFile(): void
    {
        $ini_file = "./" . ILIAS_WEB_DIR . "/" . CLIENT_ID . "/client.ini.php";

        // get settings from ini file
        $ilClientIniFile = new ilIniFile($ini_file);
        $ilClientIniFile->read();

        // invalid client id / client ini
        if ($ilClientIniFile->ERROR != "") {
            $default_client = $this->iliasIniFile->readVariable("clients", "default");
            setcookie("ilClientId", $default_client, 0, "/");
        }

        $this->makeGlobal("ilClientIniFile", $ilClientIniFile);

        // set constants
        define("SESSION_REMINDER_LEADTIME", 30);
        define("DEBUG", $ilClientIniFile->readVariable("system", "DEBUG"));
        define("DEVMODE", $ilClientIniFile->readVariable("system", "DEVMODE"));
        define("ERROR_HANDLER", $ilClientIniFile->readVariable('system', 'ERROR_HANDLER') ?: "PRETTY_PAGE");
        define("SHOWNOTICES", $ilClientIniFile->readVariable("system", "SHOWNOTICES"));
        define("DEBUGTOOLS", $ilClientIniFile->readVariable("system", "DEBUGTOOLS"));
        define("ROOT_FOLDER_ID", $ilClientIniFile->readVariable('system', 'ROOT_FOLDER_ID'));
        define("SYSTEM_FOLDER_ID", $ilClientIniFile->readVariable('system', 'SYSTEM_FOLDER_ID'));
        define("ROLE_FOLDER_ID", $ilClientIniFile->readVariable('system', 'ROLE_FOLDER_ID'));
        define("MAIL_SETTINGS_ID", $ilClientIniFile->readVariable('system', 'MAIL_SETTINGS_ID'));
        $log_error_trace = $ilClientIniFile->readVariable('system', 'LOG_ERROR_TRACE');
        define("LOG_ERROR_TRACE", $log_error_trace ? $log_error_trace : false);

        define("OH_REF_ID", $ilClientIniFile->readVariable("system", "OH_REF_ID"));

        define("SYSTEM_MAIL_ADDRESS", $ilClientIniFile->readVariable('system', 'MAIL_SENT_ADDRESS'));
        define("MAIL_REPLY_WARNING", $ilClientIniFile->readVariable('system', 'MAIL_REPLY_WARNING'));

        define("CLIENT_DATA_DIR", ILIAS_DATA_DIR . "/" . CLIENT_ID);
        define("CLIENT_WEB_DIR", ILIAS_ABSOLUTE_PATH . "/" . ILIAS_WEB_DIR . "/" . CLIENT_ID);
        define("CLIENT_NAME", $ilClientIniFile->readVariable('client', 'name'));

        $val = $ilClientIniFile->readVariable("db", "type");
        if ($val == "") {
            define("IL_DB_TYPE", "mysql");
        } else {
            define("IL_DB_TYPE", $val);
        }

        //TODO: Comentado para ilias 9
        /*$ilGlobalCacheSettings = new ilGlobalCacheSettings();
        $ilGlobalCacheSettings->readFromIniFile($ilClientIniFile);
        ilGlobalCache::setup($ilGlobalCacheSettings);*/

    }

    private function initSessionHandler()
    {
        $session = new SessionHandler();

        session_set_save_handler(array(
            &$session,
            "open",
        ), array(
            &$session,
            "close",
        ), array(
            &$session,
            "read",
        ), array(
            &$session,
            "write",
        ), array(
            &$session,
            "destroy",
        ), array(
            &$session,
            "gc",
        ));

        session_start();
    }

    /*
        * @return void
        */
    private function initDependencyInjection(): void
    {
        global $DIC;
        $DIC = new Container();
        $DIC["ilLoggerFactory"] = function ($c) {
            return ilLoggerFactory::getInstance();
        };
    }

    private function initSettings(): void
    {
        $this->settings = new ilSetting();
        $this->makeGlobal("ilSetting", $this->settings);

        // set anonymous user & role id and system role id
        define("ANONYMOUS_USER_ID", $this->settings->get("anonymous_user_id"));
        define("ANONYMOUS_ROLE_ID", $this->settings->get("anonymous_role_id"));
        define("SYSTEM_USER_ID", $this->settings->get("system_user_id"));
        define("SYSTEM_ROLE_ID", $this->settings->get("system_role_id"));
        define("USER_FOLDER_ID", 7);

        // recovery folder
        define("RECOVERY_FOLDER_ID", $this->settings->get("recovery_folder_id"));

        // installation id
        define("IL_INST_ID", $this->settings->get("inst_id", "0"));

        // define default suffix replacements
        define("SUFFIX_REPL_DEFAULT", "php,php3,php4,inc,lang,phtml,htaccess");
        define("SUFFIX_REPL_ADDITIONAL", $this->settings->get("suffix_repl_additional"));

        // payment setting
        define('IS_PAYMENT_ENABLED', false);
    }

    private function requireCommonIncludes(): void
    {
        require_once 'include/inc.ilias_version.php';

        //$this->makeGlobal("ilBench", new ilBenchmark());
    }

    private function initLocale(): void
    {
        if (trim((string)$this->settings->get("locale"))) {
            $larr = explode(",", trim($this->settings->get("locale")));
            $ls = array();
            $first = $larr[0];
            foreach ($larr as $l) {
                if (trim($l) != "") {
                    $ls[] = $l;
                }
            }
            if (count($ls) > 0) {
                setlocale(LC_ALL, $ls);

                // #15347 - making sure that floats are not changed
                setlocale(LC_NUMERIC, "C");

                if (class_exists("Collator")) {
                    //$this->makeGlobal("ilCollator", new Collator($first));
                }
            }
        }
    }

    private function initLanguage(): void
    {
        $this->makeGlobal('lng', ilLanguage::getGlobalInstance());
    }

    /**
     * Build the http path for ILIAS
     * @return void
     */
    private function buildHTTPPath(): void
    {
        $https = new ilHTTPS();
        //$this->makeGlobal("https", $https);

        if ($https->isDetected()) {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        $host = $_SERVER['HTTP_HOST'];

        $rq_uri = $_SERVER['REQUEST_URI'];

        if (is_int($pos = strpos($rq_uri, "?"))) {
            $rq_uri = substr($rq_uri, 0, $pos);
        }

        if (!defined('ILIAS_MODULE')) {
            $path = pathinfo($rq_uri);
            if (!isset($path['extension']) || !$path['extension']) {
                $uri = $rq_uri;
            } else {
                $uri = dirname($rq_uri);
            }
        } else {
            $path = dirname($rq_uri);

            $module = \ilFileUtils::removeTrailingPathSeparators(ILIAS_MODULE);

            $dirs = explode('/', $module);
            $uri = $path;
            foreach ($dirs as $dir) {
                $uri = dirname($uri);
            }
        }

        $https->enableSecureCookies();

        define('ILIAS_HTTP_PATH', self::removeTrailingPathSeparators($protocol . $host . $uri));
    }

    /**
     * @param string $path
     * @return string
     */
    public static function removeTrailingPathSeparators(string $path): string
    {
        $path = preg_replace("/[\/\\\]+$/", "", $path);
        return (string)$path;
    }

    /**
     * @return void
     */
    private function initErrorHandling(): void
    {
        $ilErr = new ilErrorHandling();
        $this->makeGlobal("ilErr", $ilErr);
    }

    /**
     * Init ilias data cache.
     */
    private function initDataCache()
    {
        $this->makeGlobal("ilObjDataCache", new ilObjectDataCache());
    }

    /**
     * Init ilias object definition.
     */
    private function initObjectDefinition()
    {
        $this->makeGlobal("objDefinition", new ilObjectDefinition());
    }

    /**
     * @throws ilCtrlException
     */
    private function initControllFlow()
    {
        global $DIC;

        $DIC['refinery'] = function ($container) {
            $dataFactory = new \ILIAS\Data\Factory();
            $language = $container['lng'];

            return new Factory($dataFactory, $language);
        };

        (new InitCtrlService())->init($DIC);

    }

    /**
     * Init log instance
     * @return void
     */
    private function initLog(): void
    {
        $log = ilLoggerFactory::getRootLogger();

        $this->makeGlobal("ilLog", $log);
        // deprecated
        $this->makeGlobal("log", $log);
    }

    /**
     * set session cookie params for path, domain, etc.
     * @return void
     */
    private function setCookieParams(): void
    {
        $GLOBALS['COOKIE_PATH'] = '/';
        $cookie_path = '/';

        $cookie_path .= (!preg_match("/[\\/|\\\\]$/", $cookie_path)) ? "/" : "";

        if ($cookie_path == "\\") {
            $cookie_path = '/';
        }

        define('IL_COOKIE_EXPIRE', 0);
        define('IL_COOKIE_PATH', $cookie_path);
        define('IL_COOKIE_DOMAIN', '');
        define('IL_COOKIE_SECURE', true); // Default Value

        define('IL_COOKIE_HTTPONLY', true); // Default Value
        session_set_cookie_params(IL_COOKIE_EXPIRE, IL_COOKIE_PATH, IL_COOKIE_DOMAIN, IL_COOKIE_SECURE,
            IL_COOKIE_HTTPONLY);
    }

    /**
     * This method determines the current client and sets the
     * constant CLIENT_ID.
     * @return void
     * @throws Exception
     */
    private function determineClient(): void
    {
        // check whether ini file object exists
        if (!is_object($this->iliasIniFile)) {
            throw new Exception("Fatal Error: ilInitialisation::determineClient called without initialisation of ILIAS ini file object.");
        }

        // set to default client if empty
        if (isset($_GET["client_id"]) and $_GET["client_id"] != "") {
            $_GET["client_id"] = stripslashes($_GET["client_id"]);
            if (!defined("IL_PHPUNIT_TEST")) {
                setcookie("ilClientId", $_GET["client_id"], 0, "/");
            }
        } else {
            if (isset($_COOKIE["ilClientId"]) and !$_COOKIE["ilClientId"]) {
                // to do: ilias ini raus nehmen
                $client_id = $this->iliasIniFile->readVariable("clients", "default");
                setcookie("ilClientId", $client_id, 0, "/");
            }
        }
        if (!defined("IL_PHPUNIT_TEST") && isset($_COOKIE["ilClientId"])) {
            define("CLIENT_ID", $_COOKIE["ilClientId"]);
        } else {
            if (isset($_GET["client_id"])) {
                define("CLIENT_ID", $_GET["client_id"]);
            }
        }
        if (!defined('CLIENT_ID')) {
            $default_client = $this->iliasIniFile->readVariable("clients", "default");
            define("CLIENT_ID", $default_client);
        }

    }

    /**
     * Create or override a global variable.
     * @param string $name The name of the global variable.
     * @param object $value The value where the global variable should point at.
     * @return void
     */
    private function makeGlobal(string $name, object $value): void
    {
        global $DIC;
        $GLOBALS[$name] = $value;
        $DIC[$name] = function ($c) use ($name) {
            return $GLOBALS[$name];
        };
    }

    /**
     * Initialise a fake user service to satisfy the help system module.
     * @return void
     */
    private function initUser()
    {
        $this->makeGlobal('ilUser', new DummyUser());
    }

    /**
     * Starting from ILIAS basic initialisation also needs rbac stuff.
     * You may ask why? well: deep down ilias wants to initialize the footer. Event hough we don't
     * want the footer. This may not seem too bad... but the footer wants to translate something
     * and the translation somehow needs rbac. god...
     *
     * We can remove this when this gets fixed: Services/UICore/classes/class.ilTemplate.php:479
     */
    private function initAccessHandling()
    {
        $ilObjDataCache = new ilObjectDataCache();
        $this->makeGlobal('ilObjDataCache', $ilObjDataCache);

        $rbacreview = new ilRbacReview();
        $this->makeGlobal('rbacreview', $rbacreview);

        $rbacsystem = ilRbacSystem::getInstance();
        $this->makeGlobal("rbacsystem", $rbacsystem);
    }

    /**
     * Initialise a fake access service to satisfy the help system module.
     * @return void
     */
    private function initAccess(): void
    {
        $this->makeGlobal('ilAccess', new ilAccess());
    }

    /**
     * Initialise a fake three service to satisfy the help system module.
     * @return void
     */
    private function initTree(): void
    {
        $this->makeGlobal('tree', new ilTree(intval(ROOT_FOLDER_ID), intval(ROOT_FOLDER_ID)));
    }

    /**
     * Initialise a fake http services to satisfy the help system module.
     * @return void
     */

    private static function initHTTPServices(): void
    {
        global $DIC;

        $DIC['http.request_factory'] = function ($c) {
            return new RequestFactoryImpl();
        };

        $DIC['http.response_factory'] = function ($c) {
            return new ResponseFactoryImpl();
        };

        $DIC['http.cookie_jar_factory'] = function ($c) {
            return new CookieJarFactoryImpl();
        };

        $DIC['http.response_sender_strategy'] = function ($c) {
            return new DefaultResponseSenderStrategy();
        };

        $DIC['http.duration_factory'] = function ($c) {
            return new \ILIAS\HTTP\Duration\DurationFactory(
                new \ILIAS\HTTP\Duration\Increment\IncrementFactory()
            );
        };

        $DIC['http'] = function ($c) {
            /*
        return new HTTPServices($c['http.response_sender_strategy'], $c['http.cookie_jar_factory'],
            $c['http.request_factory'], $c['http.response_factory']);
         */
            return new \ILIAS\HTTP\Services($c);
        };
    }

    /**
     * Initialise a fake tabs service to satisfy the help system module.
     * @return void
     */
    private function initTabs(): void
    {
        $this->makeGlobal('ilTabs', new ilTabsGUI());
    }

    /**
     * Initialise a fake NavigationHistory service to satisfy the help system module.
     * @return void
     */
    private function initNavigationHistory(): void
    {
        $this->makeGlobal('ilNavigationHistory', new ilNavigationHistory());
    }

    /**
     * Initialise a fake help service to satisfy the help system module.
     * @return void
     */
    private function initHelp(): void
    {
        $this->makeGlobal('ilHelp', new ilHelp());
    }

    /**
     * Initialise a fake help text retriever service to satisfy the help system module.
     * @return void
     */
    private function initHelpTextRetriever(): void
    {
        $this->makeGlobal('help.text_retriever', new ilHelpUITextRetriever());
    }

    /**
     * @return void
     */
    private function initAppEventHandler(): void
    {
        $this->makeGlobal("ilAppEventHandler", new ilAppEventHandler());
    }

    /**
     *
     * @throws \Exception
     */
    private function initMail()
    {
        /* global $DIC;
         $this->makeGlobal("mail.mime.transport.factory",
             new ilMailMimeTransportFactory($DIC->settings(), $DIC->event()));

         $this->makeGlobal("mail.mime.sender.factory", new ilMailMimeSenderFactory($DIC->settings(),intval(ANONYMOUS_USER_ID)));*/
    }

    /**
     * @param Container $c
     * @return void
     */
    private function initGlobalScreen(Container $c): void
    {
        /*
        Closure::bind(function (Container $dic) {
            self::initGlobalScreen($dic);
        }, null, ilInitialisation::class)(self::dic()->dic());
        */
        $c['global_screen'] = function () use ($c) {
            return new Services(
                new ilGSProviderFactory($c),
                $c->ui(),
                htmlentities(str_replace([" ", ".", "-"], "_", ILIAS_VERSION_NUMERIC))
            );
        };
        $c->globalScreen()->tool()->context()->stack()->clear();
        $c->globalScreen()->tool()->context()->claim()->main();

    }

    /**
     * @return void
     */
    private function initFilesystem(): void
    {
        ilInitialisation::bootstrapFilesystems();
    }

    /**
     * @return void
     */
    protected function initResourceStorage(): void
    {
        global $DIC;

        $initResourceStorage = new InitResourceStorage();
        $initResourceStorage->init($DIC);
    }

    /**
     * @param Container $container
     * @return void
     */
    protected static function initComponentService(\ILIAS\DI\Container $container): void
    {
        $init = new InitComponentService();
        $init->init($container);
    }

    /**
     * @return void
     */
    protected static function initCore(): void
    {
        $GLOBALS["DIC"]["ilias.version"] = (new \ILIAS\Data\Factory())->version(ILIAS_VERSION_NUMERIC);
    }

    private function initFileDelivery(): void
    {
        global $DIC;
        Init::init($DIC);
    }

}