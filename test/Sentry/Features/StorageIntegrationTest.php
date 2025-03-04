<?php

namespace Sentry\Laravel\Tests\Features;

use Illuminate\Support\Facades\Storage;
use Sentry\Laravel\Tests\TestCase;
use Sentry\Tracing\TransactionContext;

class StorageIntegrationTest extends TestCase
{
    public function testCreatesSpansFor(): void
    {
        $hub = $this->getHubFromContainer();

        $transaction = $hub->startTransaction(new TransactionContext);
        $transaction->initSpanRecorder();

        $this->getCurrentScope()->setSpan($transaction);

        Storage::put('foo', 'bar');
        $fooContent = Storage::get('foo');
        Storage::assertExists('foo', 'bar');
        Storage::delete('foo');
        Storage::delete(['foo', 'bar']);

        $spans = $transaction->getSpanRecorder()->getSpans();

        $this->assertArrayHasKey(1, $spans);
        $span = $spans[1];
        $this->assertSame('file.put', $span->getOp());
        $this->assertSame('foo (3 B)', $span->getDescription());
        $this->assertSame(['path' => 'foo', 'options' => [], 'disk' => 'local', 'driver' => 'local'], $span->getData());

        $this->assertArrayHasKey(2, $spans);
        $span = $spans[2];
        $this->assertSame('file.get', $span->getOp());
        $this->assertSame('foo', $span->getDescription());
        $this->assertSame(['path' => 'foo', 'disk' => 'local', 'driver' => 'local'], $span->getData());
        $this->assertSame('bar', $fooContent);

        $this->assertArrayHasKey(3, $spans);
        $span = $spans[3];
        $this->assertSame('file.assertExists', $span->getOp());
        $this->assertSame('foo', $span->getDescription());
        $this->assertSame(['path' => 'foo', 'disk' => 'local', 'driver' => 'local'], $span->getData());

        $this->assertArrayHasKey(4, $spans);
        $span = $spans[4];
        $this->assertSame('file.delete', $span->getOp());
        $this->assertSame('foo', $span->getDescription());
        $this->assertSame(['path' => 'foo', 'disk' => 'local', 'driver' => 'local'], $span->getData());

        $this->assertArrayHasKey(5, $spans);
        $span = $spans[5];
        $this->assertSame('file.delete', $span->getOp());
        $this->assertSame('2 paths', $span->getDescription());
        $this->assertSame(['paths' => ['foo', 'bar'], 'disk' => 'local', 'driver' => 'local'], $span->getData());
    }

    public function testDoesntCreateSpansWhenDisabled(): void
    {
        $this->resetApplicationWithConfig([
            'sentry.tracing.storage' => false,
        ]);

        $hub = $this->getHubFromContainer();

        $transaction = $hub->startTransaction(new TransactionContext);
        $transaction->initSpanRecorder();

        $this->getCurrentScope()->setSpan($transaction);

        Storage::exists('foo');

        $this->assertCount(1, $transaction->getSpanRecorder()->getSpans());
    }

    public function testCreatesBreadcrumbsFor(): void
    {
        Storage::put('foo', 'bar');
        $fooContent = Storage::get('foo');
        Storage::assertExists('foo', 'bar');
        Storage::delete('foo');
        Storage::delete(['foo', 'bar']);

        $breadcrumbs = $this->getCurrentBreadcrumbs();

        $this->assertArrayHasKey(0, $breadcrumbs);
        $span = $breadcrumbs[0];
        $this->assertSame('file.put', $span->getCategory());
        $this->assertSame('foo (3 B)', $span->getMessage());
        $this->assertSame(['path' => 'foo', 'options' => [], 'disk' => 'local', 'driver' => 'local'], $span->getMetadata());

        $this->assertArrayHasKey(1, $breadcrumbs);
        $span = $breadcrumbs[1];
        $this->assertSame('file.get', $span->getCategory());
        $this->assertSame('foo', $span->getMessage());
        $this->assertSame(['path' => 'foo', 'disk' => 'local', 'driver' => 'local'], $span->getMetadata());
        $this->assertSame('bar', $fooContent);

        $this->assertArrayHasKey(2, $breadcrumbs);
        $span = $breadcrumbs[2];
        $this->assertSame('file.assertExists', $span->getCategory());
        $this->assertSame('foo', $span->getMessage());
        $this->assertSame(['path' => 'foo', 'disk' => 'local', 'driver' => 'local'], $span->getMetadata());

        $this->assertArrayHasKey(3, $breadcrumbs);
        $span = $breadcrumbs[3];
        $this->assertSame('file.delete', $span->getCategory());
        $this->assertSame('foo', $span->getMessage());
        $this->assertSame(['path' => 'foo', 'disk' => 'local', 'driver' => 'local'], $span->getMetadata());

        $this->assertArrayHasKey(4, $breadcrumbs);
        $span = $breadcrumbs[4];
        $this->assertSame('file.delete', $span->getCategory());
        $this->assertSame('2 paths', $span->getMessage());
        $this->assertSame(['paths' => ['foo', 'bar'], 'disk' => 'local', 'driver' => 'local'], $span->getMetadata());
    }

    public function testDoesntCreateBreadcrumbsWhenDisabled(): void
    {
        $this->resetApplicationWithConfig([
            'sentry.breadcrumbs.storage' => false,
        ]);

        Storage::exists('foo');

        $this->assertCount(0, $this->getCurrentBreadcrumbs());
    }
}
