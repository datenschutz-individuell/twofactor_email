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
 *
 * This class is the register of those workarounds, and it is meant to be complete.
 * Anything the app only does because it spans several server or PHP versions belongs
 * here with the condition that ends it: supporting one version per release removes
 * all of them at once, and only a complete list makes that a sweep instead of a
 * search.
 */
final class CompatibilityShimsTest extends TestCase {
	private const ROOT = __DIR__ . '/../..';

	/**
	 * From psalm.xml, not from info.xml. The `<php min-version>` in info.xml is
	 * maintained by hand and nothing checks it, while the psalm job verifies that
	 * `phpVersion` equals the floor derived from the oldest supported server. Reading
	 * the value CI already enforces makes this trigger as reliable as that check.
	 */
	private function minimumPhpVersion(): string {
		$psalm = file_get_contents(self::ROOT . '/psalm.xml');
		self::assertIsString($psalm, 'psalm.xml is not readable');
		self::assertSame(1, preg_match('/phpVersion="([0-9.]+)"/', $psalm, $m));
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
		$generator = file_get_contents(self::ROOT . '/lib/Service/NumericalCodeGenerator.php');
		self::assertIsString($psalm);
		self::assertIsString($generator);

		// Both sides, like the Nextcloud 33 pair below. A suppression that outlives the
		// call it was written for is not reported by psalm either, because the unused
		// check is off — so it would sit here widening what is accepted, unseen.
		//
		// The interface and the call, not the property name: renaming the property is a
		// refactor that says nothing about the deprecation, and it must not read as
		// "this carve-out can go".
		self::assertStringContainsString('ISecureRandom', $generator);
		$this->assertStringContainsString(
			'->generate(',
			$generator,
			'Nothing calls ISecureRandom::generate any more: remove its suppression from '
			. 'psalm.xml and this test with it.',
		);

		// The handler itself, not the comment beside it: a bare class name would also
		// match the explanation and stay green after the handler was deleted.
		$handler = '<referencedMethod name="OCP\Security\ISecureRandom::generate"/>';

		if (version_compare($this->minimumPhpVersion(), '8.3', '>=')) {
			$this->assertStringNotContainsString(
				$handler,
				$psalm,
				'PHP 8.2 is no longer supported: generate the code with '
				. 'Random\Randomizer::getBytesFromString() and remove the suppression '
				. 'for ISecureRandom::generate from psalm.xml.',
			);
			return;
		}

		$this->assertStringContainsString($handler, $psalm);
	}

	private function minimumNextcloudVersion(): int {
		$info = file_get_contents(self::ROOT . '/appinfo/info.xml');
		self::assertIsString($info, 'appinfo/info.xml is not readable');
		self::assertSame(1, preg_match('/<nextcloud[^>]*min-version="(\d+)"/', $info, $m));
		return (int)$m[1];
	}

	/**
	 * Two pieces, one reason: Nextcloud 33 reads the two-factor exemption from the
	 * docblock annotation and does not know the attribute, which is @since 34. The
	 * attribute therefore has to sit next to the annotation, and psalm has to be told
	 * that this one class is missing when it analyses against the oldest supported
	 * OCP.
	 *
	 * Drop Nextcloud 33 and both are dead weight around a security-relevant route.
	 * Nobody would notice, so this test does. The unused-suppression flag is not part
	 * of this pair — it belongs to whichever suppressions exist, see below.
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
			return;
		}

		// While 33 is supported the two belong together: one without the other either
		// breaks the resend route on 33 or turns one of the psalm runs red.
		$this->assertStringContainsString('@NoTwoFactorRequired', $controller);
		$this->assertStringContainsString('UndefinedAttributeClass', $psalm);
	}

	/**
	 * A suppression here exists because the app spans several versions, and in any one
	 * analysis run it may simply not be triggered: the Nextcloud 33 one is idle against
	 * the newest OCP, the ISecureRandom one against every OCP in the range until 35
	 * enters it. Psalm would report each such run's idle suppression as unused, so the
	 * check stays off while any suppression is left and comes back once none is. Tying
	 * it to one particular suppression was wrong the moment a second one existed, which
	 * is how this test earned its own place.
	 */
	public function testTheUnusedSuppressionCheckFollowsTheSuppressions(): void {
		$psalm = file_get_contents(self::ROOT . '/psalm.xml');
		self::assertIsString($psalm);

		// Comments stripped first: this file explains every handler in prose, and an
		// explanation quoting the syntax would otherwise count as a suppression and keep
		// the flag alive after the last real one is gone.
		$handlers = preg_replace('/<!--.*?-->/s', '', $psalm) ?? $psalm;

		// Both forms psalm accepts: the nested element and the attribute on the handler.
		// Matching only the nested one would call a file "suppressing nothing" while a
		// suppression written the other way is still in it.
		$suppresses = preg_match('/(<errorLevel type="suppress">|errorLevel="suppress")/', $handlers) === 1;

		if ($suppresses) {
			$this->assertStringContainsString(
				'findUnusedIssueHandlerSuppression="false"',
				$psalm,
				'psalm.xml still suppresses something, so findUnusedIssueHandlerSuppression '
				. 'has to stay false: a suppression written for one end of the supported '
				. 'range is by nature unused at the other, and the analysis would report it.',
			);
			return;
		}

		$this->assertStringNotContainsString(
			'findUnusedIssueHandlerSuppression',
			$psalm,
			'Nothing is suppressed any more, so remove findUnusedIssueHandlerSuppression '
			. 'from psalm.xml. Left in place, a suppression that outlived its reason would '
			. 'go unreported.',
		);
	}
}
