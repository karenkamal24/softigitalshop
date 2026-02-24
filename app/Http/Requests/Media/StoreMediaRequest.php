<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowedTypes = Config::get('media.allowed_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $maxSizeKb = Config::get('media.max_file_size_kb', 5120);
        $mediableTypes = Config::get('media.mediable_types', [
            \App\Models\User::class,
            \App\Models\Product::class,
        ]);
        $mediableTypeRule = implode(',', array_map('strval', $mediableTypes));

        return [
            'file' => [
                'required',
                'file',
                File::types($allowedTypes)->max($maxSizeKb),
            ],
            'mediable_type' => ['required', 'string', 'in:' . $mediableTypeRule],
            'mediable_id' => [
                'required',
                'integer',
                function (string $attribute, int $value, \Closure $fail): void {
                    $mediableType = $this->input('mediable_type');
                    if (! is_string($mediableType) || ! class_exists($mediableType)) {
                        return;
                    }
                    $table = (new $mediableType)->getTable();
                    if (! DB::table($table)->where('id', $value)->exists()) {
                        $fail('The selected model does not exist.');
                    }
                },
            ],
            'collection' => ['required', 'string', 'max:255'],
            'disk' => ['sometimes', 'string', 'in:public,s3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMb = Config::get('media.max_file_size_kb', 5120) / 1024;

        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is invalid.',
            'file.max' => "The file must not exceed {$maxSizeMb}MB.",
        ];
    }
}

