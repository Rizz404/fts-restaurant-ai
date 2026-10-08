<?php

namespace App\Http\Requests\Admin;

use App\Models\MenuItem;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof MenuItem && $this->user()->can('setAvailability', $item);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['is_available' => ['required', 'boolean']];
    }

    public function available(): bool
    {
        return $this->boolean('is_available');
    }
}
