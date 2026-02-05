<?php

    namespace App\Http\Requests\Api;

    use Illuminate\Foundation\Http\FormRequest;
    use Illuminate\Http\Exceptions\HttpResponseException;

    class DepositRequest extends FormRequest
    {
        /**
         * Determine if the user is authorized to make this request.
         */
        public function authorize(): bool
        {
            return $this->attributes->has('merchant');
        }

        /**
         * Get the validation rules that apply to the request.
         *
         * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
         */
        public function rules(): array
        {
            return [
                'amount' => 'required|numeric|min:1',
                'currency' => 'required|string|min:3|max:20',
                'currency_to' => 'nullable|string|exists:currencies,name',
                'user_id' => 'required|integer',
                'transaction_id' => 'nullable|string',
            ];
        }

        protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
        {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422)
            );
        }
    }
