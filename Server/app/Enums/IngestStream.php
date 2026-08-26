<?php

namespace App\Enums;

enum IngestStream: string
{
    case TagReadings = 'tag_readings';
    case PpeViolations = 'ppe_violations';
    case RoiViolations = 'roi_violations';
    case HeadcountReadings = 'headcount_readings';
    case GasReadings = 'gas_readings';
    case EnvironmentalReadings = 'environmental_readings';
}
