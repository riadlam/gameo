<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mutual match copy (FCM, Firestore notifications, chat thread preview)
    |--------------------------------------------------------------------------
    |
    | Two values only. Optional placeholder :game in description is replaced
    | when the match has a game name; if there is no game, :game is removed.
    |
    */

    'title' => env('MATCH_TITLE', 'Found a new teammate'),

    'description' => env('MATCH_DESCRIPTION', 'Gameo found you a new teammate.'),

];
