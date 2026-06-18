<?php

declare(strict_types=1);

use App\Application\SnapshotAssembler;
use App\Domain\League\LeagueState;
use App\Domain\League\MatchResult;
use App\Domain\League\ResultOrigin;
use App\Domain\League\ScheduledMatch;
use App\Domain\Persistence\LeagueRepository;
use App\Domain\Persistence\StaleLeagueState;
use App\Models\MatchRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

const LEAGUES_URL = '/api/leagues';

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
    return test()->postJson(LEAGUES_URL, leaguePayload($seed))->json('league.id');
}

function playFirstFixture(LeagueState $state, int $homeGoals, int $awayGoals): LeagueState
{
    $targetId = $state->matches[0]->id;

    return $state->withMatches(array_map(
        static fn (ScheduledMatch $match): ScheduledMatch => $match->id === $targetId
            ? $match->withResult(
                new MatchResult($match->fixture->homeTeamId, $match->fixture->awayTeamId, $homeGoals, $awayGoals),
                ResultOrigin::Simulated,
            )
            : $match,
        $state->matches,
    ));
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
    $response = $this->postJson(LEAGUES_URL, leaguePayload());

    $response->assertCreated()
        ->assertJsonPath('version', 1)
        ->assertJsonPath('league.currentWeek', 0)
        ->assertJsonPath('league.totalWeeks', 6)
        ->assertJsonPath('predictionAvailability.available', false)
        ->assertJsonPath('predictionAvailability.availableAfterCompletedWeeks', 4)
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

it('rejects stale aggregate writes instead of overwriting a newer version', function () {
    $id = createLeague();

    $repository = app(LeagueRepository::class);
    $assembler = app(SnapshotAssembler::class);

    $firstWriter = $repository->find($id);
    $staleWriter = $repository->find($id);

    $firstWrite = playFirstFixture($firstWriter, 1, 0);
    $repository->save($firstWrite, $assembler->assemble($firstWrite));

    $staleWrite = playFirstFixture($staleWriter, 0, 1);

    expect(fn () => $repository->save($staleWrite, $assembler->assemble($staleWrite)))
        ->toThrow(StaleLeagueState::class);

    $stored = $repository->find($id);
    expect($stored->version)->toBe(2)
        ->and($stored->matches[0]->result->homeGoals)->toBe(1)
        ->and($stored->matches[0]->result->awayGoals)->toBe(0);
});

it('plays the whole season in one call, equal to playing every week by hand', function () {
    $allAtOnce = $this->postJson(LEAGUES_URL.'/'.createLeague(7).'/play-all')->json();

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
    $first = $this->postJson(LEAGUES_URL.'/'.createLeague(-7).'/play-all')->json();
    $second = $this->postJson(LEAGUES_URL.'/'.createLeague(-7).'/play-all')->json();

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

it('rejects editing a match before it has been played', function () {
    $id = createLeague();
    $snapshot = $this->getJson("/api/leagues/{$id}")->json();
    $future = $snapshot['fixtures'][5]['matches'][0];

    $this->putJson("/api/matches/{$future['id']}", ['homeGoals' => 1, 'awayGoals' => 1])
        ->assertStatus(409);
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

it('rate limits the evaluation harness separately from cheap reads', function () {
    config(['league.rate_limits.evaluation_per_minute' => 1]);

    $id = createLeague();
    $this->postJson("/api/leagues/{$id}/play-week");

    $this->getJson("/api/leagues/{$id}")->assertOk();
    $this->getJson("/api/leagues/{$id}/evaluation")->assertOk();
    $this->getJson("/api/leagues/{$id}/evaluation")->assertTooManyRequests();
});

it('rate limits mutating endpoints', function () {
    $id = createLeague();

    config(['league.rate_limits.mutations_per_minute' => 1]);

    $this->postJson("/api/leagues/{$id}/play-week")->assertOk();
    $this->postJson("/api/leagues/{$id}/play-week")->assertTooManyRequests();
});

it('returns 404 for an unknown league and 409 once the season is complete', function () {
    $this->getJson('/api/leagues/missing')->assertStatus(404);

    $id = createLeague();
    $this->postJson("/api/leagues/{$id}/play-all");
    $this->postJson("/api/leagues/{$id}/play-all")->assertStatus(409);
});

it('rejects a league that is not exactly four teams', function () {
    $tooFew = leaguePayload();
    array_pop($tooFew['teams']);
    $this->postJson(LEAGUES_URL, $tooFew)->assertStatus(422)->assertJsonValidationErrors('teams');

    $tooMany = leaguePayload();
    $tooMany['teams'][] = ['id' => 'e', 'name' => 'Echo', 'power' => 50.0];
    $tooMany['teams'][] = ['id' => 'f', 'name' => 'Foxtrot', 'power' => 40.0];
    $this->postJson(LEAGUES_URL, $tooMany)->assertStatus(422)->assertJsonValidationErrors('teams');
});

it('refuses to persist a match that references a team outside its league', function () {
    $id = createLeague();

    $danglingReference = fn () => MatchRecord::create([
        'league_id' => $id,
        'sequence' => 999,
        'week' => 1,
        'home_team_id' => 'ghost',
        'away_team_id' => 'a',
    ]);

    expect($danglingReference)->toThrow(QueryException::class);
});

it('renders unexpected failures as a safe message without leaking a stack trace, even in debug', function () {
    config(['app.debug' => true]);

    Route::get('/api/_boom', fn () => throw new RuntimeException('Database connection refused at 10.0.0.1.'));

    $response = $this->getJson('/api/_boom')->assertStatus(500);

    $response->assertExactJson(['message' => 'An unexpected error occurred. Please try again later.']);
    expect(array_keys($response->json()))->not->toContain('exception', 'file', 'line', 'trace');
});

it('renders handled HTTP errors without a stack trace in debug', function () {
    config(['app.debug' => true]);

    $id = createLeague();

    $response = $this->getJson("/api/leagues/{$id}/predictions")->assertStatus(409);

    expect(array_keys($response->json()))->not->toContain('exception', 'file', 'line', 'trace');
});

it('logs an unexpected failure once, with request context', function () {
    Log::spy();

    Route::get('/api/_boom', fn () => throw new RuntimeException('boom'));

    $this->getJson('/api/_boom')->assertStatus(500);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => $message === 'boom'
            && $context['method'] === 'GET'
            && $context['path'] === 'api/_boom'
            && $context['exception'] instanceof RuntimeException);
});

it('does not log expected domain exceptions as errors', function () {
    Log::spy();

    $this->getJson('/api/leagues/missing')->assertStatus(404);

    Log::shouldNotHaveReceived('error');
});
