<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Provider;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Provider\LoginSetup;
use OCA\TwoFactorEMail\Provider\TwoFactorEMail;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\IEMailAddressMasker;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCA\TwoFactorEMail\Settings\PersonalSettings;
use OCP\AppFramework\Services\IInitialState;
use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

class TwoFactorEMailTest extends TestCase {
	private ITemplateManager&MockObject $templateManager;
	private IL10N&MockObject $l10n;
	private IInitialState&MockObject $initialState;
	private IURLGenerator&MockObject $urlGenerator;
	private IStateManager&MockObject $stateManager;
	private IEMailAddressMasker&MockObject $masker;
	private ContainerInterface&MockObject $container;
	private ILoginChallenge&MockObject $challengeService;
	private IAppSettings&MockObject $settings;

	private TwoFactorEMail $provider;

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

	/**
	 * @throws Exception
	 */
	public function testActivate(): void {
		$user = $this->createMock(IUser::class);
		$this->stateManager->expects($this->once())->method('enable')->with($user);

		$this->provider->enableFor($user);
	}

	/**
	 * @throws Exception
	 */
	public function testIsTwoFactorAuthEnabledReflectsTheStateManager(): void {
		$user = $this->createMock(IUser::class);
		$this->stateManager->method('isEnabled')->with($user)->willReturn(true);

		self::assertTrue($this->provider->isTwoFactorAuthEnabledForUser($user));
	}

	/**
	 * @throws Exception
	 */
	public function testGetTemplateSendsACodeAndReportsNoError(): void {
		$assigns = [];
		$user = $this->createMock(IUser::class);
		$this->templateManager->method('getTemplate')->willReturn($this->templateCapturing($assigns));
		$this->settings->method('getCodeLength')->willReturn(6);
		$this->challengeService->method('sendChallenge')->with($user)->willReturn(true);

		$this->provider->getTemplate($user);

		self::assertTrue($assigns['newCodeWasSent']);
		self::assertNull($assigns['error']);
		self::assertSame(6, $assigns['codeLength']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetTemplateDoesNotResendWhileAValidCodeExists(): void {
		$assigns = [];
		$user = $this->createMock(IUser::class);
		$this->templateManager->method('getTemplate')->willReturn($this->templateCapturing($assigns));
		$this->challengeService->method('sendChallenge')->with($user)->willReturn(false);

		$this->provider->getTemplate($user);

		self::assertFalse($assigns['newCodeWasSent']);
		self::assertNull($assigns['error']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetTemplateReportsNoEmailWhenTheAddressIsMissing(): void {
		$assigns = [];
		$user = $this->createMock(IUser::class);
		$this->templateManager->method('getTemplate')->willReturn($this->templateCapturing($assigns));
		$this->challengeService->method('sendChallenge')->willThrowException(new EMailNotSet($user));

		$this->provider->getTemplate($user);

		self::assertSame('no-email', $assigns['error']);
		self::assertFalse($assigns['newCodeWasSent']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetTemplateReportsSendFailure(): void {
		$assigns = [];
		$user = $this->createMock(IUser::class);
		$this->templateManager->method('getTemplate')->willReturn($this->templateCapturing($assigns));
		$this->challengeService->method('sendChallenge')->willThrowException(new SendEMailFailed());

		$this->provider->getTemplate($user);

		self::assertSame('send-failed', $assigns['error']);
	}

	/**
	 * @throws Exception
	 */
	public function testGetLoginSetupMasksTheEmailAndProvidesIt(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('alice@example.com');
		$this->masker->expects($this->once())->method('maskForUI')->with('alice@example.com')->willReturn('a*@*.com');
		$this->initialState->expects($this->once())->method('provideInitialState')->with('maskedEmail', 'a*@*.com');
		$loginSetup = $this->createMock(ILoginSetupProvider::class);
		$this->container->method('get')->with(LoginSetup::class)->willReturn($loginSetup);

		self::assertSame($loginSetup, $this->provider->getLoginSetup($user));
	}

	/**
	 * @throws Exception
	 */
	public function testGetLoginSetupHandlesAMissingEmail(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn(null);
		$this->masker->expects($this->once())->method('maskForUI')->with('')->willReturn('');
		$this->initialState->expects($this->once())->method('provideInitialState')->with('maskedEmail', '');
		$this->container->method('get')->willReturn($this->createMock(ILoginSetupProvider::class));

		$this->provider->getLoginSetup($user);
	}

	/**
	 * @throws Exception
	 */
	private function templateCapturing(array &$assigns): ITemplate&MockObject {
		$template = $this->createMock(ITemplate::class);
		$template->method('assign')->willReturnCallback(function (string $key, $value) use (&$assigns): void {
			$assigns[$key] = $value;
		});
		return $template;
	}

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->masker = $this->createMock(IEMailAddressMasker::class);
		$this->templateManager = $this->createMock(ITemplateManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->container = $this->createMock(ContainerInterface::class);
		$this->challengeService = $this->createMock(ILoginChallenge::class);
		$this->stateManager = $this->createMock(IStateManager::class);
		$this->settings = $this->createMock(IAppSettings::class);

		$this->provider = new TwoFactorEMail(
			$this->masker,
			$this->templateManager,
			$this->l10n,
			$this->initialState,
			$this->urlGenerator,
			$this->container,
			$this->challengeService,
			$this->stateManager,
			$this->settings,
		);
	}
}
