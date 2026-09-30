<?php

namespace App\Integrations\Billing\DTO;

use App\Exceptions\PortalException;
use Illuminate\Support\Facades\Validator;

abstract class ValidatedData implements \JsonSerializable
{
    public readonly array $data;

    protected array $validationInput = [];

    public function __construct(mixed $data)
    {
        $this->validationInput = is_array($data) ? $data : [];
        if (! is_array($data) || Validator::make($data, $this->rules())->fails()) {
            throw new PortalException('UPSTREAM_SCHEMA_INVALID', 502);
        }$this->data = $data;
    }

    abstract protected function rules(): array;

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    protected function id(): array
    {
        return ['required', function ($attribute, $value, $fail) {
            if (! is_string($value) || $value === '' || strlen($value) > 128) {
                $fail('Invalid identifier');
            }
        }];
    }

    protected function money(): array
    {
        return ['required', function ($attribute, $value, $fail) {
            if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,17})$/D', $value)) {
                $fail('Invalid minor units');
            }
        }];
    }
}
