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

namespace LiveVoting\UI\Player\CustomUI;

use ilDateTime;
use ilDateTimeException;
use ilFormPropertyGUI;
use ilHiddenInputGUI;
use ilLiveVotingPlugin;
use ilRadioOption;
use ilSystemStyleException;
use ilTableFilterItem;
use ilTemplate;
use ilTemplateException;
use ilToolbarItem;
use ilUtil;

/**
 * Class MultiLineNewInputGUI
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 * @ilCtrl_IsCalledBy LiveVotingUI: ilUIPluginRouterGUI
 */
class MultiLineNewInputGUI extends ilFormPropertyGUI implements ilTableFilterItem, ilToolbarItem
{
    const SHOW_INPUT_LABEL_ALWAYS = 3;
    const SHOW_INPUT_LABEL_NONE = 1;
    const SHOW_INPUT_LABEL_ONCE = 2;
    /**
     * @var int
     */
    protected static int $counter = 0;
    /**
     * @var bool
     */
    protected static bool $init = false;
    /**
     * @var ilFormPropertyGUI[]
     */
    protected array $inputs = [];
    /**
     * @var ilFormPropertyGUI[]|null
     */
    protected ?array $inputs_generated = null;
    /**
     * @var int
     */
    protected int $show_input_label = self::SHOW_INPUT_LABEL_ONCE;
    /**
     * @var bool
     */
    protected bool $show_sort = true;
    /**
     * @var array
     */
    protected array $value = [];


    /**
     * MultiLineNewInputGUI constructor
     *
     * @param string $title
     * @param string $post_var
     */
    public function __construct(string $title = "", string $post_var = "")
    {

        parent::__construct($title, $post_var);

        self::init();
    }


    public static function init(): void
    {

        global $tpl;


        if (self::$init === false) {
            self::$init = true;
            $tpl->addCss("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/customUI/MultiLineNewInputGUI/css/multi_line_new_input_gui.css");
            $tpl->addJavaScript("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/customUI/MultiLineNewInputGUI/js/multi_line_new_input_gui.min.js");
        }
    }


    /**
     * @param ilFormPropertyGUI $input
     */
    public function addInput(ilFormPropertyGUI $input): void
    {
        $this->inputs[] = $input;
        $this->inputs_generated = null;
    }


