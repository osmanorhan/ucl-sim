<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\League\LeagueState;
use App\Domain\League\LeagueTable;
use App\Domain\League\ScheduledMatch;
use App\Domain\League\Standing;
use App\Domain\Prediction\ChampionPredictor;
use App\Domain\Random\SeededRandomSource;
use App\Domain\Ranking\Ranking;

/**
 * Projects a {@see LeagueState} into the one versioned read model the API serves and the SPA
 * applies atomically (ADR-03). Predictions are recomputed here, on write, with the live predictor —
 * so reads stay O(1) regardless of how heavy that predictor is (ADR-01). This assembler is the
 * single authority on response shape; there is no Resource layer re-wrapping the stored array.
 */
final readonly class SnapshotAssembler
{
    private const PREDICTION_SEED_OFFSET = 7_001;

    public function __construct(
        private LeagueTable $table,
        private Ranking $ranking,
        private ChampionPredictor $live,
        private PredictionAvailability $availability,
        private string $liveKey,
    ) {}

    /** @return array<string, mixed> */
    public function assemble(LeagueState $state): array
    {
        return [
            'version' => $state->version,
            'league' => [
                'id' => $state->id,
                'name' => $state->name,
                'seed' => $state->seed,
                'currentWeek' => $state->completedWeeks(),
                'totalWeeks' => $state->totalWeeks(),
            ],
            'table' => $this->table($state),
            'fixtures' => $this->fixtures($state),
            'predictionAvailability' => $this->availability->for($state),
            'predictions' => $this->predictions($state),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function table(LeagueState $state): array
    {
        $played = $state->played();
        $ranked = $this->ranking->rank($this->table->project($state->teams, $played), $played);

        return array_map(
            static fn (Standing $standing, int $index): array => [
                'position' => $index + 1,
                'teamId' => $standing->team->id,
                'name' => $standing->team->name,
                'played' => $standing->played,
                'won' => $standing->won,
                'drawn' => $standing->drawn,
                'lost' => $standing->lost,
                'goalsFor' => $standing->goalsFor,
                'goalsAgainst' => $standing->goalsAgainst,
                'goalDifference' => $standing->goalDifference(),
                'points' => $standing->points,
            ],
            $ranked,
            array_keys($ranked),
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function fixtures(LeagueState $state): array
    {
        $byWeek = [];
        foreach ($state->matches as $match) {
            $byWeek[$match->fixture->week][] = $this->match($match);
        }
        ksort($byWeek);

        return array_map(
            static fn (int $week, array $matches): array => ['week' => $week, 'matches' => $matches],
            array_keys($byWeek),
            $byWeek,
        );
    }

    /** @return array<string, mixed> */
    private function match(ScheduledMatch $match): array
    {
        return [
            'id' => $match->id,
            'homeTeamId' => $match->fixture->homeTeamId,
            'awayTeamId' => $match->fixture->awayTeamId,
            'homeGoals' => $match->result?->homeGoals,
            'awayGoals' => $match->result?->awayGoals,
            'played' => $match->isPlayed(),
            'origin' => $match->origin?->value,
        ];
    }

    /** @return array<string, mixed>|null */
    private function predictions(LeagueState $state): ?array
    {
        if (! $this->availability->availableFor($state)) {
            return null;
        }

        $odds = $this->live->predict(
            $state->teams,
            $state->played(),
            $state->remaining(),
            new SeededRandomSource($state->seed + self::PREDICTION_SEED_OFFSET),
        )->entries();

        usort($odds, static fn (array $a, array $b): int => $b['probability'] <=> $a['probability']);

        return [
            'predictor' => $this->liveKey,
            'odds' => $odds,
        ];
    }
}
