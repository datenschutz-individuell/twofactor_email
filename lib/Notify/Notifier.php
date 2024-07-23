<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author Nico Kluge <nico.kluge@klugecoded.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\TwoFactorEMail\Notify;

use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
    public function getID(): string
    {
        return Application::APP_ID;
    }

    public function getName(): string
    {
        // TODO: Implement getName() method.
    }

    public function prepare(INotification $notification, string $languageCode): INotification
    {
        // TODO: Implement prepare() method.
    }
}
