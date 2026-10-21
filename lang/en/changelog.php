<?php

return [
    'title' => "What's new",
    'help' => 'Changes in each version of :name. You are using :version.',
    'current' => 'You are here',
    'versions' => [
        '1_7_1' => [
            'title' => 'Every screen size: phones, tablets and computers',
            'items' => [
                'Pages no longer get wider than a phone screen, so the side menu stays hidden and nothing is cut off at the edge. All 84 pages were checked at seven screen sizes, from a 320px phone to a wide monitor, in Persian, English, Arabic and Chinese.',
                'The content studio fits phones: its buttons wrap instead of pushing the page sideways.',
                'Profile pictures keep their shape everywhere instead of shrinking to a thin line.',
                'Faster: fonts are served from this site instead of Google, Today shows first and loads recent activity right after, content pages need far fewer database checks, and side panels in the studio load when you open them.',
                'Inputs on phones use a size that stops iPhones from zooming in, and small buttons and checkboxes are easier to tap.',
                'Fixes: the "add field" buttons in the block editor work again, and a few labels that showed technical names are translated.',
            ],
        ],
        '1_7_0' => [
            'title' => 'Planner: never forget due dates, device notifications, faster everywhere',
            'items' => [
                'New plan type "Due date" for installments, cheques and bills, and plans that repeat every month on the same day of your calendar (Persian calendar included).',
                'Mark a plan Important: it is reminded up to a week before, also by email, and stays under "Don\'t forget" on Today until you mark it done.',
                'Notifications on your phone or computer, even when the site is closed: turn them on in the plan wizard or on the notifications page.',
                'The plan wizard shows the reason if something cannot be saved and takes you to the step to fix; every field has a short explanation.',
                'The time picker fits phones: move earlier or later with the arrows, zoom to 24, 12 or 4 hours, and tap to move the time.',
                'Pages and buttons respond faster: fewer database checks per page, dialogs close at once, and long content lists load in parts.',
            ],
        ],
        '1_6_0' => [
            'title' => 'Planner: a step-by-step plan wizard, a timeline time picker and meetings',
            'items' => [
                'New plans are made in short steps: what, when, who (for meetings), details and a final check.',
                'Pick the time on a timeline: today, tomorrow, this or next week, this or next month and its weeks, then a day — and drag from start to end. Exact date and time typing is still one tap away.',
                'Choose several days at once, or repeat every day or every week on the chosen weekdays.',
                'Plan types (event, task, routine, meeting, focus time, reminder) with their own colours, plus your own labels and colours.',
                'Meetings: offer several times to people with an account or to anyone by a private link; they answer yes/maybe/no or suggest another time. Once agreed, it is added to everyone\'s planner, and guests can add it to their calendar. A guest who later has an account keeps it.',
                'Repeating plans appear once in the planner list with their next times, and a plan\'s page shows the next few occurrences in full and the rest as a short list.',
            ],
        ],
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
