<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'league.prediction_iterations' => 200,
        'league.evaluation.scenarios' => 3,
        'league.evaluation.ground_truth_draws' => 60,
    ]);
});

/** @return array<string, mixed> */
function leaguePayload(int $seed = 42): array
{
    return [
        'name' => 'Champions League',
        'seed' => $seed,
        'teams' => [
            ['id' => 'a', 'name' => 'Alpha', 'power' => 90.0],
            ['id' => 'b', 'name' => 'Bravo', 'power' => 65.0],
            ['id' => 'c', 'name' => 'Cosmos', 'power' => 45.0],
            ['id' => 'd', 'name' => 'Delta', 'power' => 30.0],
        ],
    ];
}

function createLeague(int $seed = 42): string
{
    return test()->postJson('/api/leagues', leaguePayload($seed))->json('league.id');
}

/**
 * @param  array<int, array<string, mixed>>  $fixtures
 * @return array<string, array{int, int}> "week-home-away" => [homeGoals, awayGoals]
 */
function goalsByMatch(array $fixtures): array
{
    $goals = [];
    foreach ($fixtures as $week) {
        foreach ($week['matches'] as $match) {
            if ($match['played']) {
                $goals["{$match['homeTeamId']}-{$match['awayTeamId']}"] = [$match['homeGoals'], $match['awayGoals']];
            }
        }
    }

    return $goals;
}

it('creates a league and returns a fresh versioned snapshot', function () {
    $response = $this->postJson('/api/leagues', leaguePayload());

    $response->assertCreated()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('league.currentWeek', 0)
        ->assertJsonPath('league.totalWeeks', 6)
        ->assertJsonPath('predictions', null);

    expect($response->json('table'))->toHaveCount(4)
        ->and($response->json('table.0.points'))->toBe(0)
        ->and($response->json('fixtures'))->toHaveCount(6)
        ->and($response->json('fixtures.0.matches'))->toHaveCount(2);
});

it('plays a week and advances the snapshot', function () {
    $id = createLeague();

    $response = $this->postJson("/api/leagues/{$id}/play-week");

    $response->assertOk()
        ->assertJsonPath('version', 2)
        ->assertJsonPath('league.currentWeek', 1);

    expect(goalsByMatch($response->json('fixtures')))->toHaveCount(2)
        ->and($response->json('fixtures.0.matches.0.origin'))->toBe('simulated')
        ->and($response->json('fixtures.5.matches.0.origin'))->toBeNull();
});

it('plays the whole season in one call, equal to playing every week by hand', function () {
    $allAtOnce = $this->postJson('/api/leagues/'.createLeague(7).'/play-all')->json();

    $byHand = createLeague(7);
    $stepwise = null;
    for ($week = 0; $week < 6; $week++) {
        $stepwise = $this->postJson("/api/leagues/{$byHand}/play-week")->json();
    }

    expect($allAtOnce['league']['currentWeek'])->toBe(6)
        ->and(goalsByMatch($allAtOnce['fixtures']))->toHaveCount(12)
        ->and(goalsByMatch($stepwise['fixtures']))->toEqual(goalsByMatch($allAtOnce['fixtures']));
});

it('accepts a negative seed and remains reproducible', function () {
    $first = $this->postJson('/api/leagues/'.createLeague(-7).'/play-all')->json();
    $second = $this->postJson('/api/leagues/'.createLeague(-7).'/play-all')->json();

    expect(goalsByMatch($first['fixtures']))->toEqual(goalsByMatch($second['fixtures']));
});

it('re-folds the table and bumps the version when a result is edited', function () {
    $id = createLeague();
    $snapshot = $this->postJson("/api/leagues/{$id}/play-all")->json();

    $match = $snapshot['fixtures'][0]['matches'][0];
    $edited = $this->putJson("/api/matches/{$match['id']}", ['homeGoals' => 9, 'awayGoals' => 9]);

    $edited->assertOk()->assertJsonPath('version', $snapshot['version'] + 1);

    $editedMatch = collect($edited->json('fixtures.0.matches'))->firstWhere('id', $match['id']);
    expect($editedMatch['homeGoals'])->toBe(9)
        ->and($editedMatch['origin'])->toBe('manual')
        ->and($edited->json('table'))->not->toEqual($snapshot['table']);
});

it('keeps predictions hidden until four weeks are complete', function () {
    $id = createLeague();

    $this->getJson("/api/leagues/{$id}/predictions")->assertStatus(409);

    $this->postJson("/api/leagues/{$id}/play-all");

    $response = $this->getJson("/api/leagues/{$id}/predictions")->assertOk();
    $odds = $response->json('predictions.odds');

    expect(array_sum(array_column($odds, 'probability')))->toEqualWithDelta(1.0, 1e-9)
        ->and($response->json('predictions.predictor'))->toBe('settled-or-simulated');
});

it('ranks the strategy field through the evaluation harness', function () {
    $id = createLeague();
    $this->postJson("/api/leagues/{$id}/play-week");

    $cards = $this->getJson("/api/leagues/{$id}/evaluation")->assertOk()->json('scorecards');
    $strategies = array_column($cards, 'strategy');
    $briers = array_column($cards, 'brier');

    expect($strategies)->toContain('monte-carlo', 'clincher', 'points-heuristic')
        ->and($briers)->toEqual(collect($briers)->sort()->values()->all());
});

it('returns 404 for an unknown league and 409 once the season is complete', function () {
    $this->getJson('/api/leagues/missing')->assertStatus(404);

    $id = createLeague();
    $this->postJson("/api/leagues/{$id}/play-all");
    $this->postJson("/api/leagues/{$id}/play-all")->assertStatus(409);
});

it('rejects a league with an odd number of teams', function () {
    $payload = leaguePayload();
    array_pop($payload['teams']);

    $this->postJson('/api/leagues', $payload)->assertStatus(422)->assertJsonValidationErrors('teams');
});
