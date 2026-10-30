<?php

namespace App\Services\Robots;

use App\Exceptions\ResourceNotFoundException;
use App\Services\Robots\Contracts\RobotContract;

/**
 * Resolves a robot implementation by slug (Strategy + Factory), same idea as GatewayManager.
 * To add a robot: implement RobotContract, register it here and add a row in `robots`.
 */
class RobotManager
{
    /** @var array<string, class-string<RobotContract>> */
    protected array $robots = [
        'delta-screener' => DeltaScreenerRobot::class,
    ];

    public function make(string $slug): RobotContract
    {
        if (! isset($this->robots[$slug])) {
            throw new ResourceNotFoundException('Robô não encontrado.');
        }

        return app($this->robots[$slug]);
    }

    public function supports(string $slug): bool
    {
        return isset($this->robots[$slug]);
    }
}
