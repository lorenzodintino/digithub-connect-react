<?php

namespace App\Http\Requests;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class PublishLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalDocument::class) ?? false;
    }

    /**
     * A version label is used once per document: it is how acceptances are read.
     *
     * @return array<string, array<int, ValidationRule|Unique|string>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:40', Rule::unique('legal_documents', 'version')->where('type', $this->type()->value)],
            'file' => ['required', 'file', 'mimes:pdf', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['version' => __('version'), 'file' => __('document')];
    }

    public function type(): LegalDocumentType
    {
        $type = $this->route('type');

        return $type instanceof LegalDocumentType ? $type : LegalDocumentType::from((string) $type);
    }
}
