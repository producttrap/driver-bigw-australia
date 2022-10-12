<?php

declare(strict_types=1);

namespace ProductTrap\BigWAustralia;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use ProductTrap\Contracts\Driver;
use ProductTrap\DTOs\Brand;
use ProductTrap\DTOs\Price;
use ProductTrap\DTOs\Product;
use ProductTrap\DTOs\Results;
use ProductTrap\Enums\Currency;
use ProductTrap\Enums\Status;
use ProductTrap\Exceptions\ApiConnectionFailedException;
use ProductTrap\Exceptions\ProductTrapDriverException;
use ProductTrap\Traits\DriverCache;
use ProductTrap\Traits\DriverCrawler;

class BigWAustralia implements Driver
{
    use DriverCache;
    use DriverCrawler;

    public const IDENTIFIER = 'bigw_australia';

    public const BASE_URI = 'https://www.bigw.com.au';

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
    }

    public function getName(): string
    {
        return 'BigW Australia';
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ProductTrapDriverException
     */
    public function find(string $identifier, array $parameters = []): Product
    {
        $url = $this->url($identifier);
        $html = $this->remember($identifier, now()->addDay(), fn () => $this->scrape($url));
        $crawler = $this->crawl($html);

        // Extract product JSON as possible source of information
        preg_match('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.+)<\/script>/', $crawler->html(), $matches);
        $script = Str::of($matches[1] ?? '')->before('</script>')->trim()->toString();

        $json = json_decode($script, true);
        file_put_contents(base_path('test_bigw.json'), trim($matches[1]));

        if (empty($json)) {
            throw new ApiConnectionFailedException(
                driver: $this,
                resourceOrUrl: $url,
            );
        }

        $product = $json['props']['pageProps']['serializedData']['products'][(string) $identifier] ?? null;

        // Title
        $title = $product['name'];

        // Description
        $description = $product['information']['description'] ?? null;

        //SKU
        $sku = $product['code'];

        // Gtin
        $gtin = $product['information']['ean'] ?? null;

        // Brand
        $brand = null;
        try {
            $brand = new Brand(
                identifier: $brandName = $product['information']['brand'] ?? null,
                name: $brandName,
            );
        } catch (\Exception $e) {
            //
        }

        // Currency
        $currency = Currency::AUD;

        // Price
        $price = null;
        try {
            $price = Str::of(
                $crawler->filter('.dollars[itemprop="price"]')->first()->text()
            )->replace(['$', ',', ' '], '')->toFloat();
        } catch (\Exception $e) {
        }
        $price = ($price !== null)
            ? new Price(
                amount: $price,
                currency: $currency,
            )
            : null;

        // Images
        $images = [];
        foreach ($product['assets']['images'] as $image) {
            $images[] = static::BASE_URI . $image['sources'][0]['url'];
        }

        // Status
        $status = (str_contains(strtolower($product['listingStatus']), 'sellable')) ? Status::Available : Status::Unavailable;

        // URL
        $url = null;
        try {
            $url = $crawler->filter('link[rel="canonical"]')->first()->attr('href');
        } catch (\Exception $e) {
        }

        return new Product(
            identifier: $identifier,
            sku: $sku,
            name: $title,
            description: $description,
            url: $url,
            price: $price,
            status: $status,
            brand: $brand,
            gtin: $gtin,
            images: $images,
            raw: [
                'html' => $html,
            ],
        );
    }

    public function url(string $identifier): string
    {
        return self::BASE_URI.'/product/product/p/'.$identifier;
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ProductTrapDriverException
     */
    public function search(string $keywords, array $parameters = []): Results
    {
        return new Results();
    }
}
