<?php
declare(strict_types=1);

namespace Quenza\Core\Packages;

enum PackageScope: string
{
    case Core = 'core';
    case Plugin = 'plugin';
    case Theme = 'theme';
}
