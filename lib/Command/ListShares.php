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

use iter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListShares extends AbstractCommand {
	public function configure() {
		parent::configure();

		$this->setName('sharing:list')
			->setDescription('List who has access to shares by owner')
			->addOption(
				'output',
				'o',
				InputOption::VALUE_OPTIONAL,
				'Output format (json or csv, default is json)',
				'json'
			);
	}
	
	protected function execute(InputInterface $input, OutputInterface $output): int {
		[$user, $path, $token, $filter] = $this->getOptions($input);
		$outputOpt = $input->getOption('output');

		$shares = iter\toArray($this->sharesList->getFormattedShares($user, $filter, $path, $token));

		$output->writeln($this->sharesList->getSerializedShares($shares, $outputOpt));
		return 0;
	}
}
