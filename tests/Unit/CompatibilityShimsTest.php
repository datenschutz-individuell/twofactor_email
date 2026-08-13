<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A workaround for an old server outlives its reason quietly: nothing breaks when
 * the version it served is dropped, it just sits there. This test makes the moment
 * loud instead — it fails as soon as the supported range moves past the version a
 * shim exists for, and names everything that has to go together.
 */
final class CompatibilityShimsTest extends TestCase {
	private const ROOT = __DIR__ . '/../..';

	private function minimumPhpVersion(): string {
		$info = file_get_contents(self::ROOT . '/appinfo/info.xml');
		self::assertIsString($info, 'appinfo/info.xml is not readable');
		self::assertSame(1, preg_match('/<php[^>]*min-version="([0-9.]+)"/', $info, $m));
		return $m[1];
	}

	/**
	 * Nextcloud 35 deprecates ISecureRandom::generate in favour of PHP's
	 * Randomizer::getBytesFromString, which needs PHP 8.3. While the app supports
	 * PHP 8.2 the replacement is out of reach, so psalm is told to accept that one
	 * call. The moment the floor moves, the suppression hides a call that should
	 * have changed — and nothing else would say so.
	 */
	public function testTheSecureRandomSuppressionGoesWhenPhp82Does(): void {
		$psalm = file_get_contents(self::ROOT . '/psalm.xml');
		self::assertIsString($psalm);

		if (version_compare($this->minimumPhpVersion(), '8.3', '>=')) {
			$this->assertStringNotContainsString(
				'ISecureRandom::generate',
				$psalm,
				'PHP 8.2 is no longer supported: generate the code with '
				. 'Random\Randomizer::getBytesFromString() and remove the suppression '
				. 'for ISecureRandom::generate from psalm.xml.',
			);
			return;
		}

		$this->assertStringContainsString('ISecureRandom::generate', $psalm);
	}

	private function minimumNextcloudVersion(): int {
		$info = file_get_contents(self::ROOT . '/appinfo/info.xml');
		self::assertIsString($info, 'appinfo/info.xml is not readable');
		self::assertSame(1, preg_match('/<nextcloud[^>]*min-version="(\d+)"/', $info, $m));
		return (int)$m[1];
	}

	/**
	 * Three pieces, one reason: Nextcloud 33 reads the two-factor exemption from the
	 * docblock annotation and does not know the attribute, which is @since 34. The
	 * attribute therefore has to sit next to the annotation, and psalm has to be told
	 * that this one class is missing when it analyses against the oldest supported
	 * OCP — which in turn needs the unused-suppression check switched off, because
	 * the same handler is by nature unused against the newest OCP.
	 *
	 * Drop Nextcloud 33 and all three are dead weight around a security-relevant
	 * route. Nobody would notice, so this test does.
	 */
	public function testTheNextcloud33ExemptionShimGoesWhenNextcloud33Does(): void {
		$controller = file_get_contents(self::ROOT . '/lib/Controller/ChallengeController.php');
		$psalm = file_get_contents(self::ROOT . '/psalm.xml');
		self::assertIsString($controller);
		self::assertIsString($psalm);

		if ($this->minimumNextcloudVersion() >= 34) {
			$this->assertStringNotContainsString(
				'@NoTwoFactorRequired',
				$controller,
				'Nextcloud 33 is no longer supported: remove the @NoTwoFactorRequired docblock '
				. 'annotation from ChallengeController::resend(). The attribute alone is enough now.',
			);
			$this->assertStringNotContainsString(
				'UndefinedAttributeClass',
				$psalm,
				'Nextcloud 33 is no longer supported: remove the UndefinedAttributeClass handler '
				. 'from psalm.xml. The attribute exists in every OCP the app is analysed against.',
			);
			$this->assertStringNotContainsString(
				'findUnusedIssueHandlerSuppression',
				$psalm,
				'Nextcloud 33 is no longer supported: remove findUnusedIssueHandlerSuppression '
				. 'from psalm.xml. It existed only for the handler above, and without it a stale '
				. 'suppression would go unreported.',
			);
			return;
		}

		// While 33 is supported the three belong together: one without the others
		// either breaks the resend route on 33 or turns one of the psalm runs red.
		$this->assertStringContainsString('@NoTwoFactorRequired', $controller);
		$this->assertStringContainsString('UndefinedAttributeClass', $psalm);
		$this->assertStringContainsString('findUnusedIssueHandlerSuppression="false"', $psalm);
	}
}
