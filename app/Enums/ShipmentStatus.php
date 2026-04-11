<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case LabelCreated = 'label_created';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
}
