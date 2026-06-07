<?php
declare(strict_types=1);

namespace Quenza\Core\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
