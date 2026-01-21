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

namespace Customizing\global\plugins\Services\Repository\RepositoryObject\LiveVoting\classes\ui\voting\questions\Component\Input\Field;

use ILIAS\UI\Component\Component;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Implementation\Component\Input\Field as F;
use ILIAS\UI\Implementation\Component\Input\Field\Renderer as RendererILIAS;
use ILIAS\UI\Implementation\Render\Template;
use ilRTE;
use ilTemplate;
use ilTemplateException;
use ilTinyMCE;
use LiveVoting\Utils\LiveVotingUtils;

/**
 * Class Renderer
 */
class Renderer extends RendererILIAS
{
    private \ILIAS\UI\Renderer $default_renderer;

    /**
     * @throws ilTemplateException
     */
    public function render(Component $component, ?\ILIAS\UI\Renderer $default_renderer = null): string
    {
        global $DIC;

        if (isset($default_renderer)) {
            $this->default_renderer = $default_renderer;
        } elseif (!isset($this->default_renderer)) {
            $this->default_renderer = $DIC->ui()->renderer();
        }

        return match (true) {
            $component instanceof MultipleOptions => $this->renderMultipleOptions($component),
            $component instanceof CorrectOrder => $this->renderCorrectOrder($component),
            $component instanceof MultipleCheck => $this->renderMultipleCheck($component),
            $component instanceof TextareaRTE  => $this->renderTextareaRTE($component),
            default => $this->default_renderer->render($component),
        };
    }

    /**
     * @throws ilTemplateException
     */
    protected function wrapInFormContext(
        FormInput $component,
        string $label,
        string $input_html,
        ?string $id_for_label = null,
        ?string $dependant_group_html = null
    ): string {
        $tpl = new ilTemplate("Input/tpl.context_form.html", true, true, 'components/ILIAS/UI/src');

        $tpl->setVariable("LABEL", $label);
        $tpl->setVariable("INPUT", $input_html);
        $tpl->setVariable("UI_COMPONENT_NAME", $this->getComponentCanonicalNameAttribute($component));
        $tpl->setVariable("INPUT_NAME", $component->getName());

        if ($component->getOnLoadCode() !== null) {
            $binding_id = $this->bindJavaScript($component) ?? $this->createId();
            $tpl->setVariable("BINDING_ID", $binding_id);
        }

        if ($id_for_label) {
            $tpl->setCurrentBlock('for');
            $tpl->setVariable("ID", $id_for_label);
            $tpl->parseCurrentBlock();
        } else {
            $tpl->touchBlock('tabindex');
        }

        $byline = $component->getByline();
        if ($byline) {
            $tpl->setVariable("BYLINE", $byline);
        }

        $required = $component->isRequired();
        if ($required) {
            $tpl->setCurrentBlock('required');
            $tpl->setVariable("REQUIRED_ARIA", $this->txt('required_field'));
            $tpl->parseCurrentBlock();
        }

        if ($component->isDisabled()) {
            $tpl->touchBlock("disabled");
        }

        $error = $component->getError();
        if ($error) {
            $error_id = $this->createId();
            $tpl->setVariable("ERROR_LABEL", $this->txt("ui_error"));
            $tpl->setVariable("ERROR_ID", $error_id);
            $tpl->setVariable("ERROR", $error);
            if ($id_for_label) {
                $tpl->setVariable("ERROR_FOR_ID", $id_for_label);
            }
        }

        if ($dependant_group_html) {
            $tpl->setVariable("DEPENDANT_GROUP", $dependant_group_html);
        }
        return $tpl->get();
    }

    protected function maybeDisable(FormInput $component, ilTemplate|Template $tpl): void
    {
        if ($component->isDisabled()) {
            $tpl->setVariable("DISABLED", 'disabled="disabled"');
        }
    }

    protected function applyName(FormInput $component, ilTemplate|Template $tpl): ?string
    {
        $name = $component->getName();
        if ($name !== null) {
            $tpl->setVariable("NAME", $name);
        }
        return $name;
    }

    protected function applyValue(FormInput $component, ilTemplate|Template $tpl, callable $escape = null): void
    {
        $value = $component->getValue();
        if (!is_null($escape)) {
            $value = $escape($value);
        }
        if (isset($value) && $value != '') {
            $tpl->setVariable("VALUE", LiveVotingUtils::_solveKeyBracketsBug($value));
        }
    }

