<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Activity;

use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCP\IL10N;

enum Notification: string {
	case ENABLED_BY_USER = 'twofactor_email_enabled_by_user';
	case DISABLED_BY_USER = 'twofactor_email_disabled_by_user';
	case ENABLED_BY_ADMIN = 'twofactor_email_enabled_by_admin';
	case DISABLED_BY_ADMIN = 'twofactor_email_disabled_by_admin';
	case DISABLED_NO_EMAIL = 'twofactor_email_disabled_no_email';

	public static function fromStateChange(StateChangeActor $actor, bool $enabled): self {
		return match ($actor) {
			StateChangeActor::USER => $enabled ? self::ENABLED_BY_USER : self::DISABLED_BY_USER,
			StateChangeActor::ADMIN => $enabled ? self::ENABLED_BY_ADMIN : self::DISABLED_BY_ADMIN,
			// The system only ever disables (account lost its email address)
			StateChangeActor::SYSTEM => self::DISABLED_NO_EMAIL,
		};
	}

	/**
	 * The strings are literal $l->t() calls so the translation tool can
	 * extract them — passing them through a variable would leave the texts
	 * untranslatable.
	 */
	public function getTranslatedSubject(IL10N $l): string {
		return match ($this) {
			Notification::ENABLED_BY_USER => $l->t('You enabled email two-factor authentication for your account'),
			Notification::DISABLED_BY_USER => $l->t('You disabled email two-factor authentication for your account'),
			Notification::ENABLED_BY_ADMIN => $l->t('Email two-factor authentication was enabled by an admin'),
			Notification::DISABLED_BY_ADMIN => $l->t('Email two-factor authentication was disabled by an admin'),
			Notification::DISABLED_NO_EMAIL => $l->t('Email two-factor authentication was disabled because your account has no email address'),
		};
	}
}
