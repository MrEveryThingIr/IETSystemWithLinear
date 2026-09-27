<?php

return [
    'title' => 'System Map',
    'description' => 'Explore IET as one connected system: human purpose, domain truth, workflows, code and improvement questions.',
    'canvas_label' => 'Interactive IET system map',
    'canvas_help' => 'Scroll in both directions or drag empty space to pan. Ctrl/Alt + wheel zooms. Select a node to focus its neighborhood.',
    'actions' => [
        'manual' => 'System manual',
    ],
    'callout' => [
        'title' => 'A review instrument, not a decorative diagram',
        'body' => 'Use the map to understand a module in context before changing it. Implemented nodes describe current architecture; dashed nodes are explicit long-term directions.',
    ],
    'lenses' => [
        'north_star' => 'North-star lifecycle',
        'finance' => 'Financial flow',
        'content' => 'Content & knowledge',
        'community' => 'Community & governance',
        'execution' => 'Execution',
    ],
    'controls' => [
        'search' => 'Search',
        'search_placeholder' => 'Accounting, planner, evidence…',
        'module' => 'Area',
        'all_modules' => 'All areas',
        'status' => 'Status',
        'all_statuses' => 'Current + direction',
        'zoom_in' => 'Zoom in',
        'zoom_out' => 'Zoom out',
        'fit' => 'Fit map',
        'visible_nodes' => 'visible nodes',
        'clear_focus' => 'Clear focus',
    ],
    'status' => [
        'implemented' => 'Implemented',
        'direction' => 'Long-term direction',
    ],
    'inspector' => [
        'explore' => 'Explore the system',
        'explore_help' => 'Select any node to see why it exists, what truth it owns, how it connects, and what questions are worth reviewing next.',
        'starting_point' => 'Useful starting point',
        'starting_point_help' => 'Choose “Financial flow” to review Planner costs → Fulfillment → Obligation → Settlement → Accounting as one human story.',
        'open_area' => 'Open this area ↗',
        'human_purpose' => 'Human purpose',
        'truth' => 'Authoritative truth',
        'review_questions' => 'Review questions',
        'documentation' => 'Documentation',
        'code_anchors' => 'Code anchors',
        'connections' => 'Connections',
    ],
];
