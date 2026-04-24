<?php

declare(strict_types=1);

namespace Tests\Support;

use Web;
use Registry;

/**
 * Drop-in replacement for the Web singleton that returns scripted HTTP
 * responses instead of performing real network requests.
 */
final class MockWeb extends Web
{
    /** @var array<int, array<string, mixed>> */
    public array $queue = [];

    /** @var array<int, array{url:string, options:array<string, mixed>|null}> */
    public array $calls = [];

    /**
     * Push a canned response onto the FIFO queue.
     *
     * @param array<int, string> $headers
     */
    public function enqueue(string $body, array $headers = ['HTTP/1.1 200 OK', 'Content-Type: application/json'], int $status = 200): void
    {
        $this->queue[] = [
            'body'    => $body,
            'headers' => $headers,
            'engine'  => 'mock',
            'cached'  => false,
            'error'   => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $options
     * @return array<string, mixed>|false
     */
    public function request($url, ?array $options = null)
    {
        $this->calls[] = ['url' => $url, 'options' => $options];
        if (!$this->queue) {
            return false;
        }
        $resp = array_shift($this->queue);
        $resp['request'] = [($options['method'] ?? 'GET') . ' ' . $url];
        return $resp;
    }

    /**
     * Install this mock as the global Web singleton.
     */
    public static function install(): self
    {
        $mock = new self();
        Registry::set('Web', $mock);
        return $mock;
    }

    public static function restore(): void
    {
        Registry::clear('Web');
    }
}
