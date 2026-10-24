<?php

namespace App\Enums\Bookkeeping;

/** Where an entry came from. Shown on every entry and used by the book filters. */
enum EntryOrigin: string
{
    /** Typed in by a person. Personal income and expenses are always manual. */
    case Manual = 'manual';

    /** Proposed by an IET workflow (accepted work); counts only after the other side confirms. */
    case IetWorkflow = 'iet_workflow';

    /** Brought in from a file or another system. */
    case Import = 'import';

    /** Corrects an earlier entry. */
    case Correction = 'correction';
}
