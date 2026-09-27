<?php

use App\Support\Validation\SafeData;

test('SafeData strips html and control characters from input', function () {
    $clean = SafeData::sanitize([
        'name' => "  Ali <script>alert(1)</script> ",
        'notes' => "Line1\nLine2\x00\x07",
        'password' => 'Abcd1234!<tag>',
        'empty' => '   ',
    ]);

    expect($clean['name'])->toBe('Ali scriptalert(1)/script')
        ->and($clean['notes'])->toBe("Line1\nLine2")
        ->and($clean['password'])->toBe('Abcd1234!<tag>')
        ->and($clean['empty'])->toBeNull();
});

test('SafeData money and quantity rules include upper bounds', function () {
    expect(SafeData::money())->toContain('max:'.SafeData::MONEY_MAX)
        ->and(SafeData::quantity())->toContain('max:'.SafeData::QTY_MAX);
});
