<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Event;

/**
 * Who caused a provider state change. SYSTEM means the app itself, e.g.
 * the automatic disable when an account loses its email address.
 */
enum StateChangeActor {
	case USER;
	case ADMIN;
	case SYSTEM;
}
