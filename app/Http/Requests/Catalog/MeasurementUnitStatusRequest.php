<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\SgaFormRequest;

class MeasurementUnitStatusRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('measurement_unit'));
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
