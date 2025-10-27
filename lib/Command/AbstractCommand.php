<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Solution Libre SAS
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

use OCA\ShareListing\Service\SharesList;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Base {
	/** @var SharesList */
	protected $sharesList;

	public function __construct(SharesList $sharesList) {
		parent::__construct();

		$this->sharesList = $sharesList;
	}

	public function configure() {
		$this->addOption(
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
			);
	}

	protected function getOptions(InputInterface $input): array {
		$user = $input->getOption('user');
		$path = $input->getOption('path');
		$token = $input->getOption('token');
		$filter = $this->sharesList->filterStringToInt($input->getOption('filter'));

		return [$user, $path, $token, $filter];
	}
}
