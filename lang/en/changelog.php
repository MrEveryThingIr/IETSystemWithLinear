<?php

return [
    'title' => 'What\'s new',
    'help' => 'Changes in each version of :name. You are using :version.',
    'current' => 'You are here',
    'versions' => [
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
        '2_0_0' => [
            'title' => 'IET 2.0: messages, organisations in the market, and coherent work',
            'items' => [
                'Plans that know what time it is: nothing can be done before its time, and a forgotten plan never waits forever — it asks “How did it go?” for a week (a missing answer is never counted as “not done”). Tell what happened in one tap, or with the real time, a reason and how hard it was. Repeating plans show expected against actual for every day, with the trend towards your ideal.',
                'Habits, simply: give a plan its cue, reward and why; change one thing at a time in periods of 7 to 66 days and compare them; or replace an old habit (for example smoking) with a short new routine and tap each time the urge comes. All of it stays private to you.',
                'A clock for everyone: the home page opens with a full-screen analog clock and the time and date in your calendar under it (hover or tap for the Gregorian date and time zone), and a slim bar on top of every page — signed in or not — shows a small clock and the moving text of the day, which the superadmin writes (quotes, news or notices) under Administration → Text of the day.',
                'Messages: private conversations and every group, business, deal and work chat in one inbox — replies, reactions, photos, video, files, voice messages, location and contact cards. Contacts, blocking, reporting, and a setting for who may message you. Messages are protected by HTTPS and access control; they are not end-to-end encrypted.',
                'Needs & Offers can now be published by you, a business or a group you manage. Members-only visibility for organisations, photos and short videos, a price per unit (hour, day, square metre, item, trip…), negotiable or fixed, and “free”.',
                'One category tree for everything: 16 areas of life with about 180 subcategories in Persian, English, Arabic and Chinese. Add your own categories, suggest them for everyone, and filter Needs & Offers by area.',
                'Matches explain why they fit, including categories of the same family; your own items are never shown as matches.',
                '“What I can do” replaces “My professions”: skills with level, years and how sure you are. Earlier professions were copied over; nothing was deleted.',
                'Work: choose how to schedule agreed work — a new shared plan, a saved template, a plan already in the deal, or none. Save any schedule as a template (it keeps only the shape, never people or dates). The Work page shows work in progress.',
                'Bookkeeping shows where every entry came from — typed by hand, from IET work, imported, or a correction — with filters. Accepted work can be proposed as a debt in a book you share; it counts only after the other side confirms. Personal books stay yours alone.',
                'Business pages were rebuilt to match the rest of IET, with dark mode: team skills, the business\'s needs & offers and a team chat. Groups list their needs & offers.',
                'Every screen size: all pages checked from a 320px phone to a wide monitor in four languages; faster pages and fonts served from this site.',
                'Fixes: automatic plans for service agreements are created again; archiving a client no longer needs the market; the contact centre and the real-estate workspace are translated.',
            ],
        ],
    ],
];
