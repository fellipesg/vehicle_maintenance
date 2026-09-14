<?php

namespace App\Enums;

enum WarrantyScope: string
{
    case Order = 'order';
    case Item = 'item';
}
