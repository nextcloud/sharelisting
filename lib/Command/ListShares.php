<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\ShareListing\Command;

use iter;
use OCA\ShareListing\Service\SharesList;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use OC\Core\Command\Base;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListShares extends Base {

	/** @var ShareManager */
	private $shareManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var SharesList */
	private $sharesList;

	public function __construct(ShareManager $shareManager,
								IUserManager $userManager,
								IRootFolder $rootFolder,
								SharesList $sharesList) {
		parent::__construct();

		$this->shareManager = $shareManager;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->sharesList = $sharesList;

	}

	public function configure() {
		$this->setName('sharing:list')
			->setDescription('List who has access to shares by owner')
			->addOption(
				'user',
				'u',
				InputOption::VALUE_OPTIONAL,
				'Will list shares of the given user'
			)
			->addOption(
				'path',
				'p',
				InputOption::VALUE_OPTIONAL,
				'Will only consider the given path'
			)->addOption(
				'token',
				't',
				InputOption::VALUE_OPTIONAL,
				'Will only consider the given token'
			)->addOption(
				'filter',
				'f',
				InputOption::VALUE_OPTIONAL,
				'Filter shares, possible values: owner, initiator, recipient, token'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$user = $input->getOption('user');
		$path = $input->getOption('path');
		$token = $input->getOption('token');
		$filter = $input->getOption('filter');

		if ($filter === 'owner') {
			$filter = SharesList::FILTER_OWNER;
		} elseif ($filter === 'initiator') {
			$filter = SharesList::FILTER_INITIATOR;
		} else if ($filter === 'recipient') {
			$filter = SharesList::FILTER_RECIPIENT;
		} else {
			$filter = SharesList::FILTER_NONE;
		}

		if ($user === null && $token === null) {
			$shares = [];
			$this->userManager->callForSeenUsers(function (IUser $user) use ($token, $path, $filter, &$shares) {
				$tmp = $this->sharesList->getFormattedShares($user->getUID(), $filter, $path, $token);
				foreach ($tmp as $share) {
					$shares[] = $share;
				}
			});
		} else {
			$shares = iter\toArray($this->sharesList->getFormattedShares($user = "", $filter, $path, $token));
		}

		$output->writeln(json_encode($shares, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		return 0;
	}
}
