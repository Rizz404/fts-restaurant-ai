<?php

namespace App\Support\Ui;

use Illuminate\Support\ViewErrorBag;

/**
 * Computes the IDs and validation state shared by x-ui.field and its control.
 *
 * Both components call this helper with the same name/id, so the label,
 * help text, error message, and aria attributes always agree.
 */
final class FieldState
{
    public function __construct(
        public readonly string $id,
        public readonly string $errorKey,
        public readonly ?string $help,
        public readonly ?string $error,
    ) {}

    public static function for(string $name, string $id, mixed $errors = null, ?string $help = null, ?string $error = null): self
    {
        $key = self::errorKey($name);

        if (($error === null || $error === '') && $errors instanceof ViewErrorBag) {
            $error = $errors->first($key) ?: null;
        }

        return new self($id, $key, $help === '' ? null : $help, $error === '' ? null : $error);
    }

    /**
     * Converts an input name such as items[0][title] to its error bag key items.0.title.
     */
    public static function errorKey(string $name): string
    {
        $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);

        return trim($key, '.');
    }

    public function helpId(): string
    {
        return $this->id.'-help';
    }

    public function errorId(): string
    {
        return $this->id.'-error';
    }

    public function hasHelp(): bool
    {
        return $this->help !== null;
    }

    public function invalid(): bool
    {
        return $this->error !== null;
    }

    public function describedBy(): ?string
    {
        $ids = array_filter([
            $this->hasHelp() ? $this->helpId() : null,
            $this->invalid() ? $this->errorId() : null,
        ]);

        return $ids === [] ? null : implode(' ', $ids);
    }
}
