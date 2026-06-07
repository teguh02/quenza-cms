<?php
declare(strict_types=1);

namespace Tests\Unit;

use Quenza\Core\Translation\Translator;
use Tests\Support\TestCase;

final class TranslatorTest extends TestCase
{
    public function test_translator_returns_active_locale_value(): void
    {
        /** @var Translator $translator */
        $translator = $this->app->get(Translator::class);

        self::assertSame('Masuk', $translator->translate('auth.login'));
    }

    public function test_translator_falls_back_to_key_when_missing(): void
    {
        /** @var Translator $translator */
        $translator = $this->app->get(Translator::class);

        self::assertSame('missing.translation.key', $translator->translate('missing.translation.key'));
    }
}
