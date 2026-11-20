<?php
declare(strict_types=1);

namespace CoCart\Tests\Unit;

use CoCart\Exceptions\CoCartException;
use CoCart\Http\SafeErrorMessage;
use CoCart\Http\StreamAdapter;
use PHPUnit\Framework\TestCase;

class SafeErrorMessageTest extends TestCase
{
    public function testStripsTheQueryStringFromAGuzzleStyleMessage(): void
    {
        $message = 'cURL error 6: Could not resolve host: store.example.com '
            . '(see https://curl.haxx.se/libcurl/c/libcurl-errors.html) '
            . 'for https://store.example.com/wp-json/cocart/v2/cart?cart_key=t_secret_cart_key&_fields=items';

        $this->assertSame(
            'cURL error 6: Could not resolve host: store.example.com '
            . '(see https://curl.haxx.se/libcurl/c/libcurl-errors.html) '
            . 'for https://store.example.com/wp-json/cocart/v2/cart',
            SafeErrorMessage::from($message)
        );
    }

    public function testStripsTheQueryStringFromAStreamWarning(): void
    {
        $message = 'file_get_contents(https://store.example.com/wp-json/cocart/v2/cart?cart_key=t_secret_cart_key): '
            . 'Failed to open stream: Connection refused';

        $this->assertSame(
            'file_get_contents(https://store.example.com/wp-json/cocart/v2/cart): Failed to open stream: Connection refused',
            SafeErrorMessage::from($message)
        );
    }

    public function testLeavesMessagesWithoutAUrlAlone(): void
    {
        $this->assertSame('Operation timed out after 30001 milliseconds', SafeErrorMessage::from('Operation timed out after 30001 milliseconds'));
    }

    public function testStreamAdapterErrorsDoNotIncludeTheCartKey(): void
    {
        // Nothing listens on port 1, so the connection is refused straight away.
        $adapter = new StreamAdapter();

        try {
            $adapter->request('GET', 'http://127.0.0.1:1/wp-json/cocart/v2/cart?cart_key=t_secret_cart_key', [], null, ['timeout' => 5]);
            $this->fail('Expected a CoCartException');
        } catch (CoCartException $e) {
            $this->assertStringStartsWith('Stream HTTP Error: ', $e->getMessage());
            $this->assertStringNotContainsString('t_secret_cart_key', $e->getMessage());
        }
    }
}
