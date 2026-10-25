<?php

return [
    'password_enabled' => (bool) env('STUDENT_PASSWORD_SETUP_ENABLED', false),
    'enabled' => (bool) env('STUDENT_SETUP_ENABLED', false),
    'delivery_verified' => (bool) env('STUDENT_SETUP_DELIVERY_VERIFIED', false),
];
