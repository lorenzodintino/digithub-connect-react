<?php

namespace App\Actions\Legal;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\UploadedFile;

class PublishLegalDocument
{
    /**
     * Put a new version of a platform document in force.
     *
     * The previous versions stay where they are: the acceptances that point at
     * them are the proof of what each portal agreed to, and when.
     */
    public function handle(LegalDocumentType $type, string $version, UploadedFile $file, User $actor): LegalDocument
    {
        $document = LegalDocument::create([
            'type' => $type,
            'version' => $version,
            'file_path' => $file->store('legal/platform', 'public'),
            'published_at' => now(),
        ]);

        Activity::log('legal.published', $document, ['type' => $type->value, 'version' => $version], label: $type->label().' '.$version, actor: $actor);

        return $document;
    }
}
