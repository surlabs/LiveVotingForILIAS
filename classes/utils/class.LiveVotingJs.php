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

namespace LiveVoting\Utils;

use ilCtrlException;
use ilLiveVotingPlugin;
use ilMathJax;
use ilObjLiveVotingGUI;
use ilSetting;
use LiveVotingPlayerGUI;

/**
 * Class LiveVotingJs
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
final class LiveVotingJs
{
    private string $name = '';
    private array $settings = [];
    private string $category = '';
    /**
     * @var true
     */
    private bool $init = false;
    private string $lib = '';
    private array $translations = [];

    public static function getInstance(): LiveVotingJs
    {
        return new self();
    }

    public function name(string $string): LiveVotingJs
    {
        $this->name = $string;

        return $this;
    }

    public function addSettings(array $settings): LiveVotingJs
    {
        foreach ($settings as $k => $v) {
            $this->settings[$k] = $v;
        }

        return $this;
    }

    public function addTranslations(array $translations): LiveVotingJs
    {
        foreach ($translations as $k => $v) {
            $this->translations[$v] = ilLiveVotingPlugin::getInstance()->txt($v);
        }

        return $this;
    }

    public function addSetting($key, $value): LiveVotingJs
    {
        $this->settings[$key] = $value;

        return $this;
    }

    public function category(string $category): LiveVotingJs
    {
        $this->category = $category;

        return $this;
    }

    public function init(): LiveVotingJs
    {
        $this->init = true;
        $this->resolveLib();
        $this->addLibToHeader($this->lib, false);
        $this->setInitCode();

        return $this;
    }

    /**
     * @return LiveVotingJs
     */
    public function setRunCode(): LiveVotingJs
    {
        return $this->call("run");
    }

    /**
     * @return string
     */
    public function getRunCode(): string
    {
        return '<script>' . $this->getCallCode("run") . '</script>';
    }

    public function call($method, $params = ''): LiveVotingJs
    {
        if (!$this->init) {
            return $this;
        }

        $this->addOnLoadCode($this->getCallCode($method, $params));

        return $this;
    }

    public function addOnLoadCode($code): LiveVotingJs
    {
        global $DIC;

        $DIC->ui()->mainTemplate()->addOnLoadCode($code);

        return $this;
    }

    public function getCallCode($method, $params = ''): string
    {
        return ilLiveVotingPlugin::PLUGIN_ID . $this->name . '.' . $method . '(' . $params . ');';
    }

    protected function resolveLib(): void
    {
        $base_path = './Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/js/';
        $category = ($this->category ? $this->category . '/' : '') . $this->name . '/';
        $file_name = ilLiveVotingPlugin::PLUGIN_ID . $this->name . '.js';
        $file_name_min = ilLiveVotingPlugin::PLUGIN_ID . $this->name . '.min.js';
        $full_path_min = $base_path . $category . $file_name_min;
        $full_path = $base_path . $category . $file_name;
        if (is_file($full_path_min)) {
            $this->lib = $full_path_min;
        } else {
            $this->lib = $full_path;
        }
    }

    public function addLibToHeader($name_of_lib, $external = true): LiveVotingJs
    {
        global $DIC;

        if ($external) {
            $DIC->ui()->mainTemplate()->addJavaScript('Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/js/libs/' . $name_of_lib);
        } else {
            $DIC->ui()->mainTemplate()->addJavaScript($name_of_lib);
        }

        return $this;
    }

    public function setInitCode(): LiveVotingJs
    {
        $arr = array();

        foreach ($this->settings as $name => $value) {
            $arr[$name] = $value;
        }


        foreach ($this->translations as $key => $string) {
            $arr['lng'][$key] = $string;
        }

        return $this->call("init", json_encode($arr));
    }

    /**
     * @param LiveVotingPlayerGUI $playerGUI
     * @param array $additional_classes
     * @param string $cmd
     *
     * @return LiveVotingJs
     * @throws ilCtrlException
     *
     */
    public function api(LiveVotingPlayerGUI $playerGUI, array $additional_classes = array(), $cmd = ''): LiveVotingJs
    {

        global $DIC;
        $additional_classes[] = get_class($playerGUI);

        ParamManager::getInstance();

        $this->addSetting('base_url', $DIC->ctrl()->getLinkTargetByClass($additional_classes, $cmd, null, true));

        return $this;
    }

    /**
     * @param ilObjLiveVotingGUI $liveVotingGUI
     * @param string $cmd
     *
     * @return LiveVotingJs
     * @throws ilCtrlException
     *
     */
    public function ilias(ilObjLiveVotingGUI $liveVotingGUI, string $cmd = ''): LiveVotingJs
    {
        global $DIC;

        $url = $DIC->ctrl()->getLinkTarget($liveVotingGUI, $cmd, '', true);

        // Remove the param xlvo_voting
        $url = preg_replace('/(&|\?)xlvo_voting=[^&]*/', '', $url);

        $this->addSetting('base_url', $url);

        return $this;
    }

    public static function sendResponse($data): void
    {
        header('Content-type: application/json');
        echo json_encode($data);
        exit();
    }

    /**
     * @return void
     */
    public function initMathJax(): void
    {
        $mathJaxSetting = new ilSetting("MathJax");
        if (strpos($mathJaxSetting->get('path_to_mathjax'), 'mathjax@3') !== false) { // not sure if this check will work with >v3
            // mathjax v3 needs to be configured differently
            $this->addLibToHeader('mathjax_config.js');
        }
        ilMathJax::getInstance()->includeMathJax();
    }
}