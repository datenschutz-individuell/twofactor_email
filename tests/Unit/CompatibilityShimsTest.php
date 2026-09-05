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
	 * the newest OCP, and the two Controller.php ones depend on which OCP is installed.
	 * Psalm would report each such run's idle suppression as unused, so the check stays
	 * off while any suppression is left and comes back once none is. Tying it to one
	 * particular suppression was wrong the moment a second one existed, which is how
	 * this test earned its own place.
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
