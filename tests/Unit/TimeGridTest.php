<?php

use App\Support\TimeGrid;

test('an appointment on its own takes the whole width', function () {
    expect(TimeGrid::place(['a' => ['start' => 540, 'end' => 600]]))
        ->toBe(['a' => ['start' => 540, 'end' => 600, 'lane' => 0, 'lanes' => 1]]);
});

test('overlapping appointments sit side by side', function () {
    $placed = TimeGrid::place([
        'a' => ['start' => 540, 'end' => 600],
        'b' => ['start' => 570, 'end' => 630],
    ]);

    expect($placed['a'])->toMatchArray(['lane' => 0, 'lanes' => 2]);
    expect($placed['b'])->toMatchArray(['lane' => 1, 'lanes' => 2]);
});

test('appointments that only touch do not overlap', function () {
    $placed = TimeGrid::place([
        'a' => ['start' => 540, 'end' => 600],
        'b' => ['start' => 600, 'end' => 660],
    ]);

    expect($placed['a'])->toMatchArray(['lane' => 0, 'lanes' => 1]);
    expect($placed['b'])->toMatchArray(['lane' => 0, 'lanes' => 1]);
});

test('a freed lane is reused inside the same cluster', function () {
    $placed = TimeGrid::place([
        'long' => ['start' => 540, 'end' => 720],
        'first' => ['start' => 540, 'end' => 600],
        'second' => ['start' => 600, 'end' => 660],
    ]);

    expect($placed['long'])->toMatchArray(['lane' => 1, 'lanes' => 2]);
    expect($placed['first'])->toMatchArray(['lane' => 0, 'lanes' => 2]);
    expect($placed['second'])->toMatchArray(['lane' => 0, 'lanes' => 2]);
});

test('a later appointment after a gap gets the full width back', function () {
    $placed = TimeGrid::place([
        'a' => ['start' => 540, 'end' => 600],
        'b' => ['start' => 550, 'end' => 610],
        'c' => ['start' => 700, 'end' => 760],
    ]);

    expect($placed['c'])->toMatchArray(['lane' => 0, 'lanes' => 1]);
});

test('the input order does not matter', function () {
    $placed = TimeGrid::place([
        'b' => ['start' => 570, 'end' => 630],
        'a' => ['start' => 540, 'end' => 600],
    ]);

    expect($placed['a']['lane'])->toBe(0);
    expect($placed['b']['lane'])->toBe(1);
});
