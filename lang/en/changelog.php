<?php

return [
    'title' => "What's new",
    'help' => 'Changes in each version of :name. You are using :version.',
    'current' => 'You are here',
    'versions' => [
        '1_5_0' => [
            'title' => 'Content: delete, rename and question sections',
            'items' => [
                'Delete content permanently, after a warning where you type its title. If others have already left notes, answers or responses on it, it can only be archived, to protect their work.',
                'Rename content in one step from the content list or the editor; published content shows the new name right away.',
                'Give any section a type — question, exercise, discussion, definition, key point, example or summary — and tags. Readers get the matching options first, such as "Answer" on a question.',
                'Readers answer question and exercise sections directly; the author (or roles they allow) can mark answers correct.',
                'Reading menus and note boxes stay on screen, open next to the text and become bottom sheets on phones. Dialogs keep their header visible while scrolling.',
                'The version now shows next to the site name, with this page listing what changed.',
            ],
        ],
        '1_4_0' => [
            'title' => 'Bookkeeping replaces the old financial system',
            'items' => [
                'A private personal book: wallets, categories, spending, income and moves, month totals in your calendar, receipts and undo.',
                'Shared and project books where every debt or payment waits for the other side to confirm, with proof files, disputes, corrections and a full history.',
                'Videos start faster on slow or VPN connections.',
            ],
        ],
        '1_3_0' => [
            'title' => 'Sharing outside the system',
            'items' => [
                'Make published content public, or send personal invitation links that let one person read it.',
                'Links can expire, be turned off at any time, and show how often they were opened.',
            ],
        ],
        '1_2_0' => [
            'title' => 'Everyday usability and lesson video',
            'items' => [
                'Planner evidence opens as a post with images, video, audio or files.',
                'Large lesson videos upload in pieces and continue after a refresh; captions can be added.',
                'Today stays short however busy the day is.',
            ],
        ],
    ],
];
