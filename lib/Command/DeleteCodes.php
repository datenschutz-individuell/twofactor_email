<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Command;

use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the stored two-factor email codes — for one user or for all users.
 * Affected users simply request a new code at their next login.
 */
final class DeleteCodes extends Command {

	public function __construct(
		private readonly ICodeStorage $codeStorage,
		private readonly IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('twofactor_email:delete-codes')
			->setDescription('Delete the stored two-factor email codes of one user or of all users.')
			->addArgument('uid', InputArgument::OPTIONAL, 'Delete the code of the user with this user id')
			->addOption('all', null, InputOption::VALUE_NONE, 'Delete the codes of all users');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$uid = $input->getArgument('uid');
		$all = $input->getOption('all');

		// Symfony renders the error and the command synopsis for this exception,
		// matching core commands like trashbin:cleanup.
		if (($uid !== null && $all) || ($uid === null && !$all)) {
			throw new InvalidOptionException('Specify either a user id or --all.');
		}

		if ($all) {
			$count = $this->codeStorage->deleteAllCodes();
			if ($count === 0) {
				$io->info('No stored codes to delete.');
			} else {
				$io->success(sprintf('Deleted the stored codes of %d user(s).', $count));
			}
			return Command::SUCCESS;
		}

		// Deleting is also allowed for users unknown to Nextcloud: their
		// leftover codes should be removable, but warn about possible typos.
		if ($this->userManager->get($uid) === null) {
			$io->warning('No user with id "' . $uid . '" exists — deleting leftover codes anyway.');
		}
		if ($this->codeStorage->deleteCode($uid)) {
			$io->success('Deleted the stored code of "' . $uid . '".');
		} else {
			$io->info('No code was stored for "' . $uid . '".');
		}
		return Command::SUCCESS;
	}
}
