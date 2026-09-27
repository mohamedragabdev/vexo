<?php

namespace App\Http\Requests\Auth;

use App\Service\PhoneNormalizeService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{


    
    protected function prepareForValidation(): void
    {
        $phoneNormalize = app(PhoneNormalizeService::class);
        if ($this->has('phone') && $this->input('phone') !== null) {
            $this->merge([
                'phone' => $phoneNormalize->normalize(
                    $this->input('phone')
                ),
            ]);
        }
    }
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
              'phone' => [
                'required',
                'string',
                'exists:users,phone',
                'regex:/^\+20(10|11|12|15)[0-9]{8}$/',
            ],
            'password'=>'required|string'
        ];
    }
}
