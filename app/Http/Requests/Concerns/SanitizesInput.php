<?php

namespace App\Http\Requests\Concerns;

use App\Support\Validation\SafeData;

trait SanitizesInput
{
    protected function prepareForValidation(): void
    {
        $this->merge(SafeData::sanitize($this->all()));

        if (method_exists($this, 'afterSanitize')) {
            $this->afterSanitize();
        }
    }
}
