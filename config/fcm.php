<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase / FCM HTTP v1
    |--------------------------------------------------------------------------
    |
    | Download a service account JSON from Firebase Console → Project settings
    | → Service accounts → Generate new private key. Store outside public web root
    | and set the absolute path here (shared hosting: e.g. /home/you/credentials/fcm.json).
    |
    | FCM_PROJECT_ID defaults to the "project_id" inside that JSON if omitted.
    |
    */

    'project_id' => env('FCM_PROJECT_ID'),

    'credentials_path' => env('FCM_GOOGLE_APPLICATION_CREDENTIALS'),

];
