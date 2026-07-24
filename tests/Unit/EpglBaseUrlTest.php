<?php

use App\Support\EpglBaseUrl;

test('epgl base url normalizes full auth endpoint to host', function () {
    expect(EpglBaseUrl::normalize('https://api-stg.epgl.ae/api/v1/auth/authenticate'))
        ->toBe('https://api-stg.epgl.ae');
});

test('epgl base url keeps host-only values', function () {
    expect(EpglBaseUrl::normalize('https://api-stg.epgl.ae/'))
        ->toBe('https://api-stg.epgl.ae');
});

test('epgl base url strips any api path', function () {
    expect(EpglBaseUrl::normalize('https://api-stg.epgl.ae/api/v1/shipment/create'))
        ->toBe('https://api-stg.epgl.ae');
});
