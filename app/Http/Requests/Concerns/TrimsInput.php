<?php

namespace App\Http\Requests\Concerns;

/**
 * Normalizes request strings before validation so whitespace-only values fail required rules.
 */
trait TrimsInput
{
    /**
     * Trim only fields explicitly listed by the consuming request class.
     */
    protected function trimFields(array $fields): void
    {
        $trimmed = [];

        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $trimmed[$field] = trim($this->input($field));
            }
        }

        $this->merge($trimmed);
    }
}
