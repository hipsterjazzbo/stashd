<?php

declare(strict_types=1);

namespace App\Timeline;

enum TimelineEntryCategory: string
{
    case Chapter = 'chapter';
    case Sponsor = 'sponsor';
    case SelfPromo = 'selfpromo';
    case Interaction = 'interaction';
    case Intro = 'intro';
    case Outro = 'outro';
    case Preview = 'preview';
    case MusicOfftopic = 'music_offtopic';
    case Filler = 'filler';
    case Hook = 'hook';
    case PoiHighlight = 'poi_highlight';
    case Other = 'other';
}
