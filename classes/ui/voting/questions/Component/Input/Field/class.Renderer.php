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
use ILIAS\UI\Implementation\Component\Input\Field\Renderer as RendererILIAS;
use ILIAS\UI\Implementation\Render\Template;
use ilTemplate;

/**
 * Class Renderer
 */
class Renderer extends RendererILIAS
{
    private \ILIAS\UI\Renderer $default_renderer;

    protected function getComponentInterfaceName(): array
    {
        return [
            MultipleOptions::class, CorrectOrder::class, MultipleCheck::class
        ];
    }

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
            default => $this->default_renderer->render($component),
        };
    }

    protected function wrapInFormContext(
        FormInput $component,
        string    $input_html,
        string    $id_pointing_to_input = '',
        string    $dependant_group_html = '',
        bool      $bind_label_with_for = true
    ): string
    {
        $tpl = new ilTemplate("src/UI/templates/default/Input/tpl.context_form.html", true, true);

        $tpl->setVariable("INPUT", $input_html);

        if ($id_pointing_to_input && $bind_label_with_for) {
            $tpl->setCurrentBlock('for');
            $tpl->setVariable("ID", $id_pointing_to_input);
            $tpl->parseCurrentBlock();
        }

        $label = $component->getLabel();
        $tpl->setVariable("LABEL", $label);

        $byline = $component->getByline();
        if ($byline) {
            $tpl->setVariable("BYLINE", $byline);
        }

        if ($component->isRequired()) {
            $tpl->touchBlock("required");
        }

        $error = $component->getError();
        if ($error) {
            $tpl->setVariable("ERROR", $error);
            $tpl->setVariable("ERROR_FOR_ID", $id_pointing_to_input);
        }

        $tpl->setVariable("DEPENDANT_GROUP", $dependant_group_html);
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

    protected function bindJSandApplyId(FormInput $component, ilTemplate|Template $tpl): string
    {
        $id = $this->bindJavaScript($component) ?? $this->createId();
        $tpl->setVariable("ID", $id);
        return $id;
    }

    protected function applyValue(FormInput $component, ilTemplate|Template $tpl, callable $escape = null): void
    {
        $value = $component->getValue();
        if (!is_null($escape)) {
            $value = $escape($value);
        }
        if (isset($value) && $value != '') {
            $tpl->setVariable("VALUE", $value);
        }
    }

    private function getTemplateCustom(string $name): ilTemplate
    {
        return new ilTemplate("Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/templates/customUI/input/templates/$name", true, true);
    }

    private function renderMultipleOptions(MultipleOptions $component): string
    {
        global $DIC;

        $tpl = $this->getTemplateCustom("tpl.multiple_options.html");
        $plugin_base_path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/';

        $DIC->ui()->mainTemplate()->addJavaScript($plugin_base_path . 'templates/customUI/input/js/multiple_options.js');
        $DIC->ui()->mainTemplate()->addCss($plugin_base_path . 'templates/customUI/input/css/multiple_options.css');

        $this->applyName($component, $tpl);
        $this->maybeDisable($component, $tpl);
        $id = $this->bindJSandApplyId($component, $tpl);

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        $this->applyValue($component, $tpl);

        return $this->wrapInFormContext($component, $tpl->get(), $id);
    }

    private function renderCorrectOrder(CorrectOrder $component): string
    {
        global $DIC;

        $tpl = $this->getTemplateCustom("tpl.correct_order.html");
        $plugin_base_path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/';

        $DIC->ui()->mainTemplate()->addJavaScript($plugin_base_path . 'templates/customUI/input/js/multiple_options.js');
        $DIC->ui()->mainTemplate()->addCss($plugin_base_path . 'templates/customUI/input/css/correct_order.css');

        $this->applyName($component, $tpl);
        $this->maybeDisable($component, $tpl);
        $id = $this->bindJSandApplyId($component, $tpl);

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        $this->applyValue($component, $tpl);

        return $this->wrapInFormContext($component, $tpl->get(), $id);
    }

    private function renderMultipleCheck(MultipleCheck $component): string
    {
        global $DIC;

        $tpl = $this->getTemplateCustom("tpl.multiple_check.html");
        $plugin_base_path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/LiveVoting/';

        $DIC->ui()->mainTemplate()->addJavaScript($plugin_base_path . 'templates/customUI/input/js/multiple_options.js');
        $DIC->ui()->mainTemplate()->addCss($plugin_base_path . 'templates/customUI/input/css/multiple_check.css');

        $this->applyName($component, $tpl);
        $this->maybeDisable($component, $tpl);
        $id = $this->bindJSandApplyId($component, $tpl);

        $tpl->setVariable("LABEL", $component->getLabel());
        $tpl->setVariable("BYLINE", $component->getByline());

        $this->applyValue($component, $tpl);

        return $this->wrapInFormContext($component, $tpl->get(), $id);
    }
}