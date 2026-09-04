<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Service\CodeStorage;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Config\IUserConfig;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CodeStorageTest extends TestCase {
	private const ADDRESS = 'alice@example.org';

	private IUserConfig&MockObject $config;

	private CodeStorage $storage;

	/** @var array<string, string> what setValueString() has stored, keyed by config key */
	private array $strings = [];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IUserConfig::class);
		$settings = $this->createMock(IAppSettings::class);
		$settings->method('getCodeValidMinutes')->willReturn(10);

		$this->storage = new CodeStorage($settings, $this->config);
	}

	/**
	 * Puts a code in place through writeCode() and answers later reads from what it
	 * wrote. Going through the real write is what keeps these tests from having to
	 * know how the address fingerprint is built — only that reading back the address
	 * it was written for is what makes the code count.
	 */
	private function storeCodeFor(string $address, int $createdAt): void {
		$this->config->method('setValueString')
			->willReturnCallback(function (string $userId, string $app, string $key, string $value): bool {
				$this->strings[$key] = $value;
				return true;
			});
		$this->config->method('getValueString')
			->willReturnCallback(fn (string $userId, string $app, string $key): string => $this->strings[$key] ?? '');
		$this->config->method('getValueInt')->willReturn($createdAt);

		$this->storage->writeCode('alice', 'stored-hash', $address, $createdAt);
	}

	public function testSecondsSinceLastCodeIsNullWithoutValidCode(): void {
		// created_at = 0 → older than the validity window → no valid code
		$this->config->method('getValueInt')->willReturn(0);
		$this->config->method('getValueString')->willReturn('');

		$this->assertNull($this->storage->secondsSinceLastCode('alice', self::ADDRESS));
	}

	public function testDeleteAllCodesDeletesEveryKeyAndReturnsUserCount(): void {
		$this->config->method('getValuesByUsers')->willReturn(['alice' => 100, 'bob' => 200]);
		$deletedKeys = [];
		$this->config->expects($this->exactly(3))->method('deleteKey')
			->willReturnCallback(function (string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = $key;
			});

		$this->assertSame(2, $this->storage->deleteAllCodes());
		$this->assertSame(['code', 'code_created_at', 'code_address_hash'], $deletedKeys);
	}

	public function testDeleteCodeReturnsTrueWhenCodeWasStored(): void {
		$this->config->method('getValueString')->willReturn('hashed-code');

		$this->assertTrue($this->storage->deleteCode('alice'));
	}

	public function testDeleteCodeReturnsFalseWhenNoCodeWasStored(): void {
		$this->config->method('getValueString')->willReturn('');

		$this->assertFalse($this->storage->deleteCode('alice'));
	}

	public function testDeleteCodeTouchesNothingWhenNothingIsStored(): void {
		$this->config->method('getValueString')->willReturn('');
		$this->config->method('hasKey')->willReturn(false);
		$this->config->expects($this->never())->method('deleteUserConfig');

		$this->assertFalse($this->storage->deleteCode('alice'));
	}

	/**
	 * The fingerprint is the one key nothing else looks for: deleteExpired() searches by
	 * timestamp, and readCode() gives up before reaching it once the code is gone. Left
	 * out of the guard, an orphaned one would sit in oc_preferences for good while
	 * twofactor_email:delete-codes reports nothing to delete.
	 */
	public function testDeleteCodeRemovesAFingerprintLeftOnItsOwn(): void {
		$this->config->method('getValueString')->willReturn('');
		$this->config->method('hasKey')
			->willReturnCallback(static fn (string $userId, string $app, string $key): bool => $key === 'code_address_hash');
		$this->config->expects($this->exactly(3))->method('deleteUserConfig');

		$this->assertFalse($this->storage->deleteCode('alice'));
	}

	/**
	 * An empty code is a row like any other: judging it by its value alone would let it
	 * survive every command, because nothing else looks for that key on its own.
	 */
	public function testDeleteCodeRemovesAnEmptyCodeLeftOnItsOwn(): void {
		$this->config->method('getValueString')->willReturn('');
		$this->config->method('hasKey')
			->willReturnCallback(static fn (string $userId, string $app, string $key): bool => $key === 'code');
		$this->config->expects($this->exactly(3))->method('deleteUserConfig');

		$this->assertFalse($this->storage->deleteCode('alice'));
	}

	/**
	 * A timestamp stored as 0 is not the same as no timestamp at all, and only the
	 * stored one is a row: deleteExpired() finds users by key existence, counts it as
	 * expired, and would count it again on every run if deleting it did nothing.
	 */
	public function testDeleteCodeRemovesATimestampStoredAsZero(): void {
		$this->config->method('getValueString')->willReturn('');
		$this->config->method('hasKey')->willReturn(true);
		$this->config->expects($this->exactly(3))->method('deleteUserConfig');

		$this->assertFalse($this->storage->deleteCode('alice'));
	}

	/**
	 * readCode() deletes what it finds expired, and a timestamp with no code is what
	 * an interrupted write or a hand-edited setting leaves behind. deleteExpired()
	 * finds such a user by the timestamp alone, so it has to be removable.
	 */
	public function testDeleteCodeRemovesATimestampLeftWithoutACode(): void {
		$this->config->method('getValueString')->willReturn('');
		$this->config->method('hasKey')->willReturn(true);
		$deletedKeys = [];
		$this->config->expects($this->exactly(3))->method('deleteUserConfig')
			->willReturnCallback(function (string $userId, string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = $key;
			});

		$this->assertFalse($this->storage->deleteCode('alice'));
		$this->assertSame(['code', 'code_created_at', 'code_address_hash'], $deletedKeys);
	}

	public function testDeleteExpiredReturnsRemovedCount(): void {
		// validity is 10 minutes: created_at 0 is expired, a fresh one is not
		$this->config->method('getValuesByUsers')->willReturn(['old' => 0, 'fresh' => time()]);
		$this->config->method('getValueString')->willReturn('hashed-code');

		$this->assertSame(1, $this->storage->deleteExpired());
	}

	public function testDeleteExpiredAcceptsAUserIdOfDigitsOnly(): void {
		// Nextcloud allows such an id, and PHP hands it back as an int because it
		// is an array key. Passing it on unchanged is a TypeError.
		$this->config->method('getValuesByUsers')->willReturn(['12345' => 0]);
		$this->config->method('getValueString')->willReturn('hashed-code');

		$this->assertSame(1, $this->storage->deleteExpired());
	}

	public function testSecondsSinceLastCodeForFreshCode(): void {
		$this->storeCodeFor(self::ADDRESS, time());

		$elapsed = $this->storage->secondsSinceLastCode('alice', self::ADDRESS);

		$this->assertIsInt($elapsed);
		$this->assertGreaterThanOrEqual(0, $elapsed);
		$this->assertLessThan(60, $elapsed);
	}

	public function testReadCodeReturnsTheStoredCodeWhenFresh(): void {
		$this->storeCodeFor(self::ADDRESS, time());

		$this->assertSame('stored-hash', $this->storage->readCode('alice', self::ADDRESS));
	}

	public function testReadCodeReturnsNullAndClearsACodeSentToAnotherAddress(): void {
		$this->storeCodeFor(self::ADDRESS, time());
		$this->config->expects($this->atLeastOnce())->method('deleteUserConfig');

		$this->assertNull($this->storage->readCode('alice', 'bob@example.org'));
	}

	/**
	 * An account whose address was removed has nowhere to deliver, so nothing can be
	 * checked against — and a code that was sent somewhere is not the same as a code
	 * for no address at all.
	 */
	public function testReadCodeReturnsNullWhenTheAccountHasNoAddress(): void {
		$this->storeCodeFor(self::ADDRESS, time());

		$this->assertNull($this->storage->readCode('alice', null));
	}

	/**
	 * Nextcloud lower-cases and trims an address before storing it, but the app must
	 * not depend on having been handed the normalised form: a difference in case is
	 * the same mailbox, and re-sending a code for it would be a bug of its own.
	 */
	public function testReadCodeIgnoresCaseAndSurroundingSpaceInTheAddress(): void {
		$this->storeCodeFor(self::ADDRESS, time());

		$this->assertSame('stored-hash', $this->storage->readCode('alice', '  Alice@Example.ORG '));
	}

	/**
	 * A code stored by a version that knew no address has no fingerprint to check.
	 * Accepting it would mean trusting exactly the codes this is meant to catch, so
	 * it is refused — at the price of one extra code for a login in progress during
	 * the update.
	 */
	public function testReadCodeReturnsNullForACodeStoredWithoutAnAddress(): void {
		$this->config->method('getValueInt')->willReturn(time());
		$this->config->method('getValueString')
			->willReturnCallback(fn (string $userId, string $app, string $key): string => $key === 'code' ? 'stored-hash' : '');

		$this->assertNull($this->storage->readCode('alice', self::ADDRESS));
	}

	public function testReadCodeReturnsNullAndClearsAnExpiredCode(): void {
		// A real expired code: one is stored, and its timestamp is an hour old against
		// a ten-minute validity. Zero would mean "never written" instead, which is the
		// case deleteCode() now leaves untouched.
		$this->storeCodeFor(self::ADDRESS, time() - 3600);
		$this->config->expects($this->atLeastOnce())->method('deleteUserConfig');

		$this->assertNull($this->storage->readCode('alice', self::ADDRESS));
	}

	public function testReadCodeReturnsNullWhenTheStoredCodeIsEmpty(): void {
		$this->config->method('getValueInt')->willReturn(time());
		$this->config->method('getValueString')->willReturn('');

		$this->assertNull($this->storage->readCode('alice', self::ADDRESS));
	}
}
