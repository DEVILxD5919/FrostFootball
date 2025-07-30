<?php

declare(strict_types=1);

namespace devil\football\util;

interface Configuration {
    // Energy thresholds
    public const MIN_VERTICAL_ENERGY    = 0.09;
    public const MIN_HORIZONTAL_ENERGY  = 0.125;

    // Speed handling
    public const DEFAULT_SPEED_LOSS     = 0.025;
    public const IN_AIR_SPEED_INCREASE  = 0.02;

    // Impact adjustments
    public const IMPACT_ENERGY_LOSS     = 0.1025;
    public const IMPACT_MOTION_REDUCE   = 0.9;
}
