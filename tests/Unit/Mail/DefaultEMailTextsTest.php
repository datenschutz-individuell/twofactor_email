<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Mail;

use OCA\TwoFactorEMail\Mail\LinkScanner;
use OCA\TwoFactorEMail\Mail\TemplateRenderer;
use OCA\TwoFactorEMail\Service\AppSettings;
use OCA\TwoFactorEMail\Service\WarnOnce;
use OCA\TwoFactorEMail\Test\Support\ServerL10N;
use OCA\TwoFactorEMail\Test\Support\ShippedTranslations;
use OCP\Defaults;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * EMailSender falls back to the default texts when the configured ones would put
 * the code into a web address. That fallback is only worth anything if the default
 * texts are safe themselves — in every language, and even when the values inserted
 * into them try to build an address around the code.
 *
 * This is why the sender does not check the default text again at send time: {code}
 * sits in a paragraph of its own, outside every translatable string, so neither a
 * translation nor an inserted value can move an address next to it. These tests hold
 * both halves of that sentence true for each translation the app ships.
 */
final class DefaultEMailTextsTest extends TestCase {
	private const CODE = '123456';

	/** A display name and an instance name that end in an unfinished web address. */
	private const HOSTILE_VALUE = 'https://evil.example/?c=';

	/**
	 * @throws Exception
	 */
	#[DataProviderExternal(ShippedTranslations::class, 'languages')]
	public function testTheDefaultTextsKeepTheCodeOutOfEveryWebAddress(string $language): void {
		$appSettings = $this->appSettings($language);
		$body = $appSettings->getDefaultEMailBody();

		// The structural half: the code stands alone between two blank lines, so every
		// reader — and every link scanner — sees whitespace on both sides of it.
		$this->assertStringContainsString(
			"\n\n" . '{code}' . "\n\n",
			$body,
			'The default body of "' . $language . '" no longer keeps the code in a paragraph of its own',
		);

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn(self::HOSTILE_VALUE);
		$renderer = $this->renderer($appSettings);

		$subject = $renderer->renderSubject($appSettings->getDefaultEMailSubject(), $user, self::CODE);
		$parts = $renderer->renderBody($body, $user, self::CODE);

		// Without this the case below could pass on a text that lost the code
		$this->assertStringContainsString(
			self::CODE,
			implode(' ', array_column($parts, 0)),
			'The default body of "' . $language . '" does not deliver the code at all',
		);
		$this->assertFalse(
			LinkScanner::couldLeakCode($subject, $parts, self::CODE),
			'The default texts of "' . $language . '" would put the code into a web address',
		);
	}

	/**
	 * A provider that found no translation file would leave the sweep above testing
	 * English over and over, and pass.
	 *
	 * @throws Exception
	 */
	public function testTheShippedTranslationsAreApplied(): void {
		$this->assertGreaterThan(1, count(ShippedTranslations::languages()), 'No translation file was found');
		$this->assertNotSame(
			$this->appSettings('en')->getDefaultEMailBody(),
			$this->appSettings('de')->getDefaultEMailBody(),
			'The German default body equals the English one, so no translation was applied',
		);
	}

	/**
	 * @throws Exception
	 */
	private function appSettings(string $language): AppSettings {
		return new AppSettings(
			$this->createMock(IAppConfig::class),
			new ServerL10N(ShippedTranslations::of($language)),
			new WarnOnce($this->createMock(LoggerInterface::class)),
		);
	}

	/**
	 * @throws Exception
	 */
	private function renderer(AppSettings $appSettings): TemplateRenderer {
		$defaults = $this->createMock(Defaults::class);
		$defaults->method('getName')->willReturn(self::HOSTILE_VALUE);
		$defaults->method('getLogo')->willReturn('/themes/logo.png');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example/themes/logo.png');

		return new TemplateRenderer($defaults, $urlGenerator, $appSettings);
	}
}
