<?php

namespace App;

enum ContentEvidenceTarget: string
{
    case Revision = 'revision';
    case Field = 'field';
    case Block = 'block';
    case Asset = 'asset';
    case Relationship = 'relationship';
}
