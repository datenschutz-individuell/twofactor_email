<?php

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

class CodeStorageTest extends TestCase {
	private IUserConfig&MockObject $config;

	private CodeStorage $storage;

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

	public function testSecondsSinceLastCodeIsNullWithoutValidCode(): void {
		// created_at = 0 → older than the validity window → no valid code
		$this->config->method('getValueInt')->willReturn(0);
		$this->config->method('getValueString')->willReturn('');

		$this->assertNull($this->storage->secondsSinceLastCode('alice'));
	}

	public function testDeleteAllCodesDeletesBothKeysAndReturnsUserCount(): void {
		$this->config->method('getValuesByUsers')->willReturn(['alice' => 100, 'bob' => 200]);
		$deletedKeys = [];
		$this->config->expects($this->exactly(2))->method('deleteKey')
			->willReturnCallback(function (string $app, string $key) use (&$deletedKeys): void {
				$deletedKeys[] = $key;
			});

		$this->assertSame(2, $this->storage->deleteAllCodes());
		$this->assertSame(['code', 'code_created_at'], $deletedKeys);
	}

	public function testDeleteCodeReturnsTrueWhenCodeWasStored(): void {
		$this->config->method('getValueString')->willReturn('hashed-code');

		$this->assertTrue($this->storage->deleteCode('alice'));
	}

	public function testDeleteCodeReturnsFalseWhenNoCodeWasStored(): void {
		$this->config->method('getValueString')->willReturn('');

		$this->assertFalse($this->storage->deleteCode('alice'));
	}

	public function testDeleteExpiredReturnsRemovedCount(): void {
		// validity is 10 minutes: created_at 0 is expired, a fresh one is not
		$this->config->method('getValuesByUsers')->willReturn(['old' => 0, 'fresh' => time()]);
		$this->config->method('getValueString')->willReturn('hashed-code');

		$this->assertSame(1, $this->storage->deleteExpired());
	}

	public function testSecondsSinceLastCodeForFreshCode(): void {
		$this->config->method('getValueInt')->willReturn(time());
		$this->config->method('getValueString')->willReturn('hashed-code');

		$elapsed = $this->storage->secondsSinceLastCode('alice');

		$this->assertIsInt($elapsed);
		$this->assertGreaterThanOrEqual(0, $elapsed);
		$this->assertLessThan(60, $elapsed);
	}
}