    /**
     * @inheritDoc
     * @throws ilDateTimeException
     */
    public function checkInput(): bool
    {
        global $DIC;
        $http = $DIC->http();
        $ok = true;

        $originalPost = $http->request()->getParsedBody();

        if (count($this->getInputs($this->getRequired())) == 0 || $this->getInputs($this->getRequired()) == null) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $DIC->language()->txt("form_input_not_valid_key_missing", 'options'));
            $ok = false;
        }

        foreach ($this->getInputs($this->getRequired()) as $i => $inputs) {
            foreach ($inputs as $org_post_var => $input) {

                $post_var = $input->getPostVar();
                $parent_post_var = $this->getPostVar();


                $b_value = $originalPost[$post_var] ?? null;
                if (isset($originalPost[$parent_post_var]) &&
                    is_array($originalPost[$parent_post_var]) &&
                    isset($originalPost[$parent_post_var][$i]) &&
                    is_array($originalPost[$parent_post_var][$i]) &&
                    array_key_exists($org_post_var, $originalPost[$parent_post_var][$i])) {
                    $originalPost[$post_var] = $originalPost[$parent_post_var][$i][$org_post_var];
                } else {
                    $ok = false;
                    $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $DIC->language()->txt("form_input_not_valid_key_missing", 'options', $i));
                    return false;
                }

                $http->request()->withParsedBody($originalPost);
                $originalPost[$post_var] = $b_value;
                $http->request()->withParsedBody($originalPost);
            }
        }

        $this->inputs_generated = null;

        if ($ok) {
            return true;
        } else {

            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $DIC->language()->txt("form_input_not_valid"));

            return false;
        }
    }


    /**
     * @param bool $need_one_line_at_least
     *
     * @return ilFormPropertyGUI[][]
     * @throws ilDateTimeException
     */
    public function getInputs(bool $need_one_line_at_least = true): array
    {
        if ($this->inputs_generated === null) {
            $this->inputs_generated = [];

            foreach (array_values($this->getValue($need_one_line_at_least)) as $i => $value) {
                $inputs = [];

                foreach ($this->inputs as $input) {
                    $input = clone $input;

                    $org_post_var = $input->getPostVar();

                    /**
                     * #SUR#
                     *
                     */

                    /*      if(isset($value[$org_post_var])){
                              Items::setValueToItem($input, $value[$org_post_var]);

                          }*/

                    self::setValueToItem($input, array_key_exists($org_post_var, $value) ? $value[$org_post_var] : '');

                    $post_var = $this->getPostVar() . "[" . $i . "][";
                    if (strpos($org_post_var, "[") !== false) {
                        $post_var .= strstr($input->getPostVar(), "[", true) . "][" . strstr($org_post_var, "[");
                    } else {
                        $post_var .= $org_post_var . "]";
                    }
                    $input->setPostVar($post_var);

                    $inputs[$org_post_var] = $input;
                }

                $this->inputs_generated[] = $inputs;
            }
        }

        return $this->inputs_generated;
    }


    /**
     * @param ilFormPropertyGUI[] $inputs
     */
    public function setInputs(array $inputs): void
    {
        $this->inputs = $inputs;
        $this->inputs_generated = null;
    }


    /**
     * @return int
     */
    public function getShowInputLabel(): int
    {
        return $this->show_input_label;
    }


    /**
     * @param int $show_input_label
     */
    public function setShowInputLabel(int $show_input_label): void
    {
        $this->show_input_label = $show_input_label;
    }


    /**
     * @inheritDoc
     */
    public function getTableFilterHTML(): string
    {
        return $this->render();
    }


    /**
     * @inheritDoc
     */
    public function getToolbarHTML(): string
    {
        return $this->render();
    }


    /**
     * @param bool $need_one_line_at_least
     *
     * @return array
     */
    public function getValue(bool $need_one_line_at_least = false): array
    {
        $values = $this->value;

        if ($need_one_line_at_least && empty($values)) {
            $values = [[]];
        }

        return $values;
    }


    /**
     * @param array $value
     */
    public function setValue(/*array*/ $value): void
    {
        if (is_array($value)) {
            $this->value = $value;
        } else {
            $this->value = [];
        }
    }


    /**
     * @param ilTemplate $tpl
     * @throws ilTemplateException
     */
    public function insert(ilTemplate $tpl): void
    {
        $html = $this->render();

        $tpl->setCurrentBlock("prop_generic");
        $tpl->setVariable("PROP_GENERIC", $html);
        $tpl->parseCurrentBlock();
    }


    /**
     * @return bool
     */
    public function isShowSort(): bool
    {
        return $this->show_sort;
    }


    /**
     * @param bool $show_sort
     */
    public function setShowSort(bool $show_sort): void
    {
        $this->show_sort = $show_sort;
    }


    /**
     * @return string
     * @throws ilTemplateException
     * @throws ilDateTimeException
     * @throws ilSystemStyleException
     */
    public function render(): string
    {
        global $DIC;
        $counter = ++self::$counter;

        $tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/MultiLineNewInputGUI/templates/multi_line_new_input_gui.html", true, true);
        $tpl_hidden = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/MultiLineNewInputGUI/templates/multi_line_new_input_gui_hide.html", false, false);

        $tpl->setVariable("COUNTER", htmlspecialchars((string)$counter));

        $remove_first_line = (!$this->getRequired() && empty($this->getValue(false)));
        $tpl->setVariable("REMOVE_FIRST_LINE", htmlspecialchars((string)$remove_first_line));
        $tpl->setVariable("REQUIRED", htmlspecialchars((string)$this->getRequired()));
        $tpl->setVariable("SHOW_INPUT_LABEL", htmlspecialchars((string)$this->getShowInputLabel()));

        if (!$this->getRequired()) {
            $tpl->setCurrentBlock("add_first_line");

            if (!empty($this->getInputs())) {
                $tpl->setVariable("HIDE_ADD_FIRST_LINE", $tpl_hidden->get());
            }

            $tpl->setVariable("ADD_FIRST_LINE", $DIC->ui()->renderer()->renderAsync(($DIC->ui()->factory()->symbol()->glyph()->add()->withAdditionalOnLoadCode(function (string $id) use ($counter): string {
                return 'il.MultiLineNewInputGUI.clone_template = {};il.MultiLineNewInputGUI.init(' . $counter . ', $("#' . $id . '").parent().parent().parent(), true);';
            }))));

            $tpl->parseCurrentBlock();
        }

        $tpl->setCurrentBlock("line");

        foreach ($this->getInputs() as $i => $inputs) {
            if ($remove_first_line) {
                $tpl->setVariable("HIDE_LINE", $tpl_hidden->get());
            }

            $tpl->setVariable("INPUTS", self::renderInputs($inputs));

            if ($this->isShowSort()) {
                $sort_tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/MultiLineNewInputGUI/templates/multi_line_new_input_gui_sort.html", true, true);

                $sort_tpl->setVariable("UP", $DIC->ui()->renderer()->render($DIC->ui()->factory()->symbol()->glyph()->sortAscending()));
                if ($i === 0) {
                    $sort_tpl->setVariable("HIDE_UP", $tpl_hidden->get());
                }

                $sort_tpl->setVariable("DOWN", $DIC->ui()->renderer()->render($DIC->ui()->factory()->symbol()->glyph()->sortDescending()));
                if ($i === (count($this->getInputs()) - 1)) {
                    $sort_tpl->setVariable("HIDE_DOWN", $tpl_hidden->get());
                }

                $tpl->setVariable("SORT", $sort_tpl->get());
            }

            $tpl->setVariable("ADD", $DIC->ui()->renderer()->renderAsync($DIC->ui()->factory()->symbol()->glyph()->add()->withAdditionalOnLoadCode(function (string $id) use ($i, $counter): string {
                return 'il.MultiLineNewInputGUI.init(' . $counter . ', $("#' . $id . '").parent().parent().parent())' . ($i === (count($this->getInputs()) - 1) ? ';il.MultiLineNewInputGUI.update('
                        . $counter . ', $("#'
                        . $id
                        . '").parent().parent().parent().parent())' : '');
            })));

            $tpl->setVariable("REMOVE", $DIC->ui()->renderer()->render($DIC->ui()->factory()->symbol()->glyph()->remove()));
            if ($this->getRequired() && count($this->getInputs()) < 2) {
                $tpl->setVariable("HIDE_REMOVE", $tpl_hidden->get());
            }

            $tpl->parseCurrentBlock();
        }

        return $tpl->get();
    }


    /**
     * @param array $values
     */
    public function setValueByArray(array $values): void
    {

        if (isset($values[$this->getPostVar()])) {
            $this->setValue($values[$this->getPostVar()]);

        }
    }

    /**
     * @throws ilDateTimeException
     */
    public static function setValueToItem($item, $value): void
    {
        if ($item instanceof MultiLineNewInputGUI) {
            $item->setValueByArray([
                $item->getPostVar() => $value
            ]);

            return;
        }

        if (method_exists($item, "setChecked")) {
            $item->setChecked($value);

            return;
        }

        if (method_exists($item, "setDate")) {
            if (is_string($value)) {
                $value = new ilDateTime($value, IL_CAL_DATE);
            }

            $item->setDate($value);

            return;
        }

        if (method_exists($item, "setImage")) {
            $item->setImage($value);

            return;
        }

        if (method_exists($item, "setValue") && !($item instanceof ilRadioOption)) {
            $item->setValue((string)$value);
        }
    }

    /**
     * @throws ilTemplateException
     * @throws ilSystemStyleException
     */
    public static function renderInputs(array $inputs): string
    {
        global $DIC;
        self::init();

        $input_tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/Items/templates/input_gui_input.html", true, true);

        $required_tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/Items/templates/input_gui_input_required.html", true, false);

        $input_tpl->setCurrentBlock("input");


        foreach ($inputs as $input) {
            if ($input instanceof ilHiddenInputGUI) {
                $input_tpl->setVariable("HIDDEN", htmlspecialchars("hidden"));
            }

            $input_tpl->setVariable("TITLE", htmlspecialchars($input->getTitle()));

            if ($input->getRequired()) {
                $input_tpl->setVariable("REQUIRED", $required_tpl->get());
            }


            $input_html = $input->render();
            $input_html = str_replace('<div class="control-label"></div>', "", $input_html);
            $input_tpl->setVariable("INPUT", $input_html);

            if ($input->getInfo()) {
                $input_info_tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/Items/templates/input_gui_input_info.html", true, true);

                $input_info_tpl->setVariable("INFO", htmlspecialchars($input->getInfo()));

                $input_tpl->setVariable("INFO", $input_info_tpl->get());
            }

            if ($input->getAlert()) {
                $input_alert_tpl = new ilTemplate(ilLiveVotingPlugin::getInstance()->getDirectory() . "/templates/customUI/Items/templates/input_gui_input_alert.html", true, true);
                $input_alert_tpl->setVariable("IMG",
                    $DIC->ui()->renderer()->render($DIC->ui()->factory()->image()->standard(ilUtil::getImagePath("icon_alert.svg"), $DIC->language()->txt("alert"))));
                $input_alert_tpl->setVariable("TXT", htmlspecialchars($input->getAlert()));
                $input_tpl->setVariable("ALERT", ($input_alert_tpl->get()));
            }

            $input_tpl->parseCurrentBlock();
        }

        return $input_tpl->get();
    }
}