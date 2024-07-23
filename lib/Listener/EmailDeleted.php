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

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Db\TwoFactorEMailMapper;
use OCP\DB\Exception as DbException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Accounts\UserUpdatedEvent;
use OCP\IUser;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserUpdatedEvent>
 */
class EmailDeleted implements IEventListener {

	/** @var TwoFactorEMailMapper */
	private $twoFactorEMailMapper;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(TwoFactorEMailMapper $twoFactorEMailMapper, LoggerInterface $logger) {
		$this->twoFactorEMailMapper = $twoFactorEMailMapper;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if ($event instanceof UserUpdatedEvent && empty($event->getUser()->getEMailAddress())) {
			try {
                $this->disableTwofactorEMail($event);
                $this->notifyUser($event->getUser());
            } catch (DbException|NotFoundExceptionInterface|ContainerExceptionInterface $e) {
				$this->logger->warning($e->getMessage(), ['uid' => $event->getUser()->getUID()]);
            }
        }
	}

    /**
     * @param UserUpdatedEvent|Event $event
     * @return void
     * @throws DbException
     */
    public function disableTwofactorEMail(UserUpdatedEvent|Event $event): void
    {
        $this->twoFactorEMailMapper->deleteTwoFactorEMailByUserId($event->getUser()->getUID());
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function notifyUser(IUser $user): void
    {
        $manager = \OCP\Server::get(\OCP\Notification\IManager::class);
        $notification = $manager->createNotification();

        $acknowledgeAction = $notification->createAction();
        $acknowledgeAction->setPrimary(true)
            ->setLabel('OK');

        $notification->setApp(\OCA\TwoFactorEMail\AppInfo\Application::APP_ID)
            ->setUser($user->getUID())
            ->setDateTime(new \DateTime())
            ->setObject('user_notification', 'primary_email_deleted')
            ->setSubject('Email address deleted')
            ->setMessage('Your primary email address was deleted while you had twofactor_email active. Thus, twofactor_email was deactivated.');
    }
}
