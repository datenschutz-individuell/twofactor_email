<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Controller;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Controller\AdminSettingsController;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminSettingsControllerTest extends TestCase {
	private IAppSettings&MockObject $appSettings;

	private AdminSettingsController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appSettings = $this->createMock(IAppSettings::class);

		$this->controller = new AdminSettingsController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->appSettings,
			new SettingsValidator(),
		);
	}

	public function testSavePersistsAllSettings(): void {
		$this->appSettings->expects($this->once())->method('setCodeLength')->with(6);
		$this->appSettings->expects($this->once())->method('setCodeValidMinutes')->with(10);
		$this->appSettings->expects($this->once())->method('setResendMinMinutes')->with(30);
		$this->appSettings->expects($this->once())->method('setEMailSubject')->with('Subject');
		$this->appSettings->expects($this->once())->method('setEMailTemplate')->with('Use {code}');

		$response = $this->controller->save(6, 10, 'Use {code}', 'Subject', 30);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testSaveAcceptsEmptyTemplateParts(): void {
		// Empty parts mean "use the localized default" and are always valid
		$response = $this->controller->save(6, 10, '', '', 30);

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}

	public function testSaveRejectsCustomBodyWithoutCode(): void {
		$this->appSettings->expects($this->never())->method('setEMailTemplate');

		$response = $this->controller->save(6, 10, 'body without placeholder', '', 30);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'email-code-placeholder-missing'], $response->getData());
	}

	public function testSaveRejectsMultiLineSubject(): void {
		$this->appSettings->expects($this->never())->method('setEMailSubject');

		$response = $this->controller->save(6, 10, '', "evil\r\nBcc: spy@example.com", 30);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'email-subject-must-be-single-line'], $response->getData());
	}

	public function testSaveRejectsOutOfRangeCodeLength(): void {
		$response = $this->controller->save(3, 10, '', '', 30);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'code-length-out-of-range'], $response->getData());
	}

	public function testSaveRejectsOverlongSubject(): void {
		$response = $this->controller->save(6, 10, '', str_repeat('x', 256), 30);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'email-subject-too-long'], $response->getData());
	}

	public function testSaveRejectsOutOfRangeResendCooldown(): void {
		$response = $this->controller->save(6, 10, '', '', 99999);

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'resend-minutes-out-of-range'], $response->getData());
	}

	public function testResetDelegatesToAppSettings(): void {
		$this->appSettings->expects($this->once())->method('resetToDefaults');

		$response = $this->controller->reset();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
	}
}
