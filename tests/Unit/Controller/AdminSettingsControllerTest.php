<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Controller;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Controller\AdminSettingsController;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\AppFramework\Http;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminSettingsControllerTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IAppSettings&MockObject $appSettings;

	private AdminSettingsController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appSettings = $this->createMock(IAppSettings::class);

		$this->controller = new AdminSettingsController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->appConfig,
			$this->appSettings,
		);
	}

	public function testSavePersistsAllSettings(): void {
		$this->appConfig->expects($this->exactly(2))->method('setValueInt');
		$this->appConfig->expects($this->exactly(3))->method('setValueString');

		$response = $this->controller->save(6, 10, 'Use {code}', 'Subject', 'Footer');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testSaveAcceptsEmptyTemplateParts(): void {
		// Empty parts mean "use the localized default" and are always valid
		$response = $this->controller->save(6, 10, '', '', '');

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testSaveRejectsCustomBodyWithoutCode(): void {
		$this->appConfig->expects($this->never())->method('setValueString');

		$response = $this->controller->save(6, 10, 'body without placeholder', '', '');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'email-code-placeholder-missing'], $response->getData());
	}

	public function testSaveRejectsMultiLineSubject(): void {
		$this->appConfig->expects($this->never())->method('setValueString');

		$response = $this->controller->save(6, 10, '', "evil\r\nBcc: spy@example.com", '');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'email-subject-must-be-single-line'], $response->getData());
	}

	public function testSaveRejectsOutOfRangeCodeLength(): void {
		$response = $this->controller->save(3, 10, '', '', '');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'code-length-out-of-range'], $response->getData());
	}

	public function testSaveRejectsOverlongSubject(): void {
		$response = $this->controller->save(6, 10, '', str_repeat('x', 256), '');

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'email-subject-too-long'], $response->getData());
	}

	public function testResetDeletesAllKeys(): void {
		$this->appConfig->expects($this->exactly(5))->method('deleteKey');

		$response = $this->controller->reset();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}
}
