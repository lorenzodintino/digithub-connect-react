<?php

use App\Enums\QuestionType;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => 0,
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }
});

/**
 * Add a question to the test service.
 */
function askQuestion(array $attributes = []): ServiceQuestion
{
    return test()->service->questions()->create([
        'label' => 'Prima visita?',
        'type' => QuestionType::SingleChoice,
        'options' => ['Sì', 'No'],
        'is_required' => true,
        'position' => 0,
        ...$attributes,
    ]);
}

/**
 * The component of the public page, sitting on a free slot.
 */
function bookingPage(): Testable
{
    $tomorrow = CarbonImmutable::now(test()->tenant->timezone)->addDay()->startOfDay();
    $slot = (new SlotFinder(test()->service, test()->operator))->slotsForDay($tomorrow)[0];

    return Livewire::test('pages::portal.service', ['tenant' => test()->tenant, 'service' => test()->service])
        ->call('selectSlot', $slot->toIso8601String())
        ->set('customerName', 'Anna Ferrari')
        ->set('customerEmail', 'anna@example.test')
        ->set('privacyAccepted', true);
}

test('a manager adds questions of every kind to a service', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->service->slug)
        ->call('addQuestion')
        ->set('form.questions.0.label', 'Prima visita?')
        ->set('form.questions.0.type', QuestionType::SingleChoice->value)
        ->set('form.questions.0.options', "Sì\nNo")
        ->set('form.questions.0.is_required', true)
        ->call('addQuestion')
        ->set('form.questions.1.label', 'Come ci hai conosciuto?')
        ->set('form.questions.1.type', QuestionType::LongText->value)
        ->call('saveService')
        ->assertHasNoErrors();

    $questions = $this->service->questions()->get();

    expect($questions)->toHaveCount(2)
        ->and($questions[0]->type)->toBe(QuestionType::SingleChoice)
        ->and($questions[0]->choices())->toBe(['Sì', 'No'])
        ->and($questions[0]->is_required)->toBeTrue()
        ->and($questions[1]->type)->toBe(QuestionType::LongText)
        ->and($questions[1]->options)->toBeNull()
        ->and($questions[1]->position)->toBe(1);
});

test('a question without a label is refused', function () {
    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->service->slug)
        ->call('addQuestion')
        ->set('form.questions.0.label', '')
        ->call('saveService')
        ->assertHasErrors('form.questions.0.label');

    expect($this->service->questions()->count())->toBe(0);
});

test('a service asks at most ten questions', function () {
    $component = Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->service->slug);

    foreach (range(1, 12) as $index) {
        $component->call('addQuestion');
    }

    expect($component->get('form.questions'))->toHaveCount(ServiceQuestion::LIMIT);
});

test('a required question stops the booking until it is answered', function () {
    $question = askQuestion();

    bookingPage()
        ->call('book')
        ->assertHasErrors('answers.'.$question->id);

    expect(Booking::query()->count())->toBe(0);
});

test('an answer outside the offered choices is refused', function () {
    $question = askQuestion();

    bookingPage()
        ->set('answers.'.$question->id, 'Forse')
        ->call('book')
        ->assertHasErrors('answers.'.$question->id);
});

test('the answers reach the booking with the wording of the day', function () {
    $single = askQuestion();
    $free = askQuestion(['label' => 'Note per lo studio', 'type' => QuestionType::ShortText, 'options' => null, 'is_required' => false, 'position' => 1]);

    bookingPage()
        ->set('answers.'.$single->id, 'Sì')
        ->set('answers.'.$free->id, 'Arrivo con i documenti')
        ->call('book')
        ->assertHasNoErrors();

    $booking = Booking::query()->sole();

    expect($booking->answers)->toHaveCount(2)
        ->and($booking->answers[0]->label)->toBe('Prima visita?')
        ->and($booking->answers[0]->value)->toBe('Sì')
        ->and($booking->answers[1]->value)->toBe('Arrivo con i documenti');
});

test('a multiple choice answer is stored as one readable line', function () {
    $question = askQuestion([
        'label' => 'Di cosa parliamo?',
        'type' => QuestionType::MultipleChoice,
        'options' => ['Tasse', 'Contratti', 'Altro'],
        'is_required' => false,
    ]);

    bookingPage()
        ->set('answers.'.$question->id, ['Tasse', 'Altro'])
        ->call('book')
        ->assertHasNoErrors();

    expect(Booking::query()->sole()->answers->first()->value)->toBe('Tasse, Altro');
});

test('an unanswered optional question leaves no empty row behind', function () {
    askQuestion(['is_required' => false]);

    bookingPage()->call('book')->assertHasNoErrors();

    expect(Booking::query()->sole()->answers)->toHaveCount(0);
});

test('deleting a question does not rewrite the answers already given', function () {
    $question = askQuestion();

    bookingPage()->set('answers.'.$question->id, 'Sì')->call('book')->assertHasNoErrors();

    Livewire::actingAs($this->manager)
        ->test('pages::app.services')
        ->call('editService', $this->service->slug)
        ->call('removeQuestion', 0)
        ->call('saveService')
        ->assertHasNoErrors();

    $answer = Booking::query()->sole()->answers->sole();

    expect($this->service->questions()->count())->toBe(0)
        ->and($answer->label)->toBe('Prima visita?')
        ->and($answer->value)->toBe('Sì')
        ->and($answer->service_question_id)->toBeNull();
});

test('the agenda shows the answers on the booking card', function () {
    $question = askQuestion();

    bookingPage()->set('answers.'.$question->id, 'Sì')->call('book')->assertHasNoErrors();

    $booking = Booking::query()->sole();

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('showBooking', $booking->id)
        ->assertSee('Prima visita?')
        ->assertSee('Sì');
});

test('erasing a customer takes their answers with it', function () {
    $question = askQuestion();

    bookingPage()->set('answers.'.$question->id, 'Sì')->call('book')->assertHasNoErrors();

    $booking = Booking::query()->sole();
    $booking->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addMinutes(30)]);

    Livewire::actingAs($this->manager)
        ->test('pages::app.bookings')
        ->call('askToAnonymise', $booking->id)
        ->call('anonymiseCustomer');

    expect($booking->fresh()->answers)->toHaveCount(0);
});
