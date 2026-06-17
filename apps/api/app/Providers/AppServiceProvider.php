<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\SnapshotAssembler;
use App\Domain\Evaluation\BrierScore;
use App\Domain\Evaluation\EvaluationHarness;
use App\Domain\Evaluation\LogLoss;
use App\Domain\League\LeagueTable;
use App\Domain\Persistence\LeagueRepository;
use App\Domain\Prediction\ChampionPredictor;
use App\Domain\Prediction\ChampionSamplerFactory;
use App\Domain\Prediction\DeterministicClincher;
use App\Domain\Prediction\MonteCarloPredictor;
use App\Domain\Prediction\PointsHeuristicPredictor;
use App\Domain\Prediction\SettledOrSimulated;
use App\Domain\Ranking\PremierLeagueRanking;
use App\Domain\Ranking\Ranking;
use App\Domain\Scheduling\BergerRoundRobinScheduler;
use App\Domain\Scheduling\FixtureScheduler;
use App\Domain\Simulation\GoalModel;
use App\Domain\Simulation\MatchSimulator;
use App\Domain\Simulation\PoissonGoalModel;
use App\Domain\Simulation\PoissonMatchSimulator;
use App\Infrastructure\Persistence\EloquentLeagueRepository;
use App\Infrastructure\Registry\StrategyRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const LIVE_STRATEGY_KEY = 'settled-or-simulated';

    private const DEFAULT_ITERATIONS = 10_000;

    public function register(): void
    {
        $this->app->bind(GoalModel::class, PoissonGoalModel::class);
        $this->app->bind(MatchSimulator::class, PoissonMatchSimulator::class);
        $this->app->bind(Ranking::class, PremierLeagueRanking::class);
        $this->app->bind(FixtureScheduler::class, BergerRoundRobinScheduler::class);
        $this->app->bind(LeagueRepository::class, EloquentLeagueRepository::class);

        $this->app->singleton(MonteCarloPredictor::class, fn (Application $app): MonteCarloPredictor => new MonteCarloPredictor(
            $app->make(ChampionSamplerFactory::class),
            $this->predictionIterations(),
        ));

        $this->app->bind(ChampionPredictor::class, SettledOrSimulated::class);

        $this->app->singleton(EvaluationHarness::class, fn (Application $app): EvaluationHarness => new EvaluationHarness(
            $app->make(ChampionSamplerFactory::class),
            new BrierScore,
            new LogLoss,
        ));

        $this->app->singleton(StrategyRegistry::class, fn (Application $app): StrategyRegistry => new StrategyRegistry([
            'monte-carlo' => $app->make(MonteCarloPredictor::class),
            'clincher' => $app->make(DeterministicClincher::class),
            'points-heuristic' => $app->make(PointsHeuristicPredictor::class),
        ]));

        $this->app->singleton(SnapshotAssembler::class, fn (Application $app): SnapshotAssembler => new SnapshotAssembler(
            $app->make(LeagueTable::class),
            $app->make(Ranking::class),
            $app->make(SettledOrSimulated::class),
            self::LIVE_STRATEGY_KEY,
        ));
    }

    private function predictionIterations(): int
    {
        $configured = config('league.prediction_iterations');

        return is_int($configured) ? $configured : self::DEFAULT_ITERATIONS;
    }
}
