<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One answer given while booking, with the question as it read that day.
 *
 * @property int $id
 * @property int $booking_id
 * @property int|null $service_question_id
 * @property string $label
 * @property string $value
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['booking_id', 'service_question_id', 'label', 'value', 'position'])]
class BookingAnswer extends Model
{
    /**
     * The booking the answer belongs to.
     *
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The question this answered, unless it has since been deleted.
     *
     * @return BelongsTo<ServiceQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ServiceQuestion::class, 'service_question_id');
    }
}
