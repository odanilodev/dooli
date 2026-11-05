<?php

namespace App\Services\Robots\Contracts;

interface RobotContract
{
    /** Validation rules for the parameters the student submits. */
    public function rules(): array;

    /**
     * Data the form needs to render (available markets, assets, data range...).
     *
     * @return array<string, mixed>
     */
    public function options(): array;

    /**
     * Executes the robot with already-validated parameters.
     *
     * @return array<string, mixed> serializable result (rows + summary)
     */
    public function run(array $params): array;

    /**
     * Drill-down for one row of the result (e.g. the individual trades of an asset).
     *
     * @return array<string, mixed>
     */
    public function detail(array $params, string $symbol): array;
}
