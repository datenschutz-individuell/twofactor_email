<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Provider;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\TwoFactorEMail\Provider\TwoFactorEMail;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\IEMailAddressMasker;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCA\TwoFactorEMail\Settings\PersonalSettings;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class TwoFactorEMailTest extends TestCase {
	private ITemplateManager&MockObject $templateManager;
	private IL10N&MockObject $l10n;
	private IInitialState&MockObject $initialState;
	private IURLGenerator&MockObject $urlGenerator;
	private IStateManager&MockObject $stateManager;

	private TwoFactorEMail $provider;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$masker = $this->createMock(IEMailAddressMasker::class);
		$this->templateManager = $this->createMock(ITemplateManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$container = $this->createMock(ContainerInterface::class);
		$challengeService = $this->createMock(ILoginChallenge::class);
		$this->stateManager = $this->createMock(IStateManager::class);
		$settings = $this->createMock(IAppSettings::class);

		$this->provider = new TwoFactorEMail(
			$masker,
			$this->templateManager,
			$this->l10n,
			$logger,
			$this->initialState,
			$this->urlGenerator,
			$container,
			$challengeService,
			$this->stateManager,
			$settings,
		);
	}

	public function testGetId(): void {
		self::assertEquals('email', $this->provider->getId());
	}

	public function testGetDisplayName(): void {
		self::assertEquals('Email', $this->provider->getDisplayName());
	}

	public function testGetDescription(): void {
		$this->l10n->expects($this->once())
			->method('t')
			->willReturnArgument(0);

		self::assertEquals('Authenticate by email', $this->provider->getDescription());
	}

	public function testGetLightIcon(): void {
		$this->urlGenerator->expects(self::once())
			->method('imagePath')
			->with('twofactor_email', 'app.svg')
			->willReturn('/path/to/app.svg');

		$icon = $this->provider->getLightIcon();

		self::assertEquals('/path/to/app.svg', $icon);
	}

	public function testGetDarkIcon(): void {
		$this->urlGenerator->expects(self::once())
			->method('imagePath')
			->with('twofactor_email', 'app-dark.svg')
			->willReturn('/path/to/app-dark.svg');

		$icon = $this->provider->getDarkIcon();

		self::assertEquals('/path/to/app-dark.svg', $icon);
	}

	/**
	 * @throws Exception
	 */
	public function testGetPersonalSettingsDisabledWithoutEMail(): void {
		$expected = new PersonalSettings($this->templateManager);

		$user = $this->createMock(IUser::class);
		$user->expects(self::once())
			->method('getEMailAddress')
			->willReturn(null);
		$this->stateManager->expects($this->once())
			->method('isEnabled')
			->with($user)
			->willReturn(false);
		$this->initialState->expects($this->exactly(3))
			->method('provideInitialState')
			->with($this->logicalOr(
				$this->equalTo('enabled'),
				$this->equalTo('hasEmail'),
				$this->equalTo('email'),
			), false);

		$actual = $this->provider->getPersonalSettings($user);

		self::assertEquals($expected, $actual);
	}

	/**
	 * @throws Exception
	 */
	public function testGetPersonalSettingsEnabledWithEMail(): void {
		$expected = new PersonalSettings($this->templateManager);

		$user = $this->createMock(IUser::class);
		$user->expects(self::once())
			->method('getEMailAddress')
			->willReturn('user@localhost');
		$this->stateManager->expects($this->once())
			->method('isEnabled')
			->with($user)
			->willReturn(true);
		$this->initialState->expects($this->exactly(3))
			->method('provideInitialState')
			->with($this->logicalOr(
				$this->equalTo('enabled'),
				$this->equalTo('hasEmail'),
				$this->equalTo('email'),
			), true);

		$actual = $this->provider->getPersonalSettings($user);

		self::assertEquals($expected, $actual);
	}

	/**
	 * @throws Exception
	 */
	public function testDeactivate(): void {
		$user = $this->createMock(IUser::class);
		$this->stateManager->expects($this->once())
			->method('disable')
			->with($user);

		$this->provider->disableFor($user);
	}
}
