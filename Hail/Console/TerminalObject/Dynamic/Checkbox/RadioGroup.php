<?php

namespace Hail\Console\TerminalObject\Dynamic\Checkbox;

class RadioGroup extends CheckboxGroup
{
    /**
     * Toggle the currently selected option, uncheck all of the others
     */
    public function toggleCurrent()
    {
        [$checkbox, $checkboxKey] = $this->getCurrent();

        $checkbox->setChecked(!$checkbox->isChecked());

        foreach ($this->checkboxes as $key => $checkbox) {
            if ($key === $checkboxKey) {
                continue;
            }

            $checkbox->setChecked(false);
        }
    }

    /**
     * Get the checked option
     *
     * @return string|bool|int
     */
    public function getCheckedValues()
    {
        if ($checked = $this->getChecked()) {
            return reset($checked)->getValue();
        }

        return null;
    }
}
