<?php

/*
 * Only the settings Dopay changes from Sanctum's defaults (the rest come from the package).
 * API tokens expire after 90 days; issue a new one when an integration's token runs out.
 */
return [
    'expiration' => env('SANCTUM_TOKEN_MINUTES', 60 * 24 * 90),
];