    private function getTemplateCustom(string $name): ilTemplate
    {
        return new ilTemplate("/public/Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/customUI/input/templates/$name", true, true);
    }

    /**
     * @throws ilTemplateException
     */
    private function renderMultipleOptions(MultipleOptions $component): string
    {
        global $DIC;

        $tpl = $this->getTemplateCustom("tpl.multiple_options.html");
        $plugin_base_path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/';

        $DIC->ui()->mainTemplate()->addJavaScript($plugin_base_path . 'templates/customUI/input/js/multiple_options.js');
        $DIC->ui()->mainTemplate()->addCss($plugin_base_path . 'templates/customUI/input/css/multiple_options.css');

        $this->applyName($component, $tpl);
        $this->maybeDisable($component, $tpl);

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        $this->applyValue($component, $tpl, fn($value) => str_replace('"', "\'", $value));

        return $this->wrapInFormContext($component, $component->getLabel(), $tpl->get());
    }

    /**
     * @throws ilTemplateException
     */
    private function renderCorrectOrder(CorrectOrder $component): string
    {
        global $DIC;

        $tpl = $this->getTemplateCustom("tpl.correct_order.html");
        $plugin_base_path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/';

        $DIC->ui()->mainTemplate()->addJavaScript($plugin_base_path . 'templates/customUI/input/js/multiple_options.js');
        $DIC->ui()->mainTemplate()->addCss($plugin_base_path . 'templates/customUI/input/css/correct_order.css');

        $this->applyName($component, $tpl);
        $this->maybeDisable($component, $tpl);

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        $this->applyValue($component, $tpl, fn($value) => str_replace('"', "\'", $value));

        return $this->wrapInFormContext($component, $component->getLabel(), $tpl->get());
    }

    /**
     * @throws ilTemplateException
     */
    private function renderMultipleCheck(MultipleCheck $component): string
    {
        global $DIC;

        $tpl = $this->getTemplateCustom("tpl.multiple_check.html");
        $plugin_base_path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/';

        $DIC->ui()->mainTemplate()->addJavaScript($plugin_base_path . 'templates/customUI/input/js/multiple_options.js');
        $DIC->ui()->mainTemplate()->addCss($plugin_base_path . 'templates/customUI/input/css/multiple_check.css');

        $this->applyName($component, $tpl);
        $this->maybeDisable($component, $tpl);

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        $this->applyValue($component, $tpl, fn($value) => str_replace('"', "\'", $value));

        return $this->wrapInFormContext($component, $component->getLabel(), $tpl->get());
    }

    private function renderTextareaRTE(TextareaRTE $component): string
    {
        /** @var $component TextareaRTE */
        $component = $component->withAdditionalOnLoadCode(
            static function ($id): string {
                return "
                    il.UI.Input.textarea.init('$id');
                ";
            }
        );

        $tpl = $this->getPreparedTextareaRTETemplate($component);

        return $this->wrapInFormContext($component, $component->getLabel(), $tpl->get());
    }

    protected function getPreparedTextareaRTETemplate(TextareaRTE $component): ilTemplate
    {
        $tpl = $this->getTemplateCustom("tpl.textareaRte.html");

        if (0 < $component->getMaxLimit()) {
            $tpl->setVariable('REMAINDER_TEXT', $this->txt('ui_chars_remaining'));
            $tpl->setVariable('REMAINDER', $component->getMaxLimit() - strlen($component->getValue() ?? ''));
            $tpl->setVariable('MAX_LIMIT', $component->getMaxLimit());
        }

        if (null !== $component->getMinLimit()) {
            $tpl->setVariable('MIN_LIMIT', $component->getMinLimit());
        }

        $this->applyName($component, $tpl);
        $this->applyValue($component, $tpl, $this->htmlEntities());
        $this->maybeDisable($component, $tpl);

        $rte_string = ilRTE::_getRTEClassname();
        /** @var ilTinyMCE $rte */
        $rte = new $rte_string();

        $rte->addPlugin("emoticons");
        $rte->addPlugin("latex");
        $rte->addButton("latex");
        $rte->addButton("pastelatex");

        $rteSupport = $component->getRTESupport();

        if (!empty($rteSupport)) {
            $rte->addRTESupport($rteSupport["obj_id"], $rteSupport["obj_type"], $rteSupport["module"], false, $rteSupport['cfg_template']);

            $tpl->setVariable('RTE_EDITOR', "RTEditor");
        }

        return $tpl;
    }
}