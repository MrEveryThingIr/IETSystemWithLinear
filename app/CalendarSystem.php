<?php

namespace App;

enum CalendarSystem: string
{
    case Gregorian = 'gregory';
    case Persian = 'persian';
    case IslamicUmmAlQura = 'islamic-umalqura';
}
