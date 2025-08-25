<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Florent Poinsaut <florent@solution-libre.fr>
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

use OC\Core\Command\Base;
use OCA\ShareListing\Service\SharesList;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\Share\IManager as ShareManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function iter\toArray;

class ListShares extends Base {

	public function __construct(
		private ShareManager $shareManager,
		private IUserManager $userManager,
		private IRootFolder $rootFolder,
		private SharesList $sharesList,
	) {
		parent::__construct();

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
				'Filter shares, possible values: owner, initiator, recipient, token, has-expiration, no-expiration'
			)
			->addOption(
				'output',
				'o',
				InputOption::VALUE_OPTIONAL,
				'Output format (json or csv, default is json)',
				'json'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$user = $input->getOption('user');
		$path = $input->getOption('path');
		$token = $input->getOption('token');
		$filter = $this->sharesList->filterStringToInt($input->getOption('filter'));
		$outputOpt = $input->getOption('output');

		$shares = toArray($this->sharesList->getFormattedShares($user, $filter, $path, $token));

		$output->writeln($this->sharesList->getSerializedShares($shares, $outputOpt));
		return 0;
	}
}
