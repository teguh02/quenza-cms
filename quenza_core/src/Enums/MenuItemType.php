<?php
declare(strict_types=1);

namespace Quenza\Core\Enums;

enum MenuItemType: string
{
    case Custom = 'custom';
    case Post = 'post';
    case Page = 'page';
}
