<?php

declare(strict_types=1);

namespace Hive\DependencyInjection;

enum Kind
{
    case ClassName;
    case Factory;
    case Value;
    case Alias;
}
