<?php

namespace App\Models;

use App\Enums\LegalDocumentType;
use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * One published version of a platform document.
 *
 * Versions are never edited or deleted once published: an acceptance has to
 * keep pointing at the exact text the portal agreed to.
 *
 * @property int $id
 * @property LegalDocumentType $type
 * @property string $version
 * @property string $file_path
 * @property Carbon $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'version', 'file_path', 'published_at'])]
class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * The portals that accepted this version.
     *
     * @return HasMany<LegalAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    /**
     * The version in force for each type that has one, keyed by type value.
     *
     * @return Collection<string, LegalDocument>
     */
    public static function current(): Collection
    {
        return self::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (LegalDocument $document): string => $document->type->value)
            ->keyBy(fn (LegalDocument $document): string => $document->type->value);
    }

    /**
     * Where the PDF can be read.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
