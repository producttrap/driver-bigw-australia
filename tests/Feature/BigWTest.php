<?php

declare(strict_types=1);

use ProductTrap\BigWAustralia\BigWAustralia;
use ProductTrap\Contracts\Factory;
use ProductTrap\DTOs\Product;
use ProductTrap\Enums\Status;
use ProductTrap\Exceptions\ApiConnectionFailedException;
use ProductTrap\Facades\ProductTrap as FacadesProductTrap;
use ProductTrap\ProductTrap;
use ProductTrap\Spider;

function getMockBigWAustralia($app, string $response): void
{
    Spider::fake([
        '*' => $response,
    ]);
}

it('can add the BigWAustralia driver to ProductTrap', function () {
    /** @var ProductTrap $client */
    $client = $this->app->make(Factory::class);

    $client->extend('bigw_other', fn () => new BigWAustralia(
        cache: $this->app->make('cache.store'),
    ));

    expect($client)->driver(BigWAustralia::IDENTIFIER)->toBeInstanceOf(BigWAustralia::class)
        ->and($client)->driver('bigw_other')->toBeInstanceOf(BigWAustralia::class);
});

it('can call the ProductTrap facade', function () {
    expect(FacadesProductTrap::driver(BigWAustralia::IDENTIFIER)->getName())->toBe('BigW Australia');
});

it('can retrieve the BigWAustralia driver from ProductTrap', function () {
    expect($this->app->make(Factory::class)->driver(BigWAustralia::IDENTIFIER))->toBeInstanceOf(BigWAustralia::class);
});

it('can call `find` on the BigWAustralia driver and handle failed connection', function () {
    getMockBigWAustralia($this->app, '');

    $this->app->make(Factory::class)->driver(BigWAustralia::IDENTIFIER)->find('7XX1000');
})->throws(ApiConnectionFailedException::class, 'The connection to https://www.bigw.com.au/product/product/p/7XX1000 has failed for the BigW Australia driver');

it('can call `find` on the BigWAustralia driver and handle a successful response', function () {
    $html = file_get_contents(__DIR__.'/../fixtures/successful_response.html');
    getMockBigWAustralia($this->app, $html);

    $data = $this->app->make(Factory::class)->driver(BigWAustralia::IDENTIFIER)->find('891672');
    unset($data->raw);

    expect($this->app->make(Factory::class)->driver(BigWAustralia::IDENTIFIER)->find('891672'))
        ->toBeInstanceOf(Product::class)
        ->identifier->toBe('891672')
        ->status->toEqual(Status::Available)
        ->name->toBe('Pedigree Vital Protection Beef Dry Dog Food 15kg')
        ->description->toBe('PEDIGREE Vital Protection Adult - Beef dry dog food is packed with the nutrients your dog needs to keep them healthy and full of vitality. Vital Protection is designed to protect your dog in four ways: to help support a strong immune system, a healthy skin and coat, good digestion and healthy teeth.')
        ->price->amount->toBe(41.0)
        ->brand->name->toBe('Pedigree')
        ->images->toBe([
            '/medias/sys_master/images/images/h67/h6d/31510149922846.jpg',
            '/medias/sys_master/images/images/h52/he9/31510150447134.jpg',
            '/medias/sys_master/images/images/h5b/h76/31510150971422.jpg',
            '/medias/sys_master/images/images/hfb/h08/31510151561246.jpg',
        ]);
});
