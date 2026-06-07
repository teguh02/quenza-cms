<?php
declare(strict_types=1);

namespace Quenza\Core\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Trash = 'trash';
}
