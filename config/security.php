<?php

header('X-Content-Type-Options: nosniff');

header('X-Frame-Options: DENY');

header('Referrer-Policy: strict-origin-when-cross-origin');

header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=()'
);