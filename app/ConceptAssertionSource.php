<?php

namespace App;

enum ConceptAssertionSource: string
{
    case Manual = 'manual';
    case System = 'system';
    case Imported = 'imported';
    case AiSuggestion = 'ai_suggestion';
    case External = 'external';
}
