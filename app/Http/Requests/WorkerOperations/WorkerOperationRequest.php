<?php

namespace App\Http\Requests\WorkerOperations;

use App\Support\OperationValidation;
use Illuminate\Foundation\Http\FormRequest;

class WorkerOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission($this->route('kind').'.'.($this->route('document') ? 'update' : 'create')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('kind') === 'deliveries') {
            $lines = $this->input('lines', []);
            if (is_array($lines)) {
                foreach ($lines as &$line) {
                    if (is_array($line)) {
                        $line['unit_ids'] ??= [];
                    }
                }unset($line);
                $this->merge(['lines' => $lines]);
            }
        }
    }

    public function rules(): array
    {
        $kind = $this->route('kind');
        $rules = ['document_date' => ['required', 'date_format:Y-m-d'], 'notes' => ['nullable', 'string', 'max:10000'], 'lock_version' => ['nullable', 'integer', 'min:1'], 'expected_line_count' => ['required', 'integer', 'min:1', 'max:1000'], 'lines' => ['required', 'array', 'min:1', 'max:1000'], 'lines.*.location_id' => ['required', 'integer', 'min:1']];
        if ($kind === 'returns') {
            $rules += ['loan_id' => ['required', 'integer', 'min:1'], 'lines.*.loan_item_id' => ['required', 'integer', 'min:1', 'distinct']];
        } else {
            $rules['worker_id'] = ['required', 'integer', 'min:1'];
        }
        if ($kind === 'loans') {
            $rules += ['due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'], 'lines.*.individual_unit_id' => ['required', 'integer', 'min:1', 'distinct']];
        }
        if ($kind === 'deliveries') {
            $rules += ['lines.*.product_id' => ['required', 'integer', 'min:1'], 'lines.*.quantity' => ['required'], 'lines.*.unit_ids' => ['present', 'array', 'max:1000'], 'lines.*.unit_ids.*' => ['integer', 'min:1']];
        }

        return $rules;
    }

    public function messages(): array
    {
        return OperationValidation::MESSAGES;
    }
}
