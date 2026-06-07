<?php

declare(strict_types=1);

namespace Hive\DependencyInjection;

enum Lifetime
{
    /** returns the same instance each time */
    case Singleton;

    /** returns a new instance each time */
    case Transient;
}
