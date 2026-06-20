<?php

use App\Http\Requests\SubmitMatchRequest;
use App\Models\Tournament;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['main_mode' => 'osu']);
    $this->actingAs($this->user);
});

test('getMatchId extracts ID from HTTPS URL', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/123456789',
    ]);

    expect($request->getMatchId())->toBe(123456789);
});

test('getMatchId extracts ID from HTTP URL', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'http://osu.ppy.sh/community/matches/987654321',
    ]);

    expect($request->getMatchId())->toBe(987654321);
});

test('getMatchId handles large match IDs', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/999999999999',
    ]);

    expect($request->getMatchId())->toBe(999999999999);
});

test('validation accepts valid HTTPS URL', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/123456789',
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('validation accepts valid HTTP URL', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'http://osu.ppy.sh/community/matches/123456789',
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('validation rejects invalid domain', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://example.com/community/matches/123456789',
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('mp_link'))->toBeTrue();
});

test('validation rejects invalid path', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/invalid/path/123456789',
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('mp_link'))->toBeTrue();
});

test('validation rejects URL without match ID', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/',
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('mp_link'))->toBeTrue();
});

test('validation rejects URL with non-numeric match ID', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/abc123',
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('mp_link'))->toBeTrue();
});

test('validation rejects missing mp_link', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', []);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('mp_link'))->toBeTrue();
});

// Note: Duplicate match checking moved to controller for proper 409 response (OpenAPI compliance)
// This is now tested in MatchSubmissionTest feature tests

test('validation accepts tournament_id when valid', function () {
    $tournament = Tournament::factory()->create();

    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/123456789',
        'tournament_id' => $tournament->id,
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('validation rejects invalid tournament_id', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/123456789',
        'tournament_id' => 99999,
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tournament_id'))->toBeTrue();
});

test('validation allows null tournament_id', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', [
        'mp_link' => 'https://osu.ppy.sh/community/matches/123456789',
        'tournament_id' => null,
    ]);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules());

    expect($validator->passes())->toBeTrue();
});

test('custom error messages are returned', function () {
    $request = SubmitMatchRequest::create('/matches', 'POST', []);

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    $validator = validator($request->all(), $request->rules(), $request->messages());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('mp_link'))->toBe('Please provide a multiplayer link.');
});
