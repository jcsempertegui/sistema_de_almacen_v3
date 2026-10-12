<?php

namespace App\Http\Requests;

use App\Models\Worker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkerRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        $worker = $this->route('worker');

        return $worker ? $this->user()->can('update', $worker) : $this->user()->can('create', Worker::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['codigo', 'documento_identidad'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::upper(trim($this->input($field)));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:40', Rule::unique('workers')->ignore($this->route('worker'))],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'documento_identidad' => ['nullable', 'string', 'max:40', Rule::unique('workers')->ignore($this->route('worker'))],
            'cargo' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
