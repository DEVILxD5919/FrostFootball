<?php

declare(strict_types=1);

namespace devil\football\util;

interface Configuration {
    public const MIN_VERTICAL_ENERGY = 0.09;
    public const MIN_HORIZONTAL_ENERGY = 0.125;

    public const DEFAULT_SPEED_LOSS = 0.025;

    public const IN_AIR_SPEED_INCREASE = 0.02;

    public const IMPACT_ENERGY_LOSS = 0.1025;
    public const IMPACT_MOTION_LOSS = 0.9;
}